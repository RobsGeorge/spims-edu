<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Application;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Program;
use App\Models\StudentProgram;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wipe all demo data and re-seed it from scratch.
 *
 * Idempotent: running twice produces the same state.
 * Scope: only touches users/programs/courses/years that match the demo identifiers.
 * Non-demo records (emails outside @spims.test, other program codes) are never touched.
 */
class DemoResetCommand extends Command
{
    protected $signature = 'spims:demo-reset';

    protected $description = 'Wipe and re-seed all demo data. Safe to run multiple times.';

    public function handle(): int
    {
        $this->info('Identifying demo data...');

        $demoUserIds = User::query()
            ->where('email', 'like', '%' . DemoDataSeeder::DEMO_EMAIL_SUFFIX)
            ->pluck('id');

        $demoCourseIds = Course::query()
            ->whereIn('code', DemoDataSeeder::DEMO_COURSE_CODES)
            ->pluck('id');

        $demoOfferingIds = CourseOffering::query()
            ->whereIn('course_id', $demoCourseIds)
            ->pluck('id');

        $demoProgramIds = Program::query()
            ->whereIn('code', DemoDataSeeder::DEMO_PROGRAM_CODES)
            ->pluck('id');

        $demoYearIds = AcademicYear::query()
            ->whereIn('name', DemoDataSeeder::DEMO_YEAR_NAMES)
            ->pluck('id');

        $this->info(sprintf(
            'Found %d users, %d courses, %d offerings, %d programs, %d academic years.',
            $demoUserIds->count(),
            $demoCourseIds->count(),
            $demoOfferingIds->count(),
            $demoProgramIds->count(),
            $demoYearIds->count(),
        ));

        DB::transaction(function () use ($demoUserIds, $demoCourseIds, $demoOfferingIds, $demoProgramIds, $demoYearIds): void {
            $this->wipe($demoUserIds, $demoCourseIds, $demoOfferingIds, $demoProgramIds, $demoYearIds);
        });

        $this->info('Demo data wiped. Re-seeding...');

        config(['spims.seed_demo_data' => true]);
        $this->call(DemoDataSeeder::class);

        $this->info('spims:demo-reset complete.');

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int,string>  $demoUserIds
     * @param  \Illuminate\Support\Collection<int,string>  $demoCourseIds
     * @param  \Illuminate\Support\Collection<int,string>  $demoOfferingIds
     * @param  \Illuminate\Support\Collection<int,string>  $demoProgramIds
     * @param  \Illuminate\Support\Collection<int,string>  $demoYearIds
     */
    private function wipe(
        $demoUserIds,
        $demoCourseIds,
        $demoOfferingIds,
        $demoProgramIds,
        $demoYearIds,
    ): void {
        // ── Phase 1: Finance records (constrained on student_id — no cascade) ──────
        // Refunds reference student_id (no cascade) and payment_id / enrollment_id (nullOnDelete).
        DB::table('refunds')->whereIn('student_id', $demoUserIds)->delete();

        // Payments reference student_id (no cascade) and invoice_id (nullOnDelete).
        DB::table('payments')->whereIn('student_id', $demoUserIds)->delete();

        // Invoices reference student_id (no cascade).
        // Cascade: invoice_lines, payment_plans → payment_plan_installments.
        DB::table('invoices')->whereIn('student_id', $demoUserIds)->delete();

        // ── Phase 2: Enrollments (constrained on both student_id and offering_id) ──
        // Cascade from enrollment: enrollment_week_completions, enrollment_learning_completions.
        DB::table('enrollments')->whereIn('student_id', $demoUserIds)->delete();

        // ── Phase 3: User-linked records with no cascade ──────────────────────────
        // application_field_values cascade from applications.
        $demoApplicationIds = Application::query()
            ->whereIn('applicant_id', $demoUserIds)
            ->pluck('id');
        DB::table('application_field_values')->whereIn('application_id', $demoApplicationIds)->delete();
        DB::table('applications')->whereIn('applicant_id', $demoUserIds)->delete();

        // student_programs reference student_id (no cascade).
        // Cascade from student_programs: program_requirement_fulfillments.
        DB::table('student_programs')->whereIn('student_id', $demoUserIds)->delete();

        // academic_records reference student_id (no cascade).
        DB::table('academic_records')->whereIn('student_id', $demoUserIds)->delete();

        // Credentials reference student_id (no cascade).
        DB::table('credentials')->whereIn('student_id', $demoUserIds)->delete();

        // ── Phase 4: Course offerings (many cascade children) ─────────────────────
        // Cascade: weeks → content_items, offering_staff, live_sessions → attendance entries,
        //   assessments → assessment_questions / assessment_items / attempts → responses,
        //   gradebook_components, feedback_surveys → questions / submissions,
        //   project_assessments → projects → memberships / deliverables / submissions,
        //   live_quizzes → questions / sessions → participants → answers,
        //   discussion_boards → threads → posts, announcements.
        if ($demoOfferingIds->isNotEmpty()) {
            DB::table('course_offerings')->whereIn('id', $demoOfferingIds)->delete();
        }

        // ── Phase 5: Events ───────────────────────────────────────────────────────
        // Cascade: event_reservations, event_waitlist, event_log.
        DB::table('events')->where('title', 'Theology Orientation Day')->delete();

        // ── Phase 6: Semesters and academic years ─────────────────────────────────
        if ($demoYearIds->isNotEmpty()) {
            DB::table('semesters')->whereIn('academic_year_id', $demoYearIds)->delete();
            DB::table('academic_years')->whereIn('id', $demoYearIds)->delete();
        }

        // ── Phase 7: Programs ─────────────────────────────────────────────────────
        // Cascade: program_courses, application_forms → application_form_fields.
        if ($demoProgramIds->isNotEmpty()) {
            DB::table('programs')->whereIn('id', $demoProgramIds)->delete();
        }

        // ── Phase 8: Courses ──────────────────────────────────────────────────────
        // course_prerequisites.prerequisite_id is constrained without cascade.
        if ($demoCourseIds->isNotEmpty()) {
            DB::table('course_prerequisites')->whereIn('prerequisite_id', $demoCourseIds)->delete();
            // Cascade from courses: course_prerequisites (where course_id), question_banks → questions.
            DB::table('courses')->whereIn('id', $demoCourseIds)->delete();
        }

        // ── Phase 9: Demo users ───────────────────────────────────────────────────
        // Cascade: wallet_accounts → wallet_transactions, user_roles, OTP codes,
        //   advising assignments, notification preferences, personal access tokens.
        // Explicit wallet wipe first: deleting many users in one statement can leave
        // wallet_transactions orphaned under PostgreSQL when created_by_id also points
        // at demo users in the same DELETE set (FK violation on wallet_id).
        if ($demoUserIds->isNotEmpty()) {
            $walletIds = DB::table('wallet_accounts')
                ->whereIn('user_id', $demoUserIds)
                ->pluck('id');
            if ($walletIds->isNotEmpty()) {
                DB::table('wallet_transactions')->whereIn('wallet_id', $walletIds)->delete();
                DB::table('wallet_accounts')->whereIn('id', $walletIds)->delete();
            }
            DB::table('notifications')->whereIn('user_id', $demoUserIds)->delete();
            DB::table('users')->whereIn('id', $demoUserIds)->delete();
        }
    }
}