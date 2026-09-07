<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\RoleType;
use App\Models\Theme;
use App\Models\User;
use App\Support\ThemeTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThemeStudioTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_lists_sacred_academic_and_rejects_outsiders(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)->get(route('superadmin.theme.index'))
            ->assertOk()
            ->assertSee('Sacred Academic')
            ->assertSee(__('theme_studio.page_lead'))
            ->assertSee(__('theme_studio.page_help'))
            ->assertSee(__('theme_studio.gold_title'))
            ->assertSee(__('theme_studio.create_name_help'))
            ->assertSee(__('theme_studio.open_staff'))
            ->assertSee(route('admin.theme.edit'), false);

        $this->actingAs($student)->get(route('superadmin.theme.index'))->assertForbidden();
        $this->actingAs($adm)->get(route('superadmin.theme.index'))->assertForbidden();
        $this->actingAs($student)->post(route('superadmin.theme.store'), ['name' => 'Nope'])
            ->assertForbidden();
    }

    #[Test]
    public function activating_b_deactivates_a_and_branding_reflects_tokens(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $original = Theme::query()->where('is_active', true)->firstOrFail();
        $this->assertSame('Sacred Academic', $original->name);

        $this->actingAs($sa)
            ->from(route('superadmin.theme.index'))
            ->post(route('superadmin.theme.duplicate', $original))
            ->assertRedirect();

        $copy = Theme::query()->where('id', '!=', $original->id)->firstOrFail();
        $this->assertFalse($copy->is_active);

        $this->actingAs($sa)->put(route('superadmin.theme.update', $copy), [
            'name' => 'Lent burgundy',
            'site_name' => 'SPIMS Lent',
            'tokens' => [
                'light' => ['primary' => '#4a021e'],
            ],
        ])->assertRedirect();

        $this->actingAs($sa)
            ->from(route('superadmin.theme.index'))
            ->post(route('superadmin.theme.activate', $copy))
            ->assertRedirect();

        $this->assertFalse($original->fresh()->is_active);
        $this->assertTrue($copy->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'theme.activate',
            'actor_id' => $sa->id,
            'entity_id' => $copy->id,
        ]);

        $this->actingAs($sa)->get(route('api.branding'))
            ->assertOk()
            ->assertJsonPath('siteName', 'SPIMS Lent')
            ->assertJsonPath('tokens.light.primary', '#4a021e');

        $html = $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--color-primary: #4a021e', false)
            ->assertSee('@media (prefers-color-scheme: dark)', false)
            ->assertSee('body.theme-system', false)
            ->getContent();
        $this->assertStringContainsString('--color-primary: #4a021e', $html);
        $this->assertStringNotContainsString('#b8860b', $html);
    }

    #[Test]
    public function reset_restores_sacred_academic_defaults(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $theme = Theme::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($sa)->put(route('superadmin.theme.update', $theme), [
            'name' => $theme->name,
            'site_name' => $theme->site_name,
            'tokens' => [
                'light' => ['primary' => '#111111', 'bg1' => '#eeeeee'],
            ],
        ])->assertRedirect();
        $this->assertSame('#111111', $theme->fresh()->tokens['light']['primary']);

        $this->actingAs($sa)
            ->from(route('superadmin.theme.edit', $theme))
            ->post(route('superadmin.theme.reset', $theme))
            ->assertRedirect();

        $this->assertSame(ThemeTokens::defaults(), $theme->fresh()->tokens);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'theme.reset',
            'actor_id' => $sa->id,
        ]);
    }

    #[Test]
    public function administrative_admin_can_still_edit_the_active_theme(): void
    {
        $this->seed();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $theme = Theme::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($adm)->put(route('admin.theme.update', $theme), [
            'name' => 'Staff tweak',
            'site_name' => 'SPIMS Staff',
            'is_active' => true,
            'tokens' => [
                'light' => ['primary' => '#5d0326', 'bg1' => '#f8f9ff', 'accent' => '#eac167'],
            ],
        ])->assertRedirect();

        $fresh = $theme->fresh();
        $this->assertSame('Staff tweak', $fresh->name);
        $this->assertSame('SPIMS Staff', $fresh->site_name);
        $this->assertSame('#5d0326', $fresh->tokens['light']['primary']);
    }

    #[Test]
    public function unknown_token_key_is_rejected(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $theme = Theme::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($sa)
            ->putJson(route('superadmin.theme.update', $theme), [
                'name' => $theme->name,
                'site_name' => $theme->site_name,
                'tokens' => [
                    'light' => ['notAKey' => '#000000'],
                ],
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function logo_upload_stores_a_path_and_branding_resolves_a_url(): void
    {
        Storage::fake('local');
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $theme = Theme::query()->where('is_active', true)->firstOrFail();

        $this->actingAs($sa)
            ->from(route('superadmin.theme.edit', $theme))
            ->post(route('superadmin.theme.assets', $theme), [
                'field' => 'logo_light_url',
                'file' => UploadedFile::fake()->image('crest.png', 32, 32),
            ])
            ->assertRedirect();

        $path = $theme->fresh()->logo_light_url;
        $this->assertIsString($path);
        $this->assertStringStartsWith('logos/', $path);
        $this->assertStringNotContainsString('secret', strtolower($path));

        $branding = $this->actingAs($sa)->get(route('api.branding'))
            ->assertOk()
            ->json();
        $this->assertIsString($branding['logoLightUrl']);
        $this->assertStringContainsString('storage/', $branding['logoLightUrl']);
        $this->assertStringContainsString($path, $branding['logoLightUrl']);

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee($branding['logoLightUrl'], false);
    }

    #[Test]
    public function studio_has_entrances_from_existing_pages(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('theme_studio.dashboard_tile'))
            ->assertSee(__('theme_studio.dashboard_tile_hint'))
            ->assertSee(route('superadmin.theme.index'), false);

        $this->actingAs($sa)->get(route('superadmin.index'))
            ->assertOk()
            ->assertSee(__('superadmin.tile_theme'))
            ->assertSee(__('superadmin.tile_theme_staff'))
            ->assertSee(__('superadmin.roadmap_sa4_done'))
            ->assertSee(route('superadmin.theme.index'), false)
            ->assertSee(route('admin.theme.edit'), false);

        $this->actingAs($sa)->get(route('hubs.admin'))
            ->assertOk()
            ->assertSee(__('theme_studio.entrance_from_admin'));

        $this->actingAs($sa)->get(route('admin.theme.edit'))
            ->assertOk()
            ->assertSee(__('theme_studio.entrance_from_theme'));

        $this->actingAs($sa)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee(__('theme_studio.entrance_from_settings'));

        $this->actingAs($sa)->get(route('hubs.academic'))
            ->assertOk()
            ->assertSee(__('theme_studio.entrance_from_academic'));

        $this->actingAs($sa)->get(route('superadmin.theme.edit', Theme::query()->where('is_active', true)->firstOrFail()))
            ->assertOk()
            ->assertSee(__('theme_studio.token_primary_help'))
            ->assertSee('--color-primary', false)
            ->assertSee(__('theme_studio.preview_title'))
            ->assertSee(__('theme_studio.group_field_help'));

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('theme_studio.dashboard_tile'));
    }
}
