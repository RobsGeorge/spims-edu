<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleType;
use App\Exceptions\AuthorizationException;
use App\Models\User;
use App\Support\AuthorizeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthorizeServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_bypasses_permission_checks(): void
    {
        $user = User::factory()->withRole(RoleType::SuperAdmin)->create();

        app(AuthorizeService::class)->authorize($user, 'foundation.demo');

        $this->assertTrue(true);
    }

    #[Test]
    public function academic_admin_can_access_foundation_demo(): void
    {
        $user = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        app(AuthorizeService::class)->authorize($user, 'foundation.demo');

        $this->assertTrue(true);
    }

    #[Test]
    public function student_cannot_access_foundation_demo(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create();

        $this->expectException(AuthorizationException::class);

        app(AuthorizeService::class)->authorize($user, 'foundation.demo');
    }

    #[Test]
    public function guest_raises_unauthorized(): void
    {
        $this->expectException(AuthorizationException::class);

        app(AuthorizeService::class)->authorize(null, 'foundation.demo');
    }

    #[Test]
    public function only_super_admin_can_assign_admin_roles(): void
    {
        $service = app(AuthorizeService::class);
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $sa = User::factory()->withRole(RoleType::SuperAdmin)->create();

        $this->assertFalse($service->canAssignRole($adm, RoleType::SuperAdmin));
        $this->assertTrue($service->canAssignRole($sa, RoleType::AdministrativeAdmin));
        $this->assertTrue($service->canAssignRole($adm, RoleType::Student));
    }
}
