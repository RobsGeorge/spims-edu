<?php

namespace Tests\Feature\StaffUi;

use App\Models\Event;
use App\Models\EventCheckIn;
use App\Services\Events\EventCheckInService;
use App\Services\Events\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Events\EventFixtures;
use Tests\TestCase;

class StaffEventUiTest extends TestCase
{
    use EventFixtures;
    use RefreshDatabase;

    #[Test]
    public function admin_publishes_event_then_check_in_form_verifies_once(): void
    {
        $admin = $this->admin();
        $student = $this->student();

        $this->actingAs($admin)
            ->post(route('admin.events.store'), [
                'title' => 'Chapel retreat',
                'description' => 'Overnight',
                'starts_at' => now()->addDay()->format('Y-m-d\\TH:i'),
                'ends_at' => now()->addDays(2)->format('Y-m-d\\TH:i'),
                'venue' => 'Chapel',
                'waitlist_enabled' => 1,
            ])
            ->assertRedirect();

        $event = Event::query()->where('title', 'Chapel retreat')->firstOrFail();
        $this->assertSame('DRAFT', $event->status->value);

        $this->actingAs($admin)
            ->post(route('admin.events.publish', $event))
            ->assertRedirect();

        $event = $event->fresh();
        $this->assertSame('PUBLISHED', $event->status->value);

        $reservation = app(EventService::class)->reserve($student, $event);
        $payload = app(EventCheckInService::class)->issueQr($reservation);

        $this->actingAs($admin)
            ->from(route('admin.events.show', $event))
            ->post(route('admin.events.check-in', $event), ['payload' => $payload])
            ->assertRedirect(route('admin.events.show', $event));

        $this->assertSame(1, EventCheckIn::query()->where('reservation_id', $reservation->id)->count());

        $this->actingAs($admin)
            ->from(route('admin.events.show', $event))
            ->post(route('admin.events.check-in', $event), ['payload' => $payload])
            ->assertRedirect(route('admin.events.show', $event))
            ->assertSessionHas('error');

        $this->assertSame(1, EventCheckIn::query()->where('reservation_id', $reservation->id)->count());
    }
}
