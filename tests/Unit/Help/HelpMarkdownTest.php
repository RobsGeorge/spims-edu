<?php

namespace Tests\Unit\Help;

use App\Support\HelpMarkdown;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HelpMarkdownTest extends TestCase
{
    #[Test]
    public function converts_markdown_to_safe_html(): void
    {
        $html = app(HelpMarkdown::class)->toHtml("## Title\n\nHello **world**");

        $this->assertStringContainsString('<h2>', $html);
        $this->assertStringContainsString('<strong>world</strong>', $html);
    }

    #[Test]
    public function strips_script_tags_and_event_handlers(): void
    {
        $markdown = app(HelpMarkdown::class);

        $fromMd = $markdown->toHtml("Hello\n\n<script>alert(1)</script>\n\nWorld");
        $this->assertStringNotContainsString('<script>', $fromMd);
        $this->assertStringNotContainsString('</script>', $fromMd);

        $sanitized = $markdown->sanitize('<p onclick="alert(1)">Hi</p><a href="javascript:alert(2)">x</a><script>evil()</script>');
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringNotContainsString('onclick', $sanitized);
        $this->assertStringNotContainsString('javascript:', $sanitized);
        $this->assertStringContainsString('<p>', $sanitized);
        $this->assertStringContainsString('Hi', $sanitized);
    }

    #[Test]
    public function allows_images_and_links_with_safe_urls(): void
    {
        $html = app(HelpMarkdown::class)->toHtml('[Docs](https://example.com) ![Alt](https://cdn.example/a.png)');

        $this->assertStringContainsString('href="https://example.com"', $html);
        $this->assertStringContainsString('<img', $html);
    }
}
