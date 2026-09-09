<?php

namespace Tests\Feature\Design;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhaseBVisualAlignmentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function theme_css_defines_academic_recipe_aliases(): void
    {
        $css = file_get_contents(public_path('css/spims-theme.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('.academic-card', $css);
        $this->assertStringContainsString('.academic-form', $css);
        $this->assertStringContainsString('.academic-alert', $css);
        $this->assertStringContainsString('.academic-modal', $css);
        $this->assertStringContainsString('.academic-empty', $css);
        $this->assertStringContainsString('.spims-filter-bar', $css);
        $this->assertStringContainsString('.spims-data-panel', $css);
        $this->assertStringContainsString('spims-table-wrap--cards', $css);
        $this->assertStringNotContainsString('#faf6ee', $css);
    }

    #[Test]
    public function auth_and_shared_chrome_use_academic_recipes(): void
    {
        $this->seed();

        $this->get(route('auth.login'))
            ->assertOk()
            ->assertSee('spims-auth-split', false)
            ->assertSee('spims-auth-brand', false)
            ->assertSee('academic-form', false)
            ->assertSee('auth-card', false);

        $this->assertStringContainsString(
            'academic-alert',
            file_get_contents(resource_path('views/partials/flash.blade.php'))
        );
        $this->assertStringContainsString(
            'academic-empty',
            file_get_contents(resource_path('views/components/empty-state.blade.php'))
        );
    }

    #[Test]
    public function dashboard_catalog_settings_use_page_header_chrome(): void
    {
        $this->seed();
        $user = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('spims-page-header', false)
            ->assertSee('academic-card', false)
            ->assertSee('portal-dashboard', false);

        $this->actingAs($user)
            ->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('spims-filter-bar', false)
            ->assertSee('academic-form', false);

        $this->actingAs($user)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('spims-page-header', false)
            ->assertSee('settings-page', false)
            ->assertSee('academic-form', false);
    }

    #[Test]
    public function teach_admin_and_roles_hub_keep_shared_recipes(): void
    {
        $this->seed();
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $sa = User::factory()->withRole(RoleType::SuperAdmin)->create();

        $this->actingAs($instructor)
            ->get(route('teach.index'))
            ->assertOk()
            ->assertSee('teach-hub', false)
            ->assertSee('spims-page-header', false);

        $this->actingAs($admin)
            ->get(route('admin.programs.index'))
            ->assertOk()
            ->assertSee('admin-console', false)
            ->assertSee('spims-data-panel', false)
            ->assertSee('spims-table-wrap--cards', false);

        $this->actingAs($sa)
            ->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee('sa-hub', false)
            ->assertSee('academic-card', false);

        $this->actingAs($sa)
            ->get(route('roles.hub'))
            ->assertOk()
            ->assertSee('spims-page-header', false)
            ->assertSee('roles-hub-help-jump', false)
            ->assertSee(__('roles_hub.section_help'));
    }
}
