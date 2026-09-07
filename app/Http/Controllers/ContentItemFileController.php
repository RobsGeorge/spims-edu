<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\CourseOffering;
use App\Services\Learning\OfferingAccessService;
use App\Services\Offerings\LearningAccessService;
use App\Services\Offerings\LearningProgressService;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContentItemFileController extends Controller
{
    public function __construct(
        private readonly OfferingAccessService $offeringAccess,
        private readonly LearningAccessService $learning,
        private readonly LearningProgressService $progress,
        private readonly ObjectStorageService $storage,
    ) {}

    public function show(Request $request, ContentItem $item): StreamedResponse
    {
        $item->loadMissing('week.offering');
        $week = $item->week;
        abort_unless($week?->offering !== null && $item->isStoredFile(), 404);

        $offering = $week->offering;
        $user = $request->user();
        abort_unless($user !== null, 403);

        $isStaff = $this->offeringAccess->isStaffOrAdmin($user, $offering);
        if (! $isStaff) {
            abort_unless($item->isPublished(), 404);
            $enrollment = $this->learning->requireEnrollment($user, $offering);
            abort_unless($this->progress->isWeekUnlocked($enrollment, $offering, $week), 403);
        }

        $download = $request->boolean('download');
        if ($download && ! config('spims.content.student_file_download', true) && ! $isStaff) {
            abort(403, __('offerings.download_disabled'));
        }

        return $this->stream($item, $download);
    }

    public function publicPreview(CourseOffering $offering, ContentItem $item): StreamedResponse
    {
        $item->loadMissing('week.offering');
        $week = $item->week;
        abort_unless($week !== null && $week->offering_id === $offering->id, 404);
        abort_unless($week->number === 1 && $item->isPublished() && $item->isStoredFile(), 404);

        return $this->stream($item, false);
    }

    private function stream(ContentItem $item, bool $download): StreamedResponse
    {
        abort_unless($this->storage->exists((string) $item->file_url), 404);

        $mime = $this->mimeFor($item);
        [$filename, $fallback] = $this->dispositionFilenames($item);
        $disposition = HeaderUtils::makeDisposition(
            $download ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE,
            $filename,
            $fallback,
        );

        return response()->stream(function () use ($item) {
            echo $this->storage->disk()->get($item->file_url);
        }, Response::HTTP_OK, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dispositionFilenames(ContentItem $item): array
    {
        $ext = $item->storedFileExtension() ?: 'bin';
        $base = $this->safeDispositionBase((string) $item->title);
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base) ?? 'file';
        $ascii = trim($ascii, '._-');
        if ($ascii === '') {
            $ascii = 'file';
        }

        return [$this->withDispositionExtension($base, $ext), $this->withDispositionExtension($ascii, $ext)];
    }

    private function safeDispositionBase(string $title): string
    {
        $base = str_replace(["\0", '/', '\\', '%'], '-', $title);
        while (str_contains($base, '..')) {
            $base = str_replace('..', '', $base);
        }
        $base = trim($base, " \t.-");

        return $base !== '' ? $base : 'file';
    }

    private function withDispositionExtension(string $base, string $ext): string
    {
        $ext = ltrim(strtolower($ext), '.');
        $suffix = $ext !== '' ? '.'.$ext : '';
        if ($suffix !== '' && str_ends_with(strtolower($base), $suffix)) {
            return $base;
        }

        return $base.$suffix;
    }

    private function mimeFor(ContentItem $item): string
    {
        return match ($item->storedFileExtension()) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }
}
