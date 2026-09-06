<?php

namespace App\Services\Projects;

use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectDeliverableKind;
use App\Enums\ProjectReviewStatus;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Models\ProjectDeliverableSubmission;
use App\Models\ProjectSubmissionFile;
use App\Models\User;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class ProjectDeliverableService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly ProjectTeamService $teams,
        private readonly ObjectStorageService $storage,
    ) {}

    /**
     * @param  array{body?: ?string, link?: ?string}  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function submit(
        User $actor,
        Project $project,
        ProjectDeliverable $deliverable,
        array $data = [],
        array $files = [],
    ): ProjectDeliverableSubmission {
        $this->assertStudentOwnsTeam($actor, $project);
        $this->assertDeliverableOnProject($project, $deliverable);

        $project->loadMissing('assessment');
        $this->assertPublishedNotLocked($project->assessment);

        $this->authorize->authorize($actor, 'projects.join');

        $dueAt = $deliverable->due_at ?? $deliverable->phase?->due_at;
        $late = $dueAt !== null && now()->gt($dueAt);

        return $this->audit->withAudit($actor, 'projects.deliverable_submit', function () use ($project, $deliverable, $data, $files, $late) {
            $submission = ProjectDeliverableSubmission::query()->firstOrNew([
                'project_id' => $project->id,
                'deliverable_id' => $deliverable->id,
            ]);

            if ($deliverable->kind === ProjectDeliverableKind::File) {
                $this->assertFileKindPayload($files, $data);
                $existing = $submission->exists ? $submission->files()->count() : 0;
                if ($existing + count($files) > $deliverable->max_files) {
                    throw ValidationException::withMessages([
                        'files' => [__('projects.file_limit')],
                    ]);
                }
            } elseif ($deliverable->kind === ProjectDeliverableKind::Link) {
                if (($data['link'] ?? null) === null || $data['link'] === '') {
                    throw ValidationException::withMessages([
                        'link' => [__('projects.wrong_kind')],
                    ]);
                }
                if ($files !== []) {
                    throw ValidationException::withMessages([
                        'files' => [__('projects.wrong_kind')],
                    ]);
                }
                $submission->link = $data['link'];
                $submission->body = null;
            } else {
                if (($data['body'] ?? null) === null || $data['body'] === '') {
                    throw ValidationException::withMessages([
                        'body' => [__('projects.wrong_kind')],
                    ]);
                }
                if ($files !== []) {
                    throw ValidationException::withMessages([
                        'files' => [__('projects.wrong_kind')],
                    ]);
                }
                $submission->body = $data['body'];
                $submission->link = null;
            }

            $submission->late = $late || (bool) $submission->late;
            $submission->review_status = ProjectReviewStatus::Pending;
            $submission->reviewed_at = null;
            $submission->reviewer_id = null;
            $submission->save();

            foreach ($files as $upload) {
                $this->storeFile($submission, $deliverable, $project, $upload);
            }

            return $submission->fresh('files');
        }, 'ProjectDeliverableSubmission');
    }

    public function replaceFile(
        User $actor,
        Project $project,
        ProjectSubmissionFile $file,
        UploadedFile $upload,
    ): ProjectSubmissionFile {
        $this->assertStudentOwnsTeam($actor, $project);
        $file->loadMissing('submission');
        $this->assertFileOnProject($project, $file);

        $project->loadMissing('assessment');
        $this->assertPublishedNotLocked($project->assessment);

        $deliverable = $file->submission->deliverable;
        $this->assertFileSize($deliverable, $upload);

        return $this->audit->withAudit($actor, 'projects.file_replace', function () use ($file, $upload, $project) {
            $path = $this->storage->signedUploadPath(
                'project-deliverables',
                $project->id,
                $upload->getClientOriginalExtension() ?: $upload->extension()
            );
            $this->storage->store($path, $upload->get() ?: '');

            $file->update([
                'path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'size_bytes' => $upload->getSize() ?: 0,
            ]);

            $submission = $file->submission;
            $dueAt = $submission->deliverable?->due_at ?? $submission->deliverable?->phase?->due_at;
            if ($dueAt !== null && now()->gt($dueAt)) {
                $submission->late = true;
                $submission->save();
            }

            return $file->fresh();
        }, 'ProjectSubmissionFile');
    }

    public function deleteFile(User $actor, Project $project, ProjectSubmissionFile $file): void
    {
        $this->assertStudentOwnsTeam($actor, $project);
        $file->loadMissing('submission');
        $this->assertFileOnProject($project, $file);

        $project->loadMissing('assessment');
        $this->assertPublishedNotLocked($project->assessment);

        $this->audit->withAudit($actor, 'projects.file_delete', function () use ($file) {
            $file->delete();

            return $file;
        }, 'ProjectSubmissionFile');
    }

    /**
     * @param  array{review_status: string}  $data
     */
    public function review(User $actor, ProjectDeliverableSubmission $submission, array $data): ProjectDeliverableSubmission
    {
        $submission->loadMissing('project.assessment');
        $this->authorize->authorize($actor, 'projects.grade', $submission->project?->assessment);

        return $this->audit->withAudit($actor, 'projects.deliverable_review', function () use ($actor, $submission, $data) {
            $submission->review_status = ProjectReviewStatus::from($data['review_status']);
            $submission->reviewed_at = now();
            $submission->reviewer_id = $actor->id;
            $submission->save();

            return $submission->fresh();
        }, 'ProjectDeliverableSubmission');
    }

    public function assertStudentOwnsTeam(User $actor, Project $project): void
    {
        if ($this->teams->activeMembership($actor, $project) === null) {
            throw new AuthorizationException(__('auth.forbidden'));
        }
    }

    private function assertDeliverableOnProject(Project $project, ProjectDeliverable $deliverable): void
    {
        $deliverable->loadMissing('phase');
        if ($deliverable->phase?->project_assessment_id !== $project->project_assessment_id) {
            throw new AuthorizationException(__('auth.forbidden'));
        }
    }

    private function assertFileOnProject(Project $project, ProjectSubmissionFile $file): void
    {
        if ($file->submission?->project_id !== $project->id) {
            throw new AuthorizationException(__('auth.forbidden'));
        }
    }

    private function assertPublishedNotLocked($assessment): void
    {
        if ($assessment === null || $assessment->status === ProjectAssessmentStatus::Draft) {
            throw new ConflictException(__('projects.not_published'));
        }
        if ($assessment->status === ProjectAssessmentStatus::Locked) {
            throw new ConflictException(__('projects.locked'));
        }
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<string, mixed>  $data
     */
    private function assertFileKindPayload(array $files, array $data): void
    {
        if ($files === []) {
            throw ValidationException::withMessages([
                'files' => [__('projects.wrong_kind')],
            ]);
        }
        if (($data['link'] ?? null) || ($data['body'] ?? null)) {
            throw ValidationException::withMessages([
                'kind' => [__('projects.wrong_kind')],
            ]);
        }
    }

    private function storeFile(
        ProjectDeliverableSubmission $submission,
        ProjectDeliverable $deliverable,
        Project $project,
        UploadedFile $upload,
    ): ProjectSubmissionFile {
        $this->assertFileSize($deliverable, $upload);

        $path = $this->storage->signedUploadPath(
            'project-deliverables',
            $project->id,
            $upload->getClientOriginalExtension() ?: $upload->extension()
        );
        $this->storage->store($path, $upload->get() ?: '');

        return ProjectSubmissionFile::query()->create([
            'submission_id' => $submission->id,
            'path' => $path,
            'original_name' => $upload->getClientOriginalName(),
            'size_bytes' => $upload->getSize() ?: 0,
        ]);
    }

    private function assertFileSize(ProjectDeliverable $deliverable, UploadedFile $upload): void
    {
        $maxBytes = $deliverable->max_file_mb * 1024 * 1024;
        if (($upload->getSize() ?: 0) > $maxBytes) {
            throw ValidationException::withMessages([
                'files' => [__('projects.file_too_large')],
            ]);
        }
    }
}
