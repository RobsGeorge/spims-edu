<?php

namespace App\Support\Api;

use App\Models\ContentItem;
use App\Services\Storage\ObjectStorageService;
use Carbon\CarbonInterface;

final class StudentPayload
{
    public static function iso(?CarbonInterface $value): ?string
    {
        return $value?->clone()->utc()->toIso8601String();
    }

    /**
     * Metadata only — never body / file / vimeo.
     *
     * @return array<string, mixed>
     */
    public static function itemMeta(ContentItem $item, bool $unlocked, bool $completed): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type->value,
            'title' => $item->title,
            'order' => $item->order,
            'unlocked' => $unlocked,
            'completed' => $completed,
        ];
    }

    /**
     * Full payload when the week is unlocked; otherwise the same metadata as the list.
     *
     * @return array<string, mixed>
     */
    public static function itemPayload(ContentItem $item, bool $unlocked, bool $completed, ObjectStorageService $storage): array
    {
        $payload = self::itemMeta($item, $unlocked, $completed);
        if (! $unlocked) {
            return $payload;
        }

        $payload['body'] = $item->body;
        $payload['vimeo_id'] = $item->vimeo_id;
        $payload['file_url'] = self::signedFileUrl($item->file_url, $storage);

        return $payload;
    }

    public static function signedFileUrl(?string $path, ObjectStorageService $storage): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $storage->temporaryUrl($path);
    }
}
