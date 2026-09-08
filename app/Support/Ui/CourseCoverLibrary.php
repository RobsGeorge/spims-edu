<?php

namespace App\Support\Ui;

use App\Models\Course;

/**
 * Deterministic Unsplash covers for courses that have no uploaded image.
 * URLs are HTTPS images.unsplash.com (allowed by img-src https:).
 */
final class CourseCoverLibrary
{
    /**
     * Academic / study photography used when a course is created without a cover.
     *
     * @var list<string>
     */
    public const POOL = [
        'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?auto=format&fit=crop&w=1200&h=675&q=80',
        'https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?auto=format&fit=crop&w=1200&h=675&q=80',
        'https://images.unsplash.com/photo-1507842217343-583bb7270b66?auto=format&fit=crop&w=1200&h=675&q=80',
        'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=1200&h=675&q=80',
        'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=1200&h=675&q=80',
        'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&h=675&q=80',
        'https://images.unsplash.com/photo-1455390582262-044cdead277a?auto=format&fit=crop&w=1200&h=675&q=80',
        'https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=1200&h=675&q=80',
        'https://images.unsplash.com/photo-1541339902988-2adc0c32712d?auto=format&fit=crop&w=1200&h=675&q=80',
        'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=1200&h=675&q=80',
        'https://images.unsplash.com/photo-1460518451285-97b6aa326961?auto=format&fit=crop&w=1200&h=675&q=80',
        'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=1200&h=675&q=80',
    ];

    public static function urlForSeed(string $seed): string
    {
        $index = abs(crc32($seed)) % count(self::POOL);

        return self::POOL[$index];
    }

    public static function urlForCourse(Course $course): string
    {
        $stored = $course->getAttributes()['cover_image_url'] ?? null;
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        return self::urlForSeed((string) ($course->code ?: $course->id));
    }

    public static function isLibraryUrl(string $url): bool
    {
        return str_starts_with($url, 'https://images.unsplash.com/');
    }
}
