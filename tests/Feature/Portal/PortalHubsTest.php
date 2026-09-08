<?php

namespace Tests\Feature\Portal;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalHubsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function superadmin_sees_dashboard_tiles_and_console(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.learning_hub'))
            ->assertSee(__('dashboard.superadmin_hub'))
            ->assertSee('bi-shield-lock-fill', false);

        $this->actingAs($sa)->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('superadmin.title'))
            ->assertSee(__('superadmin.tile_security'))
            ->assertSee(__('superadmin.tile_audit'));

        $this->actingAs($sa)->get(route('hubs.learning'))->assertOk();
        $this->actingAs($sa)->get(route('hubs.academic'))->assertOk();
        $this->actingAs($sa)->get(route('superadmin.audit.index'))->assertOk();
        $this->actingAs($sa)->get(route('superadmin.observability.index'))->assertOk();
    }

    #[Test]
    public function non_superadmin_cannot_open_console(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('superadmin.index'))->assertForbidden();
        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('dashboard.superadmin_hub'));
    }

    #[Test]
    public function layout_exposes_hub_nav_for_authenticated_user(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('hubs.nav_learning'))
            ->assertSee(__('hubs.nav_superadmin'));
    }

    #[Test]
    public function superadmin_roles_hub_shows_role_guide(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $html = $this->actingAs($sa)
            ->get(route('roles.hub', ['section' => 'help']))
            ->assertOk()
            ->getContent();

        foreach ([
            __('roles_hub.section_help'),
            __('roles_hub.help_levels_title'),
            __('roles_hub.help_matrix_title'),
            __('roles_hub.help_gaps_title'),
            __('roles_hub.help_gap_holds'),
            __('roles_hub.help_gap_advising_holds'),
            __('roles_hub.help_gap_hub_nav'),
            __('roles_hub.help_gap_parent'),
            __('roles_hub.help_adm_can'),
            __('roles_hub.help_fin_can'),
            __('roles_hub.help_ins_can'),
            __('roles_hub.help_aca_can'),
            __('roles_hub.help_dom_announcements'),
            __('roles_hub.role_INSTRUCTOR'),
            __('roles_hub.role_STUDENT'),
            'id="helpSection"',
            'accordion-collapse collapse show',
        ] as $needle) {
            $this->assertTrue(str_contains($html, $needle), 'Missing from role guide: '.$needle);
        }

        foreach ([
            'Checkboxes do not edit',
            'Only Super Admin can unsuspend',
            'Live-session scheduling is read-only',
            'cannot view advising',
            'not offerings.view',
            'no waitlist grant',
            'not publish school-wide',
            'see school waitlists',
        ] as $stale) {
            $this->assertFalse(str_contains($html, $stale), 'Stale role-guide claim still present: '.$stale);
        }

        $sa->forceFill(['preferred_locale' => 'ar'])->save();
        $arHtml = $this->actingAs($sa->fresh())
            ->get(route('roles.hub', ['section' => 'help']))
            ->assertOk()
            ->getContent();

        $this->assertTrue(str_contains($arHtml, 'dir="rtl"'), 'Role guide should be RTL in Arabic.');
        $this->assertTrue(
            str_contains($arHtml, __('roles_hub.section_help', [], 'ar')),
            'Missing Arabic role-guide title'
        );
        $this->assertTrue(
            str_contains($arHtml, __('roles_hub.help_gaps_title', [], 'ar')),
            'Missing Arabic gaps heading'
        );
        $this->assertTrue(
            str_contains($arHtml, __('roles_hub.help_gap_hub_nav', [], 'ar')),
            'Missing Arabic hub-nav gap'
        );
    }
}
