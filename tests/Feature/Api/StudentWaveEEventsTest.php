<?php

namespace Tests\Feature\Api;

use App\Enums\EventReservationStatus;
use App\Enums\EventStatus;
use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\EventReservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Events\EventFixtures;
use Tests\TestCase;

class StudentWaveEEventsTest extends TestCase
{
    use EventFixtures;
    use RefreshDatabase;

    #[Test]
    public function list_show_mine_reserve_cancel_and_idempotent_reserve_replay(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $draft = $this->draftEvent($admin, ['title' => 'Hidden']);
        $event = $this->publishedEvent($admin, [
            'title' => 'Vigil',
            'capacity' => 10,
            'waitlist_enabled' => true,
        ]);

        $list = $this->asApi($student)
            ->getJson(route('api.v1.events.index'))
            ->assertOk()
            ->json('data');
        $this->assertTrue(collect($list)->pluck('id')->contains($event->id));
        $this->assertFalse(collect($list)->pluck('id')->contains($draft->id));

        $this->asApi($student)
            ->getJson(route('api.v1.events.show', $event))
            ->assertOk()
            ->assertJsonPath('data.title', 'Vigil')
            ->assertJsonPath('data.status', EventStatus::Published->value)
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.my_reservation', null);

        $first = $this->asApi($student)
            ->postJson(route('api.v1.events.reserve', $event), [], [
                'Idempotency-Key' => 'reserve-1',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', EventReservationStatus::Reserved->value)
            ->json('data');

        $replay = $this->asApi($student)
            ->postJson(route('api.v1.events.reserve', $event), [], [
                'Idempotency-Key' => 'reserve-1',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame($first['id'], $replay['id']);
        $this->assertSame(1, EventReservation::query()->where('student_id', $student->id)->where('event_id', $event->id)->where('status', EventReservationStatus::Reserved)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'events.reserve')->count());

        $mine = $this->asApi($student)
            ->getJson(route('api.v1.events.mine'))
            ->assertOk()
            ->json('data');
        $this->assertSame($first['id'], $mine[0]['id']);
        $this->assertSame('Vigil', $mine[0]['event']['title']);

        $this->asApi($student)
            ->getJson(route('api.v1.events.show', $event))
            ->assertOk()
            ->assertJsonPath('data.my_reservation.id', $first['id']);

        $this->asApi($student)
            ->postJson(route('api.v1.events.cancel', $event))
            ->assertOk()
            ->assertJsonPath('data.status', EventReservationStatus::Cancelled->value);

        $this->assertSame(1, AuditLog::query()->where('action', 'events.reservation.cancel')->count());
        $this->assertSame(EventReservationStatus::Cancelled, EventReservation::query()->find($first['id'])->status);
    }

    #[Test]
    public function events_routes_are_401_without_a_token(): void
    {
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $event = $this->publishedEvent($admin);

        $this->getJson(route('api.v1.events.index'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->getJson(route('api.v1.events.mine'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->getJson(route('api.v1.events.show', $event))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->postJson(route('api.v1.events.reserve', $event))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->postJson(route('api.v1.events.cancel', $event))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }
}
