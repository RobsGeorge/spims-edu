<?php

namespace Tests\Feature\Help;

use App\Enums\RoleType;
use App\Models\User;
use App\Services\Rbac\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HelpDiscoverabilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function finance_admin_page_links_to_minor_units_help(): void
    {
        $this->seed();
        app(RolePermissionService::class)->syncFromConfig(force: true);
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();

        $this->actingAs($fin)
            ->get(route('admin.finance.index'))
            ->assertOk()
            ->assertSee(__('help.learn_more'))
            ->assertSee(route('help.show', 'minor-units-explained'), false);
    }

    #[Test]
    public function theme_editor_links_to_branding_help(): void
    {
        $this->seed();
        app(RolePermissionService::class)->syncFromConfig(force: true);
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($admin)
            ->get(route('admin.theme.edit'))
            ->assertOk()
            ->assertSee(__('help.learn_more'))
            ->assertSee(route('help.show', 'theme-branding'), false);
    }

    #[Test]
    public function people_directory_links_to_users_roles_help(): void
    {
        $this->seed();
        app(RolePermissionService::class)->syncFromConfig(force: true);
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(__('help.learn_more'))
            ->assertSee(route('help.show', 'users-roles'), false);
    }

    #[Test]
    public function catalog_empty_state_offers_enroll_help_cta(): void
    {
        $this->seed();

        $this->get(route('catalog.index', [
            'tab' => 'courses',
            'q' => 'zzznonexistent999xyz',
        ]))
            ->assertOk()
            ->assertSee(__('help.catalog_enroll_cta'))
            ->assertSee(route('help.show', 'browse-catalog-enroll'), false);
    }
}
