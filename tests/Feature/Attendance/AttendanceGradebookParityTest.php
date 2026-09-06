<?php

namespace Tests\Feature\Attendance;

require_once __DIR__.'/AttendanceFixtures.php';

use App\Enums\AttendanceStatus;
use App\Enums\ComponentKind;
use App\Enums\RoleType;
use App\Models\AttendanceRecord;
use App\Models\GradebookComponent;
use App\Models\LiveSession;
use App\Models\User;
use App\Services\Gradebook\GradebookService;
use App\Services\Live\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceGradebookParityTest extends TestCase
{
    use AttendanceFixtures;
    use RefreshDatabase;

    #[Test]
    public function gradebook_attendance_matches_the_legacy_zoom_only_formula(): void
    {
        $offering = $this->offering('PAR1');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create(['email' => 'zoom-parity@example.com']);
        $this->enroll($student, $offering);

        $live = LiveSession::query()->create([
            'offering_id' => $offering->id,
            'title' => 'Zoom only',
            'scheduled_start' => now()->addDay(),
            'duration_minutes' => 100,
        ]);

        AttendanceRecord::query()->create([
            'live_session_id' => $live->id,
            'student_id' => $student->id,
            'status' => AttendanceStatus::Present,
            'minutes_attended' => 100,
            'source' => \App\Enums\AttendanceSource::ZoomImport,
        ]);

        GradebookComponent::query()->create([
            'offering_id' => $offering->id,
            'name' => 'Attendance',
            'weight_percent' => 100,
            'kind' => ComponentKind::Attendance,
        ]);

        $legacy = 100.0;
        $this->assertSame($legacy, app(AttendanceService::class)->offeringPercent($offering, $student));
        $this->assertSame($legacy, app(AttendanceService::class)->percentFor($student, $offering));

        $enrollment = \App\Models\Enrollment::query()->where('student_id', $student->id)->first();
        $computed = app(GradebookService::class)->computeEnrollment($enrollment);
        $this->assertEquals($legacy, $computed['percent']);
    }

    #[Test]
    public function zoom_import_dual_write_still_feeds_the_same_gradebook_value(): void
    {
        $offering = $this->offering('PAR2');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create(['email' => 'dual@example.com']);
        $this->enroll($student, $offering);

        $live = LiveSession::query()->create([
            'offering_id' => $offering->id,
            'title' => 'Lab',
            'scheduled_start' => now()->addDay(),
            'duration_minutes' => 100,
        ]);

        $service = app(AttendanceService::class);
        $service->importFromZoom($instructor, $live, [
            ['email' => 'dual@example.com', 'minutes' => 100],
        ]);

        $this->assertDatabaseHas('attendance_records', [
            'live_session_id' => $live->id,
            'student_id' => $student->id,
        ]);
        $this->assertDatabaseHas('attendance_entries', [
            'student_id' => $student->id,
        ]);

        GradebookComponent::query()->create([
            'offering_id' => $offering->id,
            'name' => 'Attendance',
            'weight_percent' => 100,
            'kind' => ComponentKind::Attendance,
        ]);

        $enrollment = \App\Models\Enrollment::query()->where('student_id', $student->id)->first();
        $this->assertEquals(100.0, app(GradebookService::class)->computeEnrollment($enrollment)['percent']);
    }
}
