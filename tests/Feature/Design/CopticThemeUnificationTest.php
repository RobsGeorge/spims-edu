<?php

namespace Tests\Feature\Design;

use App\Enums\ThemePreference;
use App\Models\User;
use App\Support\ThemeTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CopticThemeUnificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_home_defaults_to_light_and_shows_theme_toggle(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('theme-light', false);
        $response->assertSee('spims-theme-toggle', false);
        $response->assertDontSee('id="theme-select"', false);
    }

    #[Test]
    public function guest_can_switch_to_dark_via_toggle(): void
    {
        $response = $this->from(route('home'))->post(route('theme.update'), [
            'theme' => 'dark',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertCookie('theme', 'dark');
    }

    #[Test]
    public function authenticated_toggle_persists_preference(): void
    {
        $user = User::factory()->create([
            'theme_preference' => ThemePreference::Light,
        ]);

        $response = $this->actingAs($user)->from(route('dashboard'))->post(route('theme.update'), [
            'theme' => 'dark',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertCookie('theme', 'dark');
        $this->assertSame(ThemePreference::Dark, $user->fresh()->theme_preference);
    }

    #[Test]
    public function public_css_locks_cta_band_to_spine_not_dark_title(): void
    {
        $css = file_get_contents(public_path('css/spims-public.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('--color-spine', $css);
        $this->assertStringContainsString('.spims-landing-cta-band', $css);
        $this->assertStringNotContainsString(
            'background: linear-gradient(160deg, var(--color-title-accent) 0%, var(--color-title) 100%)',
            $css
        );
    }

    #[Test]
    public function theme_css_feature_panel_uses_spine_tokens(): void
    {
        $css = file_get_contents(public_path('css/spims-theme.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('--color-spine', $css);
        $this->assertStringContainsString('var(--color-spine)', $css);
        $this->assertStringContainsString('.feature-panel', $css);
    }

    #[Test]
    public function dark_inline_tokens_keep_burgundy_spine(): void
    {
        $css = ThemeTokens::inlineStyleBlock(null);

        $this->assertStringContainsString('--color-spine: #5d0326', $css);
        $this->assertStringContainsString('--bs-primary-rgb:', $css);
    }
}
