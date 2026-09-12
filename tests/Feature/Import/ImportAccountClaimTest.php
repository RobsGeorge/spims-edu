<?php

namespace Tests\Feature\Import;

use App\Enums\ImportAccountClaimStatus;
use App\Enums\ImportPopulation;
use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Mail\ImportAccountClaimMail;
use App\Models\ImportAccountClaim;
use App\Models\ImportSource;
use App\Models\User;
use App\Services\Import\ImportAccountClaimService;
use App\Services\Import\ImportBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportAccountClaimTest extends TestCase
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

    private function batchService(): ImportBatchService
    {
        return app(ImportBatchService::class);
    }

    private function claimService(): ImportAccountClaimService
    {
        return app(ImportAccountClaimService::class);
    }

    /**
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

    /**
     * Commits a one-row ACTIVE STUDENT batch and returns the resulting PENDING user.
     */
    private function commitActiveBatch(User $actor, string $legacyId, string $email, string $name = 'Person, Active'): User
    {
        $source = ImportSource::query()->where('code', 'POPULI')->first() ?? $this->populi();
        $service = $this->batchService();

        $csv = $this->csv("ID,Name,Email\n{$legacyId},\"{$name}\",{$email}\n", "students-{$legacyId}.csv");
        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Active);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        return User::query()->where('email', $email)->firstOrFail();
    }

    #[Test]
    public function committing_an_active_batch_queues_a_claim_but_sends_no_mail(): void
    {
        Mail::fake();

        $actor = $this->admin();
        $user = $this->commitActiveBatch($actor, '9001', 'claimant@example.org');

        $this->assertSame(UserStatus::Pending, $user->status);

        $claim = ImportAccountClaim::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($claim, 'Expected an import_account_claims row to be queued on commit.');
        $this->assertSame(ImportAccountClaimStatus::Queued, $claim->status);
        $this->assertNotNull($claim->batch_id);
        $this->assertNull($claim->invited_at);

        Mail::assertNothingSent();
    }

    #[Test]
    public function committing_the_alumni_population_never_queues_a_claim(): void
    {
        Mail::fake();

        $source = $this->populi();
        $actor = $this->admin();
        $service = $this->batchService();

        $csv = $this->csv("ID,Name,Email\n9002,\"Person, Alumnus\",alumnus@example.org\n");
        $batch = $service->createFromUpload($actor, $source, $csv, ImportPopulation::Alumni);
        $batch = $service->updateMapping($batch, $this->identityMapping());
        $batch = $service->validate($batch);
        $service->commit($actor, $batch->fresh());

        $user = User::query()->where('email', 'alumnus@example.org')->firstOrFail();
        $this->assertSame(UserStatus::Archived, $user->status);
        $this->assertSame(0, ImportAccountClaim::query()->where('user_id', $user->id)->count());

        Mail::assertNothingSent();
    }

    #[Test]
    public function sending_invitations_sends_mail_in_the_recipients_locale(): void
    {
        Mail::fake();

        $actor = $this->admin();
        $user = $this->commitActiveBatch($actor, '9010', 'french.claimant@example.org');
        $user->update(['preferred_locale' => 'fr']);

        $claim = ImportAccountClaim::query()->where('user_id', $user->id)->firstOrFail();
        $result = $this->claimService()->send($actor, ImportAccountClaim::query()->whereKey($claim->id)->get());

        $this->assertSame(1, $result['sent']);
        $this->assertSame(0, $result['skipped']);

        Mail::assertSent(ImportAccountClaimMail::class, function (ImportAccountClaimMail $mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->locale === 'fr';
        });

        $claim->refresh();
        $this->assertSame(ImportAccountClaimStatus::Sent, $claim->status);
        $this->assertNotNull($claim->invited_at);
    }

    #[Test]
    public function sending_is_idempotent_for_an_already_sent_or_claimed_claim(): void
    {
        Mail::fake();

        $actor = $this->admin();
        $user = $this->commitActiveBatch($actor, '9020', 'idempotent@example.org');
        $claim = ImportAccountClaim::query()->where('user_id', $user->id)->firstOrFail();

        $service = $this->claimService();
        $first = $service->send($actor, ImportAccountClaim::query()->whereKey($claim->id)->get());
        $this->assertSame(1, $first['sent']);

        // Re-selecting the now-SENT claim and pressing send again must not double-send.
        $second = $service->send($actor, ImportAccountClaim::query()->whereKey($claim->id)->get());
        $this->assertSame(0, $second['sent']);
        $this->assertSame(1, $second['skipped']);

        Mail::assertSent(ImportAccountClaimMail::class, 1);

        // Nor once genuinely CLAIMED.
        $claim->update(['status' => ImportAccountClaimStatus::Claimed, 'claimed_at' => now()]);
        $third = $service->send($actor, ImportAccountClaim::query()->whereKey($claim->id)->get());
        $this->assertSame(0, $third['sent']);
        Mail::assertSent(ImportAccountClaimMail::class, 1);
    }

    #[Test]
    public function throttled_sending_does_not_crash_on_a_larger_batch(): void
    {
        Mail::fake();
        config(['import.claim_send_chunk_size' => 10, 'import.claim_send_chunk_delay_ms' => 0]);

        $actor = $this->admin();
        for ($i = 0; $i < 30; $i++) {
            $this->commitActiveBatch($actor, (string) (9100 + $i), "bulk{$i}@example.org");
        }

        $claims = ImportAccountClaim::query()->where('status', ImportAccountClaimStatus::Queued)->get();
        $this->assertCount(30, $claims);

        $result = $this->claimService()->send($actor, $claims);

        $this->assertSame(30, $result['sent']);
        $this->assertSame(0, $result['skipped']);
        Mail::assertSent(ImportAccountClaimMail::class, 30);
    }

    #[Test]
    public function claiming_the_account_end_to_end_flips_the_claim_to_claimed(): void
    {
        Mail::fake();

        $actor = $this->admin();
        $user = $this->commitActiveBatch($actor, '9030', 'e2e-claimant@example.org');
        $claim = ImportAccountClaim::query()->where('user_id', $user->id)->firstOrFail();

        $this->claimService()->send($actor, ImportAccountClaim::query()->whereKey($claim->id)->get());

        $captured = null;
        Mail::assertSent(ImportAccountClaimMail::class, function (ImportAccountClaimMail $mail) use (&$captured) {
            $captured = $mail;

            return true;
        });
        $this->assertNotNull($captured);

        // The claimant clicks the emailed link — exactly the same session bootstrap a
        // freshly-registered user gets from RegisterController::store().
        $this->get($captured->claimUrl)->assertRedirect(route('auth.verify'));
        $this->assertSame($user->id, session('pending_user_id'));

        // From here on it is the real, unmodified auth.verify -> auth.password.create
        // flow — the exact same HTTP routes any new user goes through.
        $this->post(route('auth.verify'), ['code' => $captured->code])
            ->assertRedirect(route('auth.password.create'));

        $this->post(route('auth.password.create'), [
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();

        $user->refresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue($user->hasRole(RoleType::Student));

        $claim->refresh();
        $this->assertSame(ImportAccountClaimStatus::Claimed, $claim->status);
        $this->assertNotNull($claim->claimed_at);
    }

    #[Test]
    public function bounce_and_resend_flow_corrects_the_email_and_requeues(): void
    {
        Mail::fake();

        $actor = $this->admin();
        $user = $this->commitActiveBatch($actor, '9040', 'bad-address@example.org');
        $claim = ImportAccountClaim::query()->where('user_id', $user->id)->firstOrFail();

        $service = $this->claimService();
        $service->send($actor, ImportAccountClaim::query()->whereKey($claim->id)->get());
        $claim->refresh();
        $this->assertSame(ImportAccountClaimStatus::Sent, $claim->status);

        $service->markBounced($actor, $claim);
        $claim->refresh();
        $this->assertSame(ImportAccountClaimStatus::Bounced, $claim->status);

        $service->correctEmailAndRequeue($actor, $claim, 'corrected-address@example.org');
        $claim->refresh();
        $user->refresh();

        $this->assertSame(ImportAccountClaimStatus::Queued, $claim->status);
        $this->assertSame('corrected-address@example.org', $user->email);

        // The corrected, re-queued claim can now be sent like any other.
        $result = $service->send($actor, ImportAccountClaim::query()->whereKey($claim->id)->get());
        $this->assertSame(1, $result['sent']);
        Mail::assertSent(ImportAccountClaimMail::class, fn (ImportAccountClaimMail $mail) => $mail->hasTo('corrected-address@example.org'));
    }

    #[Test]
    public function only_administrative_admin_can_reach_the_activation_screen(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.imports.activation'))->assertOk();

        $academicAdmin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $this->actingAs($academicAdmin)->get(route('admin.imports.activation'))->assertForbidden();

        $financialAdmin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $this->actingAs($financialAdmin)->get(route('admin.imports.activation'))->assertForbidden();

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->actingAs($instructor)->get(route('admin.imports.activation'))->assertForbidden();
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.imports.activation'))->assertRedirect(route('auth.login'));
    }

    #[Test]
    public function send_endpoint_is_gated_by_import_activate(): void
    {
        $actor = $this->admin();
        $user = $this->commitActiveBatch($actor, '9050', 'gated@example.org');
        $claim = ImportAccountClaim::query()->where('user_id', $user->id)->firstOrFail();

        $academicAdmin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $this->actingAs($academicAdmin)
            ->post(route('admin.imports.activation.send'), ['claim_ids' => [$claim->id]])
            ->assertForbidden();
    }
}
