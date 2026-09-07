<?php

namespace Tests\Feature\Portal;

use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Support\ThemeTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DesignReferenceParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function landing_matches_design_reference_structure(): void
    {
        $this->seed();

        $html = $this->get(route('home'))
            ->assertOk()
            ->assertSee('spims-public.css', false)
            ->assertSee('spims-landing', false)
            ->assertSee('spims-landing-hero', false)
            ->assertSee('spims-landing-display', false)
            ->assertSee('spims-landing-stats', false)
            ->assertSee('spims-landing-featured', false)
            ->assertSee('spims-landing-band', false)
            ->assertSee('spims-landing-footer', false)
            ->assertSee('img/landing-atmosphere.svg', false)
            ->assertSee(__('ui.home_heading'))
            ->assertSee(__('ui.home_cta_primary'))
            ->assertSee(__('home.hero_display'))
            ->assertSee(__('home.hero_chip'))
            ->assertSee(__('home.nav_programs'))
            ->assertSee(__('home.featured_title'))
            ->assertSee(__('home.how_title'))
            ->assertSee(__('home.catalog_teaser'))
            ->getContent();

        $this->assertStringContainsString('id="programs"', $html);
        $this->assertStringContainsString('id="admissions"', $html);
        $this->assertStringContainsString(e(route('catalog.index')), $html);
    }

    #[Test]
    public function landing_dark_and_rtl_keep_parity_hooks(): void
    {
        $this->seed();

        $this->withCookie('theme', 'dark')
            ->get(route('home'))
            ->assertOk()
            ->assertSee('theme-dark', false)
            ->assertSee('spims-landing-hero', false)
            ->assertSee(__('home.hero_display'));

        $this->withCookie('locale', 'ar')
            ->get(route('home'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('home.hero_display', [], 'ar'))
            ->assertSee(__('home.how_title', [], 'ar'));
    }

    #[Test]
    public function auth_screens_use_split_brand_panel(): void
    {
        $this->seed();
        $pending = User::factory()->withRole(RoleType::Student)->create();

        $this->get(route('auth.login'))
            ->assertOk()
            ->assertSee('spims-auth-split', false)
            ->assertSee('spims-auth-brand', false)
            ->assertSee('auth-card', false)
            ->assertSee(__('home.auth_brand_quote'))
            ->assertSee(__('ui.auth_help_login'));

        $this->withCookie('theme', 'dark')
            ->get(route('auth.login'))
            ->assertOk()
            ->assertSee('theme-dark', false)
            ->assertSee('spims-auth-split', false);

        $this->withCookie('locale', 'ar')
            ->get(route('auth.login'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('home.auth_brand_quote', [], 'ar'));

        $this->withSession(['pending_user_id' => $pending->id])
            ->get(route('auth.verify'))
            ->assertOk()
            ->assertSee('auth-otp-input', false)
            ->assertSee('name="code"', false)
            ->assertSee('maxlength="6"', false);
    }

    #[Test]
    public function catalog_has_featured_banner_and_loading_skeletons(): void
    {
        $this->seed();
        $course = Course::query()->create([
            'code' => 'PARITY1',
            'title' => 'Parity Featured Course',
            'credit_hours' => 2,
            'is_free' => true,
            'is_standalone' => true,
            'active' => true,
        ]);
        CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Open,
        ]);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('catalog-featured', false)
            ->assertSee('Parity Featured Course')
            ->assertSee('catalog-card-media', false)
            ->assertSee('catalog-skeletons', false)
            ->assertSee('aria-busy="false"', false)
            ->assertSee(__('catalog.featured_chip'));

        $this->get(route('catalog.index', ['skeleton' => 1]))
            ->assertOk()
            ->assertSee('catalog-skeleton-card', false)
            ->assertSee('aria-busy="true"', false)
            ->assertSee(__('catalog.loading'));

        $this->get(route('catalog.index', ['q' => 'NOMATCHXYZ']))
            ->assertOk()
            ->assertSee(__('catalog.empty'))
            ->assertSee(__('catalog.empty_hint'))
            ->assertDontSee('catalog-featured', false);
    }

    #[Test]
    public function sacred_academic_text_pairs_meet_wcag_aa(): void
    {
        $light = ThemeTokens::defaults()['light'];
        $dark = ThemeTokens::defaults()['dark'];

        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio($light['text'], $light['bg1']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio($light['title'], $light['bg1']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio($light['textMuted'], $light['bg1']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio($light['primaryText'], $light['primary']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio('#f8f9ff', '#380014'));

        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio($dark['text'], $dark['bg1']));
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio($dark['title'], $dark['bg1']));
        $this->assertGreaterThanOrEqual(3.0, $this->contrastRatio($light['accent'], '#380014'));
    }

    private function contrastRatio(string $foreground, string $background): float
    {
        $l1 = $this->relativeLuminance($foreground);
        $l2 = $this->relativeLuminance($background);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $channel = static function (float $c): float {
            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
    }
}
