<?php

namespace Tests\Feature\Communications;

use App\Enums\AnnouncementStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Services\Communications\AnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnnouncementScopeTest extends TestCase
{
    use RefreshDatabase;

    private function offering(string $code): CourseOffering
    {
        $course = Course::query()->create([
            'code' => $code,
            'title' => $code,
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        return CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
    }

    #[Test]
    public function instructor_cannot_publish_to_an_offering_they_do_not_staff(): void
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $mine = $this->offering('MINE');
        $theirs = $this->offering('THEIRS');
        $this->staffOffering($instructor, $mine);

        $service = app(AnnouncementService::class);
        $own = $service->draft($instructor, $mine, ['title' => 'Mine', 'body' => 'ok']);
        $this->assertSame(AnnouncementStatus::Draft, $own->status);

        try {
            $service->draft($instructor, $theirs, ['title' => 'Theirs', 'body' => 'no']);
            $this->fail('Expected draft on an unstaffed offering to be denied.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $foreign = Announcement::query()->create([
            'offering_id' => $theirs->id,
            'author_id' => $instructor->id,
            'title' => 'Foreign',
            'body' => 'no',
            'status' => AnnouncementStatus::Draft,
        ]);

        try {
            $service->publish($instructor, $foreign);
            $this->fail('Expected publish on an unstaffed offering to be denied.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->assertFalse($foreign->fresh()->isPublished());
    }
}
