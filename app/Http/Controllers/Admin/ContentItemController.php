<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentItemType;
use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\Week;
use App\Services\Offerings\OfferingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContentItemController extends Controller
{
    public function store(Request $request, Week $week, OfferingService $service): RedirectResponse
    {
        $data = $this->validated($request);
        $service->addContentItem($request->user(), $week, $data, $request->file('file'));

        return back()->with('status', __('offerings.content_added'));
    }

    public function update(Request $request, ContentItem $item, OfferingService $service): RedirectResponse
    {
        $data = $this->validated($request, updating: true);
        $service->updateContentItem($request->user(), $item, $data, $request->file('file'));

        return back()->with('status', __('offerings.content_updated'));
    }

    public function destroy(Request $request, ContentItem $item, OfferingService $service): RedirectResponse
    {
        $service->deleteContentItem($request->user(), $item);

        return back()->with('status', __('offerings.content_deleted'));
    }

    public function publish(Request $request, ContentItem $item, OfferingService $service): RedirectResponse
    {
        $service->publishContentItem($request->user(), $item);

        return back()->with('status', __('offerings.content_published'));
    }

    public function unpublish(Request $request, ContentItem $item, OfferingService $service): RedirectResponse
    {
        $service->unpublishContentItem($request->user(), $item);

        return back()->with('status', __('offerings.content_unpublished'));
    }

    public function moveUp(Request $request, ContentItem $item, OfferingService $service): RedirectResponse
    {
        $service->moveContentItemByDelta($request->user(), $item, -1);

        return back()->with('status', __('offerings.content_reordered'));
    }

    public function moveDown(Request $request, ContentItem $item, OfferingService $service): RedirectResponse
    {
        $service->moveContentItemByDelta($request->user(), $item, 1);

        return back()->with('status', __('offerings.content_reordered'));
    }

    public function move(Request $request, ContentItem $item, OfferingService $service): RedirectResponse
    {
        $data = $request->validate([
            'week_id' => 'required|exists:weeks,id',
        ]);
        $target = Week::query()->findOrFail($data['week_id']);
        $service->moveContentItem($request->user(), $item, $target);

        return back()->with('status', __('offerings.content_moved'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $types = implode(',', array_column(ContentItemType::cases(), 'value'));

        $data = $request->validate([
            'type' => ($updating ? 'sometimes' : 'required').'|in:'.$types,
            'title' => ($updating ? 'sometimes' : 'required').'|string|max:255',
            'vimeo_id' => 'nullable|string|max:256',
            'video_url' => 'nullable|string|max:2048',
            'file_url' => 'nullable|string|max:2048',
            'body' => 'nullable|string',
            'order' => 'nullable|integer|min:1',
            'file' => 'nullable|file|max:20480',
        ]);

        return $data;
    }
}
