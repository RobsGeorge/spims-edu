<?php

namespace Tests\Feature\Offerings;

use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Models\Week;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentTeachAddWeekTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function instructor_adds_week_from_teach_content_and_sees_title(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering, $instructor] = $this->staffedOffering();

        $this->actingAs($instructor)
            ->get(route('teach.show', ['offering' => $offering, 'tab' => 'content']))
            ->assertOk()
            ->assertSee(__('offerings.add_week'))
            ->assertSee(route('admin.offerings.weeks', $offering), false);

        $this->actingAs($instructor)
            ->from(route('teach.show', ['offering' => $offering, 'tab' => 'content']))
            ->followingRedirects()
            ->post(route('admin.offerings.weeks', $offering), [
                'number' => 1,
                'title' => 'Opening unit',
            ])
            ->assertOk()
            ->assertSee(__('offerings.week_added'))
            ->assertSee('Opening unit')
            ->assertSee(__('teach.week_n', ['n' => 1]));

        $this->assertTrue(
            Week::query()->where('offering_id', $offering->id)->where('title', 'Opening unit')->exists()
        );
    }

    #[Test]
    public function ta_on_own_offering_can_add_a_week(): void
    {
        $this->seed(ThemeSeeder::class);
        [$offering] = $this->staffedOffering();
        $ta = User::factory()->withRole(RoleType::Ta)->create();
        $this->staffOffering($ta, $offering, OfferingStaffRole::Ta);

        $this->actingAs($ta)
            ->from(route('teach.show', ['offering' => $offering, 'tab' => 'content']))
            ->followingRedirects()
            ->post(route('admin.offerings.weeks', $offering), [
                'number' => 2,
                'title' => 'TA week',
            ])
            ->assertOk()
            ->assertSee('TA week');

        $this->assertTrue(
            Week::query()->where('offering_id', $offering->id)->where('title', 'TA week')->exists()
        );
    }

    #[Test]
    public function outsider_instructor_cannot_add_a_week(): void
    {
        [$offering] = $this->staffedOffering();
        $outsider = User::factory()->withRole(RoleType::Instructor)->create();

        $this->actingAs($outsider)
            ->post(route('admin.offerings.weeks', $offering), [
                'number' => 1,
                'title' => 'Nope',
            ])
            ->assertForbidden();

        $this->assertSame(0, Week::query()->where('offering_id', $offering->id)->count());
    }

    #[Test]
    public function student_cannot_add_a_week(): void
    {
        [$offering] = $this->staffedOffering();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)
            ->post(route('admin.offerings.weeks', $offering), [
                'number' => 1,
                'title' => 'Student week',
            ])
            ->assertForbidden();

        $this->assertSame(0, Week::query()->where('offering_id', $offering->id)->count());
    }

    /**
     * @return array{0: CourseOffering, 1: User}
     */
    private function staffedOffering(): array
    {
        $course = Course::query()->create([
            'code' => 'TAW1',
            'title' => 'Teach add week',
            'credit_hours' => 2,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Open,
        ]);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($instructor, $offering);

        return [$offering, $instructor];
    }
}
