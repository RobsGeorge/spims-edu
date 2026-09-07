<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\RoleType;
use App\Models\User;
use App\Services\Live\AttendanceService;
use App\Services\SuperAdmin\SystemSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_update_attendance_threshold_and_attendance_service_reads_it(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->assertSame(60, app(AttendanceService::class)->defaultThresholdPercent());

        $response = $this->actingAs($sa)
            ->from(route('superadmin.config'))
            ->put(route('superadmin.config.update'), [
                'settings' => [
                    'attendance.default_threshold' => '90',
                ],
            ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertSame(90, app(SystemSettingService::class)->value('attendance.default_threshold'));
        $this->assertSame(90, app(AttendanceService::class)->defaultThresholdPercent());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system_settings.update',
            'actor_id' => $sa->id,
        ]);
    }

    #[Test]
    public function unknown_setting_key_is_rejected_with_422(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)
            ->putJson(route('superadmin.config.update'), [
                'settings' => [
                    'school.registration_open' => true,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('key');
    }

    #[Test]
    public function config_page_never_renders_secret_values(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $secret = (string) env('SUPERADMIN_PASSWORD');
        $this->assertNotSame('', $secret);

        $html = $this->actingAs($sa)->get(route('superadmin.config'))
            ->assertOk()
            ->assertSee(__('system_settings.never_title'))
            ->assertSee(__('system_settings.never_body'))
            ->assertSee(__('system_settings.integrations_never'))
            ->assertSee(__('system_settings.allowlist_help'))
            ->assertSee(__('system_settings.attendance.default_threshold_help'))
            ->assertSee(__('system_settings.school.timezone_help'))
            ->assertSee(__('system_settings.configured'))
            ->assertSee(__('system_settings.integration_paypal'))
            ->assertDontSee('name="MAIL_PASSWORD"', false)
            ->assertDontSee('name="PAYPAL_SECRET"', false)
            ->assertDontSee('name="SUPERADMIN_PASSWORD"', false)
            ->getContent();

        $this->assertStringNotContainsString($secret, $html);
        $this->assertStringNotContainsString('type="password"', $html);
    }

    #[Test]
    public function config_page_has_entrances_and_rejects_outsiders(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)->get(route('superadmin.config'))
            ->assertOk()
            ->assertSee(__('system_settings.page_lead'))
            ->assertSee(__('system_settings.open_features'))
            ->assertSee(route('superadmin.features'), false)
            ->assertSee('name="settings[attendance.default_threshold]"', false)
            ->assertSee('name="settings[school.timezone]"', false);

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('system_settings.dashboard_tile'))
            ->assertSee(__('system_settings.dashboard_tile_hint'))
            ->assertSee(route('superadmin.config'), false);

        $this->actingAs($sa)->get(route('admin.theme.edit'))
            ->assertOk()
            ->assertSee(__('system_settings.entrance_from_theme'));

        $this->actingAs($student)->get(route('superadmin.config'))->assertForbidden();
        $this->actingAs($adm)->get(route('superadmin.config'))->assertForbidden();
        $this->actingAs($student)
            ->put(route('superadmin.config.update'), [
                'settings' => ['attendance.default_threshold' => '10'],
            ])
            ->assertForbidden();
    }
}
