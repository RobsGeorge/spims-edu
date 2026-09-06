<?php

namespace Tests\Feature\Events;

use App\Enums\EventReservationExceptionKind;
use App\Enums\EventReservationStatus;
use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\AuditLog;
use App\Models\EventReservation;
use App\Models\EventReservationException;
use App\Services\Events\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class EventReservationTest extends TestCase
{
    use EventFixtures;
    use RefreshDatabase;

    #[Test]
    public function capacity_fills_then_waitlist_or_409(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent($admin, [
            'capacity' => 1,
            'waitlist_enabled' => true,
        ]);
        $a = $this->student();
        $b = $this->student();
        $service = app(EventService::class);

        $first = $service->reserve($a, $event);
        $this->assertSame(EventReservationStatus::Reserved, $first->status);

        $waitlisted = $service->reserve($b, $event);
        $this->assertSame(EventReservationStatus::Waitlisted, $waitlisted->status);

        $noWaitlist = $this->publishedEvent($admin, [
            'title' => 'Full lecture',
            'capacity' => 1,
            'waitlist_enabled' => false,
        ]);
        $service->reserve($a, $noWaitlist);

        $this->expectException(ConflictHttpException::class);
        $service->reserve($b, $noWaitlist);
    }

    #[Test]
    public function cancelling_a_reserved_seat_promotes_the_earliest_waitlisted_student(): void
    {
        Carbon::setTestNow('2026-09-06 10:00:00');
        $admin = $this->admin();
        $event = $this->publishedEvent($admin, [
            'capacity' => 1,
            'waitlist_enabled' => true,
        ]);
        $service = app(EventService::class);
        $a = $this->student();
        $b = $this->student();
        $c = $this->student();

        $service->reserve($a, $event);
        Carbon::setTestNow('2026-09-06 10:01:00');
        $bRes = $service->reserve($b, $event);
        Carbon::setTestNow('2026-09-06 10:02:00');
        $cRes = $service->reserve($c, $event);

        $this->assertSame(EventReservationStatus::Waitlisted, $bRes->fresh()->status);
        $this->assertSame(EventReservationStatus::Waitlisted, $cRes->fresh()->status);

        Carbon::setTestNow('2026-09-06 10:03:00');
        $cancelled = $service->cancelReservation($a, $event);
        $this->assertSame(EventReservationStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);

        $this->assertSame(EventReservationStatus::Reserved, $bRes->fresh()->status);
        $this->assertSame(EventReservationStatus::Waitlisted, $cRes->fresh()->status);
        $this->assertTrue($bRes->fresh()->reserved_at->lt($cRes->fresh()->reserved_at));
    }

    #[Test]
    public function double_reservation_is_conflict_and_re_reserve_after_cancel_is_allowed(): void
    {
        $admin = $this->admin();
        $event = $this->publishedEvent($admin, ['capacity' => 2]);
        $student = $this->student();
        $service = app(EventService::class);

        $first = $service->reserve($student, $event);
        $this->assertSame(EventReservationStatus::Reserved, $first->status);

        try {
            $service->reserve($student, $event);
            $this->fail('Expected 409 on a second open reservation');
        } catch (ConflictHttpException $e) {
            $this->assertSame(__('events.already_reserved'), $e->getMessage());
        }

        $this->assertSame(1, EventReservation::query()->where('student_id', $student->id)->where('event_id', $event->id)->where('status', EventReservationStatus::Reserved)->count());

        $service->cancelReservation($student, $event);
        $again = $service->reserve($student, $event);

        $this->assertNotSame($first->id, $again->id);
        $this->assertSame(EventReservationStatus::Reserved, $again->status);
        $this->assertSame(EventReservationStatus::Cancelled, $first->fresh()->status);
    }

    #[Test]
    public function eligibility_exceptions_override_program_offering_and_role_rules(): void
    {
        $admin = $this->admin();
        $service = app(EventService::class);
        $program = $this->program('THEO');
        $offering = $this->offering('EVT1');
        $eligible = $this->student();
        $outsider = $this->student();
        $this->activeProgram($eligible, $program);
        $this->enroll($eligible, $offering);

        $event = $this->publishedEvent($admin, [
            'title' => 'Program retreat',
            'eligibility' => [
                'programs' => [$program->id],
                'offerings' => [$offering->id],
                'roles' => [RoleType::Student->value],
            ],
        ]);

        $this->assertTrue($service->eligible($eligible, $event));
        $this->assertFalse($service->eligible($outsider, $event));

        EventReservationException::query()->create([
            'event_id' => $event->id,
            'student_id' => $outsider->id,
            'kind' => EventReservationExceptionKind::Allow,
        ]);
        $this->assertTrue($service->eligible($outsider, $event));
        $allowed = $service->reserve($outsider, $event);
        $this->assertSame(EventReservationStatus::Reserved, $allowed->status);

        EventReservationException::query()->create([
            'event_id' => $event->id,
            'student_id' => $eligible->id,
            'kind' => EventReservationExceptionKind::Deny,
        ]);
        $this->assertFalse($service->eligible($eligible, $event));

        $this->expectException(AuthorizationException::class);
        $service->reserve($eligible, $event);
    }

    #[Test]
    public function ineligible_student_is_forbidden(): void
    {
        $admin = $this->admin();
        $program = $this->program('LIT');
        $event = $this->publishedEvent($admin, [
            'eligibility' => [
                'programs' => [$program->id],
                'offerings' => [],
                'roles' => [],
            ],
        ]);
        $student = $this->student();

        $this->expectException(AuthorizationException::class);
        app(EventService::class)->reserve($student, $event);
    }

    #[Test]
    public function publish_reserve_and_cancel_write_audit_rows(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $service = app(EventService::class);
        $draft = $this->draftEvent($admin, ['title' => 'Audited']);
        $published = $service->publish($admin, $draft);
        $reservation = $service->reserve($student, $published);
        $service->cancelReservation($student, $published);

        $this->assertSame(1, AuditLog::query()->where('action', 'events.publish')->where('entity_id', $published->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'events.reserve')->where('entity_id', $reservation->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'events.reservation.cancel')->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'events.create')->count());
    }

    #[Test]
    public function listing_published_events_does_not_write(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $event = $this->publishedEvent($admin);
        $before = AuditLog::query()->count();

        $listed = app(EventService::class)->publishedQuery($student)->get();

        $this->assertTrue($listed->contains('id', $event->id));
        $this->assertSame($before, AuditLog::query()->count());
    }
}
