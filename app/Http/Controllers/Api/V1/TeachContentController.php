<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentItemType;
use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\CourseOffering;
use App\Models\Week;
use App\Services\Offerings\OfferingService;
use App\Support\Api\IdempotencyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachContentController extends Controller
{
    public function storeWeek(Request $request, CourseOffering $offering, OfferingService $offerings, IdempotencyStore $idempotency): JsonResponse
    {
        $data = $request->validate([
            'number' => 'required|integer|min:1',
            'title' => 'required|string|max:255',
            'unlock_date' => 'nullable|date',
            'order' => 'nullable|integer|min:1',
        ]);

        $payload = $idempotency->remember(
            $request->user(),
            'teach.weeks.store:'.$offering->id,
            $request->header('Idempotency-Key'),
            fn () => $this->weekPayload($offerings->addWeek($request->user(), $offering, $data)),
        );

        return response()->json(['data' => $payload], 201);
    }

    public function storeItem(Request $request, Week $week, OfferingService $offerings, IdempotencyStore $idempotency): JsonResponse
    {
        $data = $request->validate($this->itemRules());

        $payload = $idempotency->remember(
            $request->user(),
            'teach.items.store:'.$week->id,
            $request->header('Idempotency-Key'),
            fn () => $this->itemPayload($offerings->addContentItem(
                $request->user(),
                $week,
                $data,
                $request->file('file'),
            )),
        );

        return response()->json(['data' => $payload], 201);
    }

    public function updateItem(Request $request, ContentItem $contentItem, OfferingService $offerings, IdempotencyStore $idempotency): JsonResponse
    {
        $data = $request->validate($this->itemRules(updating: true));

        $payload = $idempotency->remember(
            $request->user(),
            'teach.items.update:'.$contentItem->id,
            $request->header('Idempotency-Key'),
            fn () => $this->itemPayload($offerings->updateContentItem(
                $request->user(),
                $contentItem,
                $data,
                $request->file('file'),
            )),
        );

        return response()->json(['data' => $payload]);
    }

    public function destroyItem(Request $request, ContentItem $contentItem, OfferingService $offerings, IdempotencyStore $idempotency): JsonResponse
    {
        $payload = $idempotency->remember(
            $request->user(),
            'teach.items.destroy:'.$contentItem->id,
            $request->header('Idempotency-Key'),
            function () use ($offerings, $request, $contentItem) {
                $offerings->deleteContentItem($request->user(), $contentItem);

                return ['deleted' => true];
            },
        );

        return response()->json(['data' => $payload]);
    }

    public function publishItem(Request $request, ContentItem $contentItem, OfferingService $offerings, IdempotencyStore $idempotency): JsonResponse
    {
        $payload = $idempotency->remember(
            $request->user(),
            'teach.items.publish:'.$contentItem->id,
            $request->header('Idempotency-Key'),
            fn () => $this->itemPayload($offerings->publishContentItem($request->user(), $contentItem)),
        );

        return response()->json(['data' => $payload]);
    }

    public function unpublishItem(Request $request, ContentItem $contentItem, OfferingService $offerings, IdempotencyStore $idempotency): JsonResponse
    {
        $payload = $idempotency->remember(
            $request->user(),
            'teach.items.unpublish:'.$contentItem->id,
            $request->header('Idempotency-Key'),
            fn () => $this->itemPayload($offerings->unpublishContentItem($request->user(), $contentItem)),
        );

        return response()->json(['data' => $payload]);
    }

    public function moveItemUp(Request $request, ContentItem $contentItem, OfferingService $offerings, IdempotencyStore $idempotency): JsonResponse
    {
        $payload = $idempotency->remember(
            $request->user(),
            'teach.items.move-up:'.$contentItem->id,
            $request->header('Idempotency-Key'),
            function () use ($offerings, $request, $contentItem) {
                $offerings->moveContentItemByDelta($request->user(), $contentItem, -1);

                return $this->itemPayload($contentItem->fresh());
            },
        );

        return response()->json(['data' => $payload]);
    }

    public function moveItemDown(Request $request, ContentItem $contentItem, OfferingService $offerings, IdempotencyStore $idempotency): JsonResponse
    {
        $payload = $idempotency->remember(
            $request->user(),
            'teach.items.move-down:'.$contentItem->id,
            $request->header('Idempotency-Key'),
            function () use ($offerings, $request, $contentItem) {
                $offerings->moveContentItemByDelta($request->user(), $contentItem, 1);

                return $this->itemPayload($contentItem->fresh());
            },
        );

        return response()->json(['data' => $payload]);
    }

    public function moveItem(Request $request, ContentItem $contentItem, OfferingService $offerings, IdempotencyStore $idempotency): JsonResponse
    {
        $data = $request->validate([
            'week_id' => 'required|exists:weeks,id',
        ]);
        $target = Week::query()->findOrFail($data['week_id']);

        $payload = $idempotency->remember(
            $request->user(),
            'teach.items.move:'.$contentItem->id,
            $request->header('Idempotency-Key'),
            fn () => $this->itemPayload($offerings->moveContentItem($request->user(), $contentItem, $target)),
        );

        return response()->json(['data' => $payload]);
    }

    /** @return array<string, mixed> */
    private function weekPayload(Week $week): array
    {
        return [
            'id' => $week->id,
            'offering_id' => $week->offering_id,
            'number' => $week->number,
            'title' => $week->title,
            'unlock_date' => $week->unlock_date?->toIso8601String(),
            'order' => $week->order,
        ];
    }

    /** @return array<string, mixed> */
    private function itemPayload(ContentItem $item): array
    {
        return [
            'id' => $item->id,
            'week_id' => $item->week_id,
            'type' => $item->type->value,
            'title' => $item->title,
            'order' => $item->order,
            'vimeo_id' => $item->vimeo_id,
            'video_provider' => $item->video_provider?->value,
            'file_url' => $item->file_url,
            'body' => $item->body,
            'published' => (bool) $item->published,
            'published_at' => $item->published_at?->toIso8601String(),
        ];
    }

    /**
     * FILE/READING uploads are optional when `file_url` is present; FILE never requires a file.
     *
     * @return array<string, mixed>
     */
    private function itemRules(bool $updating = false): array
    {
        $types = implode(',', array_column(ContentItemType::cases(), 'value'));
        $maxKb = max(1, (int) config('spims.content.upload_max_mb', 20)) * 1024;

        return [
            'type' => ($updating ? 'sometimes' : 'required').'|in:'.$types,
            'title' => ($updating ? 'sometimes' : 'required').'|string|max:255',
            'vimeo_id' => 'nullable|string|max:256',
            'video_url' => 'nullable|string|max:2048',
            'file_url' => 'nullable|string|max:2048',
            'body' => 'nullable|string',
            'order' => 'nullable|integer|min:1',
            'published' => 'nullable|boolean',
            'file' => ['nullable', 'file', 'max:'.$maxKb],
        ];
    }
}
