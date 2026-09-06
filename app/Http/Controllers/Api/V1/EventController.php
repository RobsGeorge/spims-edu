<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventReservation;
use App\Services\Events\EventService;
use App\Support\Api\IdempotencyStore;
use App\Support\Api\PaginatedEnvelope;
use App\Support\Api\StudentPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $events,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = PaginatedEnvelope::perPage($request->integer('per_page') ?: null);
        $page = $this->events->publishedQuery($request->user())->paginate($perPage);
        $page->setCollection(
            $page->getCollection()->map(fn (Event $event) => $this->eventPayload($request, $event))
        );

        return response()->json(PaginatedEnvelope::from($page));
    }

    public function mine(Request $request): JsonResponse
    {
        $items = $this->events->mine($request->user())
            ->map(fn (EventReservation $reservation) => $this->reservationPayload($reservation, includeEvent: true))
            ->values();

        return response()->json(['data' => $items]);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $event = $this->events->show($request->user(), $event);

        return response()->json(['data' => $this->eventPayload($request, $event)]);
    }

    public function reserve(Request $request, Event $event): JsonResponse
    {
        $payload = $this->idempotency->remember(
            $request->user(),
            'events.reserve:'.$event->id,
            $request->header('Idempotency-Key'),
            function () use ($request, $event) {
                $reservation = $this->events->reserve($request->user(), $event);

                return $this->reservationPayload($reservation);
            },
        );

        return response()->json(['data' => $payload], 201);
    }

    public function cancel(Request $request, Event $event): JsonResponse
    {
        $payload = $this->idempotency->remember(
            $request->user(),
            'events.cancel:'.$event->id,
            $request->header('Idempotency-Key'),
            function () use ($request, $event) {
                $reservation = $this->events->cancelReservation($request->user(), $event);

                return $this->reservationPayload($reservation);
            },
        );

        return response()->json(['data' => $payload]);
    }

    /** @return array<string, mixed> */
    private function eventPayload(Request $request, Event $event): array
    {
        $user = $request->user();
        $mine = EventReservation::query()
            ->where('event_id', $event->id)
            ->where('student_id', $user->id)
            ->whereIn('status', [
                EventReservationStatus::Reserved,
                EventReservationStatus::Waitlisted,
            ])
            ->first();

        $reserved = $event->reservedCount();

        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'starts_at' => StudentPayload::iso($event->starts_at),
            'ends_at' => StudentPayload::iso($event->ends_at),
            'venue' => $event->venue,
            'capacity' => $event->capacity,
            'reserved_count' => $reserved,
            'spots_open' => $event->capacity === null ? null : max(0, $event->capacity - $reserved),
            'waitlist_enabled' => $event->waitlist_enabled,
            'status' => $event->status->value,
            'eligible' => $this->events->eligible($user, $event),
            'my_reservation' => $mine === null ? null : $this->reservationPayload($mine),
        ];
    }

    /** @return array<string, mixed> */
    private function reservationPayload(EventReservation $reservation, bool $includeEvent = false): array
    {
        $payload = [
            'id' => $reservation->id,
            'event_id' => $reservation->event_id,
            'status' => $reservation->status->value,
            'reserved_at' => StudentPayload::iso($reservation->reserved_at),
            'cancelled_at' => StudentPayload::iso($reservation->cancelled_at),
        ];

        if ($includeEvent && $reservation->event !== null) {
            $payload['event'] = [
                'id' => $reservation->event->id,
                'title' => $reservation->event->title,
                'starts_at' => StudentPayload::iso($reservation->event->starts_at),
                'ends_at' => StudentPayload::iso($reservation->event->ends_at),
                'venue' => $reservation->event->venue,
                'status' => $reservation->event->status->value,
            ];
        }

        return $payload;
    }
}
