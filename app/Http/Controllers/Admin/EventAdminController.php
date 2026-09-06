<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventReservationExceptionKind;
use App\Enums\EventReservationStatus;
use App\Exceptions\AuthorizationException;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\Events\EventCheckInService;
use App\Services\Events\EventService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EventAdminController extends Controller
{
    public function __construct(
        private readonly EventService $events,
        private readonly EventCheckInService $checkIns,
    ) {}

    public function index(Request $request): View
    {
        $items = $this->events->adminQuery($request->user())
            ->withCount('reservations')
            ->get();

        return view('admin.events.index', [
            'events' => $items,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'venue' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'waitlist_enabled' => 'nullable|boolean',
        ]);

        $event = $this->events->create($request->user(), [
            ...$data,
            'waitlist_enabled' => $request->boolean('waitlist_enabled'),
            'eligibility' => ['programs' => [], 'offerings' => [], 'roles' => []],
        ]);

        return redirect()
            ->route('admin.events.show', $event)
            ->with('status', __('staff.events.created'));
    }

    public function show(Request $request, Event $event): View
    {
        $this->events->adminQuery($request->user());

        $event->load(['reservations.student', 'reservations.checkIn', 'exceptions.student']);

        $reserved = $event->reservations->where('status', EventReservationStatus::Reserved);
        $waitlist = $event->reservations->where('status', EventReservationStatus::Waitlisted);

        return view('admin.events.show', [
            'event' => $event,
            'reserved' => $reserved,
            'waitlist' => $waitlist,
            'exceptionKinds' => EventReservationExceptionKind::cases(),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'venue' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'waitlist_enabled' => 'nullable|boolean',
        ]);

        try {
            $this->events->updateDraft($request->user(), $event, [
                ...$data,
                'waitlist_enabled' => $request->boolean('waitlist_enabled'),
            ]);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('staff.events.updated'));
    }

    public function publish(Request $request, Event $event): RedirectResponse
    {
        try {
            $this->events->publish($request->user(), $event);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('staff.events.published'));
    }

    public function cancel(Request $request, Event $event): RedirectResponse
    {
        try {
            $this->events->cancelEvent($request->user(), $event);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', __('staff.events.cancelled'));
    }

    public function storeException(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => 'required|string|exists:users,id',
            'kind' => 'required|in:ALLOW,DENY',
        ]);

        $this->events->setException(
            $request->user(),
            $event,
            User::query()->findOrFail($data['student_id']),
            EventReservationExceptionKind::from($data['kind']),
        );

        return back()->with('status', __('staff.events.exception_saved'));
    }

    public function checkIn(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'payload' => 'required|string|max:512',
        ]);

        try {
            $this->checkIns->verify($request->user(), $data['payload']);
        } catch (ConflictHttpException $e) {
            return back()->with('error', $e->getMessage());
        } catch (AuthorizationException $e) {
            throw $e;
        }

        return back()->with('status', __('staff.events.checked_in'));
    }
}
