<?php

namespace Tests\Feature\Api;

use App\Enums\AnnouncementStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\RoleType;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\User;
use App\Services\Communications\AnnouncementService;
use App\Services\Notifications\NotificationService;
use App\Support\AuthorizeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommunicationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(AuthorizeService::class)->forgetMatrixCache();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function offeringWithRoster(User $staff, User $student, OfferingStaffRole $role = OfferingStaffRole::Instructor): CourseOffering
    {
        $course = Course::query()->create([
            'code' => 'API1',
            'title' => 'API Course',
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => 'OPEN',
        ]);
        $this->staffOffering($staff, $offering, $role);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        return $offering;
    }

    #[Test]
    public function student_cannot_publish_and_list_is_scoped_to_deliveries(): void
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $other = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offeringWithRoster($instructor, $student);

        $service = app(AnnouncementService::class);
        $mine = $service->draft($instructor, $offering, ['title' => 'For enrolled', 'body' => 'Hi', 'is_banner' => true]);
        $service->publish($instructor, $mine);

        $otherToken = $this->token($other);
        $this->withToken($otherToken)
            ->postJson(route('api.v1.teach.announcements.publish', $mine))
            ->assertForbidden();

        $this->withToken($otherToken)
            ->getJson(route('api.v1.announcements.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Auth::forgetGuards();

        $studentToken = $this->token($student);
        $this->withToken($studentToken)
            ->getJson(route('api.v1.announcements.index'))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'For enrolled');

        $this->withToken($studentToken)
            ->postJson(route('api.v1.announcements.dismiss-banner', $mine))
            ->assertOk()
            ->assertJsonPath('data.dismissed', true);
    }

    #[Test]
    public function notification_settings_round_trip_and_inbox_marks_read(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create(['notify_email' => true]);
        $token = $this->token($student);

        app(NotificationService::class)->notify($student, 'test.event', 'Ping', 'Body');
        $notification = Notification::query()
            ->where('user_id', $student->id)
            ->where('channel', \App\Enums\NotificationChannel::InApp)
            ->first();

        $this->withToken($token)
            ->getJson(route('api.v1.notifications.index', ['unread' => 1]))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('data.0.title', 'Ping');

        $this->withToken($token)
            ->getJson(route('api.v1.notifications.show', $notification))
            ->assertOk()
            ->assertJsonPath('data.title', 'Ping');
        $this->assertNotNull($notification->fresh()->read_at);

        $this->withToken($token)
            ->getJson(route('api.v1.notification-settings.show'))
            ->assertOk()
            ->assertJsonPath('data.notify_email', true);

        $updated = $this->withToken($token)
            ->putJson(route('api.v1.notification-settings.update'), [
                'preferences' => [
                    ['event_key' => 'test.event', 'channel' => 'mail', 'enabled' => false],
                    ['event_key' => 'test.event', 'channel' => 'in_app', 'enabled' => true],
                ],
            ])
            ->assertOk()
            ->json('data.preferences');
        $this->assertFalse($updated['test.event']['mail']);
        $this->assertTrue($updated['test.event']['in_app']);

        Auth::forgetGuards();
        $again = $this->withToken($token)
            ->getJson(route('api.v1.notification-settings.show'))
            ->assertOk()
            ->json('data.preferences');
        $this->assertFalse($again['test.event']['mail']);
    }

    #[Test]
    public function instructor_can_draft_and_publish_but_ta_is_denied_publish(): void
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $ta = User::factory()->withRole(RoleType::Ta)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offeringWithRoster($instructor, $student);
        $this->staffOffering($ta, $offering, OfferingStaffRole::Ta);

        $instructorToken = $this->token($instructor);
        $created = $this->withToken($instructorToken)
            ->postJson(route('api.v1.teach.announcements.store', $offering), [
                'title' => 'Draft A',
                'body' => 'Body A',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', AnnouncementStatus::Draft->value)
            ->json('data.id');

        $this->withToken($instructorToken)
            ->putJson(route('api.v1.teach.announcements.update', $created), [
                'title' => 'Draft A2',
                'body' => 'Body A2',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Draft A2')
            ->assertJsonPath('data.revisions', 1);

        $this->withToken($instructorToken)
            ->postJson(route('api.v1.teach.announcements.publish', $created))
            ->assertOk()
            ->assertJsonPath('data.status', AnnouncementStatus::Published->value);

        Auth::forgetGuards();

        $taToken = $this->token($ta);
        $taDraft = $this->withToken($taToken)
            ->postJson(route('api.v1.teach.announcements.store', $offering), [
                'title' => 'TA draft',
                'body' => 'TA body',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withToken($taToken)
            ->postJson(route('api.v1.teach.announcements.publish', $taDraft))
            ->assertForbidden();

        $this->assertSame(
            AnnouncementStatus::Draft,
            Announcement::query()->findOrFail($taDraft)->status
        );
    }
}
