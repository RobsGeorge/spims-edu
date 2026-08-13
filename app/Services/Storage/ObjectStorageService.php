<?php

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ObjectStorageService
{
    private const SIGNED_PREFIXES = [
        'application-docs',
        'submissions',
        'logos',
        'uploads',
        'receipts',
    ];

    public function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }

    public function diskName(): string
    {
        $name = (string) config('filesystems.default', 'local');

        // Local default uses the private local disk; s3/r2 (or public) use configured disk.
        if ($name === 'local' || $name === 'public') {
            return $name;
        }

        return $name;
    }

    /**
     * @param  string|resource  $contents
     */
    public function store(string $path, mixed $contents): string
    {
        $this->disk()->put($path, $contents);

        return $path;
    }

    public function temporaryUrl(string $path, int $minutes = 60): string
    {
        $diskName = $this->diskName();

        if (! in_array($diskName, ['local', 'public'], true)) {
            try {
                return $this->disk()->temporaryUrl($path, now()->addMinutes($minutes));
            } catch (\Throwable) {
                // Fall through to a stable app URL when the driver cannot sign.
            }
        }

        if ($diskName === 'public') {
            return $this->disk()->url($path);
        }

        return rtrim((string) config('app.url'), '/').'/storage/'.ltrim($path, '/');
    }

    /**
     * Build a storage path under an approved prefix (application docs, submissions, logos, …).
     */
    public function signedUploadPath(string $prefix, ?string $ownerId = null, ?string $extension = null): string
    {
        $prefix = trim($prefix, '/');
        if (! in_array($prefix, self::SIGNED_PREFIXES, true)) {
            throw new InvalidArgumentException('Unsupported upload prefix: '.$prefix);
        }

        $ext = $extension !== null && $extension !== ''
            ? '.'.ltrim(strtolower($extension), '.')
            : '';

        $owner = $ownerId !== null && $ownerId !== '' ? $ownerId : 'system';

        return $prefix.'/'.$owner.'/'.(string) Str::ulid().$ext;
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }
}
