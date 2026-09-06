<?php

namespace Tests\Feature\Attendance;

require_once __DIR__.'/AttendanceFixtures.php';

use App\Enums\RoleType;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Attendance\RosterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RosterTest extends TestCase
{
    use AttendanceFixtures;
    use RefreshDatabase;

    #[Test]
    public function roster_csv_birthdays_and_announcement_work(): void
    {
        $offering = $this->offering('ROS1');
        $instructor = $this->instructorOn($offering);
        $soon = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Soon',
            'date_of_birth' => now()->addDays(3)->subYears(18)->toDateString(),
        ]);
        $later = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Later',
            'date_of_birth' => now()->addDays(40)->subYears(20)->toDateString(),
        ]);
        $this->enroll($soon, $offering);
        $this->enroll($later, $offering);

        $service = app(RosterService::class);
        $roster = $service->roster($instructor, $offering);
        $this->assertCount(2, $roster);

        $csv = $service->exportCsv($instructor, $offering);
        $this->assertStringContainsString('student_id,first_name,last_name,email,status,date_of_birth,enrolled_at', $csv);
        $this->assertStringContainsString('Soon', $csv);
        $this->assertSame(3, substr_count($csv, "\n"));

        $birthdays = $service->birthdays($instructor, $offering, 14);
        $this->assertCount(1, $birthdays);
        $this->assertSame($soon->id, $birthdays->first()['student']->id);

        $announcement = $service->announce($instructor, $offering, [
            'title' => 'Welcome',
            'body' => 'See you Monday',
        ]);
        $this->assertSame('Welcome', $announcement->title);
        $this->assertTrue(Announcement::query()->where('offering_id', $offering->id)->exists());
    }
}
