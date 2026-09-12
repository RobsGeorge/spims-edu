<?php

namespace Tests\Feature\Import;

use App\Enums\CredentialType;
use App\Enums\ImportEntityType;
use App\Enums\RoleType;
use App\Models\Credential;
use App\Models\ImportLink;
use App\Models\ImportRow;
use App\Models\ImportSource;
use App\Models\Setting;
use App\Models\User;
use App\Services\Credentials\CredentialService;
use App\Services\Import\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L8, Part A — legacy credentials + /verify historical state. See
 * docs/legacy-data-import-plan.md §22.1.
 */
class ImportCredentialTest extends TestCase
{
    use RefreshDatabase;

    private function populi(): ImportSource
    {
        return ImportSource::query()->create([
            'code' => 'POPULI',
            'name' => 'Populi',
            'kind' => 'SIS',
            'precedence' => 1,
            'gpa_scale_max' => 4.00,
            'default_currency' => 'EGP',
            'timezone' => 'Africa/Cairo',
            'active' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
    }

    private function linkedStudent(ImportSource $source, string $legacyId): User
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        ImportLink::query()->create([
            'source_id' => $source->id,
            'entity_type' => 'user',
            'legacy_id' => $legacyId,
            'target_type' => User::class,
            'target_id' => $student->id,
        ]);

        return $student;
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('credentials.csv', $content);
    }

    private function service(): ImportBatchService
    {
        return app(ImportBatchService::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapping(): array
    {
        return [
            ['column' => 'LegacyID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Type', 'target_field' => 'credential_type', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Serial', 'target_field' => 'serial', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'IssuedAt', 'target_field' => 'issued_at', 'transform' => 'date', 'options' => ['format' => 'Y-m-d'], 'ignored' => false],
        ];
    }

    #[Test]
    public function committing_a_credential_row_creates_a_historical_credential(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $student = $this->linkedStudent($source, '7001');

        $csv = $this->csv("LegacyID,Type,Serial,IssuedAt\n7001,PROGRAM_CERTIFICATE,POPULI-DIP-00042,2018-06-15\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Credential);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(0, $batch->error_count);
        $this->assertSame(0, $batch->warning_count);

        $service->commit($actor, $batch->fresh());

        $credential = Credential::query()->where('serial', 'POPULI-DIP-00042')->firstOrFail();
        $this->assertSame($student->id, $credential->student_id);
        $this->assertSame(CredentialType::ProgramCertificate, $credential->type);
        $this->assertSame('POPULI', $credential->source_system);
        $this->assertTrue($credential->isLegacy());
        $this->assertTrue($credential->isValid());
        $this->assertSame('2018-06-15', $credential->issued_at->toDateString());
        $this->assertNotEmpty($credential->qr_token);
    }

    #[Test]
    public function a_legacy_id_never_imported_as_a_student_is_a_hard_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        // Deliberately no ImportLink for '9999'.

        $csv = $this->csv("LegacyID,Type,Serial,IssuedAt\n9999,TRANSCRIPT,POPULI-TRX-1,2018-06-15\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Credential);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $this->assertTrue(collect($row->messages)->pluck('code')->contains('E_UNKNOWN_STUDENT'));

        $service->commit($actor, $batch->fresh());
        $this->assertSame(0, Credential::query()->count(), 'A row blocked by a hard error must not be committed.');
    }

    #[Test]
    public function an_unrecognised_credential_type_is_a_hard_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '7002');

        $csv = $this->csv("LegacyID,Type,Serial,IssuedAt\n7002,DIPLOMA,POPULI-DIP-1,2018-06-15\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Credential);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $this->assertTrue(collect($row->messages)->pluck('code')->contains('E_UNKNOWN_CREDENTIAL_TYPE'));
    }

    #[Test]
    public function a_serial_already_used_by_a_native_credential_is_a_hard_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $existingStudent = User::factory()->withRole(RoleType::Student)->create();
        Credential::query()->create([
            'student_id' => $existingStudent->id,
            'type' => CredentialType::Transcript,
            'serial' => 'SPIMS-CRED-2026-00001',
            'qr_token' => 'existing-token',
            'issued_at' => now(),
        ]);

        $this->linkedStudent($source, '7003');

        $csv = $this->csv("LegacyID,Type,Serial,IssuedAt\n7003,TRANSCRIPT,SPIMS-CRED-2026-00001,2018-06-15\n");

        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Credential);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $this->assertTrue(collect($row->messages)->pluck('code')->contains('E_DUPLICATE_SERIAL'));
    }

    #[Test]
    public function reimporting_the_same_serial_from_the_same_source_updates_instead_of_duplicating(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '7004');

        $csv1 = $this->csv("LegacyID,Type,Serial,IssuedAt\n7004,TRANSCRIPT,POPULI-TRX-9,2018-06-15\n");
        $batch1 = $service->createFromUpload($actor, $source, $csv1, null, entityType: ImportEntityType::Credential);
        $batch1 = $service->updateMapping($batch1, $this->mapping());
        $batch1 = $service->validate($batch1);
        $service->commit($actor, $batch1->fresh());

        $this->assertSame(1, Credential::query()->count());

        // A corrected re-export with a different issue date for the same record.
        $csv2 = $this->csv("LegacyID,Type,Serial,IssuedAt\n7004,TRANSCRIPT,POPULI-TRX-9,2018-07-01\n");
        $batch2 = $service->createFromUpload($actor, $source, $csv2, null, entityType: ImportEntityType::Credential);
        $batch2 = $service->updateMapping($batch2, $this->mapping());
        $batch2 = $service->validate($batch2);

        $this->assertSame(0, $batch2->error_count, 'A serial re-imported from the same source is not a duplicate.');

        $service->commit($actor, $batch2->fresh());

        $this->assertSame(1, Credential::query()->count(), 'Re-importing the same serial from the same source must update, not duplicate.');
        $this->assertSame('2018-07-01', Credential::query()->firstOrFail()->issued_at->toDateString());
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '7005');

        $csv = $this->csv("LegacyID,Type,Serial,IssuedAt\n7005,TRANSCRIPT,POPULI-TRX-5,2018-06-15\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Credential);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $countBefore = Credential::query()->count();
        $report = $service->dryRun($batch->fresh());

        $this->assertSame(1, $report['create']);
        $this->assertSame($countBefore, Credential::query()->count());
    }

    #[Test]
    public function committing_a_credential_batch_sends_no_mail_and_writes_one_audit_entry(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '7006');

        $csv = $this->csv("LegacyID,Type,Serial,IssuedAt\n7006,TRANSCRIPT,POPULI-TRX-6,2018-06-15\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Credential);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        \Illuminate\Support\Facades\Mail::assertNothingSent();
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'import.batch_commit',
        ]);
    }

    #[Test]
    public function a_legacy_credential_never_touches_the_spims_serial_counter(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '7007');

        // Confirm the counter has never been touched.
        $this->assertNull(Setting::query()->find('credentials.serial_counter'));

        $csv = $this->csv("LegacyID,Type,Serial,IssuedAt\n7007,TRANSCRIPT,POPULI-TRX-7,2018-06-15\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Credential);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        // Still untouched after a legacy import — nextSerial() was never called.
        $this->assertNull(Setting::query()->find('credentials.serial_counter'));

        $credential = Credential::query()->where('serial', 'POPULI-TRX-7')->firstOrFail();
        $this->assertDoesNotMatchRegularExpression('/^SPIMS-CRED-/', $credential->serial);

        // A native issuance right after still starts the counter at 1, proving the
        // legacy import consumed nothing from it.
        $student = User::factory()->withRole(RoleType::Student)->create();
        $native = app(CredentialService::class)->issueTranscript($actor, $student);
        $this->assertMatchesRegularExpression('/^SPIMS-CRED-\d{4}-00001$/', $native->serial);
    }

    #[Test]
    public function regenerate_refuses_for_a_legacy_sourced_credential_with_a_clear_localized_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '7008');

        $csv = $this->csv("LegacyID,Type,Serial,IssuedAt\n7008,TRANSCRIPT,POPULI-TRX-8,2018-06-15\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Credential);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $credential = Credential::query()->where('serial', 'POPULI-TRX-8')->firstOrFail();

        try {
            app(CredentialService::class)->regenerate($actor, $credential);
            $this->fail('Expected regenerate() to refuse a legacy-sourced credential.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('credential', $e->errors());
            $this->assertSame(__('credentials.legacy_no_reissue'), $e->errors()['credential'][0]);
        }

        $this->assertNull($credential->fresh()->revoked_at, 'A refused regenerate() must not have revoked the original.');
        $this->assertSame(1, Credential::query()->count(), 'A refused regenerate() must not have minted a replacement.');
    }

    #[Test]
    public function verify_page_shows_the_historical_state_for_a_legacy_credential(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '7009');

        $csv = $this->csv("LegacyID,Type,Serial,IssuedAt\n7009,TRANSCRIPT,POPULI-TRX-9-VERIFY,2018-06-15\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Credential);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $credential = Credential::query()->where('serial', 'POPULI-TRX-9-VERIFY')->firstOrFail();

        $this->get(route('credentials.verify', $credential->qr_token))
            ->assertOk()
            ->assertSee(__('credentials.historical_valid'))
            ->assertDontSee(__('credentials.valid'))
            ->assertSee('POPULI');
    }

    #[Test]
    public function rollback_reverses_a_legacy_credential_row_until_the_batch_seals(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '7010');

        $csv = $this->csv("LegacyID,Type,Serial,IssuedAt\n7010,TRANSCRIPT,POPULI-TRX-10,2018-06-15\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Credential);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $this->assertSame(1, Credential::query()->count());

        $result = $service->rollback($actor, $batch->fresh());

        $this->assertSame(1, $result['rolled_back']);
        $this->assertSame([], $result['blocked']);
        $this->assertSame(0, Credential::query()->count());
    }

    #[Test]
    public function rollback_refuses_a_credential_row_that_has_since_been_revoked(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();
        $this->linkedStudent($source, '7011');

        $csv = $this->csv("LegacyID,Type,Serial,IssuedAt\n7011,TRANSCRIPT,POPULI-TRX-11,2018-06-15\n");
        $batch = $service->createFromUpload($actor, $source, $csv, null, entityType: ImportEntityType::Credential);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $credential = Credential::query()->where('serial', 'POPULI-TRX-11')->firstOrFail();
        $credential->update(['revoked_at' => now()]);

        $result = $service->rollback($actor, $batch->fresh());

        $this->assertSame(0, $result['rolled_back']);
        $this->assertCount(1, $result['blocked']);
        $this->assertSame(1, Credential::query()->count(), 'A revoked credential must not be deleted by rollback.');
    }

    #[Test]
    public function only_administrative_admin_can_commit_a_credential_batch(): void
    {
        $source = $this->populi();
        $academicAdmin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $administrativeAdmin = $this->admin();
        $this->linkedStudent($source, '7012');

        $service = $this->service();
        $csv = $this->csv("LegacyID,Type,Serial,IssuedAt\n7012,TRANSCRIPT,POPULI-TRX-12,2018-06-15\n");
        $batch = $service->createFromUpload($administrativeAdmin, $source, $csv, null, entityType: ImportEntityType::Credential);
        $batch = $service->updateMapping($batch, $this->mapping());
        $batch = $service->validate($batch);

        $this->actingAs($academicAdmin)
            ->post(route('admin.imports.commit', $batch))
            ->assertForbidden();
    }
}
