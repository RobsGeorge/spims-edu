<?php

namespace Tests\Feature\Portal;

use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseInterestFlag;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalD2RemainingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function landing_keeps_heading_cta_and_secondary_band(): void
    {
        $this->seed();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('spims-landing', false)
            ->assertSee('spims-landing-band', false)
            ->assertSee(__('ui.home_heading'))
            ->assertSee(__('ui.home_cta_primary'))
            ->assertSee(__('home.how_title'))
            ->assertSee(__('home.how_1_title'))
            ->assertSee(__('home.how_2_title'))
            ->assertSee(__('home.how_3_title'))
            ->assertSee(__('home.catalog_teaser'))
            ->assertSee(route('catalog.index'), false);
    }

    #[Test]
    public function arabic_landing_is_rtl(): void
    {
        $this->seed();

        $this->withCookie('locale', 'ar')
            ->get(route('home'))
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('ui.home_heading', [], 'ar'))
            ->assertSee(__('home.how_title', [], 'ar'));
    }

    #[Test]
    public function login_register_forgot_and_verify_render_auth_card(): void
    {
        $this->seed();
        $pending = User::factory()->withRole(RoleType::Student)->create();

        $this->get(route('auth.login'))
            ->assertOk()
            ->assertSee('auth-card', false)
            ->assertSee(__('ui.auth_help_login'));

        $this->get(route('auth.register'))
            ->assertOk()
            ->assertSee('auth-card', false)
            ->assertSee(__('ui.auth_help_register'));

        $this->get(route('auth.password.request'))
            ->assertOk()
            ->assertSee('auth-card', false)
            ->assertSee(__('ui.auth_help_forgot'));

        $this->withSession(['pending_user_id' => $pending->id])
            ->get(route('auth.verify'))
            ->assertOk()
            ->assertSee('auth-card', false)
            ->assertSee(__('ui.auth_help_verify'));

        $this->withSession(['pending_user_id' => $pending->id, 'verified' => true])
            ->get(route('auth.password.create'))
            ->assertOk()
            ->assertSee('auth-card', false)
            ->assertSee(__('ui.auth_help_set_password'));

        $this->withSession(['reset_email' => $pending->email, 'reset_verified' => true])
            ->get(route('auth.password.reset.form'))
            ->assertOk()
            ->assertSee('auth-card', false)
            ->assertSee(__('ui.auth_help_new_password'));
    }

    #[Test]
    public function catalog_interest_sort_and_flagged_filter_do_not_500(): void
    {
        $this->seed();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $hot = Course::query()->create([
            'code' => 'HOT1',
            'title' => 'Flagged Course',
            'credit_hours' => 1,
            'is_free' => true,
            'is_standalone' => true,
            'active' => true,
        ]);
        $cold = Course::query()->create([
            'code' => 'COLD1',
            'title' => 'Quiet Course',
            'credit_hours' => 1,
            'is_free' => false,
            'is_standalone' => true,
            'active' => true,
        ]);

        CourseInterestFlag::query()->create([
            'student_id' => $student->id,
            'course_id' => $hot->id,
        ]);

        $this->get(route('catalog.index', ['sort' => 'interest']))
            ->assertOk()
            ->assertSee('HOT1')
            ->assertSee('COLD1')
            ->assertSeeInOrder(['HOT1', 'COLD1'])
            ->assertSee('aria-busy="false"', false);

        $this->get(route('catalog.index', ['interest' => 'flagged']))
            ->assertOk()
            ->assertSee('HOT1')
            ->assertSee('COLD1');

        $this->actingAs($student)
            ->get(route('catalog.index', ['interest' => 'flagged']))
            ->assertOk()
            ->assertSee('HOT1')
            ->assertDontSee('COLD1');

        $this->get(route('catalog.index', ['q' => 'NOMATCHXYZ']))
            ->assertOk()
            ->assertSee(__('catalog.empty'))
            ->assertSee(__('catalog.empty_hint'));
    }

    #[Test]
    public function existing_free_paid_filters_still_work(): void
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
    public function guest_can_open_catalog_and_offering_preview(): void
    {
        $this->seed();
        $course = Course::query()->create([
            'code' => 'PREV1',
            'title' => 'Preview Path',
            'credit_hours' => 1,
            'is_free' => true,
            'is_standalone' => true,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Open,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('catalog.index'), false);

        $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('PREV1')
            ->assertSee(route('offerings.preview', $offering), false);

        $this->get(route('offerings.preview', $offering))
            ->assertOk();
    }
}
