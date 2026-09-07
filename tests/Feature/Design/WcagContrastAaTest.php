<?php

namespace Tests\Feature\Design;

use App\Support\ThemeTokens;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WcagContrastAaTest extends TestCase
{
    #[Test]
    public function measured_sacred_academic_pairs_meet_wcag_aa(): void
    {
        $ratios = [];

        foreach (ThemeTokens::aaPairs() as [$foreground, $background, $minimum, $label]) {
            $ratio = ThemeTokens::contrastRatio($foreground, $background);
            $ratios[$label] = round($ratio, 2);
            $this->assertGreaterThanOrEqual(
                $minimum,
                $ratio,
                sprintf('%s: %s on %s is %.2f, need %.1f', $label, $foreground, $background, $ratio, $minimum)
            );
        }

        $this->assertNotEmpty($ratios);
        $this->assertArrayHasKey('light text on field', $ratios);
        $this->assertArrayHasKey('light button label', $ratios);
        $this->assertArrayHasKey('dark text on field', $ratios);
    }

    #[Test]
    public function catalog_loading_script_fetches_html_fragment(): void
    {
        $js = file_get_contents(public_path('js/catalog-loading.js'));

        $this->assertIsString($js);
        $this->assertStringContainsString("searchParams.set('fragment', '1')", $js);
        $this->assertStringContainsString('aria-busy', $js);
        $this->assertStringContainsString('catalog-skeletons', $js);
        $this->assertStringContainsString('fetch(', $js);
    }

    #[Test]
    public function landing_photography_assets_are_present(): void
    {
        $this->assertFileExists(public_path('img/landing-hero.jpg'));
        $this->assertFileExists(public_path('img/landing-featured-1.jpg'));
        $this->assertFileExists(public_path('img/landing-featured-2.jpg'));
        $this->assertGreaterThan(10_000, filesize(public_path('img/landing-hero.jpg')));
    }
}
