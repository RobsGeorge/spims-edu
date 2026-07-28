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
     * @return array{required_met: int, required_total: int, elective_credits_met: int, elective_credits_required: int, remaining: array<int, array{code: string, title: string, requirement: string}>}
     */
    public function audit(User $student, StudentProgram $studentProgram): array
    {
        $studentProgram->load('program');
        $program = $studentProgram->program;

        $fulfillments = ProgramRequirementFulfillment::query()
            ->where('student_program_id', $studentProgram->id)
            ->with(['programCourse.course', 'academicRecord'])
            ->get();

        $fulfilledCourseIds = $fulfillments->pluck('programCourse.course_id')->filter()->all();

        $requirements = ProgramCourse::query()
            ->where('program_id', $program->id)
            ->with('course')
            ->get();

        $required = $requirements->where('requirement', RequirementType::Required);
        $electives = $requirements->where('requirement', RequirementType::Elective);

        $requiredMet = $required->filter(fn (ProgramCourse $pc) => in_array($pc->course_id, $fulfilledCourseIds, true));
        $electiveMet = $electives->filter(fn (ProgramCourse $pc) => in_array($pc->course_id, $fulfilledCourseIds, true));
        $electiveCredits = $electiveMet->sum(fn (ProgramCourse $pc) => $pc->course->credit_hours);

        $remaining = $requirements
            ->filter(fn (ProgramCourse $pc) => ! in_array($pc->course_id, $fulfilledCourseIds, true))
            ->map(fn (ProgramCourse $pc) => [
                'code' => $pc->course->code,
                'title' => $pc->course->title,
                'requirement' => $pc->requirement->value,
            ])
            ->values()
            ->all();

        return [
            'program' => $program->code,
            'status' => $studentProgram->status->value,
            'required_met' => $requiredMet->count(),
            'required_total' => $required->count(),
            'elective_credits_met' => $electiveCredits,
            'elective_credits_required' => $program->elective_credits_required,
            'remaining' => $remaining,
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
