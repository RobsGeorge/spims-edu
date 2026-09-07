<?php

namespace Tests\Feature\Hardening;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function publicPages(): array
    {
        return [
            'home' => ['home'],
            'login' => ['auth.login'],
        ];
    }

    #[Test]
    #[DataProvider('publicPages')]
    public function public_pages_send_a_restrictive_csp(string $routeName): void
    {
        $response = $this->get(route($routeName));

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        $policies = $response->headers->all('Content-Security-Policy');
        $this->assertCount(1, $policies);

        $csp = (string) $policies[0];
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("connect-src 'self'", $csp);
    }

    #[Test]
    #[DataProvider('publicPages')]
    public function public_pages_allow_layout_cdns_and_inline_theme_css(string $routeName): void
    {
        $csp = (string) $this->get(route($routeName))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net", $csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com", $csp);
        $this->assertStringContainsString("font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:", $csp);
        $this->assertStringContainsString("img-src 'self' data: https:", $csp);
    }

    #[Test]
    #[DataProvider('publicPages')]
    public function frame_src_allows_vimeo_youtube_and_configured_drive_host(string $routeName): void
    {
        $csp = (string) $this->get(route($routeName))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('frame-src', $csp);
        $this->assertStringContainsString('https://player.vimeo.com', $csp);
        $this->assertStringContainsString('https://www.youtube-nocookie.com', $csp);

        $hosts = (array) config('spims.content.reading_embed_hosts');
        $drive = collect($hosts)->first(
            fn ($host) => str_contains(strtolower((string) $host), 'drive.google.com')
        );

        $this->assertNotNull($drive, 'config spims.content.reading_embed_hosts must include a Drive host');
        $this->assertStringContainsString('https://'.strtolower(trim((string) $drive)), $csp);
    }

    #[Test]
    public function frame_src_includes_each_configured_reading_embed_host(): void
    {
        config([
            'spims.content.reading_embed_hosts' => [
                'drive.google.com',
                'docs.google.com',
                'csp-embed.example',
            ],
        ]);

        $csp = (string) $this->get(route('home'))->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://drive.google.com', $csp);
        $this->assertStringContainsString('https://docs.google.com', $csp);
        $this->assertStringContainsString('https://csp-embed.example', $csp);
    }
}
