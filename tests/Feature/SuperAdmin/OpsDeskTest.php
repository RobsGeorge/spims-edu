<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpsDeskTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function hub_shows_explanations_and_live_schedule_and_rejects_outsiders(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)->get(route('superadmin.ops'))
            ->assertOk()
            ->assertSee(__('ops.page_lead'))
            ->assertSee(__('ops.page_help'))
            ->assertSee(__('ops.danger_title'))
            ->assertSee(__('ops.allowlist_title'))
            ->assertSee(__('ops.jobs_payload_hidden'))
            ->assertSee(__('ops.jobs_empty'))
            ->assertSee(__('ops.backup_now_help'))
            ->assertSee(__('ops.schedule_help'))
            ->assertSee('communications:fire-reminders', false)
            ->assertSee('spims:backup-database', false)
            ->assertSee('spims:prune-audit-logs', false)
            ->assertSee(config('session.driver'), false)
            ->assertSee(__('ops.session_cannot_flush', ['driver' => config('session.driver')]))
            ->assertDontSee('PII-SHOULD-NOT-RENDER-xyzzy', false);

        $this->actingAs($student)->get(route('superadmin.ops'))->assertForbidden();
        $this->actingAs($adm)->get(route('superadmin.ops'))->assertForbidden();
        $this->actingAs($student)->post(route('superadmin.ops.backup'))->assertForbidden();
        $this->actingAs($adm)->post(route('superadmin.ops.jobs.retry', (string) Str::uuid()))->assertForbidden();
    }

    #[Test]
    public function retry_clears_row_increments_attempts_in_audit_and_hides_payload(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $uuid = (string) Str::uuid();
        $this->insertFailedJob($uuid, 2, 'App\\Jobs\\OpsDeskUniqueRetryJob');

        $this->actingAs($sa)->get(route('superadmin.ops'))
            ->assertOk()
            ->assertSee('App\\Jobs\\OpsDeskUniqueRetryJob', false)
            ->assertSee('unique-ops-exception-line', false)
            ->assertDontSee('PII-SHOULD-NOT-RENDER-xyzzy', false)
            ->assertDontSee('email-body-secret', false);

        $this->actingAs($sa)
            ->from(route('superadmin.ops'))
            ->post(route('superadmin.ops.jobs.retry', $uuid))
            ->assertRedirect(route('superadmin.ops'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);

        $log = AuditLog::query()->where('action', 'ops.failed_jobs.retry')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($sa->id, $log->actor_id);
        $this->assertNull($log->entity_id);
        $this->assertSame($uuid, $log->after['uuid'] ?? null);
        $this->assertSame(3, (int) ($log->after['attempts'] ?? 0));
        $this->assertSame('FailedJob', $log->entity_type);
    }

    #[Test]
    public function delete_removes_row_and_writes_audit(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $uuid = (string) Str::uuid();
        $this->insertFailedJob($uuid, 1, 'App\\Jobs\\OpsDeskUniqueDeleteJob');

        $this->actingAs($sa)
            ->from(route('superadmin.ops'))
            ->post(route('superadmin.ops.jobs.delete', $uuid))
            ->assertRedirect(route('superadmin.ops'));

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ops.failed_jobs.delete',
            'actor_id' => $sa->id,
            'entity_type' => 'FailedJob',
            'entity_id' => null,
        ]);
    }

    #[Test]
    public function unknown_or_invalid_job_uuid_is_not_found(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->post(route('superadmin.ops.jobs.retry', (string) Str::uuid()))
            ->assertNotFound();
        $this->actingAs($sa)->post(route('superadmin.ops.jobs.delete', 'not-a-uuid-value-at-all'))
            ->assertNotFound();
    }

    #[Test]
    public function backup_now_writes_audit_and_sqlite_marker(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'spims-ops-'.Str::lower(Str::random(8));
        File::ensureDirectoryExists($dir);
        config(['spims.backup.path' => $dir]);

        $this->actingAs($sa)
            ->from(route('superadmin.ops'))
            ->post(route('superadmin.ops.backup'))
            ->assertRedirect(route('superadmin.ops'))
            ->assertSessionHas('status', __('ops.backup_ran', ['driver' => 'sync']));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ops.backup.run',
            'actor_id' => $sa->id,
            'entity_type' => 'Backup',
        ]);

        $files = File::files($dir);
        $this->assertNotEmpty($files);
        $this->assertTrue(collect($files)->contains(
            fn ($file) => str_contains($file->getFilename(), 'spims_')
        ));

        $html = $this->actingAs($sa)->get(route('superadmin.ops'))->assertOk()->getContent();
        $this->assertStringNotContainsString('PGPASSWORD', $html);
        $this->assertStringNotContainsString((string) env('SUPERADMIN_PASSWORD'), $html);

        File::deleteDirectory($dir);
    }

    #[Test]
    public function dashboard_hub_and_related_pages_link_to_ops_desk(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('ops.dashboard_tile'))
            ->assertSee(__('ops.dashboard_tile_hint'))
            ->assertSee(route('superadmin.ops'), false);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('ops.dashboard_tile'));

        $this->actingAs($sa)->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('superadmin.tile_ops'))
            ->assertSee(__('superadmin.tile_ops_hint'))
            ->assertSee(__('superadmin.roadmap_sa6_done'))
            ->assertSee(route('superadmin.ops'), false);

        $this->actingAs($sa)->get(route('superadmin.observability.index'))
            ->assertOk()
            ->assertSee(__('ops.entrance_from_observability'))
            ->assertSee(route('superadmin.ops'), false)
            ->assertSee('failed_jobs', false);

        $this->actingAs($sa)->get(route('superadmin.scheduled-tasks.index'))
            ->assertOk()
            ->assertSee('communications:fire-reminders', false)
            ->assertSee(__('ops.entrance_from_scheduled'))
            ->assertSee(__('superadmin.scheduled_live_help'));

        $this->actingAs($sa)->get(route('superadmin.security'))
            ->assertOk()
            ->assertSee(__('ops.entrance_from_security'))
            ->assertSee(__('superadmin.security_flush_help'));

        $this->actingAs($sa)->get(route('superadmin.system-tests.index'))
            ->assertOk()
            ->assertSee(__('superadmin.system_tests_d8'))
            ->assertSee(__('ops.entrance_from_tests'));
    }

    private function insertFailedJob(string $uuid, int $attempts, string $name): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'sync',
            'queue' => 'default',
            'payload' => json_encode([
                'displayName' => $name,
                'attempts' => $attempts,
                'data' => [
                    'secret' => 'PII-SHOULD-NOT-RENDER-xyzzy',
                    'body' => 'email-body-secret',
                ],
            ]),
            'exception' => "RuntimeException: unique-ops-exception-line\n#0 /app/Jobs/Fake.php(1)",
            'failed_at' => now(),
        ]);
    }
}
