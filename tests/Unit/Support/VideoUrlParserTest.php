<?php

namespace Tests\Unit\Support;

use App\Enums\VideoProvider;
use App\Support\Content\VideoUrlParser;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VideoUrlParserTest extends TestCase
{
    #[Test]
    public function it_parses_bare_vimeo_ids_and_urls(): void
    {
        foreach (['123456789', 'https://vimeo.com/123456789', 'https://player.vimeo.com/video/123456789'] as $input) {
            $ref = VideoUrlParser::parse($input);
            $this->assertSame(VideoProvider::Vimeo, $ref->provider);
            $this->assertSame('123456789', $ref->id);
        }
    }

    #[Test]
    public function it_parses_supported_youtube_urls(): void
    {
        $id = 'dQw4w9WgXcQ';
        $inputs = [
            'https://www.youtube.com/watch?v='.$id,
            'https://youtu.be/'.$id,
            'https://www.youtube.com/embed/'.$id,
            'https://www.youtube.com/shorts/'.$id,
            'https://www.youtube-nocookie.com/embed/'.$id,
        ];

        foreach ($inputs as $input) {
            $ref = VideoUrlParser::parse($input);
            $this->assertSame(VideoProvider::YouTube, $ref->provider, $input);
            $this->assertSame($id, $ref->id, $input);
        }
    }

    #[Test]
    public function it_rejects_playlists_live_and_junk(): void
    {
        $this->expectException(ValidationException::class);
        VideoUrlParser::parse('https://www.youtube.com/playlist?list=PLxxxx');
    }

    #[Test]
    public function it_rejects_youtube_watch_with_playlist(): void
    {
        $this->expectException(ValidationException::class);
        VideoUrlParser::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PLxxxx');
    }

    #[Test]
    public function it_rejects_youtube_live(): void
    {
        $this->expectException(ValidationException::class);
        VideoUrlParser::parse('https://www.youtube.com/live/dQw4w9WgXcQ');
    }

    #[Test]
    public function it_rejects_unknown_strings(): void
    {
        $this->expectException(ValidationException::class);
        VideoUrlParser::parse('not-a-video');
    }

    #[Test]
    public function iframe_urls_use_privacy_hosts(): void
    {
        $this->assertSame(
            'https://player.vimeo.com/video/123',
            VideoUrlParser::iframeUrl(VideoProvider::Vimeo, '123')
        );
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
            VideoUrlParser::iframeUrl(VideoProvider::YouTube, 'dQw4w9WgXcQ')
        );
    }

    #[Test]
    public function it_honors_disabled_youtube(): void
    {
        config(['spims.content.video_providers' => ['VIMEO']]);

        $this->expectException(ValidationException::class);
        VideoUrlParser::parse('https://youtu.be/dQw4w9WgXcQ');
    }
}
