<?php

namespace Tests\Feature\Portal;

use App\Enums\RoleType;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\AuthorizeService;
use App\Support\Navigation;
use App\Support\NavigationHub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalHubsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AuthorizeService::class)->forgetMatrixCache();
    }

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
            __('roles_hub.help_gap_parent'),
            __('roles_hub.help_nav_title'),
            __('roles_hub.help_nav_body'),
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
            'Hub navigation still uses role-name checks',
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
            str_contains($arHtml, __('roles_hub.help_nav_body', [], 'ar')),
            'Missing Arabic hub-nav body'
        );
    }

    #[Test]
    public function shipped_roles_see_hub_nav_from_permission_keys_not_role_names(): void
    {
        $this->seed();

        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();

        $this->assertTrue(NavigationHub::hasAdministrative($adm));
        $this->assertFalse(NavigationHub::hasAcademicAdmin($adm));
        $this->assertFalse(NavigationHub::hasFinanceAdmin($adm));

        $this->assertTrue(NavigationHub::hasAcademicAdmin($aca));
        $this->assertFalse(NavigationHub::hasAdministrative($aca));
        $this->assertFalse(NavigationHub::hasFinanceAdmin($aca));

        $this->assertTrue(NavigationHub::hasFinanceAdmin($fin));
        $this->assertFalse(NavigationHub::hasAcademicAdmin($fin));
        $this->assertFalse(NavigationHub::hasAdministrative($fin));

        $this->assertFalse(NavigationHub::hasAcademicAdmin($student));
        $this->assertFalse(NavigationHub::hasAdministrative($student));
        $this->assertFalse(NavigationHub::hasFinanceAdmin($student));
        $this->assertFalse(NavigationHub::hasSuperadmin($student));

        $this->assertFalse(NavigationHub::hasAcademicAdmin($instructor));
        $this->assertFalse(NavigationHub::hasAdministrative($instructor));

        $this->assertSame(['hubs.admin'], self::primaryHubRoutes($adm));
        $this->assertSame(['hubs.academic'], self::primaryHubRoutes($aca));
        $this->assertSame([], self::primaryHubRoutes($student));
        $this->assertSame([], self::primaryHubRoutes($instructor));

        $admHtml = $this->actingAs($adm)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertTrue(str_contains($admHtml, route('hubs.admin')));
        $this->assertFalse(str_contains($admHtml, route('hubs.academic')));
        $this->assertFalse(str_contains($admHtml, route('superadmin.index')));

        $acaHtml = $this->actingAs($aca)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertTrue(str_contains($acaHtml, route('hubs.academic')));
        $this->assertFalse(str_contains($acaHtml, route('hubs.admin')));

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('hubs.nav_learning'));
        $studentHtml = $this->actingAs($student)->get(route('dashboard'))->getContent();
        $this->assertFalse(str_contains($studentHtml, route('hubs.academic')));
        $this->assertFalse(str_contains($studentHtml, route('hubs.admin')));
        $this->assertFalse(str_contains($studentHtml, route('superadmin.index')));

        $this->actingAs($fin)->get(route('hubs.finance'))
            ->assertOk()
            ->assertSee(__('hubs.finance_admin'));

        $this->actingAs($student)->get(route('hubs.finance'))
            ->assertOk()
            ->assertDontSee(__('hubs.finance_admin'));
    }

    #[Test]
    public function granting_a_hub_key_on_student_shows_that_hub(): void
    {
        $this->seed();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->assertSame([], self::primaryHubRoutes($student));

        RolePermission::query()->create([
            'role' => RoleType::Student,
            'permission_key' => NavigationHub::HUB_ACADEMIC,
            'level' => 'F',
        ]);
        app(AuthorizeService::class)->forgetMatrixCache();

        $this->assertTrue(NavigationHub::hasAcademicAdmin($student->fresh()));
        $this->assertSame(['hubs.academic'], self::primaryHubRoutes($student->fresh()));

        RolePermission::query()->create([
            'role' => RoleType::Student,
            'permission_key' => NavigationHub::HUB_ADMIN,
            'level' => 'F',
        ]);
        app(AuthorizeService::class)->forgetMatrixCache();

        $granted = $student->fresh();
        $this->assertTrue(NavigationHub::hasAdministrative($granted));
        $this->assertSame(['hubs.academic', 'hubs.admin'], self::primaryHubRoutes($granted));
        $this->assertFalse(NavigationHub::hasSuperadmin($granted));

        $html = $this->actingAs($granted)->get(route('dashboard'))->assertOk()->getContent();
        $this->assertTrue(str_contains($html, route('hubs.academic')));
        $this->assertTrue(str_contains($html, route('hubs.admin')));
        $this->assertFalse(str_contains($html, route('superadmin.index')));
    }

    /**
     * @return list<string>
     */
    private static function primaryHubRoutes(User $user): array
    {
        return array_values(array_filter(
            array_column(NavigationHub::primaryNav($user), 'route'),
            fn (string $route): bool => in_array($route, ['hubs.academic', 'hubs.admin', 'superadmin.index'], true)
        ));
    }

    #[Test]
    public function legacy_sidebar_links_follow_permission_keys(): void
    {
        $this->seed();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $admRoutes = array_column(Navigation::linksFor($adm), 'route');
        $this->assertContains('admin.users.index', $admRoutes);
        $this->assertContains('admin.theme.edit', $admRoutes);
        $this->assertContains('admin.credentials.index', $admRoutes);
        $this->assertNotContains('admin.programs.index', $admRoutes);
        $this->assertNotContains('admin.finance.index', $admRoutes);

        $acaRoutes = array_column(Navigation::linksFor($aca), 'route');
        $this->assertContains('admin.programs.index', $acaRoutes);
        $this->assertContains('admin.offerings.index', $acaRoutes);
        $this->assertContains('admin.credentials.index', $acaRoutes);
        $this->assertNotContains('admin.users.index', $acaRoutes);

        $studentRoutes = array_column(Navigation::linksFor($student), 'route');
        $this->assertNotContains('admin.users.index', $studentRoutes);
        $this->assertNotContains('admin.programs.index', $studentRoutes);
        $this->assertContains('catalog.index', $studentRoutes);
    }
}
