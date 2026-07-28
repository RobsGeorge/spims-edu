<?php

namespace Tests\Feature\Academics;

use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseInterestFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoursePrerequisiteAndInterestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function academic_admin_can_create_course_with_prerequisite(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $prereq = Course::query()->create([
            'code' => 'BASE101',
            'title' => 'Basics',
            'credit_hours' => 2,
        ]);

        $this->actingAs($aca)->post(route('admin.courses.store'), [
            'code' => 'adv201',
            'title' => 'Advanced',
            'credit_hours' => 3,
            'default_price_usd' => 5000,
            'default_price_egp' => 25000,
            'prerequisite_id' => $prereq->id,
            'is_standalone' => true,
        ])->assertRedirect(route('admin.courses.index'));

        $course = Course::query()->where('code', 'ADV201')->first();
        $this->assertTrue($course->prerequisites->contains('id', $prereq->id));
        $this->assertSame(5000, $course->default_price_usd);
    }

    #[Test]
    public function course_cannot_be_its_own_prerequisite(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $course = Course::query()->create([
            'code' => 'SELF1',
            'title' => 'Self',
            'credit_hours' => 1,
        ]);

        $this->actingAs($aca)->post(route('admin.courses.prerequisites', $course), [
            'prerequisite_id' => $course->id,
        ])->assertSessionHasErrors('prerequisite_id');
    }

    #[Test]
    public function student_can_flag_interest_and_admin_sees_count(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $course = Course::query()->create([
            'code' => 'HIST100',
            'title' => 'Church History',
            'credit_hours' => 3,
            'active' => true,
        ]);

        $this->actingAs($student)->post(route('catalog.interest', $course))->assertRedirect();
        $this->assertSame(1, CourseInterestFlag::query()->count());

        // Idempotent second flag
        $this->actingAs($student)->post(route('catalog.interest', $course))->assertRedirect();
        $this->assertSame(1, CourseInterestFlag::query()->count());

        $this->actingAs($aca)->get(route('admin.courses.index'))
            ->assertOk()
            ->assertSee('HIST100')
            ->assertSee('1');
    }
}
