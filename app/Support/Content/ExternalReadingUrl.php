<?php

namespace App\Support\Content;

use Illuminate\Validation\ValidationException;

final class ExternalReadingUrl
{
    public const KIND_DRIVE = 'drive';

    public const KIND_DROPBOX = 'dropbox';

    public const KIND_ONEDRIVE = 'onedrive';

    public const KIND_OTHER = 'other';

    public static function parse(?string $input): ExternalReadingRef
    {
        $raw = trim((string) $input);
        if ($raw === '') {
            throw ValidationException::withMessages([
                'file_url' => [__('offerings.reading_url_invalid')],
            ]);
        }

        if (preg_match('~^(javascript|data|file):~i', $raw) === 1) {
            throw ValidationException::withMessages([
                'file_url' => [__('offerings.reading_url_invalid')],
            ]);
        }

        if (! str_starts_with(strtolower($raw), 'https://')) {
            throw ValidationException::withMessages([
                'file_url' => [__('offerings.reading_url_https')],
            ]);
        }

        $parts = parse_url($raw);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || ! filter_var($raw, FILTER_VALIDATE_URL)) {
            throw ValidationException::withMessages([
                'file_url' => [__('offerings.reading_url_invalid')],
            ]);
        }

        if (self::isDriveHost($host)) {
            return self::driveRef($raw, $parts);
        }

        if (self::isDropboxHost($host)) {
            return self::dropboxRef($raw);
        }

        if (self::isOneDriveHost($host)) {
            return self::oneDriveRef($raw);
        }

        if (! self::allowsUnknownHosts()) {
            throw ValidationException::withMessages([
                'file_url' => [__('offerings.reading_url_host_blocked')],
            ]);
        }

        return new ExternalReadingRef($raw, false, self::KIND_OTHER);
    }

    /**
     * @return list<string>
     */
    public static function embedHosts(): array
    {
        $hosts = config('spims.content.reading_embed_hosts');

        return is_array($hosts) ? array_map('strval', $hosts) : [
            'drive.google.com',
            'docs.google.com',
            'www.dropbox.com',
            'dl.dropboxusercontent.com',
            'onedrive.live.com',
            '1drv.ms',
        ];
    }

    public static function allowsUnknownHosts(): bool
    {
        $value = config('spims.content.allow_unknown_reading_urls', true);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function isDriveHost(string $host): bool
    {
        return in_array($host, ['drive.google.com', 'docs.google.com'], true);
    }

    private static function isDropboxHost(string $host): bool
    {
        return $host === 'www.dropbox.com'
            || $host === 'dropbox.com'
            || $host === 'dl.dropboxusercontent.com';
    }

    private static function isOneDriveHost(string $host): bool
    {
        return $host === 'onedrive.live.com'
            || $host === '1drv.ms'
            || str_ends_with($host, '.sharepoint.com');
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private static function driveRef(string $raw, array $parts): ExternalReadingRef
    {
        if (preg_match('~/file/d/([A-Za-z0-9_-]+)~', $raw, $matches) === 1) {
            $id = $matches[1];

            return new ExternalReadingRef(
                'https://drive.google.com/file/d/'.$id.'/preview',
                true,
                self::KIND_DRIVE,
            );
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        $id = $query['id'] ?? null;
        if (is_string($id) && $id !== '') {
            return new ExternalReadingRef(
                'https://drive.google.com/file/d/'.$id.'/preview',
                true,
                self::KIND_DRIVE,
            );
        }

        return new ExternalReadingRef($raw, false, self::KIND_DRIVE);
    }

    private static function dropboxRef(string $raw): ExternalReadingRef
    {
        $url = $raw;
        if (str_contains($url, 'dl=0')) {
            $url = str_replace('dl=0', 'raw=1', $url);
        } elseif (! str_contains($url, 'raw=1') && ! str_contains($url, 'dl=1')) {
            $url .= (str_contains($url, '?') ? '&' : '?').'raw=1';
        }

        return new ExternalReadingRef($url, true, self::KIND_DROPBOX);
    }

    private static function oneDriveRef(string $raw): ExternalReadingRef
    {
        $embeddable = str_contains(strtolower($raw), '/embed');

        return new ExternalReadingRef($raw, $embeddable, self::KIND_ONEDRIVE);
    }
}
