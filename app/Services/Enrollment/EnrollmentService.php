<?php

namespace App\Services\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeType;
use App\Enums\InvoiceStatus;
use App\Enums\OfferingMode;
use App\Enums\RequirementType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\ProgramCourse;
use App\Models\Setting;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly InvoiceService $invoices,
        private readonly PaymentService $payments,
    ) {}

    public function register(User $student, CourseOffering $offering, ?string $studentProgramId = null, bool $adminOverride = false, ?User $actor = null): Enrollment
    {
        $actor = $actor ?? $student;

        if ($adminOverride) {
            $this->authorize->authorize($actor, 'enrollment.override');
        } else {
            $this->authorize->authorize($student, 'enrollment.register');
            $this->assertCanRegister($student, $offering, $studentProgramId);
        }

        return DB::transaction(function () use ($student, $offering, $studentProgramId, $adminOverride, $actor) {
            $enrolledCount = Enrollment::query()
                ->where('offering_id', $offering->id)
                ->where('status', EnrollmentStatus::Enrolled)
                ->count();

            $status = EnrollmentStatus::Enrolled;
            if ($offering->seat_capacity !== null && $enrolledCount >= $offering->seat_capacity) {
                $status = EnrollmentStatus::Waitlisted;
            }

            $enrollment = Enrollment::query()->updateOrCreate(
                [
                    'student_id' => $student->id,
                    'offering_id' => $offering->id,
                ],
                [
                    'student_program_id' => $studentProgramId,
                    'status' => $status,
                    'enrolled_at' => now(),
                    'dropped_at' => null,
                    'grade_type' => GradeType::InProgress,
                ]
            );

            $this->audit->write($actor, $adminOverride ? 'enrollment.override_register' : 'enrollment.register', 'Enrollment', $enrollment->id, null, [
                'status' => $status->value,
                'student_id' => $student->id,
            ]);

            if ($status === EnrollmentStatus::Enrolled) {
                $this->invoices->createForEnrollment($actor, $enrollment->fresh(['offering.course', 'student']));
            }

            return $enrollment->fresh();
        });
    }

    public function assertCanRegister(User $student, CourseOffering $offering, ?string $studentProgramId = null): void
    {
        $offering->load(['course.prerequisites', 'semester', 'course']);

        if ($this->hasFinancialHold($student)) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.financial_hold')]]);
        }

        if ($offering->mode === OfferingMode::Cohort) {
            if ($offering->semester === null || ! $offering->semester->isRegistrationOpen()) {
                throw ValidationException::withMessages(['enrollment' => [__('enrollment.window_closed')]]);
            }
        }

        $passedIds = AcademicRecord::query()
            ->where('student_id', $student->id)
            ->where('is_passing', true)
            ->pluck('course_id')
            ->all();

        foreach ($offering->course->prerequisites as $prereq) {
            if (! in_array($prereq->id, $passedIds, true)) {
                throw ValidationException::withMessages([
                    'enrollment' => [__('enrollment.prerequisite_missing', ['code' => $prereq->code])],
                ]);
            }
        }

        if ($offering->course->is_standalone) {
            return;
        }

        if ($studentProgramId === null) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.program_required')]]);
        }

        $studentProgram = StudentProgram::query()
            ->where('id', $studentProgramId)
            ->where('student_id', $student->id)
            ->where('status', StudentProgramStatus::Active)
            ->first();

        if ($studentProgram === null) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.not_matriculated')]]);
        }

        $programCourse = ProgramCourse::query()
            ->where('program_id', $studentProgram->program_id)
            ->where('course_id', $offering->course_id)
            ->first();

        if ($programCourse === null) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.not_in_program')]]);
        }

        $program = $studentProgram->program()->first();
        $active = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('student_program_id', $studentProgram->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->with('offering.course')
            ->get();

        $credits = $active->sum(fn (Enrollment $e) => $e->offering->course->credit_hours);
        if ($credits + $offering->course->credit_hours > $program->max_credits_per_semester) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.max_credits')]]);
        }

        if ($active->count() >= $program->max_courses_per_semester) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.max_courses')]]);
        }
    }

    public function drop(User $actor, Enrollment $enrollment, bool $adminOverride = false): Enrollment
    {
        if (! $adminOverride && $enrollment->student_id !== $actor->id) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.not_owner')]]);
        }

        if ($adminOverride) {
            $this->authorize->authorize($actor, 'enrollment.override');
        }

        $enrollment->load('offering.semester');
        $offering = $enrollment->offering;

        $inAddDrop = true;
        if ($offering->mode === OfferingMode::Cohort && $offering->semester) {
            $weekNumber = $this->currentSemesterWeek($offering);
            $inAddDrop = $weekNumber <= $offering->semester->add_drop_end_week;
        }

        if (! $adminOverride && ! $inAddDrop) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.add_drop_closed')]]);
        }

        return DB::transaction(function () use ($actor, $enrollment, $offering) {
            $enrollment->update([
                'status' => EnrollmentStatus::Dropped,
                'dropped_at' => now(),
            ]);

            $this->payments->refundEnrollment($actor, $enrollment, 100, 'drop');
            $this->promoteWaitlist($offering);
            $this->audit->write($actor, 'enrollment.drop', 'Enrollment', $enrollment->id);

            return $enrollment->fresh();
        });
    }

    public function withdraw(User $actor, Enrollment $enrollment): Enrollment
    {
        if ($enrollment->student_id !== $actor->id) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.not_owner')]]);
        }

        $enrollment->load('offering.semester');
        $offering = $enrollment->offering;

        $refundPercent = 0;
        if ($offering->mode === OfferingMode::Cohort && $offering->semester) {
            $weekNumber = $this->currentSemesterWeek($offering);
            if ($weekNumber > $offering->semester->last_withdrawal_week) {
                throw ValidationException::withMessages(['enrollment' => [__('enrollment.withdrawal_closed')]]);
            }
            $refundPercent = (int) $offering->semester->withdrawal_refund_percent;
        }

        return DB::transaction(function () use ($actor, $enrollment, $offering, $refundPercent) {
            $enrollment->update([
                'status' => EnrollmentStatus::Withdrawn,
                'dropped_at' => now(),
                'grade_type' => GradeType::Withdrawal,
                'final_letter' => 'W',
            ]);

            $this->payments->refundEnrollment($actor, $enrollment, $refundPercent, 'withdraw');
            $this->promoteWaitlist($offering);
            $this->audit->write($actor, 'enrollment.withdraw', 'Enrollment', $enrollment->id);

            return $enrollment->fresh();
        });
    }

    public function promoteWaitlist(CourseOffering $offering): void
    {
        if ($offering->seat_capacity === null) {
            return;
        }

        $enrolled = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->count();

        if ($enrolled >= $offering->seat_capacity) {
            return;
        }

        $next = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->where('status', EnrollmentStatus::Waitlisted)
            ->orderBy('enrolled_at')
            ->first();

        if ($next) {
            $next->update(['status' => EnrollmentStatus::Enrolled]);
            $student = User::query()->findOrFail($next->student_id);
            $this->invoices->createForEnrollment(
                $student,
                $next->fresh(['offering.course', 'student'])
            );
            $this->audit->write(null, 'enrollment.waitlist_promote', 'Enrollment', $next->id);
        }
    }

    public function hasFinancialHold(User $student): bool
    {
        $setting = Setting::query()->find('enrollment.financial_holds');
        $holds = $setting?->value['user_ids'] ?? [];

        if (in_array($student->id, $holds, true)) {
            return true;
        }

        return Invoice::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Partial])
            ->exists();
    }

    public function setFinancialHold(User $actor, User $student, bool $held): void
    {
        $this->authorize->authorize($actor, 'enrollment.override');

        $setting = Setting::query()->firstOrNew(['key' => 'enrollment.financial_holds']);
        $value = $setting->value ?? ['user_ids' => []];
        $ids = $value['user_ids'] ?? [];

        if ($held && ! in_array($student->id, $ids, true)) {
            $ids[] = $student->id;
        }
        if (! $held) {
            $ids = array_values(array_filter($ids, fn ($id) => $id !== $student->id));
        }

        $setting->value = ['user_ids' => $ids];
        $setting->updated_by_id = $actor->id;
        $setting->save();

        $this->audit->write($actor, 'enrollment.financial_hold', 'User', $student->id, null, ['held' => $held]);
    }

    private function currentSemesterWeek(CourseOffering $offering): int
    {
        $start = $offering->semester?->start_date ?? $offering->start_date ?? now();
        $days = max(0, $start->diffInDays(now()));

        return (int) floor($days / 7) + 1;
    }
}
