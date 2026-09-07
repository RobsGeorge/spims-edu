<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditExplorerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function explorer_filters_by_action_prefix_and_shows_labels(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $instructor = User::factory()->withRole(RoleType::Instructor)->create([
            'email' => 'theme.writer@example.com',
        ]);

        $suspend = $this->writeLog($sa, 'users.suspend.sa2filter', [
            'entity_type' => 'User',
            'entity_id' => $instructor->id,
            'before' => ['status' => 'ACTIVE'],
            'after' => ['status' => 'SUSPENDED'],
            'request_id' => 'req-users-1',
        ]);
        $this->writeLog($instructor, 'theme.update.sa2filter', [
            'entity_type' => 'Theme',
            'after' => ['name' => 'Sacred Academic'],
        ]);

        $this->actingAs($sa)->get(route('superadmin.audit.index', ['action' => 'users.']))
            ->assertOk()
            ->assertSee(__('audit.explorer_title'))
            ->assertSee(__('audit.filter_action_help'))
            ->assertSee(__('audit.filter_actor_help'))
            ->assertSee(__('audit.append_only'))
            ->assertSee('users.suspend.sa2filter')
            ->assertSee(route('superadmin.audit.show', $suspend), false)
            ->assertDontSee('theme.update.sa2filter')
            ->assertDontSee('theme.writer@example.com');

        $this->actingAs($sa)->get(route('superadmin.audit.index', ['actor' => 'theme.writer']))
            ->assertOk()
            ->assertSee('theme.update.sa2filter')
            ->assertDontSee('users.suspend.sa2filter');
    }

    #[Test]
    public function detail_shows_before_after_and_request_context(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'email' => 'audited.student@example.com',
        ]);
        $log = $this->writeLog($sa, 'users.suspend', [
            'entity_type' => 'User',
            'entity_id' => $student->id,
            'before' => ['status' => 'ACTIVE'],
            'after' => ['status' => 'SUSPENDED'],
            'ip' => '203.0.113.9',
            'user_agent' => 'SPIMS-AuditExplorerTest/1.0',
            'request_id' => 'req-detail-9',
        ]);

        $this->actingAs($sa)->get(route('superadmin.audit.show', $log))
            ->assertOk()
            ->assertSee(__('audit.detail_lead'))
            ->assertSee(__('audit.payload_help'))
            ->assertSee('users.suspend')
            ->assertSee('ACTIVE')
            ->assertSee('SUSPENDED')
            ->assertSee('203.0.113.9')
            ->assertSee('SPIMS-AuditExplorerTest/1.0')
            ->assertSee('req-detail-9')
            ->assertSee(__('audit.filter_this_action'))
            ->assertSee(route('admin.users.show', $sa), false);

        $this->actingAs($sa)->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee(__('audit.entrance_from_people'))
            ->assertSee(route('superadmin.audit.index', ['actor' => $student->email]), false);
    }

    #[Test]
    public function export_csv_has_bom_and_localized_headers(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $this->writeLog($sa, 'users.suspend', [
            'after' => ['status' => 'SUSPENDED'],
        ]);
        $this->writeLog($sa, 'theme.update', [
            'after' => ['name' => 'ignored-by-filter'],
        ]);

        $response = $this->actingAs($sa)->get(route('superadmin.audit.export', ['action' => 'users.']));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString(__('audit.col_when'), $csv);
        $this->assertStringContainsString(__('audit.col_actor'), $csv);
        $this->assertStringContainsString(__('audit.col_before'), $csv);
        $this->assertStringContainsString('users.suspend', $csv);
        $this->assertStringNotContainsString('theme.update', $csv);
        $this->assertStringNotContainsString('ignored-by-filter', $csv);
    }

    #[Test]
    public function prune_keeps_control_plane_actions_inside_triple_retention(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $oldLogin = $this->writeLog($sa, 'auth.login');
        $oldLogin->forceFill(['created_at' => now()->subDays(400)])->save();

        $keptImpersonation = $this->writeLog($sa, 'users.impersonate.start');
        $keptImpersonation->forceFill(['created_at' => now()->subDays(400)])->save();

        $expiredImpersonation = $this->writeLog($sa, 'users.impersonate.stop');
        $expiredImpersonation->forceFill(['created_at' => now()->subDays(1200)])->save();

        $recentLogin = $this->writeLog($sa, 'auth.login');

        $this->artisan('spims:prune-audit-logs')
            ->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $oldLogin->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $keptImpersonation->id]);
        $this->assertDatabaseMissing('audit_logs', ['id' => $expiredImpersonation->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recentLogin->id]);
    }

    #[Test]
    public function student_and_administrative_admin_cannot_open_the_explorer(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $log = $this->writeLog($sa, 'users.suspend');
        $student = User::factory()->withRole(RoleType::Student)->create();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        foreach ([$student, $adm] as $actor) {
            $this->actingAs($actor)->get(route('superadmin.audit.index'))->assertForbidden();
            $this->actingAs($actor)->get(route('superadmin.audit.show', $log))->assertForbidden();
            $this->actingAs($actor)->get(route('superadmin.audit.export'))->assertForbidden();
        }

        $this->actingAs($adm)->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertDontSee(__('audit.entrance_from_people'));
    }

    #[Test]
    public function explorer_is_linked_from_existing_pages(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('superadmin.roadmap_sa2_done'))
            ->assertSee(__('superadmin.tile_audit_hint'))
            ->assertSee(route('superadmin.audit.index'), false);

        $this->actingAs($sa)->get(route('hubs.admin'))
            ->assertOk()
            ->assertSee(__('audit.entrance_from_admin'))
            ->assertSee(route('superadmin.audit.index'), false);

        $this->actingAs($sa)->get(route('superadmin.observability.index'))
            ->assertOk()
            ->assertSee(__('audit.entrance_from_observability'))
            ->assertSee(__('superadmin.stat_audit_logs'));

        $this->actingAs($sa)->get(route('superadmin.scheduled-tasks.index'))
            ->assertOk()
            ->assertSee('spims:prune-audit-logs')
            ->assertSee(__('superadmin.scheduled_prune_help'));

        $this->actingAs($sa)->get(route('superadmin.security'))
            ->assertOk()
            ->assertSee(__('audit.entrance_from_security'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function writeLog(User $actor, string $action, array $overrides = []): AuditLog
    {
        return AuditLog::query()->create(array_merge([
            'actor_id' => $actor->id,
            'actor_role' => $actor->roleTypes()->first()?->value ?? RoleType::Student->value,
            'action' => $action,
            'entity_type' => null,
            'entity_id' => null,
            'before' => null,
            'after' => ['ok' => true],
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'request_id' => null,
        ], $overrides));
    }
}
