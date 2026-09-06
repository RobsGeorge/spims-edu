<?php

namespace App\Http\Controllers\Events;

use App\Enums\EventReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventReservation;
use App\Services\Events\EventCheckInService;
use App\Services\Events\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class StudentEventController extends Controller
{
    public function __construct(
        private readonly EventService $events,
        private readonly EventCheckInService $checkIns,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $events = $this->events->publishedQuery($user)
            ->withCount(['reservations as reserved_count' => function ($query) {
                $query->where('status', EventReservationStatus::Reserved);
            }])
            ->get();

        $mine = EventReservation::query()
            ->where('student_id', $user->id)
            ->whereIn('event_id', $events->pluck('id'))
            ->whereIn('status', [
                EventReservationStatus::Reserved,
                EventReservationStatus::Waitlisted,
            ])
            ->get()
            ->keyBy('event_id');

        return view('events.index', [
            'events' => $events,
            'mine' => $mine,
        ]);
    }

    public function mine(Request $request): View
    {
        $reservations = $this->events->mine($request->user());

        $qrById = [];
        foreach ($reservations as $reservation) {
            if ($reservation->status === EventReservationStatus::Reserved) {
                $qrById[$reservation->id] = $this->checkIns->issueQr($reservation);
            }
        }

        return view('events.mine', [
            'reservations' => $reservations,
            'qrById' => $qrById,
        ]);
    }

    public function show(Request $request, Event $event): View
    {
        $user = $request->user();
        $event = $this->events->show($user, $event);

        $reservation = EventReservation::query()
            ->where('event_id', $event->id)
            ->where('student_id', $user->id)
            ->whereIn('status', [
                EventReservationStatus::Reserved,
                EventReservationStatus::Waitlisted,
            ])
            ->first();

        $qrPayload = ($reservation !== null && $reservation->status === EventReservationStatus::Reserved)
            ? $this->checkIns->issueQr($reservation)
            : null;

        return view('events.show', [
            'event' => $event,
            'eligible' => $this->events->eligible($user, $event),
            'reservation' => $reservation,
            'qrPayload' => $qrPayload,
            'reservedCount' => $event->reservedCount(),
        ]);
    }

    public function reserve(Request $request, Event $event): RedirectResponse
    {
        try {
            $this->events->reserve($request->user(), $event);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('events.show', $event)
            ->with('status', __('events.reserved_flash'));
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        try {
            $this->events->cancelReservation($request->user(), $event);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('events.show', $event)
            ->with('status', __('events.cancelled_flash'));
    }
}
