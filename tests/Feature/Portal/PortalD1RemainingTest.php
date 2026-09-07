<?php

namespace Tests\Feature\Portal;

use App\Enums\OfferingMode;
use App\Enums\OfferingStaffRole;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\OfferingStaff;
use App\Models\User;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalD1RemainingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_dashboard_includes_search_bottom_nav_and_shell_css(): void
    {
        $this->seed(ThemeSeeder::class);
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Mariam',
            'last_name' => 'Habib',
        ]);

        $html = $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('spims-shell.css', false)
            ->assertSee('app-bottom-nav', false)
            ->assertSee('app-topbar-search', false)
            ->assertSee('id="app-shell-search"', false)
            ->assertSee('name="q"', false)
            ->assertSee(__('ui.search'), false)
            ->assertSee(__('catalog.search_placeholder'))
            ->assertSee('app-avatar', false)
            ->assertSee('MH', false)
            ->assertSee('Mariam')
            ->assertSee(__('ui.logout'))
            ->getContent();

        $this->assertStringContainsString('method="GET"', $html);
        $this->assertStringContainsString(e(route('catalog.index')), $html);
    }

    #[Test]
    public function instructor_sees_teach_in_sidebar_and_bottom_nav(): void
    {
        $this->seed(ThemeSeeder::class);
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $course = Course::query()->create([
            'code' => 'D1REM',
            'title' => 'D1 Remaining Course',
            'credit_hours' => 3,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
            'status' => OfferingStatus::Open,
        ]);
        OfferingStaff::query()->create([
            'offering_id' => $offering->id,
            'user_id' => $instructor->id,
            'role' => OfferingStaffRole::Instructor,
        ]);

        $html = $this->actingAs($instructor)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('hubs.nav_teach'))
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/class="app-sidebar[\s\S]*'.preg_quote(__('hubs.nav_teach'), '/').'/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/class="app-bottom-nav[\s\S]*'.preg_quote(__('hubs.nav_teach'), '/').'/',
            $html
        );
    }

    #[Test]
    public function arabic_locale_cookie_sets_rtl_on_dashboard(): void
    {
        $this->seed(ThemeSeeder::class);
        $student = User::factory()->withRole(RoleType::Student)->create([
            'preferred_locale' => 'ar',
        ]);

        $this->actingAs($student)
            ->withCookie('locale', 'ar')
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="ar"', false);
    }

    #[Test]
    public function shell_css_enforces_touch_targets_with_logical_properties(): void
    {
        $path = public_path('css/spims-shell.css');
        $this->assertFileExists($path);

        $css = file_get_contents($path);
        $this->assertIsString($css);
        $this->assertStringContainsString('.app-bottom-link', $css);
        $this->assertStringContainsString('.app-icon-btn', $css);
        $this->assertStringContainsString('min-height: 44px', $css);
        $this->assertStringContainsString('min-width: 44px', $css);
        $this->assertStringContainsString('inset-inline-start', $css);
        $this->assertStringContainsString('margin-inline', $css);
        $this->assertDoesNotMatchRegularExpression('/margin-left\s*:/', $css);
        $this->assertDoesNotMatchRegularExpression('/margin-right\s*:/', $css);
        $this->assertDoesNotMatchRegularExpression('/padding-left\s*:/', $css);
        $this->assertDoesNotMatchRegularExpression('/padding-right\s*:/', $css);
        $this->assertDoesNotMatchRegularExpression('/(?<![a-z-])(left|right)\s*:/', $css);
    }
}
