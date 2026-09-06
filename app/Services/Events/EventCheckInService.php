<?php

namespace App\Services\Events;

use App\Enums\EventReservationStatus;
use App\Enums\EventStatus;
use App\Exceptions\AuthorizationException;
use App\Models\EventCheckIn;
use App\Models\EventReservation;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EventCheckInService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function issueQr(EventReservation $reservation): string
    {
        $body = $reservation->id.'.'.$reservation->student_id;

        return $body.'.'.$this->signature($reservation->id, $reservation->student_id);
    }

    public function verify(User $actor, string $payload): EventCheckIn
    {
        $this->authorize->authorize($actor, 'events.check_in');

        [$reservationId, $studentId, $mac] = $this->parsePayload($payload);

        $expected = $this->signature($reservationId, $studentId);
        if (! hash_equals($expected, $mac)) {
            throw new AuthorizationException(__('events.qr_forged'));
        }

        try {
            return $this->audit->withAudit($actor, 'events.check_in', function () use ($actor, $reservationId, $studentId) {
                $reservation = EventReservation::query()
                    ->whereKey($reservationId)
                    ->lockForUpdate()
                    ->first();

                if ($reservation === null) {
                    throw new NotFoundHttpException;
                }

                if ($reservation->student_id !== $studentId) {
                    throw new AuthorizationException(__('events.qr_forged'));
                }

                if ($reservation->status === EventReservationStatus::Cancelled) {
                    throw new ConflictHttpException(__('events.reservation_cancelled'));
                }

                if ($reservation->status !== EventReservationStatus::Reserved) {
                    throw new ConflictHttpException(__('events.check_in_not_reserved'));
                }

                $event = $reservation->event()->lockForUpdate()->first();
                if ($event === null || $event->status === EventStatus::Cancelled) {
                    throw new ConflictHttpException(__('events.cancelled'));
                }

                if ($reservation->checkIn()->exists()) {
                    throw new ConflictHttpException(__('events.check_in_replay'));
                }

                return EventCheckIn::query()->create([
                    'reservation_id' => $reservation->id,
                    'checked_in_at' => now(),
                    'checked_in_by_id' => $actor->id,
                ]);
            }, 'EventCheckIn');
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new ConflictHttpException(__('events.check_in_replay'));
            }

            throw $e;
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function parsePayload(string $payload): array
    {
        $parts = explode('.', $payload);
        if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '' || $parts[2] === '') {
            throw ValidationException::withMessages([
                'payload' => __('events.qr_invalid'),
            ]);
        }

        return [$parts[0], $parts[1], $parts[2]];
    }

    private function signature(string $reservationId, string $studentId): string
    {
        $message = 'event-check-in|'.$reservationId.'|'.$studentId;

        return hash_hmac('sha256', $message, (string) config('app.key'));
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
