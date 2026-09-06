<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Communications\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcements,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->announcements->inboxFor($request->user())->map(fn (Announcement $a) => $this->payload($a));

        return response()->json(['data' => $items->values()]);
    }

    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        $announcement = $this->announcements->showFor($request->user(), $announcement);

        return response()->json(['data' => $this->payload($announcement)]);
    }

    public function dismissBanner(Request $request, Announcement $announcement): JsonResponse
    {
        $this->announcements->dismissBanner($request->user(), $announcement);

        return response()->json(['data' => ['dismissed' => true]]);
    }

    /** @return array<string, mixed> */
    private function payload(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->localizedBody(),
            'is_banner' => $announcement->is_banner,
            'banner_expires_at' => $announcement->banner_expires_at?->toIso8601String(),
            'published_at' => $announcement->published_at?->toIso8601String(),
            'offering_id' => $announcement->offering_id,
        ];
    }
}
