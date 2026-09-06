<?php

namespace Tests\Feature\Communications;

use App\Enums\AnnouncementStatus;
use App\Enums\CommunicationChannel;
use App\Enums\DeliveryStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\AnnouncementRevision;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Communications\AnnouncementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnnouncementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function offeringWithStudent(User $instructor, User $student): CourseOffering
    {
        $course = Course::query()->create([
            'code' => 'COM1',
            'title' => 'Comms Course',
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
        $this->staffOffering($instructor, $offering);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        return $offering;
    }

    #[Test]
    public function draft_edit_publish_delivers_and_resend_does_not_duplicate(): void
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offeringWithStudent($instructor, $student);
        $service = app(AnnouncementService::class);

        $announcement = $service->draft($instructor, $offering, [
            'title' => 'Week 1',
            'body' => 'Welcome',
        ]);
        $this->assertSame(AnnouncementStatus::Draft, $announcement->status);

        $updated = $service->update($instructor, $announcement, [
            'title' => 'Week 1 revised',
            'body' => 'Welcome all',
        ]);
        $this->assertSame('Week 1 revised', $updated->title);
        $this->assertSame(1, AnnouncementRevision::query()->where('announcement_id', $announcement->id)->count());
        $this->assertDatabaseHas('announcement_revisions', [
            'announcement_id' => $announcement->id,
            'title' => 'Week 1',
            'body' => 'Welcome',
        ]);

        $published = $service->publish($instructor, $updated->fresh());
        $this->assertTrue($published->isPublished());

        $this->assertSame(
            2,
            AnnouncementDelivery::query()->where('announcement_id', $published->id)->where('recipient_id', $student->id)->count(),
            'One in_app and one mail delivery per targeted recipient'
        );
        $this->assertTrue(
            AnnouncementDelivery::query()
                ->where('announcement_id', $published->id)
                ->where('recipient_id', $student->id)
                ->where('channel', CommunicationChannel::InApp)
                ->where('status', DeliveryStatus::Sent)
                ->exists()
        );

        $service->resendEmail($instructor, $published->fresh());

        $this->assertSame(
            1,
            AnnouncementDelivery::query()
                ->where('announcement_id', $published->id)
                ->where('recipient_id', $student->id)
                ->where('channel', CommunicationChannel::Mail)
                ->count(),
            'Resend must not create a second mail delivery row'
        );
        $this->assertSame(1, Announcement::query()->count());
    }
}
