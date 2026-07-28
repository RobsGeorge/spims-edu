<?php

namespace App\Services\Gradebook;

use App\Enums\ComponentKind;
use App\Enums\EnrollmentStatus;
use App\Enums\GradeStatus;
use App\Enums\GradeType;
use App\Enums\StudentProgramStatus;
use App\Models\AcademicRecord;
use App\Models\Assessment;
use App\Models\AssessmentTemplate;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeBand;
use App\Models\GradebookComponent;
use App\Models\GradingScheme;
use App\Models\ProgramCourse;
use App\Models\ProgramRequirementFulfillment;
use App\Models\StudentProgram;
use App\Models\User;
use App\Services\Assessment\AttemptService;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradebookService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
        private readonly AttemptService $attempts,
    ) {}

    public function seedFromTemplate(User $actor, CourseOffering $offering, ?AssessmentTemplate $template = null): void
    {
        $this->authorize->authorize($actor, 'gradebook.configure');

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
        $this->authorize->authorize($actor, 'gradebook.configure');

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
     * @return array{percent: float, components: array<int, array{name: string, weight: float, score: float|null}>}
     */
    public function computeEnrollment(Enrollment $enrollment): array
    {
        $enrollment->loadMissing(['student', 'offering']);
        $components = GradebookComponent::query()
            ->where('offering_id', $enrollment->offering_id)
            ->get();

        $rows = [];
        $weighted = 0.0;
        $weightSum = 0.0;

        foreach ($components as $component) {
            $score = $this->componentPercent($component, $enrollment->student);
            $rows[] = [
                'name' => $component->name,
                'weight' => $component->weight_percent,
                'score' => $score,
            ];
            if ($score !== null) {
                $weighted += $score * ($component->weight_percent / 100);
                $weightSum += $component->weight_percent;
            }
        }

        $percent = $weightSum > 0
            ? round($weighted / ($weightSum / 100), 2)
            : 0.0;

        return ['percent' => $percent, 'components' => $rows];
    }

    public function submitGrades(User $actor, CourseOffering $offering): void
    {
        $this->authorize->authorize($actor, 'gradebook.lock');

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
        $this->authorize->authorize($actor, 'gradebook.lock');

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

        Enrollment::query()
            ->where('offering_id', $offering->id)
            ->where('grade_status', GradeStatus::Locked)
            ->update([
                'grade_status' => GradeStatus::InProgress,
                'grade_locked_by_id' => null,
                'grade_locked_at' => null,
            ]);

        $this->audit->write($actor, 'gradebook.reopen', 'CourseOffering', $offering->id);
    }

    private function componentPercent(GradebookComponent $component, User $student): ?float
    {
        $scores = [];

        foreach (Assessment::query()->where('component_id', $component->id)->get() as $assessment) {
            if (! $assessment->released && $assessment->results_visibility->value === 'ON_RELEASE') {
                // Still count for instructor rollup
            }
            $score = $this->attempts->effectiveScore($assessment, $student);
            if ($score === null) {
                continue;
            }
            $max = max(0.0001, (float) $assessment->max_points);
            $pct = ($score / $max) * 100;
            $weight = $assessment->item_weight ?? 1;
            $scores[] = ['pct' => $pct, 'weight' => $weight];
        }

        foreach (Assignment::query()->where('component_id', $component->id)->get() as $assignment) {
            $sub = AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('student_id', $student->id)
                ->first();
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
                'is_passing' => (bool) ($band?->is_passing ?? (($enrollment->final_percent ?? 0) >= 60)),
                'completed_at' => now(),
            ]
        );

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

            return;
        }

        $points = $records->sum(fn (AcademicRecord $r) => $r->gpa_points * $r->credit_hours);
        $sp->update(['cached_gpa' => round($points / $credits, 2)]);
    }
}
