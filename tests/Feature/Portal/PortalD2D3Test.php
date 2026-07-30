<?php

namespace Tests\Feature\Portal;

use App\Enums\ContentItemType;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Enums\SubmissionType;
use App\Enums\UserStatus;
use App\Models\Assignment;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\DiscussionBoard;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalD2D3Test extends TestCase
{
    use RefreshDatabase;

    private function offeringWithWeeks(): CourseOffering
    {
        $course = Course::query()->create([
            'code' => 'LRN101',
            'title' => 'Learning Foundations',
            'credit_hours' => 3,
            'is_standalone' => true,
            'is_free' => true,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Open,
        ]);

        $week1 = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week One',
            'order' => 1,
        ]);
        Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 2,
            'title' => 'Week Two',
            'order' => 2,
        ]);

        $week1->items()->create([
            'type' => ContentItemType::Video,
            'title' => 'Intro video',
            'order' => 1,
            'vimeo_id' => '99999',
        ]);

        return $offering->fresh(['weeks.items', 'course']);
    }

    #[Test]
    public function landing_and_auth_surfaces_render_sacred_copy(): void
    {
        $this->seed();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('spims-landing', false)
            ->assertSee(__('ui.home_heading'))
            ->assertSee(__('ui.home_cta_primary'));

        $this->get(route('auth.login'))
            ->assertOk()
            ->assertSee('auth-card', false);

        $this->withCookie('locale', 'ar')->get(route('auth.login'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('تسجيل الدخول', false);
    }

    #[Test]
    public function suspended_login_shows_localized_message(): void
    {
        $this->seed();
        $user = User::factory()->withRole(RoleType::Student)->create([
            'email' => 'suspended@example.com',
            'status' => UserStatus::Suspended,
            'password_hash' => Hash::make('password'),
        ]);

        $this->from(route('auth.login'))
            ->post(route('auth.login'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');

        app()->setLocale('fr');
        $this->from(route('auth.login'))
            ->post(route('auth.login'), [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');
        $this->assertSame(__('auth.suspended', [], 'fr'), session('errors')->first('email'));
        app()->setLocale('en');
    }

    #[Test]
    public function catalog_is_public_and_filterable(): void
    {
        $this->seed();
        Course::query()->create([
            'code' => 'FREE1',
            'title' => 'Free Course',
            'credit_hours' => 1,
            'is_free' => true,
            'is_standalone' => true,
            'active' => true,
        ]);
        Course::query()->create([
            'code' => 'PAID1',
            'title' => 'Paid Course',
            'credit_hours' => 2,
            'is_free' => false,
            'is_standalone' => false,
            'active' => true,
        ]);

        $this->get(route('catalog.index', ['price' => 'free']))
            ->assertOk()
            ->assertSee('FREE1')
            ->assertDontSee('PAID1');

        $this->get(route('catalog.index', ['q' => 'Paid']))
            ->assertOk()
            ->assertSee('PAID1')
            ->assertDontSee('FREE1');
    }

    #[Test]
    public function auth_locale_files_include_failed_and_suspended(): void
    {
        foreach (['ar', 'fr'] as $locale) {
            foreach (['failed', 'suspended', 'not_active', 'otp_invalid', 'password_reset_success'] as $key) {
                $value = __("auth.{$key}", [], $locale);
                $this->assertNotSame("auth.{$key}", $value, "Missing auth.{$key} in {$locale}");
            }
        }
    }

    #[Test]
    public function student_dashboard_shows_bento_and_player_path(): void
    {
        $this->seed();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offeringWithWeeks();

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'progress_percent' => 0,
        ]);

        $this->actingAs($student)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('bento-grid', false)
            ->assertSee('LRN101')
            ->assertSee(route('courses.player', $offering), false);
    }

    #[Test]
    public function enrolled_student_can_use_course_player_and_complete_week(): void
    {
        $this->seed();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offeringWithWeeks();
        $week1 = $offering->weeks->firstWhere('number', 1);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'progress_percent' => 0,
        ]);

        $this->actingAs($student)
            ->get(route('courses.player', $offering))
            ->assertOk()
            ->assertSee('Intro video')
            ->assertSee('player.vimeo.com/video/99999', false)
            ->assertSee('Week Two');

        $this->actingAs($student)
            ->post(route('courses.weeks.complete', [$offering, $week1]))
            ->assertRedirect();

        $this->assertDatabaseHas('enrollment_week_completions', [
            'week_id' => $week1->id,
        ]);
    }

    #[Test]
    public function outsider_cannot_open_player_assignment_or_discussion(): void
    {
        $this->seed();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $outsider = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->offeringWithWeeks();

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $item = ContentItem::query()->create([
            'week_id' => $offering->weeks->firstWhere('number', 1)->id,
            'type' => ContentItemType::Assignment,
            'title' => 'Essay',
            'order' => 2,
        ]);
        $assignment = Assignment::query()->create([
            'content_item_id' => $item->id,
            'instructions' => 'Write',
            'submission_type' => SubmissionType::Text,
            'allowed_file_types' => [],
            'max_points' => 10,
            'released' => true,
        ]);

        DiscussionBoard::query()->create([
            'offering_id' => $offering->id,
            'allow_student_threads' => true,
        ]);

        $this->actingAs($outsider)
            ->get(route('courses.player', $offering))
            ->assertSessionHasErrors('offering');

        $this->actingAs($outsider)
            ->get(route('assignments.show', $assignment))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->get(route('discussions.board', $offering))
            ->assertSessionHasErrors('offering');
    }

    #[Test]
    public function settings_and_grades_pages_work_for_student(): void
    {
        $this->seed();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Mariam',
        ]);

        $this->actingAs($student)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Mariam');

        $this->actingAs($student)
            ->put(route('settings.update'), [
                'first_name' => 'Marina',
                'last_name' => $student->last_name,
                'phone' => '0100',
                'preferred_locale' => 'ar',
                'theme_preference' => 'DARK',
                'notify_email' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('Marina', $student->fresh()->first_name);
        $this->assertSame('ar', $student->fresh()->preferred_locale);
        $this->assertTrue($student->fresh()->notify_email);

        $this->actingAs($student)
            ->get(route('grades.index'))
            ->assertOk()
            ->assertSee(__('learning.grades'));
    }
}
