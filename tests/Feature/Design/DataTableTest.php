<?php

namespace Tests\Feature\Design;

use Tests\TestCase;

class DataTableTest extends TestCase
{
    protected string $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->component = file_get_contents(
            resource_path('views/components/data-table.blade.php')
        );
    }

    public function test_data_table_component_exists(): void
    {
        $this->assertFileExists(
            resource_path('views/components/data-table.blade.php')
        );
    }

    public function test_has_desktop_table_hidden_on_mobile(): void
    {
        // Desktop table: visible md+ (d-none d-md-block)
        $this->assertStringContainsString('d-none d-md-block', $this->component);
        $this->assertStringContainsString('<table', $this->component);
    }

    public function test_has_mobile_stacked_card_view(): void
    {
        // Mobile stack: hidden md+ (d-md-none)
        $this->assertStringContainsString('d-md-none', $this->component);
        $this->assertStringContainsString('spims-dt-stack-item', $this->component);
        $this->assertStringContainsString('spims-dt-stack-label', $this->component);
    }

    public function test_no_inline_styles(): void
    {
        $stripped = preg_replace('/@php.*?@endphp/s', '', $this->component);
        $this->assertStringNotContainsString('style="', $stripped);
    }

    public function test_numeric_columns_use_logical_alignment_class(): void
    {
        $this->assertStringContainsString('spims-dt-th--numeric', $this->component);
        $this->assertStringContainsString('spims-dt-td--numeric', $this->component);
    }

    public function test_empty_state_uses_no_results_lang_key(): void
    {
        $this->assertStringContainsString("__('ui.no_results')", $this->component);
    }
}
