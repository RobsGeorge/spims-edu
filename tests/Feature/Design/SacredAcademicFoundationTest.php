<?php

namespace Tests\Feature\Design;

use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SacredAcademicFoundationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seed_activates_sacred_academic_tokens(): void
    {
        $this->seed();

        $theme = Theme::query()->where('is_active', true)->first();

        $this->assertNotNull($theme);
        $this->assertSame('Sacred Academic', $theme->name);
        $this->assertSame('#f8f9ff', $theme->tokens['light']['bg1'] ?? null);
        $this->assertSame('#5d0326', $theme->tokens['light']['primary'] ?? null);
        $this->assertSame('#eac167', $theme->tokens['light']['accent'] ?? null);
    }

    #[Test]
    public function home_uses_sacred_academic_fonts_and_theme_system_class(): void
    {
        $this->seed();

        $response = $this->withCookie('theme', 'system')->get(route('home'));

        $response->assertOk();
        $response->assertSee('theme-system', false);
        $response->assertSee('Playfair+Display', false);
        $response->assertSee('family=Inter', false);
        $response->assertSee('spims-theme-tokens', false);
        $response->assertSee('--color-primary: #5d0326', false);
        $response->assertSee('--color-bg-1: #f8f9ff', false);
        $response->assertDontSee('#faf6ee', false);
        $response->assertDontSee('class="theme-dark"', false);
    }

    #[Test]
    public function arabic_locale_loads_ibm_plex_sans_arabic(): void
    {
        $this->seed();

        $this->withCookie('locale', 'ar')
            ->get(route('home'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('IBM+Plex+Sans+Arabic', false)
            ->assertDontSee('Playfair+Display', false);
    }

    #[Test]
    public function branding_api_returns_sacred_academic_tokens(): void
    {
        $this->seed();

        $this->getJson(route('api.branding'))
            ->assertOk()
            ->assertJsonPath('tokens.light.bg1', '#f8f9ff')
            ->assertJsonPath('tokens.light.primary', '#5d0326')
            ->assertJsonPath('tokens.light.accent', '#eac167');
    }

    #[Test]
    public function theme_stylesheet_rejects_parchment_palette(): void
    {
        $css = file_get_contents(public_path('css/spims-theme.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('Sacred Academic', $css);
        $this->assertStringContainsString('#f8f9ff', $css);
        $this->assertStringContainsString('#5d0326', $css);
        $this->assertStringNotContainsString('#faf6ee', $css);
        $this->assertStringNotContainsString('#b8860b', $css);
        $this->assertStringNotContainsString('--color-bg-1: #faf6ee', $css);
    }
}
