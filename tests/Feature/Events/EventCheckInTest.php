<?php

namespace Tests\Feature\Events;

use App\Enums\EventReservationStatus;
use App\Models\EventCheckIn;
use App\Services\Events\EventCheckInService;
use App\Services\Events\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventCheckInTest extends TestCase
{
    use EventFixtures;
    use RefreshDatabase;

    #[Test]
    public function qr_verifies_once_then_replay_conflicts(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $event = $this->publishedEvent($admin);
        $reservation = app(EventService::class)->reserve($student, $event);
        $payload = app(EventCheckInService::class)->issueQr($reservation);

        $created = $this->asApi($admin, 'ADMINISTRATIVE_ADMIN')
            ->postJson(route('api.v1.events.check-in.verify'), ['payload' => $payload])
            ->assertCreated()
            ->json('data');

        $this->assertSame($reservation->id, $created['reservation_id']);
        $this->assertSame($admin->id, $created['checked_in_by_id']);
        $this->assertNotNull($created['checked_in_at']);
        $this->assertSame(1, EventCheckIn::query()->where('reservation_id', $reservation->id)->count());

        $this->asApi($admin, 'ADMINISTRATIVE_ADMIN')
            ->postJson(route('api.v1.events.check-in.verify'), ['payload' => $payload])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');
    }

    #[Test]
    public function forged_signature_is_forbidden(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $event = $this->publishedEvent($admin);
        $reservation = app(EventService::class)->reserve($student, $event);
        $payload = app(EventCheckInService::class)->issueQr($reservation);
        $forged = substr($payload, 0, -1).(substr($payload, -1) === 'a' ? 'b' : 'a');

        $this->asApi($admin, 'ADMINISTRATIVE_ADMIN')
            ->postJson(route('api.v1.events.check-in.verify'), ['payload' => $forged])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->assertSame(0, EventCheckIn::query()->count());
    }

    #[Test]
    public function malformed_payload_is_unprocessable(): void
    {
        $admin = $this->admin();

        $this->asApi($admin, 'ADMINISTRATIVE_ADMIN')
            ->postJson(route('api.v1.events.check-in.verify'), ['payload' => 'not-a-qr'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED');
    }

    #[Test]
    public function cancelled_reservation_cannot_check_in(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $event = $this->publishedEvent($admin);
        $service = app(EventService::class);
        $reservation = $service->reserve($student, $event);
        $payload = app(EventCheckInService::class)->issueQr($reservation);
        $service->cancelReservation($student, $event);
        $this->assertSame(EventReservationStatus::Cancelled, $reservation->fresh()->status);

        $this->asApi($admin, 'ADMINISTRATIVE_ADMIN')
            ->postJson(route('api.v1.events.check-in.verify'), ['payload' => $payload])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONFLICT');

        $this->assertSame(0, EventCheckIn::query()->count());
    }

    #[Test]
    public function student_cannot_verify_check_in(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $event = $this->publishedEvent($admin);
        $reservation = app(EventService::class)->reserve($student, $event);
        $payload = app(EventCheckInService::class)->issueQr($reservation);

        $this->asApi($student)
            ->postJson(route('api.v1.events.check-in.verify'), ['payload' => $payload])
            ->assertForbidden();
    }

    #[Test]
    public function academic_admin_can_verify(): void
    {
        $admin = $this->admin();
        $academic = $this->academicAdmin();
        $student = $this->student();
        $event = $this->publishedEvent($admin);
        $reservation = app(EventService::class)->reserve($student, $event);
        $payload = app(EventCheckInService::class)->issueQr($reservation);

        $this->asApi($academic, 'ACADEMIC_ADMIN')
            ->postJson(route('api.v1.events.check-in.verify'), ['payload' => $payload])
            ->assertCreated();
    }
}
