<?php

namespace App\Services\Credentials;

use App\Enums\CredentialType;
use App\Enums\RequirementType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\Credential;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\ProgramRequirementFulfillment;
use App\Models\Setting;
use App\Models\StudentProgram;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CredentialService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function issueTranscript(User $actor, User $student, string $language = 'en'): Credential
    {
        $this->authorize->authorize($actor, 'credentials.issue');

        return $this->create($actor, $student, CredentialType::Transcript, $language, null, null, [
            'signatory_name' => config('app.name', 'SPIMS'),
            'signatory_title' => 'Registrar',
        ]);
    }

    public function issueProgramCertificate(User $actor, User $student, Program $program, string $language = 'en'): Credential
    {
        $this->authorize->authorize($actor, 'credentials.issue');

        $sp = StudentProgram::query()
            ->where('student_id', $student->id)
            ->where('program_id', $program->id)
            ->whereIn('status', [StudentProgramStatus::Active, StudentProgramStatus::Completed])
            ->first();

        if ($sp === null) {
            throw ValidationException::withMessages(['program' => [__('credentials.not_in_program')]]);
        }

        if (! $this->programRequirementsMet($sp)) {
            throw ValidationException::withMessages(['program' => [__('credentials.requirements_incomplete')]]);
        }

        return $this->create($actor, $student, CredentialType::ProgramCertificate, $language, $program->id, null, [
            'signatory_name' => $program->signatory_name,
            'signatory_title' => $program->signatory_title,
        ]);
    }

    public function issueStandaloneCertificate(User $actor, User $student, CourseOffering $offering, string $language = 'en'): Credential
    {
        $this->authorize->authorize($actor, 'credentials.issue');

        $offering->load('course');
        if (! $offering->course->is_standalone) {
            throw ValidationException::withMessages(['offering' => [__('credentials.not_standalone')]]);
        }

        $passed = AcademicRecord::query()
            ->where('student_id', $student->id)
            ->where('course_id', $offering->course_id)
            ->where('is_passing', true)
            ->exists();

        if (! $passed) {
            throw ValidationException::withMessages(['offering' => [__('credentials.not_passed')]]);
        }

        return $this->create($actor, $student, CredentialType::StandaloneCertificate, $language, null, $offering->id, [
            'signatory_name' => $offering->course->title,
            'signatory_title' => 'Course Director',
        ]);
    }

    public function regenerate(User $actor, Credential $credential): Credential
    {
        $this->authorize->authorize($actor, 'credentials.issue');

        return DB::transaction(function () use ($actor, $credential) {
            $credential->update(['revoked_at' => now()]);
            $this->audit->write($actor, 'credentials.revoke', 'Credential', $credential->id);

            return match ($credential->type) {
                CredentialType::Transcript => $this->issueTranscript($actor, $credential->student, $credential->language),
                CredentialType::ProgramCertificate => $this->issueProgramCertificate(
                    $actor,
                    $credential->student,
                    Program::query()->findOrFail($credential->program_id),
                    $credential->language
                ),
                CredentialType::StandaloneCertificate => $this->issueStandaloneCertificate(
                    $actor,
                    $credential->student,
                    CourseOffering::query()->findOrFail($credential->offering_id),
                    $credential->language
                ),
            };
        });
    }

    public function findByQrToken(string $token): ?Credential
    {
        return Credential::query()->where('qr_token', $token)->with(['student', 'program', 'offering.course'])->first();
    }

    /**
     * @return array{records: \Illuminate\Support\Collection, gpa: float|null}
     */
    public function transcriptData(User $student): array
    {
        $records = AcademicRecord::query()
            ->where('student_id', $student->id)
            ->with('course')
            ->orderByDesc('completed_at')
            ->get();

        $credits = $records->sum('credit_hours');
        $gpa = $credits > 0
            ? round($records->sum(fn (AcademicRecord $r) => $r->gpa_points * $r->credit_hours) / $credits, 2)
            : null;

        return ['records' => $records, 'gpa' => $gpa];
    }

    private function programRequirementsMet(StudentProgram $sp): bool
    {
        $required = ProgramCourse::query()
            ->where('program_id', $sp->program_id)
            ->where('requirement', RequirementType::Required)
            ->pluck('id');

        if ($required->isEmpty()) {
            return true;
        }

        $met = ProgramRequirementFulfillment::query()
            ->where('student_program_id', $sp->id)
            ->whereIn('program_course_id', $required)
            ->count();

        return $met >= $required->count();
    }

    /**
     * @param  array{signatory_name?: string|null, signatory_title?: string|null}  $signatory
     */
    private function create(
        User $actor,
        User $student,
        CredentialType $type,
        string $language,
        ?string $programId,
        ?string $offeringId,
        array $signatory,
    ): Credential {
        return $this->audit->withAudit($actor, 'credentials.issue', function () use ($student, $type, $language, $programId, $offeringId, $signatory) {
            $id = (string) Str::ulid();
            $serial = $this->nextSerial();
            $token = (string) Str::ulid();

            return Credential::query()->create([
                'id' => $id,
                'student_id' => $student->id,
                'type' => $type,
                'program_id' => $programId,
                'offering_id' => $offeringId,
                'serial' => $serial,
                'qr_token' => $token,
                'language' => $language,
                'signatory_name' => $signatory['signatory_name'] ?? null,
                'signatory_title' => $signatory['signatory_title'] ?? null,
                'file_url' => 'credentials/'.$id.'.html',
                'issued_at' => now(),
            ]);
        }, 'Credential');
    }

    private function nextSerial(): string
    {
        $year = now()->format('Y');
        $setting = Setting::query()->lockForUpdate()->find('credentials.serial_counter');
        if ($setting === null) {
            $setting = new Setting(['key' => 'credentials.serial_counter']);
        }
        $value = $setting->value ?? [];
        $years = $value['years'] ?? [];
        $next = ((int) ($years[$year] ?? 0)) + 1;
        $years[$year] = $next;
        $setting->value = ['years' => $years];
        $setting->save();

        return sprintf('SPIMS-CRED-%s-%05d', $year, $next);
    }
}
