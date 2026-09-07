<?php

namespace Tests\Unit\Support;

use App\Support\Content\ExternalReadingUrl;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExternalReadingUrlTest extends TestCase
{
    #[Test]
    public function it_normalizes_drive_view_and_open_urls(): void
    {
        $view = ExternalReadingUrl::parse('https://drive.google.com/file/d/abc123XYZ/view?usp=sharing');
        $this->assertSame('https://drive.google.com/file/d/abc123XYZ/preview', $view->canonicalUrl);
        $this->assertTrue($view->embeddable);
        $this->assertSame(ExternalReadingUrl::KIND_DRIVE, $view->hostKind);

        $open = ExternalReadingUrl::parse('https://drive.google.com/open?id=abc123XYZ');
        $this->assertSame('https://drive.google.com/file/d/abc123XYZ/preview', $open->canonicalUrl);
        $this->assertTrue($open->embeddable);
    }

    #[Test]
    public function it_marks_unknown_https_as_link_only(): void
    {
        $ref = ExternalReadingUrl::parse('https://example.com/notes.pdf');
        $this->assertSame('https://example.com/notes.pdf', $ref->canonicalUrl);
        $this->assertFalse($ref->embeddable);
        $this->assertSame(ExternalReadingUrl::KIND_OTHER, $ref->hostKind);
    }

    #[Test]
    public function it_rejects_http_and_dangerous_schemes(): void
    {
        foreach (['http://example.com/a.pdf', 'javascript:alert(1)', 'data:text/html,hi', 'file:///etc/passwd'] as $input) {
            try {
                ExternalReadingUrl::parse($input);
                $this->fail('Expected rejection for '.$input);
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    #[Test]
    public function it_rejects_unknown_hosts_when_allowlist_is_closed(): void
    {
        config(['spims.content.allow_unknown_reading_urls' => false]);

        $this->expectException(ValidationException::class);
        ExternalReadingUrl::parse('https://example.com/notes.pdf');
    }

    #[Test]
    public function it_rewrites_dropbox_share_links(): void
    {
        $ref = ExternalReadingUrl::parse('https://www.dropbox.com/s/abc/file.pdf?dl=0');
        $this->assertStringContainsString('raw=1', $ref->canonicalUrl);
        $this->assertTrue($ref->embeddable);
        $this->assertSame(ExternalReadingUrl::KIND_DROPBOX, $ref->hostKind);
    }
}
