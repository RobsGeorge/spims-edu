<?php

namespace Tests\Feature\Design;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DesignTokenTest extends TestCase
{
    private string $css;

    /** @var list<string> */
    private array $tokens = [
        // Type scale
        '--text-xs', '--text-sm', '--text-base', '--text-lg',
        '--text-xl', '--text-2xl', '--text-3xl', '--text-4xl',
        // Paired line-heights
        '--leading-xs', '--leading-sm', '--leading-base', '--leading-lg',
        '--leading-xl', '--leading-2xl', '--leading-3xl', '--leading-4xl',
        // Spacing scale
        '--space-0', '--space-1', '--space-2', '--space-3',
        '--space-4', '--space-6', '--space-8', '--space-10',
        '--space-12', '--space-16', '--space-20', '--space-24',
        // Focus ring
        '--focus-ring',
        // Motion
        '--motion-fast', '--motion-base', '--motion-slow',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $path = base_path('public/css/spims-theme.css');
        $this->assertTrue(file_exists($path), 'spims-theme.css not found');
        $this->css = file_get_contents($path);
    }

    #[Test]
    public function all_new_tokens_are_present_in_css(): void
    {
        foreach ($this->tokens as $token) {
            $this->assertStringContainsString(
                $token,
                $this->css,
                "Token {$token} not found in spims-theme.css"
            );
        }
    }

    #[Test]
    public function all_new_tokens_are_in_root_light_block(): void
    {
        // Find the first :root { ... } block (non-greedy up to first closing })
        preg_match('/:root\s*\{[^}]*\}/s', $this->css, $matches);
        $rootBlock = $matches[0] ?? '';

        $this->assertNotEmpty($rootBlock, ':root block not found in CSS');

        foreach ($this->tokens as $token) {
            $this->assertStringContainsString(
                $token,
                $rootBlock,
                "Token {$token} not found in :root (light) block"
            );
        }
    }

    #[Test]
    public function all_new_tokens_are_in_dark_block(): void
    {
        // body.theme-dark is the dark block (contains ".dark")
        preg_match('/body\.theme-dark\s*\{[^}]*\}/s', $this->css, $matches);
        $darkBlock = $matches[0] ?? '';

        $this->assertNotEmpty($darkBlock, 'body.theme-dark block not found in CSS');

        foreach ($this->tokens as $token) {
            $this->assertStringContainsString(
                $token,
                $darkBlock,
                "Token {$token} not found in body.theme-dark (dark) block"
            );
        }
    }

    #[Test]
    public function prefers_reduced_motion_overrides_all_motion_tokens(): void
    {
        $this->assertStringContainsString(
            '@media (prefers-reduced-motion: reduce)',
            $this->css,
            'No prefers-reduced-motion media query found'
        );

        // Find the first prefers-reduced-motion block and look for motion overrides within it
        $pos = strpos($this->css, '@media (prefers-reduced-motion: reduce)');
        $this->assertNotFalse($pos);

        // Grab a generous window after the media query open
        $window = substr($this->css, $pos, 600);

        foreach (['--motion-fast', '--motion-base', '--motion-slow'] as $motionToken) {
            $this->assertStringContainsString(
                $motionToken,
                $window,
                "{$motionToken} not overridden inside prefers-reduced-motion block"
            );
        }
    }
}
