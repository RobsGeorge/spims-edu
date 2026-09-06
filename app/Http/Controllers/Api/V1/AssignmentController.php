<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CourseOffering;
use App\Services\Assessment\AssignmentService;
use App\Services\Storage\ObjectStorageService;
use App\Support\Api\IdempotencyStore;
use App\Support\Api\PaginatedEnvelope;
use App\Support\Api\StudentPayload;
use App\Support\Api\StudentRecordGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function __construct(
        private readonly StudentRecordGuard $guard,
        private readonly AssignmentService $assignments,
        private readonly ObjectStorageService $storage,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function index(Request $request, CourseOffering $offering): JsonResponse
    {
        $this->guard->enrollmentForRead($request->user(), $offering);
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);

        $page = Assignment::query()
            ->whereHas('contentItem.week', fn ($q) => $q->where('offering_id', $offering->id))
            ->where('released', true)
            ->with('contentItem')
            ->paginate($perPage);

        $userId = $request->user()->id;
        $page->setCollection($page->getCollection()->map(function (Assignment $assignment) use ($userId) {
            $submission = $assignment->submissions()->where('student_id', $userId)->first();

            return $this->payload($assignment, $submission, list: true);
        }));

        return response()->json(PaginatedEnvelope::from($page));
    }

    public function show(Request $request, Assignment $assignment): JsonResponse
    {
        $offering = $this->offeringOf($assignment);
        $this->guard->enrollmentForRead($request->user(), $offering);
        $this->assertPublished($assignment);

        $submission = $assignment->submissions()->where('student_id', $request->user()->id)->first();

        return response()->json(['data' => $this->payload($assignment, $submission, list: false)]);
    }

    public function submit(Request $request, Assignment $assignment): JsonResponse
    {
        $offering = $this->offeringOf($assignment);
        $this->guard->enrollmentForWrite($request->user(), $offering);
        $this->assertPublished($assignment);

        $payload = $this->idempotency->remember(
            $request->user(),
            'assignments.submit:'.$assignment->id,
            $request->header('Idempotency-Key'),
            fn () => $this->storeSubmission($request, $assignment),
        );

        return response()->json(['data' => $payload], 201);
    }

    public function resubmit(Request $request, Assignment $assignment): JsonResponse
    {
        $offering = $this->offeringOf($assignment);
        $this->guard->enrollmentForWrite($request->user(), $offering);
        $this->assertPublished($assignment);

        return response()->json(['data' => $this->storeSubmission($request, $assignment)]);
    }

    /** @return array<string, mixed> */
    private function storeSubmission(Request $request, Assignment $assignment): array
    {
        $data = $request->validate([
            'text_body' => 'nullable|string',
            'file' => 'nullable|file|max:10240',
        ]);

        $fileUrl = null;
        if ($request->hasFile('file')) {
            /** @var \Illuminate\Http\UploadedFile $file */
            $file = $request->file('file');
            $path = $this->storage->signedUploadPath(
                'submissions',
                (string) $request->user()->id,
                $file->getClientOriginalExtension() ?: $file->extension()
            );
            $this->storage->store($path, $file->get() ?: '');
            $fileUrl = $path;
        }

        $submission = $this->assignments->submit(
            $request->user(),
            $assignment,
            $data['text_body'] ?? null,
            $fileUrl,
        );

        return $this->submissionPayload($assignment->fresh('contentItem'), $submission);
    }

    private function assertPublished(Assignment $assignment): void
    {
        if (! $assignment->released) {
            abort(404);
        }
    }

    private function offeringOf(Assignment $assignment): CourseOffering
    {
        $assignment->loadMissing('contentItem.week.offering');
        $offering = $assignment->contentItem?->week?->offering;
        if ($offering === null) {
            abort(404);
        }

        return $offering;
    }

    /** @return array<string, mixed> */
    private function payload(Assignment $assignment, ?AssignmentSubmission $submission, bool $list): array
    {
        $data = [
            'id' => $assignment->id,
            'title' => $assignment->contentItem?->title,
            'due_date' => StudentPayload::iso($assignment->due_date),
            'max_points' => $assignment->max_points,
            'submission_type' => $assignment->submission_type->value,
            'allow_resubmission' => $assignment->allow_resubmission,
            'resubmission_deadline' => StudentPayload::iso($assignment->resubmission_deadline),
            'released' => $assignment->released,
        ];

        if (! $list) {
            $data['instructions'] = $assignment->instructions;
        }

        $data['submission'] = $submission === null ? null : $this->submissionPayload($assignment, $submission);

        return $data;
    }

    /** @return array<string, mixed> */
    private function submissionPayload(Assignment $assignment, AssignmentSubmission $submission): array
    {
        $showGrade = $assignment->released && $submission->final_score !== null;

        return [
            'id' => $submission->id,
            'attempt_no' => $submission->attempt_no,
            'text_body' => $submission->text_body,
            'file_url' => StudentPayload::signedFileUrl($submission->file_url, $this->storage),
            'submitted_at' => StudentPayload::iso($submission->submitted_at),
            'is_late' => $submission->is_late,
            'final_score' => $showGrade ? $submission->final_score : null,
            'feedback' => $showGrade ? $submission->feedback : null,
        ];
    }
}
