<?php

namespace App\Services\Credentials;

use App\Enums\CredentialType;
use App\Enums\RequirementType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\Credential;
use App\Models\LegacyAcademicSummary;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\ProgramRequirementFulfillment;
use App\Models\Setting;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Pdf\PdfRenderService;
use App\Services\Storage\ObjectStorageService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CredentialService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly CertificateTemplateService $templates,
        private readonly PdfRenderService $pdf,
        private readonly ObjectStorageService $storage,
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

    /**
     * S4's general offering-completion certificate: any offering whose S4
     * CompletionResult is COMPLETED, independent of Course::is_standalone (which
     * issueStandaloneCertificate() requires). Idempotent: a second call for the
     * same (student, offering) while a non-revoked credential already exists
     * returns that credential instead of minting a duplicate — the guarantee
     * OfferingClosingService::close() relies on to be safely re-runnable.
     */
    public function issueOfferingCompletion(User $actor, User $student, CourseOffering $offering, string $language = 'en'): Credential
    {
        $this->authorize->authorize($actor, 'credentials.issue');

        $existing = Credential::query()
            ->where('student_id', $student->id)
            ->where('offering_id', $offering->id)
            ->where('type', CredentialType::OfferingCompletion)
            ->whereNull('revoked_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $offering->loadMissing('course');

        return $this->create($actor, $student, CredentialType::OfferingCompletion, $language, null, $offering->id, [
            'signatory_name' => $offering->course->title,
            'signatory_title' => 'Course Director',
        ]);
    }

    public function regenerate(User $actor, Credential $credential): Credential
    {
        $this->authorize->authorize($actor, 'credentials.issue');

        // L8 acceptance bar: "a migrated diploma verifies as historical and cannot be
        // reissued under a SPIMS serial." A legacy-sourced credential's serial is the
        // source system's own certificate number, kept verbatim — reissuing it would
        // mint a fresh SPIMS-format serial for a record SPIMS never actually issued.
        // See docs/legacy-data-import-plan.md §22.1.
        if ($credential->isLegacy()) {
            throw ValidationException::withMessages(['credential' => [__('credentials.legacy_no_reissue')]]);
        }

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
                CredentialType::OfferingCompletion => $this->issueOfferingCompletion(
                    $actor,
                    $credential->student,
                    CourseOffering::query()->findOrFail($credential->offering_id),
                    $credential->language
                ),
            };
        });
    }

    /**
     * Non-revoked credentials belonging to this student. A read — no audit.
     * The API passes `$request->user()` so a caller only ever lists their own.
     *
     * @return Collection<int, Credential>
     */
    public function forStudent(User $student): Collection
    {
        return Credential::query()
            ->where('student_id', $student->id)
            ->whereNull('revoked_at')
            ->latest('issued_at')
            ->get();
    }

    /**
     * @return array{contents: string, mime: string, filename: string}
     */
    public function download(User $actor, Credential $credential): array
    {
        if ($credential->student_id !== $actor->id) {
            $this->authorize->authorize($actor, 'credentials.issue');
        }

        if ($credential->file_url === null || ! $this->storage->exists($credential->file_url)) {
            $credential->update(['file_url' => $this->renderAndStore($credential)]);
        }

        $isPdf = str_ends_with((string) $credential->file_url, '.pdf');
        $contents = $this->storage->disk()->get($credential->file_url);

        return [
            'contents' => $contents,
            'mime' => $isPdf ? 'application/pdf' : 'text/html; charset=UTF-8',
            'filename' => $credential->serial.($isPdf ? '.pdf' : '.html'),
        ];
    }

    public function findByQrToken(string $token): ?Credential
    {
        return Credential::query()->where('qr_token', $token)->with(['student', 'program', 'offering.course'])->first();
    }

    /**
     * L4 — the "Prior study" surface, docs/legacy-data-import-plan.md §7 and §11.12.
     * `records` stays native-only (as before L3); a legacy AcademicRecord
     * (`source_system` set) is grouped separately under `prior_study` by source, and
     * never mixed into the main table. The computed SPIMS GPA sums every record whose
     * `counts_toward_gpa` is true — true by default for every native record (so this
     * is unchanged from before L3) and true for a legacy record only once a registrar
     * has promoted it (D7), at which point it correctly joins the live GPA.
     * `legacy_summaries` is the attested Populi/Canvas figure (D3) — rendered beside
     * the computed GPA, never averaged into it.
     *
     * @return array{records: \Illuminate\Support\Collection, gpa: float|null, prior_study: \Illuminate\Support\Collection, legacy_summaries: \Illuminate\Support\Collection}
     */
    public function transcriptData(User $student): array
    {
        $records = AcademicRecord::query()
            ->where('student_id', $student->id)
            ->whereNull('source_system')
            ->with('course')
            ->orderByDesc('completed_at')
            ->get();

        $gpaRecords = AcademicRecord::query()
            ->where('student_id', $student->id)
            ->where('counts_toward_gpa', true)
            ->get();

        $credits = $gpaRecords->sum('credit_hours');
        $gpa = $credits > 0
            ? round($gpaRecords->sum(fn (AcademicRecord $r) => $r->gpa_points * $r->credit_hours) / $credits, 2)
            : null;

        $priorStudy = AcademicRecord::query()
            ->where('student_id', $student->id)
            ->whereNotNull('source_system')
            ->with('course')
            ->orderBy('term')
            ->get()
            ->groupBy('source_system');

        $legacySummaries = LegacyAcademicSummary::query()
            ->where('student_id', $student->id)
            ->with(['source', 'program'])
            ->get();

        return [
            'records' => $records,
            'gpa' => $gpa,
            'prior_study' => $priorStudy,
            'legacy_summaries' => $legacySummaries,
        ];
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

            $credential = Credential::query()->create([
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
                'file_url' => null,
                'issued_at' => now(),
            ]);

            $credential->file_url = $this->renderAndStore($credential);
            $credential->save();

            return $credential;
        }, 'Credential');
    }

    /**
     * Renders the credential's certificate (real PDF via DomPDF, guarded by a
     * try/catch inside PdfRenderService) and stores it via ObjectStorageService
     * at `credentials/{id}.pdf`; falls back to storing the same content as
     * `credentials/{id}.html` when the renderer is unavailable or throws, so a
     * rendering hiccup never blocks credential issuance (CLAUDE.md rule 7,
     * generalized from mailers to PDF rendering).
     */
    private function renderAndStore(Credential $credential): string
    {
        $html = $this->templates->render($credential);
        $pdfBytes = $this->pdf->renderPdf($html);

        if ($pdfBytes !== null) {
            $path = 'credentials/'.$credential->id.'.pdf';
            $this->storage->store($path, $pdfBytes);

            return $path;
        }

        $path = 'credentials/'.$credential->id.'.html';
        $this->storage->store($path, $html);

        return $path;
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
