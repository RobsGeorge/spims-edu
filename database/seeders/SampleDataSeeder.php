<?php

namespace Database\Seeders;

use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\GradingScheme;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\Semester;
use App\Models\Week;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('spims.seed_sample_data', true)) {
            return;
        }

        $scheme = GradingScheme::query()->where('is_default', true)->first()
            ?? GradingScheme::query()->first();

        $program = Program::query()->updateOrCreate(
            ['code' => 'DEMO-DIP'],
            [
                'name' => 'Diploma in Theology (Sample)',
                'type' => ProgramType::Diploma,
                'passing_threshold' => 60,
                'max_credits_per_semester' => 15,
                'max_courses_per_semester' => 5,
                'max_semesters_to_graduate' => 8,
                'elective_credits_required' => 0,
                'signatory_name' => 'Dean of Studies',
                'signatory_title' => 'Academic Dean',
                'grading_scheme_id' => $scheme?->id,
                'active' => true,
            ]
        );

        $course = Course::query()->updateOrCreate(
            ['code' => 'DEMO101'],
            [
                'title' => 'Introduction to Theology (Sample)',
                'credit_hours' => 3,
                'default_price_usd' => 15000,
                'default_price_egp' => 75000,
                'is_free' => false,
                'is_standalone' => false,
                'passing_threshold' => 60,
                'active' => true,
            ]
        );

        ProgramCourse::query()->updateOrCreate(
            ['program_id' => $program->id, 'course_id' => $course->id],
            [
                'requirement' => RequirementType::Required,
                'year_level' => 1,
            ]
        );

        $year = AcademicYear::query()->updateOrCreate(
            ['name' => '2026/2027'],
            [
                'start_date' => '2026-09-01',
                'end_date' => '2027-06-30',
            ]
        );

        $semester = Semester::query()->updateOrCreate(
            ['academic_year_id' => $year->id, 'name' => 'Fall'],
            [
                'start_date' => '2026-09-01',
                'end_date' => '2026-12-20',
                'registration_start' => now()->subMonth()->startOfDay(),
                'registration_end' => now()->addMonths(2)->endOfDay(),
                'add_drop_end_week' => 2,
                'last_withdrawal_week' => 8,
                'withdrawal_refund_percent' => 50,
                'status' => OfferingStatus::Open,
            ]
        );

        $offering = CourseOffering::query()->updateOrCreate(
            [
                'course_id' => $course->id,
                'semester_id' => $semester->id,
                'mode' => OfferingMode::Cohort,
            ],
            [
                'seat_capacity' => 40,
                'attendance_threshold_percent' => 75,
                'status' => OfferingStatus::Open,
                'start_date' => '2026-09-01',
                'end_date' => '2026-12-20',
            ]
        );

        Week::query()->updateOrCreate(
            ['offering_id' => $offering->id, 'number' => 1],
            [
                'title' => 'Week 1',
                'unlock_date' => $offering->start_date,
                'order' => 1,
            ]
        );
    }
}
