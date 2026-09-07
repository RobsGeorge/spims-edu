<?php

namespace App\Services\Gradebook;

use App\Enums\AttemptStatus;
use App\Enums\ComponentKind;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\GradeType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicRecord;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentTemplate;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CourseOffering;
use App\Models\DiscussionBoard;
use App\Models\DiscussionGrade;
use App\Models\DiscussionThread;
use App\Models\Enrollment;
use App\Models\GradeBand;
use App\Models\GradebookComponent;
use App\Models\GradingScheme;
use App\Models\ProgramCourse;
use App\Models\ProgramRequirementFulfillment;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Assessment\AttemptService;
use App\Services\Assessment\ResultsVisibilityService;
use App\Services\Live\AttendanceService;
use App\Services\Projects\ProjectGradingService;
use App\Services\Reports\AcademicStandingService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GradebookService
{
    /**
     * Offering-scoped preload so show()/CSV is not N+1.
     *
     * @var array{
     *     offering_id: string,
     *     components: Collection<int, GradebookComponent>,
     *     assessments_by_component: Collection<string, Collection<int, Assessment>>,
     *     assignments_by_component: Collection<string, Collection<int, Assignment>>,
     *     attempts: Collection<string, Collection<int, \App\Models\AssessmentAttempt>>,
     *     submissions: Collection<string, AssignmentSubmission>,
     *     attendance: array<string, float|null>,
     *     discussion: array<string, float|null>
     * }|null
     */
    private ?array $offeringCache = null;

    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly AttemptService $attempts,
        private readonly AttendanceService $attendance,
        private readonly ProjectGradingService $projects,
        private readonly AcademicStandingService $standing,
        private readonly ResultsVisibilityService $visibility,
    ) {}

    public function seedFromTemplate(User $actor, CourseOffering $offering, ?AssessmentTemplate $template = null): void
    {
        $this->authorize->authorize($actor, 'gradebook.configure', $offering);

        $template ??= AssessmentTemplate::query()->where('is_default', true)->first()
            ?? AssessmentTemplate::query()->first();

        if ($template === null) {
            return;
        }

        foreach ($template->components as $c) {
            GradebookComponent::query()->firstOrCreate(
                [
                    'offering_id' => $offering->id,
                    'name' => $c->name,
                ],
                [
                    'weight_percent' => $c->weight_percent,
                    'kind' => $c->kind,
                ]
            );
        }

        $this->audit->write($actor, 'gradebook.seed_template', 'CourseOffering', $offering->id);
    }

    /**
     * @param  array{name: string, weight_percent: float, kind: string}  $data
     */
    public function addComponent(User $actor, CourseOffering $offering, array $data): GradebookComponent
    {
        $this->authorize->authorize($actor, 'gradebook.configure', $offering);

        return $this->audit->withAudit($actor, 'gradebook.component_create', function () use ($offering, $data) {
            return GradebookComponent::query()->create([
                'offering_id' => $offering->id,
                'name' => $data['name'],
                'weight_percent' => $data['weight_percent'],
                'kind' => ComponentKind::from($data['kind']),
            ]);
        }, 'GradebookComponent');
    }

    /**
     * Preload attempts, submissions, attendance, and discussion scores for one offering.
     */
    public function preloadOffering(CourseOffering $offering): void
    {
        $components = GradebookComponent::query()
            ->where('offering_id', $offering->id)
            ->with(['assessments', 'assignments'])
            ->get();

        $assessments = $components->pluck('assessments')->flatten(1);
        $assignments = $components->pluck('assignments')->flatten(1);

        $attempts = AssessmentAttempt::query()
            ->whereIn('assessment_id', $assessments->pluck('id')->all() ?: ['-'])
            ->whereIn('status', [
                AttemptStatus::Submitted,
                AttemptStatus::AutoSubmitted,
                AttemptStatus::Graded,
            ])
            ->get()
            ->groupBy(fn (AssessmentAttempt $attempt) => $attempt->assessment_id.'|'.$attempt->student_id);

        $submissions = AssignmentSubmission::query()
            ->whereIn('assignment_id', $assignments->pluck('id')->all() ?: ['-'])
            ->get()
            ->keyBy(fn (AssignmentSubmission $sub) => $sub->assignment_id.'|'.$sub->student_id);

        $discussion = [];
        $board = DiscussionBoard::query()->where('offering_id', $offering->id)->first();
        if ($board !== null) {
            $threadIds = DiscussionThread::query()
                ->where('board_id', $board->id)
                ->where('is_graded', true)
                ->pluck('id');
            $grades = DiscussionGrade::query()
                ->whereIn('thread_id', $threadIds->all() ?: ['-'])
                ->whereNotNull('final_score')
                ->get()
                ->groupBy('student_id');
            foreach ($grades as $studentId => $rows) {
                $discussion[$studentId] = round((float) $rows->avg('final_score'), 2);
            }
        }

        $this->offeringCache = [
            'offering_id' => $offering->id,
            'components' => $components,
            'assessments_by_component' => $assessments->groupBy('component_id'),
            'assignments_by_component' => $assignments->groupBy('component_id'),
            'attempts' => $attempts,
            'submissions' => $submissions,
            'attendance' => $this->attendance->offeringPercents($offering),
            'discussion' => $discussion,
        ];
    }

    /**
     * @return Collection<int, GradebookComponent>
     */
    public function componentsFor(CourseOffering $offering): Collection
    {
        if ($this->offeringCache !== null && $this->offeringCache['offering_id'] === $offering->id) {
            return $this->offeringCache['components'];
        }

        return GradebookComponent::query()
            ->where('offering_id', $offering->id)
            ->get();
    }

    public function weightSum(CourseOffering $offering): float
    {
        return round((float) $this->componentsFor($offering)->sum('weight_percent'), 2);
    }

    /**
     * @return array{
     *     enrollments: Collection<int, Enrollment>,
     *     components: Collection<int, GradebookComponent>,
     *     weight_sum: float
     * }
     */
    public function gridForOffering(CourseOffering $offering): array
    {
        $this->preloadOffering($offering);

        $enrollments = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->with(['student', 'studentProgram.program'])
            ->orderBy('enrolled_at')
            ->get();

        foreach ($enrollments as $enrollment) {
            $computed = $this->computeEnrollment($enrollment);
            $computed['letter'] = $enrollment->final_letter
                ?? $this->resolveBand($enrollment, $computed['percent'])?->letter;
            $enrollment->computed = $computed;
        }

        return [
            'enrollments' => $enrollments,
            'components' => $this->offeringCache['components'],
            'weight_sum' => $this->weightSum($offering),
        ];
    }

    public function exportCsv(User $actor, CourseOffering $offering): string
    {
        $this->authorize->authorize($actor, 'gradebook.configure', $offering);

        $grid = $this->gridForOffering($offering);
        $headers = ['student_name', 'email'];
        foreach ($grid['components'] as $component) {
            $headers[] = $component->name;
        }
        $headers[] = 'final_percent';
        $headers[] = 'letter';

        $lines = [implode(',', array_map(fn (string $h) => $this->csvCell($h), $headers))];

        foreach ($grid['enrollments'] as $enrollment) {
            $student = $enrollment->student;
            $computed = $enrollment->computed;
            $scoresByName = collect($computed['components'])->keyBy('name');
            $row = [
                trim(($student?->first_name ?? '').' '.($student?->last_name ?? '')),
                (string) ($student?->email ?? ''),
            ];
            foreach ($grid['components'] as $component) {
                $score = $scoresByName->get($component->name)['score'] ?? null;
                $row[] = $score === null ? '' : (string) $score;
            }
            $row[] = (string) $computed['percent'];
            $row[] = (string) ($computed['letter'] ?? $enrollment->final_letter ?? '');
            $lines[] = implode(',', array_map(fn (string $v) => $this->csvCell($v), $row));
        }

        $this->audit->write($actor, 'gradebook.export', 'CourseOffering', $offering->id);

        return implode("\n", $lines)."\n";
    }

    /**
     * Staff / lock / submit rollup. Includes every scored component, even when
     * linked exam results are not yet announced to the student.
     *
     * @return array{percent: float, components: array<int, array{id: string, name: string, weight: float, score: float|null}>, letter?: string|null}
     */
    public function computeEnrollment(Enrollment $enrollment): array
    {
        return $this->computeEnrollmentPercent($enrollment, null);
    }

    /**
     * Student-facing running percent. Drops components whose linked assessments
     * are not scoresVisible (typically ON_RELEASE exams before announce) and
     * renormalizes remaining visible weights. Null when nothing visible is scored
     * so the grades Blade dash stays "—".
     *
     * @return array{percent: float|null, components: array<int, array{id: string, name: string, weight: float, score: float|null}>}
     */
    public function computeEnrollmentForStudent(Enrollment $enrollment, User $student): array
    {
        return $this->computeEnrollmentPercent($enrollment, $student);
    }

    /**
     * @return array{percent: float|null, components: array<int, array{id: string, name: string, weight: float, score: float|null}>}
     */
    private function computeEnrollmentPercent(Enrollment $enrollment, ?User $studentViewer): array
    {
        $enrollment->loadMissing(['student', 'offering']);
        $components = $this->offeringCache !== null && $this->offeringCache['offering_id'] === $enrollment->offering_id
            ? $this->offeringCache['components']
            : GradebookComponent::query()
                ->where('offering_id', $enrollment->offering_id)
                ->get();

        $rows = [];
        $weighted = 0.0;
        $weightSum = 0.0;

        foreach ($components as $component) {
            $hidden = $studentViewer !== null
                && ! $this->componentScoresVisibleToStudent($component, $studentViewer);
            $score = $hidden ? null : $this->componentPercent($component, $enrollment->student);
            $rows[] = [
                'id' => $component->id,
                'name' => $component->name,
                'weight' => $component->weight_percent,
                'score' => $score,
            ];
            if ($score !== null) {
                $weighted += $score * ($component->weight_percent / 100);
                $weightSum += $component->weight_percent;
            }
        }

        if ($weightSum > 0) {
            $percent = round($weighted / ($weightSum / 100), 2);
        } else {
            $percent = $studentViewer !== null ? null : 0.0;
        }

        return ['percent' => $percent, 'components' => $rows];
    }

    /**
     * A component leaks into the student rollup if any linked assessment is
     * still hidden. Assignment / attendance / discussion / project components
     * with no Assessment rows stay visible.
     */
    private function componentScoresVisibleToStudent(GradebookComponent $component, User $student): bool
    {
        $assessments = $this->assessmentsFor($component);
        foreach ($assessments as $assessment) {
            $assessment->loadMissing(['offering', 'resultAnnouncement']);
            if (! $this->visibility->scoresVisible($assessment, $student)) {
                return false;
            }
        }

        return true;
    }

    public function submitGrades(User $actor, CourseOffering $offering): void
    {
        $this->authorize->authorize($actor, 'gradebook.lock', $offering);

        $enrollments = Enrollment::query()
            ->where('offering_id', $offering->id)
            ->where('status', EnrollmentStatus::Enrolled)
            ->get();

        foreach ($enrollments as $enrollment) {
            if ($enrollment->grade_status === GradeStatus::Locked) {
                continue;
            }
            if (in_array($enrollment->grade_type, [GradeType::Withdrawal, GradeType::Audit], true)) {
                continue;
            }

            $computed = $this->computeEnrollment($enrollment);
            $band = $this->resolveBand($enrollment, $computed['percent']);

            $enrollment->update([
                'final_percent' => $computed['percent'],
                'final_letter' => $band?->letter,
                'final_gpa_points' => $band?->gpa_points,
                'grade_type' => GradeType::Standard,
                'grade_status' => GradeStatus::Submitted,
            ]);
        }

        $this->audit->write($actor, 'gradebook.submit', 'CourseOffering', $offering->id);
    }

    public function lockGrades(User $actor, CourseOffering $offering): void
    {
        $this->authorize->authorize($actor, 'gradebook.lock', $offering);

        DB::transaction(function () use ($actor, $offering) {
            $enrollments = Enrollment::query()
                ->where('offering_id', $offering->id)
                ->where('status', EnrollmentStatus::Enrolled)
                ->lockForUpdate()
                ->get();

            foreach ($enrollments as $enrollment) {
                if ($enrollment->grade_status === GradeStatus::Locked) {
                    continue;
                }
                if ($enrollment->grade_type === GradeType::Withdrawal) {
                    continue;
                }

                if ($enrollment->grade_status !== GradeStatus::Submitted) {
                    $computed = $this->computeEnrollment($enrollment);
                    $band = $this->resolveBand($enrollment, $computed['percent']);
                    $enrollment->final_percent = $computed['percent'];
                    $enrollment->final_letter = $band?->letter ?? 'F';
                    $enrollment->final_gpa_points = $band?->gpa_points ?? 0;
                    $enrollment->grade_type = GradeType::Standard;
                }

                $enrollment->grade_status = GradeStatus::Locked;
                $enrollment->grade_locked_by_id = $actor->id;
                $enrollment->grade_locked_at = now();
                $enrollment->save();

                if ($enrollment->is_audit || $enrollment->grade_type === GradeType::Audit) {
                    continue;
                }

                $this->postAcademicRecord($enrollment);
            }

            $this->audit->write($actor, 'gradebook.lock', 'CourseOffering', $offering->id);
        });
    }

    public function reopen(User $actor, CourseOffering $offering): void
    {
        $this->authorize->authorize($actor, 'gradebook.reopen');

        DB::transaction(function () use ($actor, $offering) {
            Enrollment::query()
                ->where('offering_id', $offering->id)
                ->where('grade_status', GradeStatus::Locked)
                ->where('status', EnrollmentStatus::Completed)
                ->update([
                    'status' => EnrollmentStatus::Enrolled,
                ]);

            Enrollment::query()
                ->where('offering_id', $offering->id)
                ->where('grade_status', GradeStatus::Locked)
                ->update([
                    'grade_status' => GradeStatus::InProgress,
                    'grade_locked_by_id' => null,
                    'grade_locked_at' => null,
                ]);

            $this->audit->write($actor, 'gradebook.reopen', 'CourseOffering', $offering->id);
        });
    }

    public function componentPercent(GradebookComponent $component, User $student): ?float
    {
        if ($component->kind === ComponentKind::Attendance) {
            if ($this->cachedFor($component->offering_id)) {
                return $this->offeringCache['attendance'][$student->id] ?? null;
            }

            return $this->attendance->offeringPercent($component->offering, $student);
        }

        if ($component->kind === ComponentKind::Discussion) {
            if ($this->cachedFor($component->offering_id)) {
                return $this->offeringCache['discussion'][$student->id] ?? null;
            }

            $board = DiscussionBoard::query()->where('offering_id', $component->offering_id)->first();
            if ($board === null) {
                return null;
            }
            $scores = DiscussionGrade::query()
                ->whereIn('thread_id', DiscussionThread::query()->where('board_id', $board->id)->where('is_graded', true)->pluck('id'))
                ->where('student_id', $student->id)
                ->whereNotNull('final_score')
                ->pluck('final_score');

            return $scores->isEmpty() ? null : round((float) $scores->avg(), 2);
        }

        if ($component->kind === ComponentKind::Project) {
            return $this->projects->announcedPercentForComponent($component, $student);
        }

        $scores = [];

        foreach ($this->assessmentsFor($component) as $assessment) {
            if (! $assessment->released && $assessment->results_visibility->value === 'ON_RELEASE') {
                // Still count for instructor rollup
            }
            $score = $this->attemptScore($component, $assessment, $student);
            if ($score === null) {
                continue;
            }
            $max = max(0.0001, (float) $assessment->max_points);
            $pct = ($score / $max) * 100;
            $weight = $assessment->item_weight ?? 1;
            $scores[] = ['pct' => $pct, 'weight' => $weight];
        }

        foreach ($this->assignmentsFor($component) as $assignment) {
            $sub = $this->submissionFor($component, $assignment, $student);
            if ($sub?->final_score === null) {
                continue;
            }
            $max = max(0.0001, (float) $assignment->max_points);
            $pct = ($sub->final_score / $max) * 100;
            $weight = $assignment->item_weight ?? 1;
            $scores[] = ['pct' => $pct, 'weight' => $weight];
        }

        if ($scores === []) {
            return null;
        }

        $num = 0.0;
        $den = 0.0;
        foreach ($scores as $s) {
            $num += $s['pct'] * $s['weight'];
            $den += $s['weight'];
        }

        return $den > 0 ? round($num / $den, 2) : null;
    }

    private function cachedFor(string $offeringId): bool
    {
        return $this->offeringCache !== null && $this->offeringCache['offering_id'] === $offeringId;
    }

    /**
     * @return Collection<int, Assessment>
     */
    private function assessmentsFor(GradebookComponent $component): Collection
    {
        if ($this->cachedFor($component->offering_id)) {
            return $this->offeringCache['assessments_by_component']->get($component->id, collect());
        }

        return Assessment::query()->where('component_id', $component->id)->get();
    }

    /**
     * @return Collection<int, Assignment>
     */
    private function assignmentsFor(GradebookComponent $component): Collection
    {
        if ($this->cachedFor($component->offering_id)) {
            return $this->offeringCache['assignments_by_component']->get($component->id, collect());
        }

        return Assignment::query()->where('component_id', $component->id)->get();
    }

    private function attemptScore(GradebookComponent $component, Assessment $assessment, User $student): ?float
    {
        if ($this->cachedFor($component->offering_id)) {
            $key = $assessment->id.'|'.$student->id;
            $loaded = $this->offeringCache['attempts']->get($key, collect());

            return $this->attempts->scoreFromAttempts($assessment, $loaded);
        }

        return $this->attempts->effectiveScore($assessment, $student);
    }

    private function submissionFor(GradebookComponent $component, Assignment $assignment, User $student): ?AssignmentSubmission
    {
        if ($this->cachedFor($component->offering_id)) {
            return $this->offeringCache['submissions']->get($assignment->id.'|'.$student->id);
        }

        return AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();
    }

    private function csvCell(?string $value): string
    {
        $value = (string) $value;
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }

    private function resolveBand(Enrollment $enrollment, float $percent): ?GradeBand
    {
        $enrollment->loadMissing(['studentProgram.program']);

        $schemeId = $enrollment->studentProgram?->program?->grading_scheme_id
            ?? GradingScheme::query()->where('is_default', true)->value('id');

        if (! $schemeId) {
            return null;
        }

        return GradeBand::query()
            ->where('scheme_id', $schemeId)
            ->where('min_percent', '<=', $percent)
            ->where('max_percent', '>=', $percent)
            ->first();
    }

    private function postAcademicRecord(Enrollment $enrollment): void
    {
        $enrollment->loadMissing(['offering.course', 'offering.semester', 'student']);
        $course = $enrollment->offering->course;
        $term = $enrollment->offering->semester?->name ?? 'self-paced';

        $band = GradeBand::query()
            ->where('letter', $enrollment->final_letter)
            ->first();

        $isPassing = (bool) ($band?->is_passing ?? (($enrollment->final_percent ?? 0) >= 60));

        $record = AcademicRecord::query()->updateOrCreate(
            ['enrollment_id' => $enrollment->id],
            [
                'student_id' => $enrollment->student_id,
                'course_id' => $course->id,
                'letter_grade' => $enrollment->final_letter ?? 'F',
                'percent' => $enrollment->final_percent ?? 0,
                'gpa_points' => $enrollment->final_gpa_points ?? 0,
                'credit_hours' => $course->credit_hours,
                'term' => $term,
                'is_passing' => $isPassing,
                'completed_at' => now(),
            ]
        );

        if ($isPassing && $enrollment->status === EnrollmentStatus::Enrolled) {
            $enrollment->status = EnrollmentStatus::Completed;
            $enrollment->save();
        }

        // Cross-program reuse: apply one passed record to every active program that lists this course.
        $programs = StudentProgram::query()
            ->where('student_id', $enrollment->student_id)
            ->where('status', StudentProgramStatus::Active)
            ->get();

        foreach ($programs as $sp) {
            $pc = ProgramCourse::query()
                ->where('program_id', $sp->program_id)
                ->where('course_id', $course->id)
                ->first();

            if ($pc === null) {
                continue;
            }

            ProgramRequirementFulfillment::query()->updateOrCreate(
                [
                    'student_program_id' => $sp->id,
                    'program_course_id' => $pc->id,
                ],
                [
                    'academic_record_id' => $record->id,
                    'applied_at' => now(),
                ]
            );

            $this->refreshGpa($sp);
        }
    }

    private function refreshGpa(StudentProgram $sp): void
    {
        $records = ProgramRequirementFulfillment::query()
            ->where('student_program_id', $sp->id)
            ->with('academicRecord')
            ->get()
            ->pluck('academicRecord')
            ->filter();

        $credits = $records->sum('credit_hours');
        if ($credits <= 0) {
            $sp->update(['cached_gpa' => null]);
            $this->standing->apply($sp->fresh());

            return;
        }

        $points = $records->sum(fn (AcademicRecord $r) => $r->gpa_points * $r->credit_hours);
        $sp->update(['cached_gpa' => round($points / $credits, 2)]);
        $this->standing->apply($sp->fresh());
    }
}
