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
use Illuminate\Validation\Rule;

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
        $types = implode(',', array_column(ContentItemType::cases(), 'value'));
        $data = $request->validate([
            'type' => 'required|in:'.$types,
            'title' => 'required|string|max:255',
            'vimeo_id' => 'nullable|string|max:64',
            'file_url' => 'nullable|string|max:2048',
            'body' => 'nullable|string',
            'order' => 'nullable|integer|min:1',
            'file' => [
                'nullable',
                Rule::requiredIf($request->input('type') === ContentItemType::File->value),
                'file',
                'max:20480',
            ],
        ]);

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
        $types = implode(',', array_column(ContentItemType::cases(), 'value'));
        $data = $request->validate([
            'type' => 'sometimes|in:'.$types,
            'title' => 'sometimes|string|max:255',
            'vimeo_id' => 'nullable|string|max:64',
            'file_url' => 'nullable|string|max:2048',
            'body' => 'nullable|string',
            'order' => 'nullable|integer|min:1',
            'file' => 'nullable|file|max:20480',
        ]);

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
            'file_url' => $item->file_url,
            'body' => $item->body,
        ];
    }
}
