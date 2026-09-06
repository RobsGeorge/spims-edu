<?php

namespace Tests\Feature\Communications;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationLogStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\CommunicationLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Communications\AnnouncementService;
use App\Services\Communications\ChannelDispatcher;
use App\Services\Communications\CommunicationLogWriter;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommunicationLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_dispatch_writes_a_log_row_including_unimplemented_whatsapp(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create();
        $dispatcher = app(ChannelDispatcher::class);

        $dispatcher->dispatch(CommunicationChannel::InApp, $user, 'test.event', 'T', 'B');
        $dispatcher->dispatch(CommunicationChannel::Mail, $user, 'test.event', 'T', 'B');
        $whatsapp = $dispatcher->dispatch(CommunicationChannel::Whatsapp, $user, 'test.event', 'T', 'B');

        $this->assertSame(3, CommunicationLog::query()->count());
        $this->assertSame(CommunicationLogStatus::Skipped, $whatsapp->status);
        $this->assertSame('unimplemented', $whatsapp->error);
    }

    #[Test]
    public function report_filters_and_exports_csv(): void
    {
        $this->seed(ThemeSeeder::class);
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        app(ChannelDispatcher::class)->dispatch(
            CommunicationChannel::InApp,
            $student,
            'announcement.published',
            'Hello',
            'Body',
        );
        app(ChannelDispatcher::class)->dispatch(
            CommunicationChannel::Mail,
            $student,
            'test.event',
            'Other',
            'Body',
        );

        $this->actingAs($admin)
            ->get(route('admin.communications.report', ['type' => 'announcement.published']))
            ->assertOk()
            ->assertSee('Hello')
            ->assertDontSee('Other');

        $csv = $this->actingAs($admin)
            ->get(route('admin.communications.export', ['type' => 'announcement.published']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('announcement.published', $csv);
        $this->assertStringNotContainsString('test.event', $csv);
    }

    #[Test]
    public function open_tracking_pixel_sets_opened_at_once_and_is_idempotent(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create();
        $log = app(ChannelDispatcher::class)->dispatch(
            CommunicationChannel::Mail,
            $user,
            'test.event',
            'Subj',
            'Body',
        );

        $this->assertNull($log->opened_at);

        $this->get(route('communications.open', $log))
            ->assertOk()
            ->assertHeader('content-type', 'image/gif');

        $first = $log->fresh()->opened_at;
        $this->assertNotNull($first);

        $this->get(route('communications.open', $log))->assertOk();
        $this->assertTrue($first->equalTo($log->fresh()->opened_at));
        $this->assertSame(1, CommunicationLog::query()->whereNotNull('opened_at')->count());

        app(CommunicationLogWriter::class)->markOpened($log->fresh());
        $this->assertTrue($first->equalTo($log->fresh()->opened_at));
    }

    #[Test]
    public function publishing_an_announcement_writes_a_log_per_channel(): void
    {
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $course = Course::query()->create([
            'code' => 'LOG1',
            'title' => 'Log',
            'credit_hours' => 2,
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

        $service = app(AnnouncementService::class);
        $draft = $service->draft($instructor, $offering, ['title' => 'Hi', 'body' => 'There']);
        $service->publish($instructor, $draft);

        $this->assertGreaterThanOrEqual(
            2,
            CommunicationLog::query()->where('type', 'announcement.published')->count()
        );
    }
}
