<?php

namespace Tests\Feature\Design;

use PHPUnit\Framework\TestCase;

class DesignDocsTest extends TestCase
{
    private string $designSystem;
    private string $routes;

    protected function setUp(): void
    {
        parent::setUp();
        $root = dirname(__DIR__, 3);
        $this->designSystem = (string) file_get_contents($root . '/docs/design-system.md');
        $this->routes = (string) file_get_contents($root . '/routes/web.php');
    }

    public function test_design_system_has_all_required_sections(): void
    {
        $sections = [
            '## Surface Tiers',
            '## Tokens',
            '## Component Library',
            '## Icon Vocabulary',
            '## Responsive Rules',
            '## Migration Table',
            '## Design Review Checklist',
        ];

        foreach ($sections as $section) {
            $this->assertStringContainsString(
                $section,
                $this->designSystem,
                "docs/design-system.md is missing section: {$section}"
            );
        }
    }

    public function test_confirm_dialog_is_documented(): void
    {
        $this->assertStringContainsString(
            '### confirm-dialog',
            $this->designSystem,
            'docs/design-system.md is missing ### confirm-dialog section'
        );
    }

    public function test_status_badge_is_documented(): void
    {
        $this->assertStringContainsString(
            '### status-badge',
            $this->designSystem,
            'docs/design-system.md is missing ### status-badge section'
        );
    }

    public function test_routes_has_all_track_anchors(): void
    {
        $tracks = ['A2-a', 'A2-b', 'A2-c', 'A2-d', 'A2-e', 'A2-f', 'A2-g', 'A2-h'];

        foreach ($tracks as $track) {
            $this->assertStringContainsString(
                "track:{$track}",
                $this->routes,
                "routes/web.php is missing anchor comment for track:{$track}"
            );
        }
    }
}
