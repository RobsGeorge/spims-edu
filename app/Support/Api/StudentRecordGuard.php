<?php

namespace App\Support\Api;

use App\Exceptions\AuthorizationException;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Learning\OfferingAccessService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Student API ownership: reads of someone else's records are 404; writes
 * outside the caller's scope are 403.
 */
class StudentRecordGuard
{
    public function __construct(
        private readonly OfferingAccessService $access,
    ) {}

    public function enrollmentForRead(User $user, CourseOffering $offering): Enrollment
    {
        $enrollment = $this->access->enrollmentFor($user, $offering);
        if ($enrollment === null) {
            throw new NotFoundHttpException;
        }

        return $enrollment;
    }

    public function enrollmentForWrite(User $user, CourseOffering $offering): Enrollment
    {
        $enrollment = $this->access->enrollmentFor($user, $offering);
        if ($enrollment === null) {
            throw new AuthorizationException(__('auth.forbidden'));
        }

        return $enrollment;
    }

    public function ownRead(User $user, ?string $ownerId): void
    {
        if ($ownerId === null || $user->id !== $ownerId) {
            throw new NotFoundHttpException;
        }
    }

    public function ownWrite(User $user, ?string $ownerId): void
    {
        if ($ownerId === null || $user->id !== $ownerId) {
            throw new AuthorizationException(__('auth.forbidden'));
        }
    }
}
