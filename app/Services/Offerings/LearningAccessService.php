<?php

namespace App\Services\Offerings;

use App\Enums\EnrollmentStatus;
use App\Models\ContentItem;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LearningAccessService
{
    /**
     * @return list<EnrollmentStatus>
     */
    public function allowedStatuses(): array
    {
        return [EnrollmentStatus::Enrolled, EnrollmentStatus::Completed];
    }

    public function requireEnrollment(User $user, CourseOffering $offering): Enrollment
    {
        $enrollment = Enrollment::query()
            ->where('student_id', $user->id)
            ->where('offering_id', $offering->id)
            ->whereIn('status', array_map(fn (EnrollmentStatus $s) => $s->value, $this->allowedStatuses()))
            ->first();

        if (! $enrollment) {
            throw new AccessDeniedHttpException(__('learn.access_denied'));
        }

        return $enrollment;
    }

    public function assertWeekBelongsToOffering(Week $week, CourseOffering $offering): void
    {
        if ($week->offering_id !== $offering->id) {
            throw new NotFoundHttpException;
        }
    }

    public function assertItemBelongsToOffering(ContentItem $item, CourseOffering $offering): Week
    {
        $week = $item->week()->first();
        if (! $week || $week->offering_id !== $offering->id) {
            throw new NotFoundHttpException;
        }

        return $week;
    }
}
