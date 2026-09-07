<?php

namespace Tests\Feature\Design;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SurfaceTierTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function panel_variant_renders_without_exception(): void
    {
        $html = $this->renderCard('panel');
        $this->assertStringContainsString('spims-card-panel', $html);
    }

    #[Test]
    public function quiet_variant_renders_without_exception(): void
    {
        $html = $this->renderCard('quiet');
        $this->assertStringContainsString('spims-card-quiet', $html);
    }

    #[Test]
    public function bare_variant_renders_without_exception(): void
    {
        $html = $this->renderCard('bare');
        $this->assertStringContainsString('spims-card-bare', $html);
    }

    #[Test]
    public function each_variant_produces_different_class_strings(): void
    {
        $panel = $this->renderCard('panel');
        $quiet = $this->renderCard('quiet');
        $bare  = $this->renderCard('bare');

        // Extract the class attribute from each rendered card
        preg_match('/class="([^"]+)"/', $panel, $panelM);
        preg_match('/class="([^"]+)"/', $quiet, $quietM);
        preg_match('/class="([^"]+)"/', $bare, $bareM);

        $this->assertNotSame($panelM[1] ?? '', $quietM[1] ?? '', 'panel and quiet have identical classes');
        $this->assertNotSame($quietM[1] ?? '', $bareM[1] ?? '', 'quiet and bare have identical classes');
        $this->assertNotSame($panelM[1] ?? '', $bareM[1] ?? '', 'panel and bare have identical classes');
    }

    #[Test]
    public function unknown_variant_throws_invalid_argument_exception(): void
    {
        $this->expectException(\Throwable::class);
        $this->renderCard('nonexistent');
    }

    #[Test]
    public function panel_and_quiet_reference_css_variables(): void
    {
        $css = file_get_contents(base_path('public/css/spims-theme.css'));

        // The CSS file must contain var(--...) references for both tier classes
        $this->assertStringContainsString('.spims-card-panel', $css);
        $this->assertStringContainsString('.spims-card-quiet', $css);

        // Each tier block must reference at least one CSS variable
        preg_match('/\.spims-card-panel\s*\{([^}]*)\}/s', $css, $panelBlock);
        preg_match('/\.spims-card-quiet\s*\{([^}]*)\}/s', $css, $quietBlock);

        $this->assertStringContainsString('var(--', $panelBlock[0] ?? '', 'panel tier has no CSS variable');
        $this->assertStringContainsString('var(--', $quietBlock[0] ?? '', 'quiet tier has no CSS variable');
    }

    private function renderCard(string $variant, string $content = 'body'): string
    {
        return view('components.card', ['variant' => $variant, 'slot' => $content])
            ->render();
    }
}
