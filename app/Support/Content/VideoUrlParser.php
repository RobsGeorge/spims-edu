<?php

namespace App\Support\Content;

use App\Enums\VideoProvider;
use Illuminate\Validation\ValidationException;

final class VideoUrlParser
{
    private const YOUTUBE_ID = '[A-Za-z0-9_-]{11}';

    public static function parse(?string $input): VideoRef
    {
        $raw = trim((string) $input);
        if ($raw === '') {
            throw ValidationException::withMessages([
                'video_url' => [__('offerings.video_url_invalid')],
            ]);
        }

        $allowed = self::allowedProviders();

        if (preg_match('/^\d{6,12}$/', $raw) === 1) {
            self::assertProviderAllowed(VideoProvider::Vimeo, $allowed);

            return new VideoRef(VideoProvider::Vimeo, $raw);
        }

        if (self::looksLikePlaylistOrLive($raw)) {
            throw ValidationException::withMessages([
                'video_url' => [__('offerings.video_url_unsupported')],
            ]);
        }

        $youtubeId = self::youtubeId($raw);
        if ($youtubeId !== null) {
            self::assertProviderAllowed(VideoProvider::YouTube, $allowed);

            return new VideoRef(VideoProvider::YouTube, $youtubeId);
        }

        $vimeoId = self::vimeoId($raw);
        if ($vimeoId !== null) {
            self::assertProviderAllowed(VideoProvider::Vimeo, $allowed);

            return new VideoRef(VideoProvider::Vimeo, $vimeoId);
        }

        throw ValidationException::withMessages([
            'video_url' => [__('offerings.video_url_invalid')],
        ]);
    }

    public static function iframeUrl(VideoProvider $provider, string $id): string
    {
        $safe = rawurlencode($id);

        return match ($provider) {
            VideoProvider::Vimeo => 'https://player.vimeo.com/video/'.$safe,
            VideoProvider::YouTube => 'https://www.youtube-nocookie.com/embed/'.$safe,
        };
    }

    /**
     * @return list<VideoProvider>
     */
    public static function allowedProviders(): array
    {
        $configured = config('spims.content.video_providers');
        if (! is_array($configured) || $configured === []) {
            return VideoProvider::cases();
        }

        $allowed = [];
        foreach ($configured as $value) {
            $provider = VideoProvider::tryFrom(strtoupper((string) $value));
            if ($provider !== null) {
                $allowed[] = $provider;
            }
        }

        return $allowed !== [] ? $allowed : VideoProvider::cases();
    }

    /**
     * @param  list<VideoProvider>  $allowed
     */
    private static function assertProviderAllowed(VideoProvider $provider, array $allowed): void
    {
        if (! in_array($provider, $allowed, true)) {
            throw ValidationException::withMessages([
                'video_url' => [__('offerings.video_provider_disabled', ['provider' => $provider->value])],
            ]);
        }
    }

    private static function looksLikePlaylistOrLive(string $raw): bool
    {
        $lower = strtolower($raw);

        if (str_contains($lower, 'youtube.com/playlist') || str_contains($lower, 'youtube.com/live/')) {
            return true;
        }

        if (str_contains($lower, 'list=') && (str_contains($lower, 'youtube.com') || str_contains($lower, 'youtu.be'))) {
            return true;
        }

        return false;
    }

    private static function youtubeId(string $raw): ?string
    {
        $patterns = [
            '~(?:https?://)?(?:www\.|m\.)?youtube\.com/watch\?[^#]*v=('.self::YOUTUBE_ID.')~i',
            '~(?:https?://)?(?:www\.)?youtu\.be/('.self::YOUTUBE_ID.')~i',
            '~(?:https?://)?(?:www\.|m\.)?youtube\.com/embed/('.self::YOUTUBE_ID.')~i',
            '~(?:https?://)?(?:www\.|m\.)?youtube\.com/shorts/('.self::YOUTUBE_ID.')~i',
            '~(?:https?://)?(?:www\.)?youtube-nocookie\.com/embed/('.self::YOUTUBE_ID.')~i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $raw, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private static function vimeoId(string $raw): ?string
    {
        $patterns = [
            '~(?:https?://)?(?:www\.)?vimeo\.com/(?:video/)?(\d{6,12})~i',
            '~(?:https?://)?player\.vimeo\.com/video/(\d{6,12})~i',
            '~(?:https?://)?(?:www\.)?vimeo\.com/channels/[^/]+/(\d{6,12})~i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $raw, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}
