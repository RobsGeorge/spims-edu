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
        $this->assertSame('#0d1322', $defaults['dark']['bg1']);
        $this->assertSame('#ffb1c0', $defaults['dark']['primary']);

        // Reject parchment-era values.
        $this->assertNotSame('#faf6ee', $defaults['light']['bg1']);
        $this->assertNotSame('#b8860b', $defaults['light']['primary']);
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
}
