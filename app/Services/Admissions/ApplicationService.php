<?php

namespace App\Services\Admissions;

use App\Enums\ApplicationStatus;
use App\Enums\FormFieldType;
use App\Enums\StudentProgramStatus;
use App\Models\Application;
use App\Models\ApplicationFieldValue;
use App\Models\ApplicationForm;
use App\Models\ApplicationFormField;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApplicationService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function start(User $applicant, ApplicationForm $form): Application
    {
        $this->authorize->authorize($applicant, 'admissions.apply');

        $blocking = Application::query()
            ->where('applicant_id', $applicant->id)
            ->where('program_id', $form->program_id)
            ->whereIn('status', [
                ...ApplicationStatus::openCases(),
                ApplicationStatus::Accepted,
            ])
            ->latest()
            ->first();

        if ($blocking) {
            return $blocking;
        }

        return Application::query()->create([
            'applicant_id' => $applicant->id,
            'program_id' => $form->program_id,
            'form_id' => $form->id,
            'status' => ApplicationStatus::Draft,
        ]);
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, UploadedFile|null>  $files
     */
    public function saveAnswers(User $applicant, Application $application, array $answers, array $files = []): void
    {
        if ($application->applicant_id !== $applicant->id) {
            throw ValidationException::withMessages(['application' => [__('admissions.not_owner')]]);
        }

        if (! in_array($application->status, [ApplicationStatus::Draft, ApplicationStatus::Submitted], true)) {
            throw ValidationException::withMessages(['application' => [__('admissions.not_editable')]]);
        }

        $application->load('form.fields', 'values');

        foreach ($application->form->fields->where('active', true) as $field) {
            if ($field->type === FormFieldType::File) {
                $this->saveFileAnswer($applicant, $application, $field, $files[$field->id] ?? null);

                continue;
            }

            $answer = $answers[$field->id] ?? null;
            if ($answer === null) {
                continue;
            }

            if ($answer === '' || $answer === []) {
                ApplicationFieldValue::query()
                    ->where('application_id', $application->id)
                    ->where('field_id', $field->id)
                    ->delete();

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

    private function saveFileAnswer(
        User $applicant,
        Application $application,
        ApplicationFormField $field,
        mixed $uploaded,
    ): void {
        $existing = $application->values->firstWhere('field_id', $field->id);

        if (! $uploaded instanceof UploadedFile) {
            return;
        }

        $path = $this->storeApplicationDocument($uploaded, $application);

        ApplicationFieldValue::query()->updateOrCreate(
            ['application_id' => $application->id, 'field_id' => $field->id],
            [
                'value' => $path,
                'file_url' => $path,
            ]
        );

        $this->audit->write($applicant, 'admissions.document_upload', 'Application', $application->id, null, [
            'field_id' => $field->id,
            'path' => $path,
        ]);
    }

    private function storeApplicationDocument(UploadedFile $file, Application $application): string
    {
        if (class_exists(\App\Services\Storage\ObjectStorageService::class)) {
            /** @var \App\Services\Storage\ObjectStorageService $storage */
            $storage = app(\App\Services\Storage\ObjectStorageService::class);
            $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
            $path = $storage->signedUploadPath('application-docs', $application->id, $extension);
            $contents = file_get_contents($file->getRealPath() ?: $file->getPathname());
            if ($contents === false) {
                throw ValidationException::withMessages([
                    'files' => [__('admissions.upload_failed')],
                ]);
            }

            return $storage->store($path, $contents);
        }

        $stored = $file->store('applications/'.$application->id, 'local');
        if ($stored === false) {
            throw ValidationException::withMessages([
                'files' => [__('admissions.upload_failed')],
            ]);
        }

        return $stored;
    }

    public function submit(User $applicant, Application $application): Application
    {
        if ($application->applicant_id !== $applicant->id) {
            throw ValidationException::withMessages(['application' => [__('admissions.not_owner')]]);
        }

        if ($application->status === ApplicationStatus::Withdrawn) {
            throw ValidationException::withMessages(['application' => [__('admissions.not_editable')]]);
        }

        $application->load('form.fields', 'values', 'program');
        foreach ($application->form->fields->where('active', true) as $field) {
            if ($field->required && ! $this->hasFilledValue($application, $field)) {
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

        $fresh = $application->fresh(['program', 'applicant']);
        $this->notifyApplicant(
            $fresh,
            'admissions.submitted',
            __('admissions.notify_submitted_title', [], $applicant->preferred_locale ?: app()->getLocale()),
            __('admissions.notify_submitted_body', [
                'program' => $fresh->program?->code ?? '',
            ], $applicant->preferred_locale ?: app()->getLocale()),
        );

        return $fresh;
    }

    public function decide(User $actor, Application $application, ApplicationStatus $decision, ?string $note = null): Application
    {
        $this->authorize->authorize($actor, 'admissions.decide');

        if (! in_array($decision, [ApplicationStatus::Accepted, ApplicationStatus::Rejected, ApplicationStatus::Waitlisted], true)) {
            throw ValidationException::withMessages(['status' => [__('admissions.invalid_decision')]]);
        }

        $fresh = DB::transaction(function () use ($actor, $application, $decision, $note) {
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

            return $application->fresh(['program', 'applicant']);
        });

        $locale = $fresh->applicant?->preferred_locale ?: app()->getLocale();
        $this->notifyApplicant(
            $fresh,
            'admissions.decided',
            __('admissions.notify_decided_title', [
                'status' => __('application_status.'.$decision->value, [], $locale),
            ], $locale),
            __('admissions.notify_decided_body', [
                'program' => $fresh->program?->code ?? '',
                'status' => __('application_status.'.$decision->value, [], $locale),
            ], $locale),
        );

        return $fresh;
    }

    public function withdraw(User $actor, Application $application): Application
    {
        $this->authorize->authorize($actor, 'admissions.apply');

        if ($application->applicant_id !== $actor->id) {
            throw ValidationException::withMessages(['application' => [__('admissions.not_owner')]]);
        }

        if (! $application->status->isWithdrawable()) {
            throw ValidationException::withMessages(['application' => [__('admissions.not_withdrawable')]]);
        }

        return $this->audit->withAudit($actor, 'admissions.withdraw', function () use ($application) {
            $application->update([
                'status' => ApplicationStatus::Withdrawn,
                'decided_at' => now(),
            ]);

            return $application->fresh();
        }, 'Application');
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

    /**
     * Precomputed answer rows for Blade — never echo raw enum or model ->value there.
     *
     * @return list<array{label: string, display: ?string, is_file: bool}>
     */
    public function displayAnswers(Application $application): array
    {
        $application->loadMissing(['form.fields', 'values']);

        $rows = [];
        foreach ($application->form?->fields?->where('active', true) ?? [] as $field) {
            $stored = $application->values->firstWhere('field_id', $field->id);
            $raw = $stored === null ? null : ($stored->file_url ?: $stored->getAttribute('value'));
            $isFile = $stored !== null && filled($stored->file_url);
            $display = $raw === null ? null : (string) $raw;

            if ($field->type === FormFieldType::Checkbox) {
                $display = $stored === null
                    ? null
                    : ((string) $stored->getAttribute('value') === '1'
                        ? __('admissions.answer_yes')
                        : __('admissions.answer_no'));
            }

            if ($field->type === FormFieldType::Multiselect && is_string($raw) && str_starts_with(trim($raw), '[')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $display = implode(', ', array_map(strval(...), $decoded));
                }
            }

            $rows[] = [
                'label' => $field->label,
                'display' => $display,
                'is_file' => $isFile,
            ];
        }

        return $rows;
    }

    private function hasFilledValue(Application $application, ApplicationFormField $field): bool
    {
        $value = $application->values->firstWhere('field_id', $field->id);
        if ($value === null) {
            return false;
        }

        if ($field->type === FormFieldType::File) {
            return filled($value->file_url) || filled($value->value);
        }

        $raw = $value->value;
        if (is_string($raw) && str_starts_with(trim($raw), '[')) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded !== [] : trim($raw) !== '';
        }

        return trim((string) $raw) !== '';
    }

    private function notifyApplicant(Application $application, string $type, string $title, string $body): void
    {
        $applicant = $application->applicant ?? User::query()->find($application->applicant_id);
        if ($applicant === null) {
            return;
        }

        try {
            $this->notifications->notify($applicant, $type, $title, $body, [
                'application_id' => $application->id,
            ]);
        } catch (Throwable) {
            // Notifications must never block an admissions mutation.
        }
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
