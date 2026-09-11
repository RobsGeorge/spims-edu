<?php

namespace App\Services\Enrollment;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeType;
use App\Enums\InvoiceStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicRecord;
use App\Models\AdvisingHold;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\LiveSession;
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

    public function register(User $student, CourseOffering $offering, ?string $studentProgramId = null, bool $adminOverride = false, ?User $actor = null, bool $isAudit = false): Enrollment
    {
        $actor = $actor ?? $student;

        if ($adminOverride) {
            $this->authorize->authorize($actor, 'enrollment.override');
        } else {
            $this->authorize->authorize($student, 'enrollment.register');
        }

        return DB::transaction(function () use ($student, $offering, $studentProgramId, $adminOverride, $actor, $isAudit) {
            if (! $adminOverride) {
                $this->assertCanRegister($student, $offering, $studentProgramId, $actor);
            }

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
                    'is_audit' => $isAudit,
                    'grade_type' => $isAudit ? GradeType::Audit : GradeType::InProgress,
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

    public function assertCanRegister(User $student, CourseOffering $offering, ?string $studentProgramId = null, ?User $actor = null): void
    {
        $offering->load(['course.prerequisites', 'semester', 'course']);

        if ($offering->status !== OfferingStatus::Open) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.offering_not_open')]]);
        }

        if ($this->hasFinancialHold($student)) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.financial_hold')]]);
        }

        if ($this->hasAdvisingHold($student)) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.advising_hold')]]);
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

        // 6d: Block re-enrollment of a passed course. Admin override bypasses this via the
        // caller skipping assertCanRegister entirely.
        if (in_array($offering->course_id, $passedIds, true)) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.already_passed')]]);
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
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Waitlisted])
            ->with(['offering.course', 'offering.semester'])
            ->get()
            ->filter(fn (Enrollment $enrollment) => $this->countsTowardTermCap($enrollment, $offering));

        $credits = $active->sum(fn (Enrollment $e) => $e->offering->course->credit_hours);
        if ($credits + $offering->course->credit_hours > $program->max_credits_per_semester) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.max_credits')]]);
        }

        if ($active->count() >= $program->max_courses_per_semester) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.max_courses')]]);
        }

        // 6a: Hard block when enrolling would exceed max_semesters_to_graduate.
        // Only semester-based (Cohort) offerings consume a semester slot.
        if ($offering->semester_id !== null) {
            $usedSemesterIds = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('student_program_id', $studentProgram->id)
                ->join('course_offerings', 'enrollments.offering_id', '=', 'course_offerings.id')
                ->whereNotNull('course_offerings.semester_id')
                ->distinct()
                ->pluck('course_offerings.semester_id')
                ->all();

            $projectedCount = count($usedSemesterIds);
            if (! in_array($offering->semester_id, $usedSemesterIds, true)) {
                $projectedCount++;
            }

            if ($projectedCount > $program->max_semesters_to_graduate) {
                throw ValidationException::withMessages(['enrollment' => [__('enrollment.max_semesters')]]);
            }
        }

        // 6c: year_level sequencing.
        // year_level is a curriculum PLAN, not a dependency. Real dependencies are course
        // prerequisites (enforced above). Sequence enforcement is a program-level flag.
        $targetYearLevel = $programCourse->year_level;
        if ($targetYearLevel !== null && $targetYearLevel > 1) {
            $lowerYearCourses = ProgramCourse::query()
                ->where('program_id', $studentProgram->program_id)
                ->whereNotNull('year_level')
                ->where('year_level', '<', $targetYearLevel)
                ->get(['course_id', 'year_level']);

            $incompleteYearLevels = $lowerYearCourses
                ->filter(fn (ProgramCourse $pc) => ! in_array($pc->course_id, $passedIds, true))
                ->pluck('year_level')
                ->all();

            if (! empty($incompleteYearLevels)) {
                $highestIncomplete = max($incompleteYearLevels);
                $auditActor = $actor ?? $student;

                $this->audit->write(
                    $auditActor,
                    'enrollment.year_level_sequence_warning',
                    null,
                    null,
                    null,
                    [
                        'student_id' => $student->id,
                        'program_id' => $studentProgram->program_id,
                        'course_id' => $offering->course_id,
                        'attempted_year_level' => $targetYearLevel,
                        'highest_incomplete_year_level' => $highestIncomplete,
                    ]
                );

                if ($program->enforce_year_sequence) {
                    throw ValidationException::withMessages(['enrollment' => [__('enrollment.sequence_blocked')]]);
                }
            }
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

        return DB::transaction(function () use ($actor, $enrollment, $offering, $adminOverride) {
            $enrollment->update([
                'status' => EnrollmentStatus::Dropped,
                'dropped_at' => now(),
            ]);

            $this->payments->refundEnrollment($actor, $enrollment, 100, 'drop');
            $this->promoteWaitlist($offering);
            $this->audit->write($actor, $adminOverride ? 'enrollment.override_drop' : 'enrollment.drop', 'Enrollment', $enrollment->id);

            return $enrollment->fresh();
        });
    }

    public function withdraw(User $actor, Enrollment $enrollment, bool $adminOverride = false): Enrollment
    {
        if (! $adminOverride && $enrollment->student_id !== $actor->id) {
            throw ValidationException::withMessages(['enrollment' => [__('enrollment.not_owner')]]);
        }

        if ($adminOverride) {
            $this->authorize->authorize($actor, 'enrollment.override');
        }

        $enrollment->load('offering.semester');
        $offering = $enrollment->offering;

        $refundPercent = 0;
        if (! $adminOverride && $offering->mode === OfferingMode::Cohort && $offering->semester) {
            $weekNumber = $this->currentSemesterWeek($offering);
            if ($weekNumber > $offering->semester->last_withdrawal_week) {
                throw ValidationException::withMessages(['enrollment' => [__('enrollment.withdrawal_closed')]]);
            }
            $refundPercent = (int) $offering->semester->withdrawal_refund_percent;
        }
        // Admin override past the withdrawal window: refundPercent stays 0 (no refund issued).

        return DB::transaction(function () use ($actor, $enrollment, $offering, $refundPercent, $adminOverride) {
            $enrollment->update([
                'status' => EnrollmentStatus::Withdrawn,
                'dropped_at' => now(),
                'grade_type' => GradeType::Withdrawal,
                'final_letter' => 'W',
            ]);

            $this->payments->refundEnrollment($actor, $enrollment, $refundPercent, 'withdraw');
            $this->promoteWaitlist($offering);
            $this->audit->write($actor, $adminOverride ? 'enrollment.override_withdraw' : 'enrollment.withdraw', 'Enrollment', $enrollment->id);

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
        if ($this->hasManualFinancialHold($student)) {
            return true;
        }

        return Invoice::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::Partial])
            ->exists();
    }

    public function hasManualFinancialHold(User $student): bool
    {
        $setting = Setting::query()->find('enrollment.financial_holds');
        $holds = $setting?->value['user_ids'] ?? [];

        return in_array($student->id, $holds, true);
    }

    public function hasAdvisingHold(User $student): bool
    {
        return AdvisingHold::query()
            ->where('student_id', $student->id)
            ->active()
            ->exists();
    }

    /**
     * API-facing conflict check: closed window, financial/advising hold, or overlapping live sessions.
     *
     * @return 'hold'|'window'|'schedule'|null
     */
    public function registrationConflict(User $student, CourseOffering $offering): ?string
    {
        if ($this->hasFinancialHold($student) || $this->hasAdvisingHold($student)) {
            return 'hold';
        }

        $offering->loadMissing('semester');

        if ($offering->mode === OfferingMode::Cohort) {
            if ($offering->semester === null || ! $offering->semester->isRegistrationOpen()) {
                return 'window';
            }
        }

        if ($this->hasLiveSessionConflict($student, $offering)) {
            return 'schedule';
        }

        return null;
    }

    /**
     * Non-blocking warning helper: true when the target offering's live sessions
     * overlap any live session on offerings the student is already enrolled in.
     */
    public function hasLiveSessionConflict(User $student, CourseOffering $target): bool
    {
        $targetSessions = LiveSession::query()
            ->where('offering_id', $target->id)
            ->get();

        if ($targetSessions->isEmpty()) {
            return false;
        }

        $enrolledOfferingIds = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->where('offering_id', '!=', $target->id)
            ->pluck('offering_id');

        if ($enrolledOfferingIds->isEmpty()) {
            return false;
        }

        $existingSessions = LiveSession::query()
            ->whereIn('offering_id', $enrolledOfferingIds)
            ->get();

        foreach ($targetSessions as $targetSession) {
            $start = $targetSession->scheduled_start;
            $end = $targetSession->endsAt();

            foreach ($existingSessions as $existing) {
                if ($start->lt($existing->endsAt()) && $end->gt($existing->scheduled_start)) {
                    return true;
                }
            }
        }

        return false;
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

    /**
     * Credit/course caps are per target term. Waitlisted rows count because they
     * hold a term slot and take a seat on promote. Self-paced offerings without
     * a semester count against the current term only when dates overlap.
     */
    private function countsTowardTermCap(Enrollment $enrollment, CourseOffering $target): bool
    {
        $existing = $enrollment->offering;
        if ($existing === null) {
            return false;
        }

        if ($target->semester_id && $existing->semester_id) {
            return $existing->semester_id === $target->semester_id;
        }

        return $this->offeringWindowsOverlap($existing, $target);
    }

    private function offeringWindowsOverlap(CourseOffering $a, CourseOffering $b): bool
    {
        [$aStart, $aEnd] = $this->offeringWindow($a);
        [$bStart, $bEnd] = $this->offeringWindow($b);

        $now = now();
        $aStart ??= $now;
        $aEnd ??= $now;
        $bStart ??= $now;
        $bEnd ??= $now;

        return $aStart->lte($bEnd) && $bStart->lte($aEnd);
    }

    /**
     * @return array{0: \Illuminate\Support\Carbon|null, 1: \Illuminate\Support\Carbon|null}
     */
    private function offeringWindow(CourseOffering $offering): array
    {
        $offering->loadMissing('semester');

        return [
            $offering->start_date ?? $offering->semester?->start_date,
            $offering->end_date ?? $offering->semester?->end_date,
        ];
    }

    private function currentSemesterWeek(CourseOffering $offering): int
    {
        $start = $offering->semester?->start_date ?? $offering->start_date ?? now();
        $days = max(0, $start->diffInDays(now()));

        return (int) floor($days / 7) + 1;
    }

    /**
     * Open offerings a student can actually register for: standalone courses,
     * plus program courses they are already matriculated into.
     *
     * @return \Illuminate\Support\Collection<int, CourseOffering>
     */
    public function registerableOfferings(User $student)
    {
        $open = CourseOffering::query()
            ->with(['course', 'semester'])
            ->where('status', OfferingStatus::Open)
            ->latest()
            ->get();

        $programIds = StudentProgram::query()
            ->where('student_id', $student->id)
            ->where('status', StudentProgramStatus::Active)
            ->pluck('program_id');

        $courseIdsInPrograms = $programIds->isEmpty()
            ? collect()
            : ProgramCourse::query()
                ->whereIn('program_id', $programIds)
                ->pluck('course_id');

        return $open
            ->filter(function (CourseOffering $offering) use ($courseIdsInPrograms) {
                $course = $offering->course;
                if ($course === null) {
                    return false;
                }

                return $course->is_standalone || $courseIdsInPrograms->contains($course->id);
            })
            ->values();
    }
}
