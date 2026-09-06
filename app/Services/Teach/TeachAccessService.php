<?php

namespace App\Services\Teach;

use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\CourseOffering;
use App\Models\DiscussionThread;
use App\Models\OfferingStaff;
use App\Models\User;
use App\Support\AuthorizeService;
use Illuminate\Support\Collection;

class TeachAccessService
{
    public function canTeach(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()
            || $user->hasRole(RoleType::AcademicAdmin)
            || $user->hasRole(RoleType::Instructor)
            || $user->hasRole(RoleType::Ta)) {
            return true;
        }

        return OfferingStaff::query()->where('user_id', $user->id)->exists();
    }

    /**
     * @return Collection<int, CourseOffering>
     */
    public function offeringsFor(User $user): Collection
    {
        $query = CourseOffering::query()
            ->with(['course', 'semester', 'staff.user'])
            ->orderByDesc('created_at');

        if ($user->isSuperAdmin() || $user->hasRole(RoleType::AcademicAdmin)) {
            return $query->limit(50)->get();
        }

        return $query
            ->whereHas('staff', fn ($q) => $q->where('user_id', $user->id))
            ->get();
    }

    public function assertCanTeachOffering(User $user, CourseOffering $offering): void
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleType::AcademicAdmin)) {
            return;
        }

        $isStaff = $offering->staff()->where('user_id', $user->id)->exists();
        if (! $isStaff) {
            abort(403);
        }
    }

    public function canGradeDiscussions(?User $user, CourseOffering|DiscussionThread $resource): bool
    {
        if ($user === null || ! $this->canTeach($user)) {
            return false;
        }

        try {
            app(AuthorizeService::class)->authorize($user, 'discussions.grade', $resource);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }
}
