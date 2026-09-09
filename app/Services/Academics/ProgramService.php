<?php

namespace App\Services\Academics;

use App\Enums\ProgramType;
use App\Enums\RequirementType;
use App\Models\Program;
use App\Models\ProgramCourse;
use App\Models\User;
use App\Support\AuditLogWriter;
use App\Support\AuthorizeService;
use Illuminate\Validation\ValidationException;

class ProgramService
{
    public function __construct(
        private readonly AuthorizeService $authorize,
        private readonly AuditLogWriter $audit,
    ) {}

    public function create(User $actor, array $data): Program
    {
        $this->authorize->authorize($actor, 'programs.manage');

        return $this->audit->withAudit($actor, 'programs.create', function () use ($data) {
            return Program::query()->create([
                'code' => strtoupper($data['code']),
                'name' => $data['name'],
                'type' => ProgramType::from($data['type']),
                'passing_threshold' => $data['passing_threshold'] ?? 60,
                'max_credits_per_semester' => $data['max_credits_per_semester'],
                'max_courses_per_semester' => $data['max_courses_per_semester'],
                'max_semesters_to_graduate' => $data['max_semesters_to_graduate'],
                'elective_credits_required' => $data['elective_credits_required'] ?? 0,
                'signatory_name' => $data['signatory_name'] ?? null,
                'signatory_title' => $data['signatory_title'] ?? null,
                'grading_scheme_id' => $data['grading_scheme_id'] ?? null,
                'active' => $data['active'] ?? true,
            ]);
        }, 'Program');
    }

    public function update(User $actor, Program $program, array $data): Program
    {
        $this->authorize->authorize($actor, 'programs.manage');
        $before = $program->toArray();

        $program->update([
            'name' => $data['name'] ?? $program->name,
            'type' => isset($data['type']) ? ProgramType::from($data['type']) : $program->type,
            'level' => array_key_exists('level', $data) ? $data['level'] : $program->level,
            'passing_threshold' => $data['passing_threshold'] ?? $program->passing_threshold,
            'require_all_courses_to_graduate' => array_key_exists('require_all_courses_to_graduate', $data) ? (bool) $data['require_all_courses_to_graduate'] : $program->require_all_courses_to_graduate,
            'max_credits_per_semester' => $data['max_credits_per_semester'] ?? $program->max_credits_per_semester,
            'max_courses_per_semester' => $data['max_courses_per_semester'] ?? $program->max_courses_per_semester,
            'max_semesters_to_graduate' => $data['max_semesters_to_graduate'] ?? $program->max_semesters_to_graduate,
            'elective_credits_required' => $data['elective_credits_required'] ?? $program->elective_credits_required,
            'enforce_year_sequence' => array_key_exists('enforce_year_sequence', $data) ? (bool) $data['enforce_year_sequence'] : $program->enforce_year_sequence,
            'signatory_name' => $data['signatory_name'] ?? $program->signatory_name,
            'signatory_title' => $data['signatory_title'] ?? $program->signatory_title,
            'certificate_template' => array_key_exists('certificate_template', $data) ? $data['certificate_template'] : $program->certificate_template,
            'issue_credential_on_completion' => array_key_exists('issue_credential_on_completion', $data) ? (bool) $data['issue_credential_on_completion'] : $program->issue_credential_on_completion,
            'grading_scheme_id' => array_key_exists('grading_scheme_id', $data) ? $data['grading_scheme_id'] : $program->grading_scheme_id,
            'active' => $data['active'] ?? $program->active,
        ]);

        $this->audit->write($actor, 'programs.update', 'Program', $program->id, $before, $program->fresh()->toArray());

        return $program->fresh();
    }

    public function attachCourse(User $actor, Program $program, string $courseId, string $requirement, ?int $yearLevel = null): ProgramCourse
    {
        $this->authorize->authorize($actor, 'programs.manage');

        if (! in_array($requirement, array_column(RequirementType::cases(), 'value'), true)) {
            throw ValidationException::withMessages(['requirement' => [__('academics.invalid_requirement')]]);
        }

        return $this->audit->withAudit($actor, 'programs.attach_course', function () use ($program, $courseId, $requirement, $yearLevel) {
            return ProgramCourse::query()->updateOrCreate(
                ['program_id' => $program->id, 'course_id' => $courseId],
                ['requirement' => RequirementType::from($requirement), 'year_level' => $yearLevel]
            );
        }, 'ProgramCourse');
    }

    public function detachCourse(User $actor, Program $program, ProgramCourse $programCourse): void
    {
        $this->authorize->authorize($actor, 'programs.manage');

        if ($programCourse->program_id !== $program->id) {
            throw ValidationException::withMessages(['course' => [__('academics.course_not_attached')]]);
        }

        $this->audit->withAudit($actor, 'programs.detach_course', function () use ($programCourse) {
            $programCourse->delete();

            return $programCourse;
        }, 'ProgramCourse');
    }
}
