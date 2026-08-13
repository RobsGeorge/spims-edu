<?php

namespace App\Services\Learning;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\Assignment;
use App\Models\Assessment;
use App\Models\CourseOffering;
use App\Models\DiscussionThread;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OfferingAccessService
{
    public function enrolledOfferingIds(User $user): array
    {
        return Enrollment::query()
            ->where('student_id', $user->id)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->pluck('offering_id')
            ->all();
    }

    public function enrollmentFor(User $user, CourseOffering $offering): ?Enrollment
    {
        return Enrollment::query()
            ->where('student_id', $user->id)
            ->where('offering_id', $offering->id)
            ->whereIn('status', [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed])
            ->first();
    }

    public function isStaffOrAdmin(User $user, CourseOffering $offering): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->hasRole(RoleType::AcademicAdmin) || $user->hasRole(RoleType::AdministrativeAdmin)) {
            return true;
        }

        return $offering->staff()->where('user_id', $user->id)->exists();
    }

    public function canAccessOffering(User $user, CourseOffering $offering): bool
    {
        return $this->enrollmentFor($user, $offering) !== null
            || $this->isStaffOrAdmin($user, $offering);
    }

    public function assertCanAccessOffering(User $user, CourseOffering $offering): void
    {
        if (! $this->canAccessOffering($user, $offering)) {
            throw ValidationException::withMessages([
                'offering' => [__('learning.not_enrolled')],
            ]);
        }
    }

    public function assertCanAccessAssignment(User $user, Assignment $assignment): Enrollment|User
    {
        $assignment->loadMissing('contentItem.week');
        $offeringId = $assignment->contentItem?->week?->offering_id;
        if ($offeringId === null) {
            throw new AuthorizationException(__('auth.forbidden'));
        }

        $offering = CourseOffering::query()->findOrFail($offeringId);
        if ($this->isStaffOrAdmin($user, $offering)) {
            return $user;
        }

        $enrollment = $this->enrollmentFor($user, $offering);
        if ($enrollment === null) {
            throw new AuthorizationException(__('learning.not_enrolled'));
        }

        return $enrollment;
    }

    public function assertCanAccessAssessment(User $user, Assessment $assessment): void
    {
        $offering = CourseOffering::query()->findOrFail($assessment->offering_id);
        if ($this->isStaffOrAdmin($user, $offering)) {
            return;
        }

        if ($this->enrollmentFor($user, $offering) === null) {
            throw new AuthorizationException(__('learning.not_enrolled'));
        }
    }

    public function assertCanAccessDiscussion(User $user, CourseOffering $offering): void
    {
        $this->assertCanAccessOffering($user, $offering);
    }

    public function assertCanAccessThread(User $user, DiscussionThread $thread): void
    {
        $thread->loadMissing('board');
        $offering = CourseOffering::query()->findOrFail($thread->board->offering_id);
        $this->assertCanAccessOffering($user, $offering);
    }
}
