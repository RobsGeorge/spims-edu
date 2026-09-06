<?php

namespace Tests\Feature\Events;

use App\Enums\EventReservationStatus;
use App\Models\EventCheckIn;
use App\Models\EventReservation;
use App\Services\Events\EventCheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class StudentEventUiTest extends TestCase
{
    use EventFixtures;
    use RefreshDatabase;

    #[Test]
    public function student_sees_published_events_and_not_drafts(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $draft = $this->draftEvent($admin, ['title' => 'Hidden draft']);
        $published = $this->publishedEvent($admin, ['title' => 'Open vigil']);

        $this->actingAs($student)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee('Open vigil', false)
            ->assertDontSee('Hidden draft');

        $this->actingAs($student)
            ->get(route('events.show', $draft))
            ->assertNotFound();

        $this->actingAs($student)
            ->get(route('events.show', $published))
            ->assertOk()
            ->assertSee('Open vigil', false)
            ->assertSee(__('events.reserve'), false);
    }

    #[Test]
    public function student_reserves_then_sees_the_event_in_mine_and_can_cancel(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $event = $this->publishedEvent($admin, ['title' => 'Chapel retreat']);

        $this->actingAs($student)
            ->from(route('events.show', $event))
            ->post(route('events.reserve', $event))
            ->assertRedirect(route('events.show', $event));

        $reservation = EventReservation::query()
            ->where('student_id', $student->id)
            ->where('event_id', $event->id)
            ->firstOrFail();
        $this->assertSame(EventReservationStatus::Reserved, $reservation->status);

        $payload = app(EventCheckInService::class)->issueQr($reservation);

        $this->actingAs($student)
            ->get(route('events.mine'))
            ->assertOk()
            ->assertSee('Chapel retreat', false)
            ->assertSee($payload, false);

        $this->actingAs($student)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee(__('events.cancel_reservation'), false)
            ->assertSee($payload, false);

        $this->actingAs($student)
            ->from(route('events.show', $event))
            ->post(route('events.cancel', $event))
            ->assertRedirect(route('events.show', $event));

        $this->assertSame(EventReservationStatus::Cancelled, $reservation->fresh()->status);
        $this->assertSame(0, EventReservation::query()
            ->where('student_id', $student->id)
            ->where('event_id', $event->id)
            ->whereIn('status', [
                EventReservationStatus::Reserved,
                EventReservationStatus::Waitlisted,
            ])
            ->count());
    }

    #[Test]
    public function ineligible_student_is_forbidden_from_reserving(): void
    {
        $admin = $this->admin();
        $program = $this->program('LIT');
        $event = $this->publishedEvent($admin, [
            'title' => 'Liturgy only',
            'eligibility' => [
                'programs' => [$program->id],
                'offerings' => [],
                'roles' => [],
            ],
        ]);
        $student = $this->student();

        $this->actingAs($student)
            ->from(route('events.show', $event))
            ->post(route('events.reserve', $event))
            ->assertForbidden();

        $this->assertSame(0, EventReservation::query()->where('student_id', $student->id)->count());
    }

    #[Test]
    public function check_in_payload_from_the_student_page_verifies_once(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $event = $this->publishedEvent($admin, ['title' => 'Vigil']);

        $this->actingAs($student)
            ->post(route('events.reserve', $event))
            ->assertRedirect(route('events.show', $event));

        $reservation = EventReservation::query()
            ->where('student_id', $student->id)
            ->where('event_id', $event->id)
            ->firstOrFail();
        $payload = app(EventCheckInService::class)->issueQr($reservation);

        $this->actingAs($student)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee($payload, false);

        $checkIns = app(EventCheckInService::class);
        $created = $checkIns->verify($admin, $payload);
        $this->assertSame($reservation->id, $created->reservation_id);
        $this->assertSame(1, EventCheckIn::query()->where('reservation_id', $reservation->id)->count());

        try {
            $checkIns->verify($admin, $payload);
            $this->fail('Expected a replay conflict');
        } catch (ConflictHttpException) {
            $this->assertSame(1, EventCheckIn::query()->where('reservation_id', $reservation->id)->count());
        }
    }

    #[Test]
    public function learning_hub_and_dashboard_link_to_the_student_catalog(): void
    {
        $student = $this->student();

        $this->actingAs($student)
            ->get(route('hubs.learning'))
            ->assertOk()
            ->assertSee(__('events.hub'), false)
            ->assertSee(route('events.index'), false);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('events.hub'), false)
            ->assertSee(route('events.index'), false);
    }

    #[Test]
    public function guest_is_redirected_from_student_event_routes(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent($admin);

        $this->get(route('events.index'))->assertRedirect(route('auth.login'));
        $this->get(route('events.mine'))->assertRedirect(route('auth.login'));
        $this->get(route('events.show', $event))->assertRedirect(route('auth.login'));
        $this->post(route('events.reserve', $event))->assertRedirect(route('auth.login'));
        $this->post(route('events.cancel', $event))->assertRedirect(route('auth.login'));
    }
}
