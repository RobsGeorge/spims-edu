<?php

namespace Tests;

use App\Enums\OfferingStaffRole;
use App\Models\CourseOffering;
use App\Models\OfferingStaff;
use App\Models\User;
use App\Support\AuthorizeService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // role_permissions matrix is cached statically; RefreshDatabase can leave a
        // stale grant set from a prior test (e.g. RolesHub stripping STUDENT keys).
        app(AuthorizeService::class)->forgetMatrixCache();
    }

    /**
     * Staff a user on an offering.
     *
     * Holding the Instructor or TA role is not by itself authority over an offering —
     * `AuthorizeService` confines those roles to offerings they appear in `offering_staff`
     * for. Fixtures that act on an offering must therefore say so explicitly.
     */
    protected function staffOffering(
        User $user,
        CourseOffering $offering,
        OfferingStaffRole $role = OfferingStaffRole::Instructor,
    ): OfferingStaff {
        return OfferingStaff::query()->firstOrCreate(
            ['offering_id' => $offering->id, 'user_id' => $user->id],
            ['role' => $role],
        );
    }
}
