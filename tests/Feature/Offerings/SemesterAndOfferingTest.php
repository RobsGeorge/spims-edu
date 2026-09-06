<?php

namespace Tests\Feature\Offerings;

use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SemesterAndOfferingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function adm_can_create_year_and_semester(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $this->actingAs($adm)->post(route('admin.academic-years.store'), [
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
        ])->assertRedirect();

        $year = AcademicYear::query()->first();
        $this->assertNotNull($year);

        $this->actingAs($adm)->post(route('admin.semesters.store', $year), [
            'name' => 'Fall',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-20',
            'registration_start' => '2026-08-01',
            'registration_end' => '2026-08-31',
            'add_drop_end_week' => 2,
            'last_withdrawal_week' => 8,
            'withdrawal_refund_percent' => 50,
        ])->assertRedirect();

        $semester = Semester::query()->first();
        $this->assertTrue($semester->isRegistrationOpen(now()->setDate(2026, 8, 15)));
        $this->assertFalse($semester->isRegistrationOpen(now()->setDate(2026, 9, 15)));
    }

    #[Test]
    public function aca_can_clone_cohort_offering_and_assign_staff(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $ins = User::factory()->withRole(RoleType::Instructor)->create();

        $this->actingAs($adm)->post(route('admin.academic-years.store'), [
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
        ]);
        $year = AcademicYear::query()->first();
        $this->actingAs($adm)->post(route('admin.semesters.store', $year), [
            'name' => 'Fall',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-20',
            'registration_start' => '2026-08-01',
            'registration_end' => '2026-08-31',
            'add_drop_end_week' => 2,
            'last_withdrawal_week' => 8,
        ]);
        $semester = Semester::query()->first();

        $course = Course::query()->create([
            'code' => 'TH101',
            'title' => 'Theology',
            'credit_hours' => 3,
            'default_price_usd' => 10000,
            'default_price_egp' => 50000,
            'active' => true,
        ]);

        $this->actingAs($aca)->post(route('admin.offerings.store'), [
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort->value,
            'seat_capacity' => 30,
            'clone' => true,
        ])->assertRedirect();

        $offering = CourseOffering::query()->first();
        $this->assertCount(1, $offering->weeks);
        $this->assertSame(1, $offering->weeks->first()->number);

        $this->actingAs($aca)->post(route('admin.offerings.staff', $offering), [
            'user_id' => $ins->id,
            'role' => OfferingStaffRole::Instructor->value,
        ])->assertRedirect();

        $this->assertSame(1, $offering->fresh()->staff()->count());
    }

    #[Test]
    public function cohort_without_semester_is_rejected(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $course = Course::query()->create([
            'code' => 'X1',
            'title' => 'X',
            'credit_hours' => 1,
            'active' => true,
        ]);

        $this->actingAs($aca)->post(route('admin.offerings.store'), [
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort->value,
        ])->assertSessionHasErrors('semester_id');
    }

    #[Test]
    public function admin_can_update_year_semester_and_offering_and_pages_are_reachable(): void
    {
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $this->actingAs($adm)->post(route('admin.academic-years.store'), [
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
        ]);
        $year = AcademicYear::query()->first();
        $this->actingAs($adm)->post(route('admin.semesters.store', $year), [
            'name' => 'Fall',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-20',
            'registration_start' => '2026-08-01',
            'registration_end' => '2026-08-31',
            'add_drop_end_week' => 2,
            'last_withdrawal_week' => 8,
        ]);
        $semester = Semester::query()->first();

        $this->actingAs($adm)->get(route('admin.academic-years.edit', $year))->assertOk();
        $this->actingAs($adm)->put(route('admin.academic-years.update', $year), [
            'name' => '2026/2027 Revised',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
        ])->assertRedirect(route('admin.semesters.index'));
        $this->assertSame('2026/2027 Revised', $year->fresh()->name);

        $this->actingAs($adm)->get(route('admin.semesters.edit', $semester))->assertOk();
        $this->actingAs($adm)->put(route('admin.semesters.update', $semester), [
            'name' => 'Fall Updated',
            'start_date' => '2026-09-01',
            'end_date' => '2026-12-20',
            'registration_start' => '2026-08-01',
            'registration_end' => '2026-09-15',
            'add_drop_end_week' => 2,
            'last_withdrawal_week' => 8,
            'withdrawal_refund_percent' => 50,
            'status' => OfferingStatus::Open->value,
        ])->assertRedirect(route('admin.semesters.index'));
        $this->assertSame('Fall Updated', $semester->fresh()->name);

        $course = Course::query()->create([
            'code' => 'TH200',
            'title' => 'Theology II',
            'credit_hours' => 3,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'mode' => OfferingMode::Cohort,
            'seat_capacity' => 10,
            'status' => OfferingStatus::Draft,
        ]);

        $this->actingAs($aca)->get(route('admin.offerings.show', $offering))
            ->assertOk()
            ->assertSee(__('ui.edit'), false);
        $this->actingAs($aca)->get(route('admin.offerings.edit', $offering))->assertOk();

        $this->actingAs($aca)->put(route('admin.offerings.update', $offering), [
            'semester_id' => $semester->id,
            'seat_capacity' => 25,
            'status' => OfferingStatus::Open->value,
        ])->assertRedirect(route('admin.offerings.show', $offering));

        $offering->refresh();
        $this->assertSame(25, $offering->seat_capacity);
        $this->assertSame(OfferingStatus::Open, $offering->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'offerings.status_change']);
    }

    #[Test]
    public function student_cannot_register_on_a_draft_offering(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $course = Course::query()->create([
            'code' => 'FREE2',
            'title' => 'Standalone draft',
            'credit_hours' => 1,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Draft,
        ]);

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
        ])->assertSessionHasErrors('enrollment');

        $this->actingAs($aca)->put(route('admin.offerings.update', $offering), [
            'status' => OfferingStatus::Open->value,
        ])->assertRedirect();

        $this->actingAs($student)->post(route('enrollments.store'), [
            'offering_id' => $offering->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'offering_id' => $offering->id,
        ]);
    }

    #[Test]
    public function unstaff_removes_instructor_access_to_scoped_action(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $ins = User::factory()->withRole(RoleType::Instructor)->create();
        $course = Course::query()->create([
            'code' => 'STAFF1',
            'title' => 'Staffed',
            'credit_hours' => 1,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Draft,
        ]);

        $this->staffOffering($ins, $offering, OfferingStaffRole::Instructor);

        $this->actingAs($ins)->post(route('admin.offerings.weeks', $offering), [
            'number' => 1,
            'title' => 'Week 1',
        ])->assertRedirect();

        $staff = $offering->fresh()->staff()->first();
        $this->actingAs($aca)->delete(route('admin.offerings.unstaff', [$offering, $staff]))
            ->assertRedirect();

        $this->assertSame(0, $offering->fresh()->staff()->count());

        $this->actingAs($ins)->post(route('admin.offerings.weeks', $offering), [
            'number' => 2,
            'title' => 'Week 2',
        ])->assertForbidden();
    }
}
