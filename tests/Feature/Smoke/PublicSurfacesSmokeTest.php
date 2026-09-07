<?php

namespace Tests\Feature\Smoke;

use App\Enums\RoleType;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\User;
use App\Models\Week;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Smoke test: every GET route in routes/web.php must return HTTP < 500.
 *
 * Test actors:
 *   $student    – factory-created Student user
 *   $superAdmin – SuperAdminSeeder user (bypasses all AuthorizeService checks)
 *
 * Fixture models come from SampleDataSeeder (always runs in test env):
 *   CourseOffering, Course, Week, AcademicYear, Semester.
 *
 * Models NOT seeded in test env (SEED_DEMO_DATA=false):
 *   Assessment, ContentItem, Announcement, ClassSession, Invoice, LiveSession,
 *   ApplicationForm, Application, DiscussionThread.
 *   Routes that need these models are attempted with the seeded offering ID
 *   as a stand-in — they will return 404, which is < 500 and therefore pass.
 *   All such routes are listed in docs/smoke-baseline.md.
 *
 * Any 5xx response is a test failure.
 */
class PublicSurfacesSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $superAdmin;
    private ?CourseOffering $offering = null;
    private ?Course $course = null;
    private ?Week $week = null;
    private ?AcademicYear $academicYear = null;
    private ?Semester $semester = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->loadFixtures();
    }

    private function loadFixtures(): void
    {
        // Actors — student via factory, superadmin from seed
        $this->student = User::factory()->withRole(RoleType::Student)->create();
        $this->superAdmin = User::whereHas(
            'roles',
            fn ($q) => $q->where('role', RoleType::SuperAdmin->value)
        )->firstOrFail();

        // Seeded structural models (SampleDataSeeder always runs in test env)
        $this->offering = CourseOffering::first();
        $this->course = Course::first();
        $this->week = Week::first();
        $this->academicYear = AcademicYear::first();
        $this->semester = Semester::first();
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function assertNotServerError(TestResponse $response, string $desc): void
    {
        $status = $response->getStatusCode();
        $this->assertLessThan(
            500,
            $status,
            "Route [{$desc}] returned HTTP {$status} — server error detected"
        );
    }

    // -----------------------------------------------------------------------
    // Always-public routes (no auth, no model params)
    // -----------------------------------------------------------------------

    public function test_always_public_routes_are_not_5xx(): void
    {
        $checks = [
            'GET /'             => $this->get('/'),
            'GET /health'       => $this->get('/health'),
            'GET /up'           => $this->get('/up'),
            'GET /api/branding' => $this->get('/api/branding'),
            'GET /catalog'      => $this->get('/catalog'),
        ];

        foreach ($checks as $desc => $response) {
            $this->assertNotServerError($response, $desc);
        }
    }

    // -----------------------------------------------------------------------
    // Guest-only routes (redirect or 200 for unauthenticated visitors)
    // -----------------------------------------------------------------------

    public function test_guest_auth_routes_are_not_5xx(): void
    {
        $checks = [
            'GET /register'        => $this->get('/register'),
            'GET /login'           => $this->get('/login'),
            'GET /verify-email'    => $this->get('/verify-email'),
            'GET /set-password'    => $this->get('/set-password'),
            'GET /forgot-password' => $this->get('/forgot-password'),
            'GET /reset-password'  => $this->get('/reset-password'),
        ];

        foreach ($checks as $desc => $response) {
            $this->assertNotServerError($response, $desc);
        }
    }

    // -----------------------------------------------------------------------
    // Public routes that need an offering model
    // -----------------------------------------------------------------------

    public function test_offering_preview_public_routes_are_not_5xx(): void
    {
        if (! $this->offering) {
            $this->markTestSkipped('No CourseOffering seeded');
        }

        $id = $this->offering->id;

        $checks = [
            "GET /offerings/{$id}/preview"     => $this->get("/offerings/{$id}/preview"),
            "GET /api/offerings/{$id}/preview" => $this->get("/api/offerings/{$id}/preview"),
            "GET /api/offerings/{$id}/pricing" => $this->get("/api/offerings/{$id}/pricing"),
        ];

        foreach ($checks as $desc => $response) {
            $this->assertNotServerError($response, $desc);
        }
    }

    // -----------------------------------------------------------------------
    // Auth routes — no model params (student actor)
    // -----------------------------------------------------------------------

    public function test_auth_routes_no_model_params_are_not_5xx(): void
    {
        $actor = $this->actingAs($this->student);

        $checks = [
            'GET /dashboard'               => $actor->get('/dashboard'),
            'GET /hubs/learning'           => $actor->get('/hubs/learning'),
            'GET /hubs/academic'           => $actor->get('/hubs/academic'),
            'GET /hubs/admin'              => $actor->get('/hubs/admin'),
            'GET /hubs/finance'            => $actor->get('/hubs/finance'),
            'GET /teach'                   => $actor->get('/teach'),
            'GET /surveys'                 => $actor->get('/surveys'),
            'GET /announcements'           => $actor->get('/announcements'),
            'GET /attendance'              => $actor->get('/attendance'),
            'GET /attendance/check-in'     => $actor->get('/attendance/check-in'),
            'GET /enrollments'             => $actor->get('/enrollments'),
            'GET /finance'                 => $actor->get('/finance'),
            'GET /settings'                => $actor->get('/settings'),
            'GET /settings/notifications'  => $actor->get('/settings/notifications'),
            'GET /applications'            => $actor->get('/applications'),
            'GET /projects'                => $actor->get('/projects'),
            'GET /live'                    => $actor->get('/live'),
            'GET /events'                  => $actor->get('/events'),
            'GET /events/mine'             => $actor->get('/events/mine'),
            'GET /grades'                  => $actor->get('/grades'),
            'GET /transcript'              => $actor->get('/transcript'),
            'GET /advising'                => $actor->get('/advising'),
            'GET /notifications'           => $actor->get('/notifications'),
            'GET /live-quiz/join'          => $actor->get('/live-quiz/join'),
            'GET /api/me'                  => $actor->get('/api/me'),
        ];

        foreach ($checks as $desc => $response) {
            $this->assertNotServerError($response, $desc);
        }
    }

    // -----------------------------------------------------------------------
    // Auth routes — offering param (student actor)
    // Routes 404 if student not enrolled, but still not 5xx.
    // -----------------------------------------------------------------------

    public function test_auth_offering_routes_are_not_5xx(): void
    {
        if (! $this->offering) {
            $this->markTestSkipped('No CourseOffering seeded');
        }

        $actor = $this->actingAs($this->student);
        $id = $this->offering->id;

        $checks = [
            "GET /courses/{$id}"               => $actor->get("/courses/{$id}"),
            "GET /learn/{$id}"                 => $actor->get("/learn/{$id}"),
            "GET /offerings/{$id}/discussions" => $actor->get("/offerings/{$id}/discussions"),
            "GET /offerings/{$id}/completion"  => $actor->get("/offerings/{$id}/completion"),
            "GET /offerings/{$id}/projects"    => $actor->get("/offerings/{$id}/projects"),
        ];

        foreach ($checks as $desc => $response) {
            $this->assertNotServerError($response, $desc);
        }
    }

    // -----------------------------------------------------------------------
    // Auth routes — week param (student actor)
    // -----------------------------------------------------------------------

    public function test_auth_week_routes_are_not_5xx(): void
    {
        if (! $this->offering || ! $this->week) {
            $this->markTestSkipped('No CourseOffering or Week seeded');
        }

        $actor = $this->actingAs($this->student);
        $ofId = $this->offering->id;
        $wId = $this->week->id;

        $this->assertNotServerError(
            $actor->get("/learn/{$ofId}/weeks/{$wId}"),
            "GET /learn/{$ofId}/weeks/{$wId}"
        );
    }

    // -----------------------------------------------------------------------
    // Teach routes — offering param (superadmin actor covers all permissions)
    // -----------------------------------------------------------------------

    public function test_teach_offering_routes_are_not_5xx(): void
    {
        if (! $this->offering) {
            $this->markTestSkipped('No CourseOffering seeded');
        }

        $actor = $this->actingAs($this->superAdmin);
        $id = $this->offering->id;

        $checks = [
            "GET /teach/{$id}"             => $actor->get("/teach/{$id}"),
            "GET /teach/{$id}/attendance"  => $actor->get("/teach/{$id}/attendance"),
            "GET /teach/{$id}/assignments" => $actor->get("/teach/{$id}/assignments"),
            "GET /teach/{$id}/discussions" => $actor->get("/teach/{$id}/discussions"),
            "GET /teach/{$id}/surveys"     => $actor->get("/teach/{$id}/surveys"),
            "GET /teach/{$id}/projects"    => $actor->get("/teach/{$id}/projects"),
            "GET /teach/{$id}/live"        => $actor->get("/teach/{$id}/live"),
            "GET /teach/{$id}/live-quiz"   => $actor->get("/teach/{$id}/live-quiz"),
            "GET /teach/{$id}/completion"  => $actor->get("/teach/{$id}/completion"),
        ];

        foreach ($checks as $desc => $response) {
            $this->assertNotServerError($response, $desc);
        }
    }

    // -----------------------------------------------------------------------
    // Admin routes — no model params (superadmin actor)
    // -----------------------------------------------------------------------

    public function test_admin_routes_no_model_params_are_not_5xx(): void
    {
        $actor = $this->actingAs($this->superAdmin);

        $checks = [
            'GET /admin/users'                        => $actor->get('/admin/users'),
            'GET /admin/programs'                     => $actor->get('/admin/programs'),
            'GET /admin/programs/create'              => $actor->get('/admin/programs/create'),
            'GET /admin/courses'                      => $actor->get('/admin/courses'),
            'GET /admin/courses/create'               => $actor->get('/admin/courses/create'),
            'GET /admin/semesters'                    => $actor->get('/admin/semesters'),
            'GET /admin/offerings'                    => $actor->get('/admin/offerings'),
            'GET /admin/offerings/create'             => $actor->get('/admin/offerings/create'),
            'GET /admin/application-forms'            => $actor->get('/admin/application-forms'),
            'GET /admin/applications'                 => $actor->get('/admin/applications'),
            'GET /admin/enrollments'                  => $actor->get('/admin/enrollments'),
            'GET /admin/finance'                      => $actor->get('/admin/finance'),
            'GET /admin/finance/reports'              => $actor->get('/admin/finance/reports'),
            'GET /admin/reports'                      => $actor->get('/admin/reports'),
            'GET /admin/reports/headcount'            => $actor->get('/admin/reports/headcount'),
            'GET /admin/reports/admissions'           => $actor->get('/admin/reports/admissions'),
            'GET /admin/reports/attendance'           => $actor->get('/admin/reports/attendance'),
            'GET /admin/reports/grades'               => $actor->get('/admin/reports/grades'),
            'GET /admin/reports/finance'              => $actor->get('/admin/reports/finance'),
            'GET /admin/reports/standing'             => $actor->get('/admin/reports/standing'),
            'GET /admin/reports/standing/thresholds'  => $actor->get('/admin/reports/standing/thresholds'),
            'GET /admin/credentials'                  => $actor->get('/admin/credentials'),
            'GET /admin/grading-schemes'              => $actor->get('/admin/grading-schemes'),
            'GET /admin/translations'                 => $actor->get('/admin/translations'),
            'GET /admin/assessment-templates'         => $actor->get('/admin/assessment-templates'),
            'GET /admin/communications'               => $actor->get('/admin/communications'),
            'GET /admin/email-templates'              => $actor->get('/admin/email-templates'),
            'GET /admin/surveys'                      => $actor->get('/admin/surveys'),
            'GET /admin/events'                       => $actor->get('/admin/events'),
            'GET /admin/theme'                        => $actor->get('/admin/theme'),
            'GET /admin/attendance/policy'            => $actor->get('/admin/attendance/policy'),
            'GET /admin/attendance/report'            => $actor->get('/admin/attendance/report'),
            'GET /admin/certificate-templates'        => $actor->get('/admin/certificate-templates'),
            'GET /admin/certificate-templates/preview' => $actor->get('/admin/certificate-templates/preview'),
        ];

        foreach ($checks as $desc => $response) {
            $this->assertNotServerError($response, $desc);
        }
    }

    // -----------------------------------------------------------------------
    // Admin routes — offering param (superadmin actor)
    // -----------------------------------------------------------------------

    public function test_admin_offering_routes_are_not_5xx(): void
    {
        if (! $this->offering) {
            $this->markTestSkipped('No CourseOffering seeded');
        }

        $actor = $this->actingAs($this->superAdmin);
        $id = $this->offering->id;

        $checks = [
            "GET /admin/offerings/{$id}"                   => $actor->get("/admin/offerings/{$id}"),
            "GET /admin/offerings/{$id}/edit"              => $actor->get("/admin/offerings/{$id}/edit"),
            "GET /admin/offerings/{$id}/gradebook"         => $actor->get("/admin/offerings/{$id}/gradebook"),
            "GET /admin/offerings/{$id}/live"              => $actor->get("/admin/offerings/{$id}/live"),
            "GET /admin/offerings/{$id}/closing"           => $actor->get("/admin/offerings/{$id}/closing"),
            "GET /admin/offerings/{$id}/waitlist"          => $actor->get("/admin/offerings/{$id}/waitlist"),
            "GET /admin/offerings/{$id}/assessments/create" => $actor->get("/admin/offerings/{$id}/assessments/create"),
        ];

        foreach ($checks as $desc => $response) {
            $this->assertNotServerError($response, $desc);
        }
    }

    // -----------------------------------------------------------------------
    // Admin routes — course param (superadmin actor)
    // -----------------------------------------------------------------------

    public function test_admin_course_routes_are_not_5xx(): void
    {
        if (! $this->course) {
            $this->markTestSkipped('No Course seeded');
        }

        $actor = $this->actingAs($this->superAdmin);
        $cId = $this->course->id;

        $checks = [
            "GET /admin/courses/{$cId}"                      => $actor->get("/admin/courses/{$cId}"),
            "GET /admin/courses/{$cId}/edit"                 => $actor->get("/admin/courses/{$cId}/edit"),
            "GET /admin/courses/{$cId}/banks"                => $actor->get("/admin/courses/{$cId}/banks"),
            "GET /admin/courses/{$cId}/completion-criteria"  => $actor->get("/admin/courses/{$cId}/completion-criteria"),
        ];

        foreach ($checks as $desc => $response) {
            $this->assertNotServerError($response, $desc);
        }
    }

    // -----------------------------------------------------------------------
    // Admin routes — academic year + semester params (superadmin actor)
    // -----------------------------------------------------------------------

    public function test_admin_calendar_routes_are_not_5xx(): void
    {
        $actor = $this->actingAs($this->superAdmin);
        $ran = false;

        if ($this->academicYear) {
            $yId = $this->academicYear->id;
            $this->assertNotServerError(
                $actor->get("/admin/academic-years/{$yId}/edit"),
                "GET /admin/academic-years/{$yId}/edit"
            );
            $ran = true;
        }

        if ($this->semester) {
            $sId = $this->semester->id;
            $this->assertNotServerError(
                $actor->get("/admin/semesters/{$sId}/edit"),
                "GET /admin/semesters/{$sId}/edit"
            );
            $ran = true;
        }

        if (! $ran) {
            $this->markTestSkipped('No AcademicYear or Semester seeded');
        }
    }

    // -----------------------------------------------------------------------
    // Admin routes — user detail (superadmin actor, uses factory student)
    // -----------------------------------------------------------------------

    public function test_admin_user_detail_routes_are_not_5xx(): void
    {
        $actor = $this->actingAs($this->superAdmin);
        $uId = $this->student->id;

        $this->assertNotServerError(
            $actor->get("/admin/users/{$uId}"),
            "GET /admin/users/{$uId}"
        );
    }

    // -----------------------------------------------------------------------
    // Superadmin-only routes
    // -----------------------------------------------------------------------

    public function test_superadmin_routes_are_not_5xx(): void
    {
        $actor = $this->actingAs($this->superAdmin);

        $checks = [
            'GET /superadmin'                  => $actor->get('/superadmin'),
            'GET /superadmin/security'         => $actor->get('/superadmin/security'),
            'GET /superadmin/audit'            => $actor->get('/superadmin/audit'),
            'GET /superadmin/observability'    => $actor->get('/superadmin/observability'),
            'GET /superadmin/scheduled-tasks'  => $actor->get('/superadmin/scheduled-tasks'),
            'GET /superadmin/system-tests'     => $actor->get('/superadmin/system-tests'),
            'GET /superadmin/feedback-reveals' => $actor->get('/superadmin/feedback-reveals'),
            'GET /roles-hub'                   => $actor->get('/roles-hub'),
        ];

        foreach ($checks as $desc => $response) {
            $this->assertNotServerError($response, $desc);
        }
    }

    // -----------------------------------------------------------------------
    // Verification: smoke-baseline.md documents skipped routes
    // -----------------------------------------------------------------------

    public function test_smoke_baseline_document_exists(): void
    {
        $this->assertTrue(
            file_exists(base_path('docs/smoke-baseline.md')),
            'docs/smoke-baseline.md must exist and document routes that 404 or are skipped'
        );
    }
}
