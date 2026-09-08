<?php

namespace Tests\Feature\Design;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResponsiveContractTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function theme_css_defines_the_mobile_viewport_contract(): void
    {
        $css = (string) file_get_contents(public_path('css/spims-theme.css'));
        $public = (string) file_get_contents(public_path('css/spims-public.css'));
        $shell = (string) file_get_contents(public_path('css/spims-shell.css'));

        $this->assertStringContainsString('SPIMS mobile viewport contract', $css);
        $this->assertStringContainsString('overflow-x: clip', $css);
        $this->assertStringContainsString('minmax(min(14rem, 100%), 1fr)', $css);
        $this->assertStringContainsString('minmax(min(20rem, 100%), 1fr)', $css);
        $this->assertStringContainsString('minmax(min(28rem, 100%), 1fr)', $css);
        $this->assertStringNotContainsString('min-width: 36rem', $css);
        $this->assertStringContainsString('.hub-page', $css);
        $this->assertStringContainsString('SPIMS mobile viewport contract', $public);
        $this->assertStringContainsString('.app-topbar .app-topbar-select', $shell);

        $provider = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringContainsString('Paginator::useBootstrapFive()', $provider);
    }

    #[Test]
    public function app_layout_declares_a_device_width_viewport(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString(
            'name="viewport" content="width=device-width, initial-scale=1"',
            $layout
        );
        $this->assertStringContainsString('app-bottom-nav', $layout);
        $this->assertStringContainsString('app-drawer', $layout);
    }

    #[Test]
    public function catalog_cards_and_filters_are_mobile_first(): void
    {
        $catalog = (string) file_get_contents(resource_path('views/catalog/index.blade.php'));
        $results = (string) file_get_contents(resource_path('views/catalog/partials/results.blade.php'));
        $tile = (string) file_get_contents(resource_path('views/partials/hub-link-tile.blade.php'));

        $this->assertStringContainsString('col-12 col-md-4 col-lg-3', $catalog);
        $this->assertStringNotContainsString('col-6 col-md-4', $catalog);
        $this->assertStringContainsString('col-12 col-md-6 col-xl-4', $results);
        $this->assertStringContainsString("'col-12 col-sm-6'", $tile);
    }

    #[Test]
    public function data_table_desktop_wrap_uses_the_shared_table_contract(): void
    {
        $component = (string) file_get_contents(resource_path('views/components/data-table.blade.php'));

        $this->assertStringContainsString('spims-dt-wrap spims-table-wrap', $component);
        $this->assertStringContainsString('d-none d-md-block', $component);
        $this->assertStringContainsString('spims-dt-stack-list', $component);
    }

    #[Test]
    public function all_blade_tables_are_inside_a_scroll_wrap(): void
    {
        $this->assertSame([], self::unwrappedTables(resource_path('views')));
    }

    #[Test]
    public function it_fails_when_unwrapped_table_present(): void
    {
        $this->assertNotEmpty(self::unwrappedIn('<div><table class="table"></table></div>'));
        $this->assertSame(
            [],
            self::unwrappedIn('<div class="spims-table-wrap"><table class="table"></table></div>')
        );
        $this->assertSame(
            [],
            self::unwrappedIn('<div class="table-responsive"><table class="table"></table></div>')
        );
    }

    #[Test]
    public function grids_do_not_use_breakpoint_columns_without_a_phone_col(): void
    {
        $this->assertSame([], self::missingPhoneColumns(resource_path('views')));
    }

    #[Test]
    public function catalog_hub_and_live_pages_render_the_contract_classes(): void
    {
        $this->seed();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $catalog = $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('name="viewport" content="width=device-width, initial-scale=1"', false)
            ->assertSee('col-12 col-md-6 col-xl-4', false)
            ->assertSee('col-12 col-md-4 col-lg-3', false)
            ->getContent();
        $this->assertStringContainsString('spims-theme.css', $catalog);

        $this->actingAs($student)->get(route('hubs.learning'))
            ->assertOk()
            ->assertSee('hub-page', false)
            ->assertSee('col-12 col-sm-6', false);

        $this->actingAs($student)->get(route('live.index'))
            ->assertOk()
            ->assertSee('spims-table-wrap', false)
            ->assertSee('spims-page-actions', false);

        $dashboard = $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('app-bottom-nav', $dashboard);
        $this->assertStringContainsString('bento-grid', $dashboard);
    }

    /**
     * @return list<string>
     */
    public static function unwrappedTables(string $dir): array
    {
        $violations = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $hits = self::unwrappedIn((string) file_get_contents($file->getPathname()));
            foreach ($hits as $hit) {
                $violations[] = $file->getPathname().': '.$hit;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public static function unwrappedIn(string $html): array
    {
        preg_match_all('/<\/?div\b[^>]*>|<table\b[^>]*>|<\/table>/i', $html, $matches);
        $stack = [];
        $hits = [];

        foreach ($matches[0] as $token) {
            $lower = strtolower($token);
            if (str_starts_with($lower, '<div')) {
                $stack[] = (bool) preg_match(
                    '/\b(spims-table-wrap|table-responsive|spims-dt-wrap)\b/',
                    $token
                );
            } elseif (str_starts_with($lower, '</div')) {
                if ($stack !== []) {
                    array_pop($stack);
                }
            } elseif (str_starts_with($lower, '<table') && ! in_array(true, $stack, true)) {
                $hits[] = $token;
            }
        }

        return $hits;
    }

    /**
     * @return list<string>
     */
    public static function missingPhoneColumns(string $dir): array
    {
        $violations = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $html = (string) file_get_contents($file->getPathname());
            if (! preg_match_all('/\bclass="([^"]*)"/', $html, $matches)) {
                continue;
            }
            foreach ($matches[1] as $class) {
                if (str_contains($class, 'col-form-label')) {
                    continue;
                }
                $hasPrefixed = (bool) preg_match('/(^|\s)col-(sm|md|lg|xl|xxl)-([1-9]|1[0-2]|auto)(\s|$)/', $class);
                $hasPhone = (bool) preg_match('/(^|\s)col-([1-9]|1[0-2]|auto)(\s|$)/', $class);
                if ($hasPrefixed && ! $hasPhone) {
                    $violations[] = $file->getPathname().': '.$class;
                }
            }
        }

        return $violations;
    }
}
