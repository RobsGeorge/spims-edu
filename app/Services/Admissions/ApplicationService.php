<?php

namespace App\Services\Admissions;

use App\Enums\ApplicationStatus;
use App\Enums\StudentProgramStatus;
use App\Models\Application;
use App\Models\ApplicationFieldValue;
use App\Models\ApplicationForm;
use App\Models\StudentProgram;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function start(User $applicant, ApplicationForm $form): Application
    {
        $this->authorize->authorize($applicant, 'admissions.apply');

        return Application::query()->firstOrCreate(
            [
                'applicant_id' => $applicant->id,
                'program_id' => $form->program_id,
            ],
            [
                'form_id' => $form->id,
                'status' => ApplicationStatus::Draft,
            ]
        );
    }

    public function saveAnswers(User $applicant, Application $application, array $answers): void
    {
        if ($application->applicant_id !== $applicant->id) {
            throw ValidationException::withMessages(['application' => [__('admissions.not_owner')]]);
        }

        if (! in_array($application->status, [ApplicationStatus::Draft, ApplicationStatus::Submitted], true)) {
            throw ValidationException::withMessages(['application' => [__('admissions.not_editable')]]);
        }

        $application->load('form.fields');

        foreach ($application->form->fields as $field) {
            $answer = $answers[$field->id] ?? null;
            if ($field->required && ($answer === null || $answer === '')) {
                throw ValidationException::withMessages([
                    "answers.{$field->id}" => [__('admissions.field_required', ['field' => $field->label])],
                ]);
            }

            if ($answer === null) {
                continue;
            }

            ApplicationFieldValue::query()->updateOrCreate(
                ['application_id' => $application->id, 'field_id' => $field->id],
                [
                    'value' => is_array($answer) ? json_encode($answer) : (string) $answer,
                    'file_url' => is_string($answer) && str_starts_with($answer, 'http') ? $answer : null,
                ]
            );
        }
    }

    public function submit(User $applicant, Application $application): Application
    {
        if ($application->applicant_id !== $applicant->id) {
            throw ValidationException::withMessages(['application' => [__('admissions.not_owner')]]);
        }

        $application->load('form.fields', 'values');
        foreach ($application->form->fields as $field) {
            if ($field->required && ! $application->values->firstWhere('field_id', $field->id)) {
                throw ValidationException::withMessages([
                    'answers' => [__('admissions.field_required', ['field' => $field->label])],
                ]);
            }
        }

        $reviewer = $this->nextReviewer();

        $application->update([
            'status' => ApplicationStatus::UnderReview,
            'submitted_at' => now(),
            'reviewer_id' => $reviewer?->id,
        ]);

        if ($reviewer) {
            $reviewer->update(['last_reviewed_at' => now()]);
        }

        $this->audit->write($applicant, 'admissions.submit', 'Application', $application->id);

        return $application->fresh();
    }

    public function decide(User $actor, Application $application, ApplicationStatus $decision, ?string $note = null): Application
    {
        $this->authorize->authorize($actor, 'admissions.decide');

        if (! in_array($decision, [ApplicationStatus::Accepted, ApplicationStatus::Rejected, ApplicationStatus::Waitlisted], true)) {
            throw ValidationException::withMessages(['status' => [__('admissions.invalid_decision')]]);
        }

        if (! $actor->isSuperAdmin() && ! $actor->hasRole(\App\Enums\RoleType::AdministrativeAdmin)) {
            if ($application->reviewer_id !== $actor->id) {
                throw ValidationException::withMessages(['application' => [__('admissions.not_assigned_reviewer')]]);
            }
        }

        return DB::transaction(function () use ($actor, $application, $decision, $note) {
            $before = $application->only(['status']);
            $application->update([
                'status' => $decision,
                'decision_note' => $note,
                'decided_at' => now(),
            ]);

            if ($decision === ApplicationStatus::Accepted) {
                $this->matriculate($application);
            }

            $this->audit->write($actor, 'admissions.decide', 'Application', $application->id, $before, [
                'status' => $decision->value,
            ]);

            return $application->fresh();
        });
    }

    public function matriculate(Application $application): StudentProgram
    {
        return StudentProgram::query()->firstOrCreate(
            [
                'student_id' => $application->applicant_id,
                'program_id' => $application->program_id,
            ],
            [
                'status' => StudentProgramStatus::Active,
                'enrolled_at' => now(),
            ]
        );
    }

    private function nextReviewer(): ?User
    {
        return User::query()
            ->where('is_reviewer', true)
            ->where('status', \App\Enums\UserStatus::Active)
            ->orderByRaw('last_reviewed_at IS NOT NULL')
            ->orderBy('last_reviewed_at')
            ->first();
    }
}
