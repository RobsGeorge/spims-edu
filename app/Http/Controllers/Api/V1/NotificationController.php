<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationChannel;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\Notifications\NotificationService;
use App\Support\Api\PaginatedEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);
        $query = Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('channel', NotificationChannel::InApp)
            ->latest('created_at');

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $page = $query->paginate($perPage);
        $page->setCollection($page->getCollection()->map(fn (Notification $n) => $this->payload($n)));

        return response()->json(PaginatedEnvelope::from($page));
    }

    public function show(Request $request, Notification $notification): JsonResponse
    {
        $notification = $this->notifications->markRead($request->user(), $notification);

        return response()->json(['data' => $this->payload($notification)]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $notification = $this->notifications->markRead($request->user(), $notification);

        return response()->json(['data' => $this->payload($notification)]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $count = $this->notifications->markAllRead($request->user());

        return response()->json(['data' => ['marked' => $count]]);
    }

    /** @return array<string, mixed> */
    private function payload(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'metadata' => $notification->metadata,
        ];
    }
}
