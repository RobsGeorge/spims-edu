<?php

namespace Tests\Feature\Demo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalAuthzWalkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['spims.seed_demo_data' => true, 'spims.demo_console' => true]);
        $this->seed();
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    #[Test]
    public function student_cannot_open_staff_or_superadmin_consoles(): void
    {
        $john = $this->user('student1@spims.test');

        $this->actingAs($john)->get('/admin/users')->assertForbidden();
        $this->actingAs($john)->get('/superadmin')->assertForbidden();
        $this->actingAs($john)->get('/admin/finance')->assertForbidden();
        $this->actingAs($john)->get('/projects')->assertOk();
    }

    #[Test]
    public function instructor_can_view_programs_but_not_office_finance_or_users(): void
    {
        $mina = $this->user('ins1@spims.test');

        $this->actingAs($mina)->get('/admin/programs')->assertOk();
        $this->actingAs($mina)->get('/admin/finance')->assertForbidden();
        $this->actingAs($mina)->get('/admin/users')->assertForbidden();
    }

    #[Test]
    public function office_admin_can_view_programs_and_finance_but_academic_manage_stays_closed(): void
    {
        $adm = $this->user('adm@spims.test');

        $this->actingAs($adm)->get('/admin/applications')->assertOk();
        $this->actingAs($adm)->get('/admin/programs')->assertOk();
        $this->actingAs($adm)->get('/admin/finance')->assertOk();
        $this->actingAs($adm)->get('/admin/programs/create')->assertForbidden();
    }

    #[Test]
    public function finance_cannot_review_admissions(): void
    {
        $fin = $this->user('fin@spims.test');

        $this->actingAs($fin)->get('/admin/finance')->assertOk();
        $this->actingAs($fin)->get('/admin/applications')->assertForbidden();
    }

    #[Test]
    public function academic_communications_index_is_the_report_route(): void
    {
        $aca = $this->user('aca@spims.test');

        $this->actingAs($aca)->get('/admin/communications')->assertOk();
        $this->actingAs($aca)->get('/admin/communications/report')->assertNotFound();
    }
}
