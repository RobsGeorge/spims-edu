<?php

namespace App\Services\Enrollment;

use App\Enums\RequirementType;
use App\Enums\StudentProgramStatus;
use App\Models\ProgramCourse;
use App\Models\ProgramRequirementFulfillment;
use App\Models\StudentProgram;
use App\Models\User;

class DegreeAuditService
{
    /**
     * @return array{program: string, status: string, required_met: int, required_total: int, elective_credits_met: int, elective_credits_required: int, remaining: array<int, array{code: string, title: string, requirement: string}>, met: array<int, array{code: string, title: string, requirement: string, letter: ?string, percent: ?float}>, overall_percent: float, what_if: bool}
     */
    public function audit(User $student, StudentProgram $studentProgram): array
    {
        return $this->compute($studentProgram, []);
    }

    /**
     * Same shape as audit(), treating catalog course IDs as fulfilled.
     * Does not persist academic_records or program requirement fulfillments.
     *
     * @param  list<string>  $hypotheticalCourseIds
     * @return array{program: string, status: string, required_met: int, required_total: int, elective_credits_met: int, elective_credits_required: int, remaining: array<int, array{code: string, title: string, requirement: string}>, met: array<int, array{code: string, title: string, requirement: string, letter: ?string, percent: ?float}>, overall_percent: float, what_if: bool}
     */
    public function whatIf(StudentProgram $sp, array $hypotheticalCourseIds): array
    {
        $result = $this->compute($sp, $hypotheticalCourseIds);
        $result['what_if'] = true;

        return $result;
    }

    /**
     * @param  list<string>  $hypotheticalCourseIds
     * @return array{program: string, status: string, required_met: int, required_total: int, elective_credits_met: int, elective_credits_required: int, remaining: array<int, array{code: string, title: string, requirement: string}>, met: array<int, array{code: string, title: string, requirement: string, letter: ?string, percent: ?float}>, overall_percent: float, what_if: bool}
     */
    private function compute(StudentProgram $studentProgram, array $hypotheticalCourseIds): array
    {
        $studentProgram->load('program');
        $program = $studentProgram->program;

        $fulfillments = ProgramRequirementFulfillment::query()
            ->where('student_program_id', $studentProgram->id)
            ->with(['programCourse.course', 'academicRecord'])
            ->get();

        $fulfilledCourseIds = $fulfillments->pluck('programCourse.course_id')->filter()->values()->all();

        $requirements = ProgramCourse::query()
            ->where('program_id', $program->id)
            ->with('course')
            ->get();

        $hypotheticalOnProgram = [];
        foreach ($hypotheticalCourseIds as $courseId) {
            if (! is_string($courseId) && ! is_int($courseId)) {
                continue;
            }
            $courseId = (string) $courseId;
            if (in_array($courseId, $fulfilledCourseIds, true)) {
                continue;
            }
            $pc = $requirements->firstWhere('course_id', $courseId);
            if ($pc === null) {
                continue;
            }
            $fulfilledCourseIds[] = $courseId;
            $hypotheticalOnProgram[] = $pc;
        }

        $required = $requirements->where('requirement', RequirementType::Required);
        $electives = $requirements->where('requirement', RequirementType::Elective);

        $requiredMet = $required->filter(fn (ProgramCourse $pc) => in_array($pc->course_id, $fulfilledCourseIds, true));
        $electiveMet = $electives->filter(fn (ProgramCourse $pc) => in_array($pc->course_id, $fulfilledCourseIds, true));
        $electiveCredits = $electiveMet->sum(fn (ProgramCourse $pc) => $pc->course->credit_hours);

        $met = $fulfillments->map(function (ProgramRequirementFulfillment $f) {
            return [
                'code' => $f->programCourse?->course?->code ?? '',
                'title' => $f->programCourse?->course?->title ?? '',
                'requirement' => $f->programCourse?->requirement?->value ?? '',
                'letter' => $f->academicRecord?->letter_grade,
                'percent' => $f->academicRecord?->percent,
            ];
        })->values()->all();

        foreach ($hypotheticalOnProgram as $pc) {
            $met[] = [
                'code' => $pc->course->code,
                'title' => $pc->course->title,
                'requirement' => $pc->requirement->value,
                'letter' => null,
                'percent' => null,
            ];
        }

        $remaining = $requirements
            ->filter(fn (ProgramCourse $pc) => ! in_array($pc->course_id, $fulfilledCourseIds, true))
            ->map(fn (ProgramCourse $pc) => [
                'code' => $pc->course->code,
                'title' => $pc->course->title,
                'requirement' => $pc->requirement->value,
            ])
            ->values()
            ->all();

        $totalReqs = max(1, $requirements->count());
        $metCount = count($fulfilledCourseIds);

        return [
            'program' => $program->code,
            'status' => $studentProgram->status->value,
            'required_met' => $requiredMet->count(),
            'required_total' => $required->count(),
            'elective_credits_met' => $electiveCredits,
            'elective_credits_required' => $program->elective_credits_required,
            'remaining' => $remaining,
            'met' => $met,
            'overall_percent' => round(($metCount / $totalReqs) * 100, 1),
            'what_if' => false,
        ];
    }

    public function activePrograms(User $student)
    {
        return StudentProgram::query()
            ->where('student_id', $student->id)
            ->where('status', StudentProgramStatus::Active)
            ->with('program')
            ->get();
    }
}
