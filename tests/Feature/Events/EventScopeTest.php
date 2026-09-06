<?php

namespace Tests\Feature\Events;

use App\Enums\EventReservationStatus;
use App\Enums\EventStatus;
use App\Enums\RoleType;
use App\Models\User;
use App\Services\Events\EventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventScopeTest extends TestCase
{
    use EventFixtures;
    use RefreshDatabase;

    #[Test]
    public function unpublished_draft_is_not_listed_and_show_is_404_for_students(): void
    {
        $admin = $this->admin();
        $student = $this->student();
        $draft = $this->draftEvent($admin, ['title' => 'Secret draft']);
        $published = $this->publishedEvent($admin, ['title' => 'Open lecture']);

        $list = $this->asApi($student)
            ->getJson(route('api.v1.events.index'))
            ->assertOk()
            ->json('data');

        $ids = collect($list)->pluck('id');
        $this->assertTrue($ids->contains($published->id));
        $this->assertFalse($ids->contains($draft->id));

        $this->asApi($student)
            ->getJson(route('api.v1.events.show', $draft))
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->asApi($student)
            ->getJson(route('api.v1.events.show', $published))
            ->assertOk()
            ->assertJsonPath('data.id', $published->id)
            ->assertJsonPath('data.status', EventStatus::Published->value);

        $this->asApi($student)
            ->postJson(route('api.v1.events.reserve', $draft))
            ->assertNotFound();
    }

    #[Test]
    public function student_cannot_cancel_someone_elses_reservation(): void
    {
        $admin = $this->admin();
        $owner = $this->student();
        $peer = $this->student();
        $event = $this->publishedEvent($admin);
        $reservation = app(EventService::class)->reserve($owner, $event);

        $this->asApi($peer)
            ->postJson(route('api.v1.events.cancel', $event))
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');

        $this->assertSame(EventReservationStatus::Reserved, $reservation->fresh()->status);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson(route('api.v1.events.reserve', $event))
            ->assertForbidden();
    }
}
