<?php

namespace Tests\Feature\Api;

use App\Enums\AssessmentMode;
use App\Enums\AttendanceStatus;
use App\Enums\ClassSessionMode;
use App\Enums\RoleType;
use App\Models\Assessment;
use App\Models\User;
use App\Services\Learning\StudentGradesService;
use App\Services\Live\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Guard against a logic fork: for one fixture student, API grades and attendance
 * match StudentGradesService / AttendanceService. Completion is owned by S4.
 */
class StudentApiParityTest extends TestCase
{
    use RefreshDatabase;
    use StudentApiFixtures;

    #[Test]
    public function api_grades_and_attendance_match_the_web_services(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $bundle = $this->playerBundle('PAR1');
        $student = $bundle['student'];
        $offering = $bundle['offering'];
        $this->assignmentOn($offering, released: true, title: 'Parity essay');
        Assessment::query()->create([
            'offering_id' => $offering->id,
            'title' => 'Parity quiz',
            'mode' => AssessmentMode::Quiz,
            'released' => true,
            'attempts_allowed' => 1,
            'max_points' => 10,
        ]);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);
        $session = app(AttendanceService::class)->openSession($instructor, $offering, [
            'title' => 'Parity lecture',
            'scheduled_start' => now(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ]);
        app(AttendanceService::class)->markRoster($instructor, $session, [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
        ], 0);

        $token = $this->apiToken($student);

        $serviceGrades = app(StudentGradesService::class)->forOffering($student, $offering);
        $this->assertNotNull($serviceGrades);
        $apiGrades = $this->withToken($token)
            ->getJson(route('api.v1.offerings.grades', $offering))
            ->assertOk()
            ->json('data');

        // JSON has no float/int distinction (0.0 encodes as 0).
        $this->assertEquals($serviceGrades['running_percent'], $apiGrades['running_percent']);
        $this->assertSame($serviceGrades['final_letter'], $apiGrades['final_letter']);
        $this->assertEquals($serviceGrades['final_percent'], $apiGrades['final_percent']);
        $this->assertSame($serviceGrades['grade_status'], $apiGrades['grade_status']);
        $this->assertEquals(
            collect($serviceGrades['items'])->map(fn (array $item) => [
                'kind' => $item['kind'],
                'title' => $item['title'],
                'score' => $item['score'],
                'status' => $item['status'],
            ])->values()->all(),
            $apiGrades['items']
        );

        $history = app(AttendanceService::class)->historyForStudent($student);
        $apiAttendance = $this->withToken($token)
            ->getJson(route('api.v1.attendance.mine'))
            ->assertOk()
            ->json('data');

        $this->assertSame(
            $history->map(fn ($entry) => [
                'id' => $entry->id,
                'session_id' => $entry->class_session_id,
                'status' => $entry->status->value,
                'minutes_attended' => $entry->minutes_attended,
                'source' => $entry->source->value,
            ])->values()->all(),
            collect($apiAttendance)->map(fn (array $row) => [
                'id' => $row['id'],
                'session_id' => $row['session_id'],
                'status' => $row['status'],
                'minutes_attended' => $row['minutes_attended'],
                'source' => $row['source'],
            ])->all()
        );

        $percent = app(AttendanceService::class)->percentFor($student, $offering);
        $apiOffering = $this->withToken($token)
            ->getJson(route('api.v1.offerings.attendance.mine', $offering))
            ->assertOk()
            ->json('data');
        $this->assertEquals($percent, $apiOffering['percent']);
    }
}
