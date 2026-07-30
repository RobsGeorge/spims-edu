<?php

namespace App\Services\Learning;

use App\Enums\EnrollmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Gradebook\GradebookService;

class StudentGradesService
{
    public function __construct(
        private readonly OfferingAccessService $access,
        private readonly GradebookService $gradebook,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forStudent(User $student): array
    {
        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->with(['offering.course'])
            ->get();

        $rows = [];
        foreach ($enrollments as $enrollment) {
            $offering = $enrollment->offering;
            $computed = $this->gradebook->computeEnrollment($enrollment);

            $assessments = Assessment::query()
                ->where('offering_id', $offering->id)
                ->where('released', true)
                ->orderBy('title')
                ->get();

            $items = [];
            foreach ($assessments as $assessment) {
                $attempt = AssessmentAttempt::query()
                    ->where('assessment_id', $assessment->id)
                    ->where('student_id', $student->id)
                    ->whereNotNull('submitted_at')
                    ->latest('submitted_at')
                    ->first();

                $items[] = [
                    'kind' => 'assessment',
                    'title' => $assessment->title,
                    'score' => $attempt?->total_score,
                    'status' => $attempt?->status?->value ?? 'NOT_STARTED',
                    'url' => route('assessments.show', $assessment),
                ];
            }

            $assignments = Assignment::query()
                ->whereHas('contentItem.week', fn ($q) => $q->where('offering_id', $offering->id))
                ->where('released', true)
                ->with('contentItem')
                ->get();

            foreach ($assignments as $assignment) {
                $submission = AssignmentSubmission::query()
                    ->where('assignment_id', $assignment->id)
                    ->where('student_id', $student->id)
                    ->first();

                $items[] = [
                    'kind' => 'assignment',
                    'title' => $assignment->contentItem?->title ?? __('learning.item_assignment'),
                    'score' => $submission?->final_score,
                    'status' => $submission?->submitted_at ? 'SUBMITTED' : 'NOT_STARTED',
                    'url' => route('assignments.show', $assignment),
                ];
            }

            $rows[] = [
                'enrollment' => $enrollment,
                'course_code' => $offering->course->code,
                'course_title' => $offering->course->title,
                'running_percent' => $computed['percent'] ?? null,
                'final_letter' => $enrollment->final_letter,
                'final_percent' => $enrollment->final_percent,
                'grade_status' => $enrollment->grade_status?->value,
                'items' => $items,
                'player_url' => route('courses.player', $offering),
            ];
        }

        return $rows;
    }
}
