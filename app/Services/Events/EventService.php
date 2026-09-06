<?php

namespace App\Services\Events;

use App\Enums\EnrollmentStatus;
use App\Enums\EventReservationExceptionKind;
use App\Enums\EventReservationStatus;
use App\Enums\EventStatus;
use App\Enums\StudentProgramStatus;
use App\Exceptions\AuthorizationException;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventReservation;
use App\Models\EventReservationException;
use App\Models\StudentProgram;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Event
    {
        $this->authorize->authorize($actor, 'events.admin');

        $validated = Validator::make($data, [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'venue' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'waitlist_enabled' => 'sometimes|boolean',
            'eligibility' => 'sometimes|array',
            'eligibility.programs' => 'sometimes|array',
            'eligibility.programs.*' => 'string',
            'eligibility.offerings' => 'sometimes|array',
            'eligibility.offerings.*' => 'string',
            'eligibility.roles' => 'sometimes|array',
            'eligibility.roles.*' => 'string',
        ])->validate();

        return $this->audit->withAudit($actor, 'events.create', function () use ($actor, $validated) {
            $event = Event::query()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'venue' => $validated['venue'] ?? null,
                'capacity' => $validated['capacity'] ?? null,
                'status' => EventStatus::Draft,
                'eligibility' => $this->normalizeEligibility($validated['eligibility'] ?? []),
                'waitlist_enabled' => (bool) ($validated['waitlist_enabled'] ?? false),
                'created_by' => $actor->id,
            ]);

            EventAdmin::query()->create([
                'event_id' => $event->id,
                'user_id' => $actor->id,
            ]);

            return $event;
        }, 'Event');
    }

    public function publish(User $actor, Event $event): Event
    {
        $this->authorize->authorize($actor, 'events.admin');

        return $this->audit->withAudit($actor, 'events.publish', function () use ($event) {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== EventStatus::Draft) {
                throw new ConflictHttpException(__('events.invalid_transition', [
                    'from' => $locked->status->value,
                    'to' => EventStatus::Published->value,
                ]));
            }

            $locked->update(['status' => EventStatus::Published]);

            return $locked->fresh();
        }, 'Event');
    }

    public function cancelEvent(User $actor, Event $event): Event
    {
        $this->authorize->authorize($actor, 'events.admin');

        return $this->audit->withAudit($actor, 'events.cancel', function () use ($event) {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === EventStatus::Cancelled) {
                throw new ConflictHttpException(__('events.invalid_transition', [
                    'from' => $locked->status->value,
                    'to' => EventStatus::Cancelled->value,
                ]));
            }

            $locked->update(['status' => EventStatus::Cancelled]);

            EventReservation::query()
                ->where('event_id', $locked->id)
                ->whereIn('status', [
                    EventReservationStatus::Reserved,
                    EventReservationStatus::Waitlisted,
                ])
                ->update([
                    'status' => EventReservationStatus::Cancelled->value,
                    'cancelled_at' => now(),
                ]);

            return $locked->fresh();
        }, 'Event');
    }

    public function eligible(User $user, Event $event): bool
    {
        $exception = EventReservationException::query()
            ->where('event_id', $event->id)
            ->where('student_id', $user->id)
            ->first();

        if ($exception !== null) {
            return $exception->kind === EventReservationExceptionKind::Allow;
        }

        $eligibility = $this->normalizeEligibility($event->eligibility ?? []);

        if ($eligibility['offerings'] !== []) {
            $enrolled = Enrollment::query()
                ->where('student_id', $user->id)
                ->whereIn('offering_id', $eligibility['offerings'])
                ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
                ->exists();
            if (! $enrolled) {
                return false;
            }
        }

        if ($eligibility['programs'] !== []) {
            $inProgram = StudentProgram::query()
                ->where('student_id', $user->id)
                ->whereIn('program_id', $eligibility['programs'])
                ->where('status', StudentProgramStatus::Active)
                ->exists();
            if (! $inProgram) {
                return false;
            }
        }

        if ($eligibility['roles'] !== []) {
            $roleValues = $user->roleTypes()->map(fn ($role) => $role->value)->all();
            if (count(array_intersect($eligibility['roles'], $roleValues)) === 0) {
                return false;
            }
        }

        return true;
    }

    public function reserve(User $user, Event $event): EventReservation
    {
        $this->authorize->authorize($user, 'events.reserve');

        try {
            return $this->audit->withAudit($user, 'events.reserve', function () use ($user, $event) {
                $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === EventStatus::Cancelled) {
                    throw new ConflictHttpException(__('events.cancelled'));
                }

                if ($locked->status !== EventStatus::Published) {
                    throw new NotFoundHttpException;
                }

                if (! $this->eligible($user, $locked)) {
                    throw new AuthorizationException(__('events.ineligible'));
                }

                $open = EventReservation::query()
                    ->where('event_id', $locked->id)
                    ->where('student_id', $user->id)
                    ->whereIn('status', [
                        EventReservationStatus::Reserved,
                        EventReservationStatus::Waitlisted,
                    ])
                    ->lockForUpdate()
                    ->first();

                if ($open !== null) {
                    throw new ConflictHttpException(__('events.already_reserved'));
                }

                return EventReservation::query()->create([
                    'event_id' => $locked->id,
                    'student_id' => $user->id,
                    'status' => $this->allocationStatus($locked),
                    'reserved_at' => now(),
                ]);
            }, 'EventReservation');
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new ConflictHttpException(__('events.already_reserved'));
            }

            throw $e;
        }
    }

    public function cancelReservation(User $user, Event $event): EventReservation
    {
        $this->authorize->authorize($user, 'events.reserve');

        return $this->audit->withAudit($user, 'events.reservation.cancel', function () use ($user, $event) {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

            $reservation = EventReservation::query()
                ->where('event_id', $locked->id)
                ->where('student_id', $user->id)
                ->whereIn('status', [
                    EventReservationStatus::Reserved,
                    EventReservationStatus::Waitlisted,
                ])
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                throw new NotFoundHttpException;
            }

            $wasReserved = $reservation->status === EventReservationStatus::Reserved;

            $reservation->update([
                'status' => EventReservationStatus::Cancelled,
                'cancelled_at' => now(),
            ]);

            if ($wasReserved) {
                $this->promoteWaitlist($locked);
            }

            return $reservation->fresh();
        }, 'EventReservation');
    }

    public function publishedQuery(User $user): Builder
    {
        $this->authorize->authorize($user, 'events.view');

        return Event::query()
            ->where('status', EventStatus::Published)
            ->orderBy('starts_at');
    }

    public function show(User $user, Event $event): Event
    {
        $this->authorize->authorize($user, 'events.view');

        if ($event->status !== EventStatus::Published && ! $this->hasAdminGrant($user)) {
            throw new NotFoundHttpException;
        }

        return $event;
    }

    /**
     * @return Collection<int, EventReservation>
     */
    public function mine(User $user): Collection
    {
        $this->authorize->authorize($user, 'events.view');

        return EventReservation::query()
            ->where('student_id', $user->id)
            ->with('event')
            ->orderByDesc('reserved_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{programs: array<int, string>, offerings: array<int, string>, roles: array<int, string>}
     */
    private function normalizeEligibility(array $raw): array
    {
        return [
            'programs' => array_values(array_filter(array_map('strval', $raw['programs'] ?? []))),
            'offerings' => array_values(array_filter(array_map('strval', $raw['offerings'] ?? []))),
            'roles' => array_values(array_filter(array_map('strval', $raw['roles'] ?? []))),
        ];
    }

    private function allocationStatus(Event $event): EventReservationStatus
    {
        if ($event->capacity === null) {
            return EventReservationStatus::Reserved;
        }

        $taken = EventReservation::query()
            ->where('event_id', $event->id)
            ->where('status', EventReservationStatus::Reserved)
            ->count();

        if ($taken < $event->capacity) {
            return EventReservationStatus::Reserved;
        }

        if ($event->waitlist_enabled) {
            return EventReservationStatus::Waitlisted;
        }

        throw new ConflictHttpException(__('events.full'));
    }

    private function promoteWaitlist(Event $event): void
    {
        if ($event->capacity === null) {
            return;
        }

        $taken = EventReservation::query()
            ->where('event_id', $event->id)
            ->where('status', EventReservationStatus::Reserved)
            ->count();

        if ($taken >= $event->capacity) {
            return;
        }

        $next = EventReservation::query()
            ->where('event_id', $event->id)
            ->where('status', EventReservationStatus::Waitlisted)
            ->orderBy('reserved_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        $next?->update(['status' => EventReservationStatus::Reserved]);
    }

    private function hasAdminGrant(User $user): bool
    {
        try {
            $this->authorize->authorize($user, 'events.admin');

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return $sqlState === '23505'
            || $sqlState === '23000'
            || str_contains($e->getMessage(), 'UNIQUE')
            || str_contains($e->getMessage(), 'unique');
    }
}
