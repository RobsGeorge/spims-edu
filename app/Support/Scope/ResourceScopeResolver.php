<?php

namespace App\Support\Scope;

use App\Models\Announcement;
use App\Models\AnnouncementDelivery;
use App\Models\AnnouncementRevision;
use App\Models\AnnouncementTarget;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\AssessmentResultAnnouncement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AttemptAnswer;
use App\Models\AttendanceEntry;
use App\Models\AttendancePolicy;
use App\Models\ClassSession;
use App\Models\CompletionCriterion;
use App\Models\CompletionResult;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\DiscussionBoard;
use App\Models\DiscussionPost;
use App\Models\DiscussionThread;
use App\Models\EmailTemplate;
use App\Models\Enrollment;
use App\Models\GradebookComponent;
use App\Models\LiveQuiz;
use App\Models\LiveQuizQuestion;
use App\Models\LiveQuizSession;
use App\Models\LiveSession;
use App\Models\ModuleStudentAssessment;
use App\Models\OfferingClosing;
use App\Models\OfferingStaff;
use App\Models\ProctorEvent;
use App\Models\QuestionBank;
use App\Models\StudentNote;
use App\Models\User;
use App\Models\Week;

/**
 * Answers "is this actor staffed on the offering behind this resource?".
 *
 * Resolution is deliberately explicit rather than reflective: a model that is not
 * listed here resolves to null, and `AuthorizeService` treats an unresolvable
 * resource as out of scope. Adding a new offering-owned model therefore requires
 * opting it in, instead of silently inheriting access.
 */
class ResourceScopeResolver
{
    public function scopedTo(User $user, mixed $resource): bool
    {
        $offeringIds = $this->offeringIdsFor($resource);

        if ($offeringIds === []) {
            return false;
        }

        return OfferingStaff::query()
            ->where('user_id', $user->id)
            ->whereIn('offering_id', $offeringIds)
            ->exists();
    }

    /** Offerings this actor is staffed on. */
    public function staffedOfferingIds(User $user): array
    {
        return OfferingStaff::query()
            ->where('user_id', $user->id)
            ->pluck('offering_id')
            ->all();
    }

    /**
     * A resource may map to more than one offering — a question bank belongs to a
     * course, and a course may be offered several times.
     *
     * @return array<int, string>
     */
    public function offeringIdsFor(mixed $resource): array
    {
        if ($resource instanceof CourseOffering) {
            return [$resource->id];
        }

        if (is_string($resource) && $resource !== '') {
            return CourseOffering::query()->whereKey($resource)->exists() ? [$resource] : [];
        }

        $single = match (true) {
            $resource instanceof Week => $resource->offering_id,
            $resource instanceof Enrollment => $resource->offering_id,
            $resource instanceof LiveSession => $resource->offering_id,
            $resource instanceof ClassSession => $resource->offering_id,
            $resource instanceof AttendanceEntry => $resource->session?->offering_id,
            $resource instanceof AttendancePolicy => $resource->offering_id,
            $resource instanceof GradebookComponent => $resource->offering_id,
            $resource instanceof DiscussionBoard => $resource->offering_id,
            $resource instanceof Assessment => $resource->offering_id,
            $resource instanceof ContentItem => $resource->week?->offering_id,
            $resource instanceof Assignment => $resource->contentItem?->week?->offering_id,
            $resource instanceof AssignmentSubmission => $resource->assignment?->contentItem?->week?->offering_id,
            $resource instanceof AssessmentAttempt => $resource->assessment?->offering_id,
            $resource instanceof AttemptAnswer => $resource->attempt?->assessment?->offering_id,
            $resource instanceof ProctorEvent => $resource->attempt?->assessment?->offering_id,
            $resource instanceof AssessmentResultAnnouncement => $resource->assessment?->offering_id,
            $resource instanceof DiscussionThread => $resource->board?->offering_id,
            $resource instanceof DiscussionPost => $resource->thread?->board?->offering_id,
            $resource instanceof Announcement => $resource->offering_id,
            $resource instanceof AnnouncementTarget => $resource->announcement?->offering_id,
            $resource instanceof AnnouncementRevision => $resource->announcement?->offering_id,
            $resource instanceof AnnouncementDelivery => $resource->announcement?->offering_id,
            $resource instanceof EmailTemplate => $resource->scope_type === 'offering' ? $resource->scope_id : null,
            $resource instanceof OfferingClosing => $resource->offering_id,
            $resource instanceof CompletionResult => $resource->offering_id,
            $resource instanceof StudentNote => $resource->offering_id,
            $resource instanceof ModuleStudentAssessment => $resource->week?->offering_id,
            $resource instanceof CompletionCriterion && $resource->isOfferingScoped() => $resource->offering_id,
            $resource instanceof LiveQuiz => $resource->offering_id,
            $resource instanceof LiveQuizSession => $resource->quiz?->offering_id,
            $resource instanceof LiveQuizQuestion => $resource->quiz?->offering_id,
            default => null,
        };

        if ($single !== null) {
            return [$single];
        }

        // Question banks are course-level, so staffing any offering of that course counts.
        $courseId = match (true) {
            $resource instanceof QuestionBank => $resource->course_id,
            $resource instanceof Course => $resource->id,
            $resource instanceof EmailTemplate && $resource->scope_type === 'course' => $resource->scope_id,
            $resource instanceof CompletionCriterion && ! $resource->isOfferingScoped() => $resource->course_id,
            default => null,
        };

        if ($courseId !== null) {
            return CourseOffering::query()
                ->where('course_id', $courseId)
                ->pluck('id')
                ->all();
        }

        return [];
    }
}
