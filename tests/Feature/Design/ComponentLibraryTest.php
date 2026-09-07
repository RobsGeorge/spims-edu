<?php

namespace Tests\Feature\Design;

use Tests\TestCase;

class ComponentLibraryTest extends TestCase
{
    /** All required component files must exist */
    public function test_required_components_exist(): void
    {
        $base = resource_path('views/components');

        $required = [
            'stat.blade.php',
            'field.blade.php',
            'modal.blade.php',
            'tabs.blade.php',
            'toolbar.blade.php',
            'avatar.blade.php',
            'progress.blade.php',
            'timeline.blade.php',
            'file-drop.blade.php',
            'empty-state.blade.php',
            'confirm-dialog.blade.php',
            'status-badge.blade.php',
        ];

        foreach ($required as $file) {
            $this->assertFileExists("$base/$file", "Component missing: $file");
        }
    }

    /** No inline style= attributes in any component blade file */
    public function test_no_inline_styles_in_components(): void
    {
        $base = resource_path('views/components');
        $files = glob("$base/*.blade.php") ?: [];

        $violations = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            // Strip @php blocks to avoid false positives from PHP attribute arrays
            $stripped = preg_replace('/@php.*?@endphp/s', '', $content);
            if (preg_match('/\bstyle\s*=\s*["\']/', $stripped)) {
                $violations[] = basename($file);
            }
        }

        $this->assertEmpty(
            $violations,
            'Components with inline style=: ' . implode(', ', $violations)
        );
    }

    /** CSS grid utilities must be defined */
    public function test_grid_auto_utilities_defined(): void
    {
        $css = file_get_contents(public_path('css/spims-theme.css'));

        $this->assertStringContainsString('.grid-auto-sm', $css);
        $this->assertStringContainsString('.grid-auto-md', $css);
        $this->assertStringContainsString('.grid-auto-lg', $css);
    }

    /** empty-state uses class instead of inline max-width */
    public function test_empty_state_uses_lede_class_not_inline_style(): void
    {
        $content = file_get_contents(
            resource_path('views/components/empty-state.blade.php')
        );

        $this->assertStringContainsString('spims-empty-lede', $content);
        $this->assertStringNotContainsString('style="max-width', $content);
    }

    /** CSS must include the x-cloak hide rule */
    public function test_css_has_x_cloak_rule(): void
    {
        $css = file_get_contents(public_path('css/spims-theme.css'));
        $this->assertStringContainsString('[x-cloak]', $css);
    }
}
