<?php

namespace Tests\Feature\Import;

use App\Enums\ImportPopulation;
use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Models\ImportLink;
use App\Models\ImportRow;
use App\Models\ImportSource;
use App\Models\Program;
use App\Models\User;
use App\Services\Import\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportBatchServiceTest extends TestCase
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

    private function csv(string $content, string $name = 'students.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function service(): ImportBatchService
    {
        return app(ImportBatchService::class);
    }

    /**
     * Standard identity mapping: legacy_id, name split, email — used by most scenarios.
     *
     * @return array<int, array<string, mixed>>
     */
    private function identityMapping(): array
    {
        return [
            ['column' => 'ID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'options' => [], 'ignored' => false],
            ['column' => 'Name', 'target_field' => 'first_name', 'transform' => 'name_part_first', 'options' => ['format' => 'last_first'], 'ignored' => false],
            ['column' => 'Name', 'target_field' => 'last_name', 'transform' => 'name_part_last', 'options' => ['format' => 'last_first'], 'ignored' => false],
            ['column' => 'Email', 'target_field' => 'email', 'transform' => 'lower', 'options' => [], 'ignored' => false],
        ];
    }

    #[Test]
    public function committing_an_alumni_batch_creates_archived_users_with_no_login(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email\n1001,\"Boutros, Mina\",mina@example.org\n1002,\"Samuel, Mariam\",mariam@example.org\n");

        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);

        $this->assertSame(0, $batch->error_count);
        $this->assertSame(2, ImportRow::query()->where('batch_id', $batch->id)->count());

        $service->commit($actor, $batch->fresh());

        $mina = User::query()->where('email', 'mina@example.org')->firstOrFail();
        $this->assertSame('Mina', $mina->first_name);
        $this->assertSame('Boutros', $mina->last_name);
        $this->assertSame(UserStatus::Archived, $mina->status);
        $this->assertNull($mina->password_hash);
        $this->assertSame('POPULI', $mina->source_system);

        $this->assertSame(2, ImportLink::query()->where('source_id', $source->id)->count());
    }

    #[Test]
    public function committing_an_active_batch_creates_pending_users_that_can_claim_an_account(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email\n2001,\"Fahmy, Andrew\",andrew@example.org\n");

        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Active);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);

        $this->assertSame(0, $batch->error_count);

        $service->commit($actor, $batch->fresh());

        $andrew = User::query()->where('email', 'andrew@example.org')->firstOrFail();
        $this->assertSame(UserStatus::Pending, $andrew->status);
    }

    #[Test]
    public function an_active_row_with_no_email_is_a_validation_error(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email\n3001,\"NoEmail, Person\",\n");

        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Active);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);

        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $this->assertSame('ERROR', $row->status->value);
        $codes = collect($row->messages)->pluck('code');
        $this->assertTrue($codes->contains('E_ACTIVE_NO_EMAIL'));
    }

    #[Test]
    public function an_alumnus_with_no_email_gets_a_synthetic_address_and_stays_unreachable(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email\n4001,\"NoEmail, Alumnus\",\n");

        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);

        // Missing email is a warning for alumni, not an error — the row still commits.
        $this->assertSame(0, $batch->error_count);
        $this->assertSame(1, $batch->warning_count);

        $service->commit($actor, $batch->fresh());

        $user = User::query()->where('first_name', 'Alumnus')->firstOrFail();
        $this->assertSame('legacy.populi.4001@no-email.invalid', $user->email);
        $this->assertSame(UserStatus::Archived, $user->status);
        $this->assertFalse($user->email_verified);
    }

    #[Test]
    public function a_duplicate_legacy_id_within_one_file_is_flagged(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email\n5001,\"One, Person\",one@example.org\n5001,\"Two, Person\",two@example.org\n");

        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);

        $this->assertGreaterThanOrEqual(1, $batch->error_count);
        $codes = ImportRow::query()->where('batch_id', $batch->id)->get()
            ->flatMap(fn (ImportRow $r) => collect($r->messages)->pluck('code'));
        $this->assertTrue($codes->contains('E_DUPLICATE_NATURAL_KEY'));
    }

    #[Test]
    public function a_bad_date_is_flagged_without_blocking_the_rest_of_the_row(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email,DOB\n6001,\"Person, Some\",some@example.org,14-Mar-94\n");

        $mapping = array_merge($this->identityMapping(), [
            ['column' => 'DOB', 'target_field' => 'date_of_birth', 'transform' => 'date', 'options' => ['format' => 'd/m/Y'], 'ignored' => false],
        ]);

        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $mapping);
        $batch = $service->validate($batch);

        $this->assertSame(1, $batch->error_count);
        $row = ImportRow::query()->where('batch_id', $batch->id)->firstOrFail();
        $codes = collect($row->messages)->pluck('code');
        $this->assertTrue($codes->contains('E_BAD_DATE'));
    }

    #[Test]
    public function dry_run_writes_nothing_to_the_school_records(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email\n7001,\"Person, Dry\",dry@example.org\n");

        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);

        $usersBefore = User::query()->count();
        $linksBefore = ImportLink::query()->count();

        $report = $service->dryRun($batch->fresh());

        $this->assertSame(1, $report['create']);
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertSame($linksBefore, ImportLink::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'dry@example.org']);
    }

    #[Test]
    public function a_second_import_of_the_same_legacy_id_links_instead_of_duplicating(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv1 = $this->csv("ID,Name,Email\n8001,\"Person, Repeat\",repeat@example.org\n");
        $batch1 = $service->createFromUpload($actor, $source, $csv1, ImportPopulation::Alumni);
        $batch1 = $service->updateMapping($batch1, $this->identityMapping());
        $batch1 = $service->validate($batch1);
        $service->commit($actor, $batch1->fresh());

        $this->assertSame(1, User::query()->where('email', 'repeat@example.org')->count());

        // A second batch, same source, same legacy id — perhaps a corrected re-export.
        $csv2 = $this->csv("ID,Name,Email\n8001,\"Person, Repeat\",repeat@example.org\n");
        $batch2 = $service->createFromUpload($actor, $source, $csv2, ImportPopulation::Alumni);
        $batch2 = $service->updateMapping($batch2, $this->identityMapping());
        $batch2 = $service->validate($batch2);
        $service->commit($actor, $batch2->fresh());

        $this->assertSame(1, User::query()->where('email', 'repeat@example.org')->count(), 'Re-importing the same legacy id must not create a duplicate user.');

        $row = ImportRow::query()->where('batch_id', $batch2->id)->firstOrFail();
        $this->assertSame('LINK', $row->action->value);
    }

    #[Test]
    public function commit_writes_one_audit_log_entry(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email\n9001,\"Person, Audited\",audited@example.org\n");
        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);

        $service->commit($actor, $batch->fresh());

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'import.batch_commit',
        ]);
    }

    #[Test]
    public function rollback_reverses_created_users_and_links(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email\n1101,\"Person, Reversible\",reversible@example.org\n");
        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);
        $batch = $service->commit($actor, $batch->fresh());

        $this->assertDatabaseHas('users', ['email' => 'reversible@example.org']);

        $result = $service->rollback($actor, $batch->fresh());

        $this->assertSame(1, $result['rolled_back']);
        $this->assertSame([], $result['blocked']);
        $this->assertDatabaseMissing('users', ['email' => 'reversible@example.org']);
        $this->assertSame(0, ImportLink::query()->where('source_id', $source->id)->count());
        $this->assertSame('ROLLED_BACK', $batch->fresh()->status->value);
    }

    #[Test]
    public function rollback_is_refused_once_a_created_account_has_been_claimed(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email\n1201,\"Person, Claimed\",claimed@example.org\n");
        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Active);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);
        $batch = $service->commit($actor, $batch->fresh());

        // The student claims their account: sets a password, verifies email.
        User::query()->where('email', 'claimed@example.org')->update([
            'password_hash' => 'hashed',
            'email_verified' => true,
        ]);

        $result = $service->rollback($actor, $batch->fresh());

        $this->assertSame(0, $result['rolled_back']);
        $this->assertCount(1, $result['blocked']);
        $this->assertDatabaseHas('users', ['email' => 'claimed@example.org']);
    }

    #[Test]
    public function rollback_is_refused_after_the_batch_is_sealed(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email\n1301,\"Person, Sealed\",sealed@example.org\n");
        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);
        $batch = $service->commit($actor, $batch->fresh());

        $batch->update(['sealed_at' => now()->subDay()]);

        $this->expectException(\RuntimeException::class);
        $service->rollback($actor, $batch->fresh());
    }

    #[Test]
    public function a_program_code_matching_the_live_catalog_attaches_a_student_program(): void
    {
        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $program = Program::query()->create([
            'code' => 'DIP-COPT',
            'name' => 'Diploma in Coptic Studies',
            'type' => 'DIPLOMA',
            'passing_threshold' => 60,
            'max_credits_per_semester' => 18,
            'max_courses_per_semester' => 6,
            'max_semesters_to_graduate' => 8,
            'active' => true,
        ]);

        $csv = $this->csv("ID,Name,Email,Program\n1401,\"Person, Enrolled\",enrolled@example.org,DIP-COPT\n1402,\"Person, Unknown\",unknown@example.org,NOT-REAL\n");

        $mapping = array_merge($this->identityMapping(), [
            ['column' => 'Program', 'target_field' => 'program_code', 'transform' => 'upper', 'options' => [], 'ignored' => false],
        ]);

        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $mapping);
        $batch = $service->validate($batch);

        $this->assertSame(0, $batch->error_count);
        $this->assertSame(1, $batch->warning_count);

        $service->commit($actor, $batch->fresh());

        $enrolled = User::query()->where('email', 'enrolled@example.org')->firstOrFail();
        $this->assertDatabaseHas('student_programs', ['student_id' => $enrolled->id, 'program_id' => $program->id]);

        $unknown = User::query()->where('email', 'unknown@example.org')->firstOrFail();
        $this->assertDatabaseMissing('student_programs', ['student_id' => $unknown->id]);
    }

    #[Test]
    public function committing_a_batch_sends_no_mail(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->service();

        $csv = $this->csv("ID,Name,Email\n1501,\"Person, Silent\",silent@example.org\n");
        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Active);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }
}
