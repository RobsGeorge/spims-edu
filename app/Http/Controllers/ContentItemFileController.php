<?php

namespace App\Http\Controllers;

use App\Models\ContentItem;
use App\Models\CourseOffering;
use App\Services\Learning\OfferingAccessService;
use App\Services\Offerings\LearningAccessService;
use App\Services\Offerings\LearningProgressService;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Http\Request;
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
        $name = basename((string) $item->file_url);
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$name.'"';

        return response()->stream(function () use ($item) {
            echo $this->storage->disk()->get($item->file_url);
        }, Response::HTTP_OK, [
            'Content-Type' => $mime,
            'Content-Disposition' => $disposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
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
