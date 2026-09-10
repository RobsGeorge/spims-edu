<?php

namespace Tests\Feature\SystemDocs;

use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\SystemDocs\SystemDocsCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SystemDocsPortalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_can_browse_client_docs_by_default(): void
    {
        $this->get(route('system-docs.index'))
            ->assertOk()
            ->assertSee(__('system_docs.title'))
            ->assertSee(__('system_docs.guest_banner'))
            ->assertSee(__('system_docs.pages.overview.title'), false)
            ->assertDontSee(__('system_docs.pages.architecture.title'), false);

        $this->get(route('system-docs.show', 'overview'))->assertOk();
        $this->get(route('system-docs.show', 'architecture'))->assertNotFound();
    }

    #[Test]
    public function guest_landing_links_to_system_docs_when_published(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('system-docs.index'), false)
            ->assertSee(__('system_docs.nav'));
    }

    #[Test]
    public function guest_cannot_browse_when_unpublished(): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'system_docs.guest_published'],
            ['value' => ['enabled' => false]]
        );

        $this->get(route('system-docs.index'))->assertNotFound();
        $this->get(route('system-docs.show', 'overview'))->assertNotFound();
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('system-docs.index'), false);
    }

    #[Test]
    public function signed_in_user_can_read_client_and_technical_docs(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('system-docs.index'))
            ->assertOk()
            ->assertSee(__('system_docs.title'))
            ->assertSee(__('system_docs.open_guide'))
            ->assertSee(__('system_docs.pages.overview.title'))
            ->assertSee(__('system_docs.pages.architecture.title'));

        $this->actingAs($student)->get(route('system-docs.show', 'overview'))
            ->assertOk()
            ->assertSee(__('system_docs.pages.overview.title'));

        $this->actingAs($student)->get(route('system-docs.show', 'architecture'))
            ->assertOk()
            ->assertSee(__('system_docs.pages.architecture.title'));
    }

    #[Test]
    public function doc_pages_render_markdown_tables_as_html_not_pipes(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $roles = $this->actingAs($student)->get(route('system-docs.show', 'roles-guide'));
        $roles->assertOk();
        $roles->assertSee('<table class="help-article-table"', false);
        $roles->assertSee('<th>', false);
        $roles->assertSee('spims-table-wrap', false);
        $roles->assertSee('spims-table-wrap--cards', false);
        $roles->assertSee('data-label=', false);
        $roles->assertDontSee('|---|', false);
        $roles->assertSee('Super Admin', false);
    }

    #[Test]
    public function super_admin_can_publish_client_docs_to_guests(): void
    {
        $sa = User::factory()->withRole(RoleType::SuperAdmin)->create();

        $this->actingAs($sa)->get(route('superadmin.system-docs.publish'))
            ->assertOk()
            ->assertSee(__('system_docs.publish_title'));

        $this->actingAs($sa)
            ->put(route('superadmin.system-docs.publish.update'), ['guest_published' => '1'])
            ->assertRedirect();

        $this->assertTrue(app(SystemDocsCatalog::class)->isGuestPublished());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system_docs.guest_publish',
        ]);

        auth()->logout();

        $this->get(route('system-docs.index'))
            ->assertOk()
            ->assertSee(__('system_docs.pages.overview.title'), false)
            ->assertDontSee(__('system_docs.pages.architecture.title'), false);

        $this->get(route('system-docs.show', 'overview'))->assertOk();
        $this->get(route('system-docs.show', 'architecture'))->assertNotFound();
    }

    #[Test]
    public function super_admin_can_unpublish_guest_docs(): void
    {
        $sa = User::factory()->withRole(RoleType::SuperAdmin)->create();
        Setting::query()->updateOrCreate(
            ['key' => 'system_docs.guest_published'],
            ['value' => ['enabled' => true]]
        );

        $this->actingAs($sa)
            ->put(route('superadmin.system-docs.publish.update'), ['guest_published' => '0'])
            ->assertRedirect();

        $this->assertFalse(app(SystemDocsCatalog::class)->isGuestPublished());
        $this->assertTrue(
            AuditLog::query()->where('action', 'system_docs.guest_unpublish')->exists()
        );

        auth()->logout();

        $this->get(route('system-docs.index'))->assertNotFound();
    }

    #[Test]
    public function non_super_admin_cannot_open_publish_panel(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($admin)->get(route('superadmin.system-docs.publish'))->assertForbidden();
        $this->actingAs($admin)
            ->put(route('superadmin.system-docs.publish.update'), ['guest_published' => '1'])
            ->assertForbidden();
    }

    #[Test]
    public function unknown_slug_returns_404_for_signed_in_user(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('system-docs.show', 'not-a-real-guide'))
            ->assertNotFound();
    }

    #[Test]
    public function superadmin_hub_lists_system_docs_tile(): void
    {
        $sa = User::factory()->withRole(RoleType::SuperAdmin)->create();

        $this->actingAs($sa)->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('system_docs.superadmin_tile'));
    }
}
