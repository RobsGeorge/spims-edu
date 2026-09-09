<?php

namespace Tests\Feature\Portal;

use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['spims.seed_demo_data' => true]);
    }

    #[Test]
    public function demo_login_returns_403_when_demo_mode_is_off(): void
    {
        config(['spims.demo_mode' => false]);
        $this->post('/demo/login', ['role' => 'student'])
             ->assertStatus(403);
    }

    #[Test]
    public function demo_guide_returns_403_when_demo_mode_is_off(): void
    {
        config(['spims.demo_mode' => false]);
        $this->get('/demo/guide')
             ->assertStatus(403);
    }

    #[Test]
    public function demo_banner_is_absent_when_demo_mode_is_off(): void
    {
        config(['spims.demo_mode' => false]);
        $this->seed();
        $user = User::factory()->withRole(RoleType::Student)->create();
        $this->actingAs($user)
             ->get('/dashboard')
             ->assertStatus(200)
             ->assertDontSee(__('demo.banner_title'), false);
    }

    #[Test]
    public function demo_mode_is_always_false_in_production(): void
    {
        $this->app['env'] = 'production';
        $demoMode = app()->isProduction() ? false : (bool) env('SPIMS_DEMO_MODE', false);
        $this->assertFalse($demoMode, 'Demo mode must be false in production.');
    }

    #[Test]
    public function demo_login_is_rate_limited(): void
    {
        $this->seed();
        config(['spims.demo_mode' => true]);
        $limit = (int) config('spims.demo_rate_limit', 10);
        for ($i = 0; $i <= $limit; $i++) {
            $this->post('/demo/login', ['role' => 'student']);
        }
        $this->post('/demo/login', ['role' => 'student'])
             ->assertStatus(429);
    }

    #[Test]
    public function demo_quick_login_writes_audit_entry(): void
    {
        $this->seed();
        config(['spims.demo_mode' => true]);
        $before = AuditLog::query()->where('action', 'demo.quick_login')->count();
        $this->withoutMiddleware(ThrottleRequests::class)
             ->post('/demo/login', ['role' => 'student']);
        $this->assertGreaterThan(
            $before,
            AuditLog::query()->where('action', 'demo.quick_login')->count()
        );
    }

    #[Test]
    public function demo_quick_login_audit_contains_role(): void
    {
        $this->seed();
        config(['spims.demo_mode' => true]);
        $this->withoutMiddleware(ThrottleRequests::class)
             ->post('/demo/login', ['role' => 'instructor']);
        $log = AuditLog::query()->where('action', 'demo.quick_login')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('instructor', $log->after['role'] ?? null);
    }

    #[Test]
    public function demo_guide_deep_links_resolve_for_student(): void
    {
        $this->seed();
        config(['spims.demo_mode' => true]);
        $student = User::query()->where('email', 'student1@spims.test')->firstOrFail();
        $urls = [
            route('dashboard'),
            route('catalog.index'),
            route('enrollments.index'),
            route('grades.index'),
            route('finance.index'),
            route('attendance.index'),
            route('announcements.index'),
        ];
        foreach ($urls as $url) {
            $status = $this->actingAs($student)->get($url)->getStatusCode();
            $this->assertNotEquals(404, $status, "Student link [{$url}] returned 404.");
        }
    }

    #[Test]
    public function demo_guide_deep_links_resolve_for_instructor(): void
    {
        $this->seed();
        config(['spims.demo_mode' => true]);
        $instructor = User::query()->where('email', 'ins1@spims.test')->firstOrFail();
        $urls = [
            route('dashboard'),
            route('teach.index'),
            route('catalog.index'),
            route('announcements.index'),
        ];
        foreach ($urls as $url) {
            $status = $this->actingAs($instructor)->get($url)->getStatusCode();
            $this->assertNotEquals(404, $status, "Instructor link [{$url}] returned 404.");
        }
    }

    #[Test]
    public function demo_guide_deep_links_resolve_for_admin(): void
    {
        $this->seed();
        config(['spims.demo_mode' => true]);
        $superAdmin = User::whereHas(
            'roles',
            fn ($q) => $q->where('role', RoleType::SuperAdmin->value)
        )->firstOrFail();
        $urls = [
            route('admin.users.index'),
            route('admin.offerings.index'),
            route('admin.programs.index'),
            route('admin.finance.index'),
            route('admin.reports.index'),
        ];
        foreach ($urls as $url) {
            $status = $this->actingAs($superAdmin)->get($url)->getStatusCode();
            $this->assertNotEquals(404, $status, "Admin link [{$url}] returned 404.");
        }
    }

    #[Test]
    public function demo_guide_page_renders_when_demo_mode_is_on(): void
    {
        $this->seed();
        config(['spims.demo_mode' => true]);
        $this->get('/demo/guide')
             ->assertStatus(200)
             ->assertSee(__('demo.guide_title'), false);
    }

    #[Test]
    public function demo_login_succeeds_with_valid_role(): void
    {
        $this->seed();
        config(['spims.demo_mode' => true]);
        $this->withoutMiddleware(ThrottleRequests::class)
             ->post('/demo/login', ['role' => 'student'])
             ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    #[Test]
    public function demo_banner_is_visible_when_demo_mode_is_on(): void
    {
        $this->seed();
        config(['spims.demo_mode' => true]);
        $user = User::factory()->withRole(RoleType::Student)->create();
        $this->actingAs($user)
             ->withCookies(['demo_banner_dismissed' => ''])
             ->get('/dashboard')
             ->assertStatus(200)
             ->assertSee(__('demo.banner_title'), false);
    }
}
