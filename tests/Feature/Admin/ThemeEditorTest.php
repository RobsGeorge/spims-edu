<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleType;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThemeEditorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_update_active_theme(): void
    {
        $this->seed();
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $theme = Theme::query()->where('is_active', true)->first();

        $this->actingAs($admin)->put(route('admin.theme.update', $theme), [
            'name' => 'Sacred Academic Updated',
            'site_name' => 'SPIMS Academy',
            'is_active' => true,
        ])->assertRedirect();

        $fresh = $theme->fresh();
        $this->assertSame('SPIMS Academy', $fresh->site_name);
        $this->assertSame('Sacred Academic Updated', $fresh->name);
    }

    #[Test]
    public function admin_can_update_expanded_color_tokens(): void
    {
        $this->seed();
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $theme = Theme::query()->where('is_active', true)->first();

        $this->actingAs($admin)->put(route('admin.theme.update', $theme), [
            'name' => $theme->name,
            'site_name' => $theme->site_name,
            'is_active' => true,
            'tokens' => [
                'light' => [
                    'primary' => '#4a021e',
                    'accent' => '#d4a84a',
                    'bg1' => '#f0f2ff',
                    'surface' => '#fefefe',
                    'textMuted' => '#665555',
                ],
                'dark' => [
                    'primary' => '#ffc0cc',
                    'accent' => '#f0d080',
                    'bg1' => '#101828',
                    'surface' => '#1a2233',
                    'textMuted' => '#cbb0b4',
                ],
            ],
        ])->assertRedirect();

        $tokens = $theme->fresh()->tokens;
        $this->assertSame('#4a021e', $tokens['light']['primary']);
        $this->assertSame('#d4a84a', $tokens['light']['accent']);
        $this->assertSame('#f0f2ff', $tokens['light']['bg1']);
        $this->assertSame('#fefefe', $tokens['light']['surface']);
        $this->assertSame('#665555', $tokens['light']['textMuted']);
        $this->assertSame('#ffc0cc', $tokens['dark']['primary']);
        $this->assertSame('#1a2233', $tokens['dark']['surface']);
        $this->assertSame('#cbb0b4', $tokens['dark']['textMuted']);
        $this->assertSame('#3b82f6', $tokens['light']['info']);
    }

    #[Test]
    public function theme_editor_exposes_surface_and_muted_token_fields(): void
    {
        $this->seed();
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($admin)
            ->get(route('admin.theme.edit'))
            ->assertOk()
            ->assertSee('name="tokens[light][surface]"', false)
            ->assertSee('name="tokens[light][textMuted]"', false)
            ->assertSee('name="tokens[dark][surface]"', false)
            ->assertSee('name="tokens[dark][textMuted]"', false)
            ->assertSee(__('ui.token_surface'), false)
            ->assertSee(__('ui.token_muted_fg'), false);
    }
}
