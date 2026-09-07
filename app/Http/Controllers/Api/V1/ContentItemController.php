<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\CourseOffering;
use App\Services\Offerings\LearningProgressService;
use App\Services\Storage\ObjectStorageService;
use App\Support\Api\StudentPayload;
use App\Support\Api\StudentRecordGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentItemController extends Controller
{
    public function __construct(
        private readonly StudentRecordGuard $guard,
        private readonly LearningProgressService $progress,
        private readonly ObjectStorageService $storage,
    ) {}

    public function show(Request $request, ContentItem $item): JsonResponse
    {
        $offering = $this->offeringFor($item);
        abort_unless($item->isPublished(), 404);
        $enrollment = $this->guard->enrollmentForRead($request->user(), $offering);
        $week = $item->week;
        $unlocked = $this->progress->isWeekUnlocked($enrollment, $offering, $week);

        return response()->json([
            'data' => StudentPayload::itemPayload(
                $item,
                $unlocked,
                $this->progress->isItemComplete($enrollment, $item),
                $this->storage,
            ),
        ]);
    }

    public function complete(Request $request, ContentItem $item): JsonResponse
    {
        $offering = $this->offeringFor($item);
        $enrollment = $this->guard->enrollmentForWrite($request->user(), $offering);
        $completion = $this->progress->markItemComplete($request->user(), $enrollment, $item, manual: true);

        return response()->json([
            'data' => [
                'id' => $completion->id,
                'item_id' => $item->id,
                'completed_at' => StudentPayload::iso($completion->completed_at),
            ],
        ]);
    }

    private function offeringFor(ContentItem $item): CourseOffering
    {
        $item->loadMissing('week.offering');
        $offering = $item->week?->offering;
        if ($offering === null) {
            abort(404);
        }

        return $offering;
    }
}
