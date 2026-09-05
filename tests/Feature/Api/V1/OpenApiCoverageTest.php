<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * docs/api/openapi.yaml is hand-maintained, so nothing enforces that it stays in
 * sync except this test: every registered `api.v1.*` route must have a matching
 * `paths` entry (with its HTTP method) in the document.
 */
class OpenApiCoverageTest extends TestCase
{
    #[Test]
    public function every_v1_route_is_documented_in_the_openapi_spec(): void
    {
        $spec = Yaml::parseFile(base_path('docs/api/openapi.yaml'));
        $documented = $spec['paths'] ?? [];

        $undocumented = [];

        foreach (Route::getRoutes() as $route) {
            // Filter on the URI, not the route name: a route registered under
            // `api/v1/*` with a name that doesn't happen to start with `api.v1.`
            // (a typo, or one added outside the `Route::prefix('v1')->name(...)`
            // group) is still a live, reachable /api/v1 endpoint and must still be
            // documented. Filtering on the name alone would silently pass such a
            // route through undocumented — the exact gap a coverage test exists
            // to close.
            if (! str_starts_with($route->uri(), 'api/v1/') && $route->uri() !== 'api/v1') {
                continue;
            }

            // "api/v1/me" -> "/me", matching the yaml's `servers.url: /api/v1` base
            // and its paths keys.
            $path = '/'.preg_replace('#^api/v1/?#', '', $route->uri());
            $path = $path === '/' ? '/' : rtrim($path, '/');

            $methods = array_diff($route->methods(), ['HEAD']);

            foreach ($methods as $method) {
                $method = strtolower($method);

                if (! isset($documented[$path][$method])) {
                    $undocumented[] = "$method $path (route: {$route->getName()})";
                }
            }
        }

        $this->assertSame(
            [],
            $undocumented,
            "The following api/v1 routes have no docs/api/openapi.yaml entry:\n".implode("\n", $undocumented)
        );
    }

    #[Test]
    public function the_openapi_document_itself_is_valid_yaml_with_the_expected_top_level_keys(): void
    {
        $spec = Yaml::parseFile(base_path('docs/api/openapi.yaml'));

        $this->assertArrayHasKey('openapi', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertNotEmpty($spec['paths']);
    }
}
