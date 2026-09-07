<?php

namespace Tests\Feature\Design;

use App\Support\ThemeTokens;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalD0TokensTest extends TestCase
{
    private function stylesheet(): string
    {
        $css = file_get_contents(public_path('css/spims-theme.css'));
        $this->assertIsString($css);

        return $css;
    }

    #[Test]
    public function light_primary_is_burgundy_and_bg1_is_cool_field(): void
    {
        $css = $this->stylesheet();
        $defaults = ThemeTokens::defaults();

        $this->assertSame('#5d0326', $defaults['light']['primary']);
        $this->assertSame('#f8f9ff', $defaults['light']['bg1']);
        $this->assertStringContainsString('--color-primary: #5d0326', $css);
        $this->assertStringContainsString('--color-bg-1: #f8f9ff', $css);
    }

    #[Test]
    public function css_has_no_parchment_and_no_gold_as_primary(): void
    {
        $css = $this->stylesheet();

        $this->assertStringNotContainsString('#faf6ee', $css);
        $this->assertDoesNotMatchRegularExpression('/--color-primary:\s*#b8860b/i', $css);
        $this->assertDoesNotMatchRegularExpression('/--color-primary:\s*#eac167/i', $css);
        $this->assertDoesNotMatchRegularExpression('/--color-primary:\s*#e9c16d/i', $css);
        $this->assertDoesNotMatchRegularExpression('/--color-text:\s*#eac167/i', $css);
        $this->assertDoesNotMatchRegularExpression('/--color-text:\s*#e9c16d/i', $css);
        $this->assertDoesNotMatchRegularExpression('/--color-text:\s*#b8860b/i', $css);
        $this->assertDoesNotMatchRegularExpression('/--color-bg-1:\s*#faf6ee/i', $css);
        $this->assertStringContainsString('--color-accent: #eac167', $css);

        $php = file_get_contents(app_path('Support/ThemeTokens.php'));
        $this->assertIsString($php);
        $this->assertStringNotContainsString('#faf6ee', $php);
        $this->assertStringNotContainsString('#b8860b', $php);
    }

    #[Test]
    public function theme_system_follows_prefers_color_scheme_dark(): void
    {
        $css = $this->stylesheet();

        $this->assertStringContainsString('body.theme-system', $css);
        $this->assertStringContainsString('@media (prefers-color-scheme: dark)', $css);
        $this->assertMatchesRegularExpression(
            '/@media\s*\(\s*prefers-color-scheme:\s*dark\s*\)\s*\{\s*body\.theme-system/s',
            $css
        );

        $inline = ThemeTokens::inlineStyleBlock(null);
        $this->assertStringContainsString('body.theme-light, body.theme-system', $inline);
        $this->assertStringContainsString('@media (prefers-color-scheme: dark)', $inline);
        $this->assertStringContainsString('body.theme-system {', $inline);
    }

    #[Test]
    public function soft_lift_and_hairline_tokens_exist_and_apply_to_cards(): void
    {
        $css = $this->stylesheet();

        $this->assertStringContainsString('--shadow-lift', $css);
        $this->assertStringContainsString('--color-hairline', $css);
        $this->assertStringContainsString('rgba(219, 192, 196', $css);
        $this->assertMatchesRegularExpression(
            '/\.(?:app-)?card[\s\S]{0,240}border:\s*1px solid var\(--color-hairline\)/s',
            $css
        );

        $vars = ThemeTokens::toCssVariables(ThemeTokens::defaults()['light']);
        $this->assertArrayHasKey('--shadow-lift', $vars);
        $this->assertArrayHasKey('--color-hairline', $vars);
        $this->assertStringContainsString('219, 192, 196', $vars['--color-hairline']);
    }
}
