<?php

namespace Tests\Feature\Live;

use App\Enums\AttendanceStatus;
use App\Enums\ComponentKind;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\DiscussionGrade;
use App\Models\Enrollment;
use App\Models\GradebookComponent;
use App\Models\LiveSession;
use App\Models\Notification;
use App\Models\User;
use App\Services\Discussions\DiscussionService;
use App\Services\Gradebook\GradebookService;
use App\Services\Live\AttendanceService;
use App\Services\Live\LiveSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LiveCommsTest extends TestCase
{
    use RefreshDatabase;

    private function offeringWithStudent(User $student, OfferingMode $mode = OfferingMode::Cohort): CourseOffering
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $course = Course::query()->create([
            'code' => 'LIVE1',
            'title' => 'Live Course',
            'credit_hours' => 2,
            'is_standalone' => true,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => $mode,
            'status' => 'OPEN',
            'attendance_threshold_percent' => 60,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        return $offering;
    }

    #[Test]
    public function scheduler_blocks_overlapping_sessions_and_join_works(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offeringWithStudent($student);

        $start = now()->addHour()->seconds(0);
        $session = app(LiveSessionService::class)->schedule($adm, $offering, [
            'title' => 'Lecture 1',
            'scheduled_start' => $start,
            'duration_minutes' => 60,
        ]);

        $this->assertNotNull($session->zoom_meeting_id);
        $this->assertStringContainsString('zoom.test', $session->zoom_join_url);

        try {
            app(LiveSessionService::class)->schedule($adm, $offering, [
                'title' => 'Conflict',
                'scheduled_start' => $start->copy()->addMinutes(30),
                'duration_minutes' => 60,
            ]);
            $this->fail('Expected overlap validation');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('session', $e->errors());
        }

        // Outside join window
        $this->actingAs($student)->post(route('live.join', $session))->assertSessionHasErrors('session');

        $session->update(['scheduled_start' => now()->subMinutes(5)]);
        $response = $this->actingAs($student)->post(route('live.join', $session));
        $response->assertRedirect();
        $this->assertStringContainsString('zoom.test', $response->headers->get('Location'));
    }

    #[Test]
    public function attendance_import_threshold_override_and_gradebook_feed(): void
    {
        $ins = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create(['email' => 'stu@example.com']);
        $offering = $this->offeringWithStudent($student);
        $this->staffOffering($ins, $offering);

        $session = app(LiveSessionService::class)->schedule(
            User::factory()->withRole(RoleType::AdministrativeAdmin)->create(),
            $offering,
            [
                'title' => 'Lab',
                'scheduled_start' => now()->addDay(),
                'duration_minutes' => 100,
            ]
        );

        // 50 minutes of 100 with 60% threshold → absent
        app(AttendanceService::class)->importFromZoom($ins, $session, [
            ['email' => 'stu@example.com', 'minutes' => 50],
        ]);
        $this->assertSame(AttendanceStatus::Absent, AttendanceRecord::query()->first()->status);

        app(AttendanceService::class)->override($ins, $session, $student, AttendanceStatus::Present, 100);
        $this->assertSame(AttendanceStatus::Present, AttendanceRecord::query()->first()->fresh()->status);

        GradebookComponent::query()->create([
            'offering_id' => $offering->id,
            'name' => 'Attendance',
            'weight_percent' => 100,
            'kind' => ComponentKind::Attendance,
        ]);

        $enrollment = Enrollment::query()->where('student_id', $student->id)->first();
        $computed = app(GradebookService::class)->computeEnrollment($enrollment);
        $this->assertEquals(100.0, $computed['percent']);
    }

    #[Test]
    public function reminders_discussions_auto_score_and_notifications(): void
    {
        $ins = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $peer = User::factory()->withRole(RoleType::Student)->create(['email' => 'peer@spims.test']);
        $offering = $this->offeringWithStudent($student);
        $this->staffOffering($ins, $offering);
        Enrollment::query()->create([
            'student_id' => $peer->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $session = LiveSession::query()->create([
            'offering_id' => $offering->id,
            'title' => 'Soon',
            'scheduled_start' => now()->addHours(24),
            'duration_minutes' => 45,
            'zoom_meeting_id' => 'm1',
            'zoom_join_url' => 'https://zoom.test/j/m1',
        ]);

        Artisan::call('live:send-reminders');
        $this->assertNotNull($session->fresh()->reminder_24h_sent_at);
        $this->assertTrue(
            Notification::query()->where('user_id', $student->id)->where('type', 'live.reminder_24h')->exists()
        );

        $board = app(DiscussionService::class)->provisionBoard($ins, $offering);
        $this->assertTrue($board->allow_student_threads);

        $thread = app(DiscussionService::class)->createThread($ins, $board, [
            'title' => 'Graded topic',
            'body' => 'Start here',
            'is_graded' => true,
            'participation_min_words' => 5,
            'participation_min_posts' => 1,
            'participation_min_replies' => 1,
        ]);

        app(DiscussionService::class)->post($student, $thread, 'Short');
        $grade = DiscussionGrade::query()->where('student_id', $student->id)->first();
        $this->assertNotNull($grade);
        $this->assertLessThan(100, $grade->auto_score);

        app(DiscussionService::class)->post(
            $student,
            $thread,
            'This is a longer post with enough words for the rule @peer@spims.test',
            null
        );
        // reply
        $root = $thread->posts()->where('author_id', $ins->id)->first();
        app(DiscussionService::class)->post($student, $thread, 'Thanks for the prompt reply text here', $root->id);

        $grade = DiscussionGrade::query()->where('student_id', $student->id)->first()->fresh();
        $this->assertEquals(100.0, $grade->final_score);

        $this->assertTrue(
            Notification::query()->where('user_id', $peer->id)->where('type', 'discussions.mention')->exists()
        );

        app(DiscussionService::class)->overrideGrade($ins, $thread, $student, 85, 'Nice');
        $this->assertEquals(85.0, DiscussionGrade::query()->where('student_id', $student->id)->value('final_score'));

        // Webhook signature + recording
        $payload = ['object' => ['id' => 'm1', 'share_url' => 'https://zoom.test/rec/1']];
        $body = json_encode(['event' => 'recording.completed', 'payload' => $payload]);
        $ts = (string) time();
        $sig = 'v0='.hash_hmac('sha256', 'v0:'.$ts.':'.$body, 'zoom-test');

        $this->call(
            'POST',
            route('api.webhooks.zoom'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_ZM_SIGNATURE' => $sig,
                'HTTP_X_ZM_REQUEST_TIMESTAMP' => $ts,
            ],
            $body
        )->assertOk();

        $this->assertSame('https://zoom.test/rec/1', $session->fresh()->recording_url);
    }

    #[Test]
    public function academic_admin_can_schedule_a_live_session(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offeringWithStudent($student);
        $start = now()->addDays(2)->seconds(0);

        $this->actingAs($aca)->post(route('admin.live.store', $offering), [
            'title' => 'Dean lecture',
            'scheduled_start' => $start->toDateTimeString(),
            'duration_minutes' => 60,
        ])->assertRedirect();

        $this->assertDatabaseHas('live_sessions', [
            'offering_id' => $offering->id,
            'title' => 'Dean lecture',
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'live.schedule', 'actor_id' => $aca->id]);
    }

    #[Test]
    public function recurrence_creates_weekly_sessions_and_overlap_returns_422(): void
    {
        $adm = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offeringWithStudent($student);

        $this->actingAs($adm)
            ->get(route('admin.live.index', $offering))
            ->assertOk()
            ->assertSee(__('live.schedule_recurrence'), false);

        $this->actingAs($adm)->post(route('admin.live.recurrence', $offering), [
            'days_of_week' => [1, 3],
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-20',
            'title_prefix' => 'Weekly',
        ])->assertRedirect();

        $sessions = LiveSession::query()->where('offering_id', $offering->id)->orderBy('scheduled_start')->get();
        $this->assertCount(4, $sessions);
        $this->assertSame('Weekly 2026-09-07', $sessions[0]->title);
        $this->assertSame('Weekly 2026-09-09', $sessions[1]->title);
        $this->assertSame('Weekly 2026-09-14', $sessions[2]->title);
        $this->assertSame('Weekly 2026-09-16', $sessions[3]->title);

        $this->actingAs($adm)->postJson(route('admin.live.recurrence', $offering), [
            'days_of_week' => [1],
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-14',
            'title_prefix' => 'Conflict',
        ])->assertStatus(422);

        $this->actingAs($adm)->postJson(route('admin.live.store', $offering), [
            'title' => 'Overlap session',
            'scheduled_start' => '2026-09-07 10:30:00',
            'duration_minutes' => 60,
        ])->assertStatus(422);

        $this->assertSame(4, LiveSession::query()->where('offering_id', $offering->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'live.recurrence']);
    }

    #[Test]
    public function join_url_uses_offering_staff_not_instructor_role(): void
    {
        $adm = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $staffed = User::factory()->withRole(RoleType::Instructor)->create();
        $outsider = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offeringWithStudent($student);
        $this->staffOffering($staffed, $offering);

        $session = app(LiveSessionService::class)->schedule($adm, $offering, [
            'title' => 'Host check',
            'scheduled_start' => now()->addHour(),
            'duration_minutes' => 60,
        ]);
        $session->update(['scheduled_start' => now()->subMinutes(5)]);

        $staffedRedirect = $this->actingAs($staffed)->post(route('live.join', $session));
        $staffedRedirect->assertRedirect();
        $this->assertSame($session->zoom_start_url, $staffedRedirect->headers->get('Location'));

        $deanRedirect = $this->actingAs($adm)->post(route('live.join', $session));
        $deanRedirect->assertRedirect();
        $this->assertSame($session->zoom_start_url, $deanRedirect->headers->get('Location'));

        $studentRedirect = $this->actingAs($student)->post(route('live.join', $session));
        $studentRedirect->assertRedirect();
        $this->assertSame($session->zoom_join_url, $studentRedirect->headers->get('Location'));
        $this->assertNotSame($session->zoom_start_url, $studentRedirect->headers->get('Location'));

        $this->actingAs($outsider)
            ->postJson(route('live.join', $session))
            ->assertStatus(422)
            ->assertJsonValidationErrors('session');
    }
}
