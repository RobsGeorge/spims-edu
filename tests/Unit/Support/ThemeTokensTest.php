<?php

namespace Tests\Unit\Support;

use App\Support\ThemeTokens;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ThemeTokensTest extends TestCase
{
    #[Test]
    public function defaults_use_sacred_academic_cool_field_and_burgundy(): void
    {
        $defaults = ThemeTokens::defaults();

        $this->assertSame('#f8f9ff', $defaults['light']['bg1']);
        $this->assertSame('#5d0326', $defaults['light']['primary']);
        $this->assertSame('#eac167', $defaults['light']['accent']);
        $this->assertSame('#ffffff', $defaults['light']['primaryText']);
        $this->assertSame('#3b82f6', $defaults['light']['info']);
        $this->assertSame('#0d1322', $defaults['dark']['bg1']);
        $this->assertSame('#ffb1c0', $defaults['dark']['primary']);
        $this->assertSame('#60a5fa', $defaults['dark']['info']);

        // Reject parchment-era values.
        $this->assertNotSame('#faf6ee', $defaults['light']['bg1']);
        $this->assertNotSame('#b8860b', $defaults['light']['primary']);
    }

    #[Test]
    public function gold_is_accent_never_primary_or_body_text(): void
    {
        $defaults = ThemeTokens::defaults();
        $gold = ['#eac167', '#e9c16d', '#b8860b'];

        foreach (['light', 'dark'] as $mode) {
            $this->assertNotContains($defaults[$mode]['primary'], $gold);
            $this->assertNotContains($defaults[$mode]['text'], $gold);
            $this->assertNotContains($defaults[$mode]['bg1'], $gold);
        }

        $this->assertSame('#eac167', $defaults['light']['accent']);
        $this->assertSame('#e9c16d', $defaults['dark']['accent']);
    }

    #[Test]
    public function defaults_include_soft_lift_and_rose_hairline(): void
    {
        $defaults = ThemeTokens::defaults();

        $this->assertSame('rgba(219, 192, 196, 0.65)', $defaults['light']['hairline']);
        $this->assertSame('0 6px 24px rgba(0, 0, 0, 0.08)', $defaults['light']['shadowLift']);
        $this->assertSame('rgba(219, 192, 196, 0.28)', $defaults['dark']['hairline']);
        $this->assertSame('0 6px 24px rgba(0, 0, 0, 0.38)', $defaults['dark']['shadowLift']);
        $this->assertStringContainsString('219, 192, 196', $defaults['light']['hairline']);
    }

    #[Test]
    public function source_rejects_parchment_palette(): void
    {
        $php = file_get_contents(app_path('Support/ThemeTokens.php'));

        $this->assertIsString($php);
        $this->assertStringNotContainsString('#faf6ee', $php);
        $this->assertStringNotContainsString('#b8860b', $php);
    }

    #[Test]
    public function inline_style_block_covers_system_prefers_color_scheme(): void
    {
        $css = ThemeTokens::inlineStyleBlock(null);

        $this->assertStringContainsString('body.theme-light, body.theme-system', $css);
        $this->assertStringContainsString('body.theme-dark', $css);
        $this->assertStringContainsString('@media (prefers-color-scheme: dark)', $css);
        $this->assertStringContainsString('--color-primary: #5d0326', $css);
        $this->assertStringContainsString('--color-bg-1: #f8f9ff', $css);
        $this->assertStringContainsString('--shadow-lift:', $css);
        $this->assertStringContainsString('--color-hairline:', $css);
        $this->assertStringNotContainsString('--color-primary: #eac167', $css);
        $this->assertStringNotContainsString('--color-primary: #e9c16d', $css);
        $this->assertStringNotContainsString('--color-primary: #b8860b', $css);
    }

    #[Test]
    public function css_variables_map_soft_lift_and_hairline(): void
    {
        $vars = ThemeTokens::toCssVariables(ThemeTokens::defaults()['light']);

        $this->assertSame('#5d0326', $vars['--color-primary']);
        $this->assertSame('#f8f9ff', $vars['--color-bg-1']);
        $this->assertSame('#eac167', $vars['--color-accent']);
        $this->assertSame('rgba(219, 192, 196, 0.65)', $vars['--color-hairline']);
        $this->assertSame('0 6px 24px rgba(0, 0, 0, 0.08)', $vars['--shadow-lift']);
        $this->assertSame('0 4px 20px rgba(0, 0, 0, 0.05)', $vars['--shadow-soft']);
    }

    #[Test]
    public function resolve_merges_stored_overrides_onto_defaults(): void
    {
        $resolved = ThemeTokens::resolve([
            'light' => ['siteExtra' => 'ignored', 'primary' => '#4a021e'],
        ]);

        $this->assertSame('#4a021e', $resolved['light']['primary']);
        $this->assertSame('#f8f9ff', $resolved['light']['bg1']);
        $this->assertSame('#ffb1c0', $resolved['dark']['primary']);
    }

    #[Test]
    public function contrast_ratio_matches_wcag_reference_pair(): void
    {
        $this->assertEqualsWithDelta(21.0, ThemeTokens::contrastRatio('#000000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(21.0, ThemeTokens::contrastRatio('#ffffff', '#000000'), 0.01);
    }

    #[Test]
    public function locked_token_pairs_meet_wcag_aa(): void
    {
        foreach (ThemeTokens::aaPairs() as [$foreground, $background, $minimum, $label]) {
            $this->assertGreaterThanOrEqual(
                $minimum,
                ThemeTokens::contrastRatio($foreground, $background),
                $label.' ('.$foreground.' on '.$background.')'
            );
        }
    }

    #[Test]
    public function to_css_variables_maps_info_color(): void
    {
        $vars = ThemeTokens::toCssVariables(ThemeTokens::defaults()['light']);

        $this->assertSame('#3b82f6', $vars['--color-info']);
    }

    #[Test]
    public function to_css_variables_emits_bs_primary_rgb_and_spine(): void
    {
        $vars = ThemeTokens::toCssVariables(ThemeTokens::defaults()['light']);

        $this->assertSame('93, 3, 38', $vars['--bs-primary-rgb']);
        $this->assertSame('#5d0326', $vars['--color-spine']);
        $this->assertSame('#380014', $vars['--color-spine-deep']);
        $this->assertSame('#f8f9ff', $vars['--color-spine-text']);
    }

    #[Test]
    public function resolve_derives_title_and_link_from_overridden_primary(): void
    {
        $resolved = ThemeTokens::resolve([
            'light' => ['primary' => '#4a021e'],
        ]);

        $this->assertSame('#4a021e', $resolved['light']['primary']);
        $this->assertSame('#4a021e', $resolved['light']['title']);
        $this->assertSame('#4a021e', $resolved['light']['link']);
        $this->assertSame('#4a021e', $resolved['light']['navActive']);
        $this->assertSame('#380014', $resolved['light']['primaryHover']);
        $this->assertSame('#5d0326', $resolved['light']['spine']);
    }

    #[Test]
    public function dark_spine_stays_burgundy_not_pink_or_gold(): void
    {
        $dark = ThemeTokens::defaults()['dark'];

        $this->assertSame('#5d0326', $dark['spine']);
        $this->assertSame('#380014', $dark['spineDeep']);
        $this->assertNotSame($dark['title'], $dark['spine']);
        $this->assertNotSame($dark['accent'], $dark['spine']);
    }
}
