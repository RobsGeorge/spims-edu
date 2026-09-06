<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CourseOffering;
use App\Services\Communications\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachAnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcements,
    ) {}

    public function store(Request $request, CourseOffering $offering): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'is_banner' => ['nullable', 'boolean'],
            'banner_expires_at' => ['nullable', 'date'],
            'targets' => ['nullable', 'array'],
            'targets.*.type' => ['required_with:targets', 'string'],
            'targets.*.id' => ['required_with:targets', 'string'],
        ]);

        $announcement = $this->announcements->draft($request->user(), $offering, $data);

        return response()->json(['data' => $this->payload($announcement)], 201);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'body' => ['sometimes', 'string', 'max:10000'],
            'is_banner' => ['nullable', 'boolean'],
            'banner_expires_at' => ['nullable', 'date'],
            'targets' => ['nullable', 'array'],
            'targets.*.type' => ['required_with:targets', 'string'],
            'targets.*.id' => ['required_with:targets', 'string'],
        ]);

        $announcement = $this->announcements->update($request->user(), $announcement, $data);

        return response()->json(['data' => $this->payload($announcement)]);
    }

    public function publish(Request $request, Announcement $announcement): JsonResponse
    {
        $announcement = $this->announcements->publish($request->user(), $announcement);

        return response()->json(['data' => $this->payload($announcement)]);
    }

    /** @return array<string, mixed> */
    private function payload(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'offering_id' => $announcement->offering_id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'status' => $announcement->status->value,
            'is_banner' => $announcement->is_banner,
            'published_at' => $announcement->published_at?->toIso8601String(),
            'revisions' => $announcement->revisions()->count(),
        ];
    }
}
