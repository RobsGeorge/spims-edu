<?php

namespace Tests\Feature\Events;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\ProgramType;
use App\Enums\RoleType;
use App\Enums\StudentProgramStatus;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Events\EventService;
use Illuminate\Support\Facades\Auth;

trait EventFixtures
{
    protected function admin(): User
    {
        return User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
    }

    protected function academicAdmin(): User
    {
        return User::factory()->withRole(RoleType::AcademicAdmin)->create();
    }

    protected function student(array $attrs = []): User
    {
        return User::factory()->withRole(RoleType::Student)->create($attrs);
    }

    protected function apiToken(User $user, string $role = 'STUDENT'): string
    {
        return $user->createToken('api', ["role:{$role}"])->plainTextToken;
    }

    protected function asApi(User $user, string $role = 'STUDENT')
    {
        Auth::forgetGuards();

        return $this->withToken($this->apiToken($user, $role));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function draftEvent(User $admin, array $overrides = []): Event
    {
        return app(EventService::class)->create($admin, array_merge([
            'title' => 'Retreat',
            'description' => 'School retreat',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'venue' => 'Chapel',
            'capacity' => null,
            'waitlist_enabled' => false,
            'eligibility' => ['programs' => [], 'offerings' => [], 'roles' => []],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function publishedEvent(User $admin, array $overrides = []): Event
    {
        $event = $this->draftEvent($admin, $overrides);

        return app(EventService::class)->publish($admin, $event);
    }

    protected function offering(string $code): CourseOffering
    {
        $course = Course::query()->create([
            'code' => $code,
            'title' => "Course $code",
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        return CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
    }

    protected function enroll(User $student, CourseOffering $offering): Enrollment
    {
        return Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);
    }

    protected function program(string $code = 'THEO'): Program
    {
        $scheme = GradingScheme::query()->first() ?? GradingScheme::query()->create([
            'name' => 'Default',
            'is_default' => true,
        ]);

        return Program::query()->create([
            'code' => $code,
            'name' => $code,
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 18,
            'max_courses_per_semester' => 6,
            'max_semesters_to_graduate' => 8,
            'grading_scheme_id' => $scheme->id,
            'active' => true,
        ]);
    }

    protected function activeProgram(User $student, Program $program): StudentProgram
    {
        return StudentProgram::query()->create([
            'student_id' => $student->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);
    }
}
