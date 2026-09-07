<?php

namespace Tests\Feature\SuperAdmin;

use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Week;
use App\Services\SuperAdmin\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function turning_learn_off_404s_the_player_and_hides_learning_nav(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $offering = $this->enrolledOffering($student);

        $this->actingAs($student)->get(route('courses.player', $offering))->assertOk();
        $this->actingAs($student)->get(route('learn.offering', $offering))->assertOk();
        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('hubs.nav_learning'))
            ->assertSee(__('dashboard.learning_hub'));

        $this->actingAs($sa)
            ->from(route('superadmin.features'))
            ->put(route('superadmin.features.update', 'learn'), ['enabled' => '0'])
            ->assertRedirect();

        $this->assertFalse(app(FeatureFlagService::class)->enabled('learn'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'features.toggle',
            'actor_id' => $sa->id,
            'entity_id' => 'learn',
        ]);

        $this->actingAs($student)->get(route('courses.player', $offering))->assertNotFound();
        $this->actingAs($student)->get(route('learn.offering', $offering))->assertNotFound();
        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('hubs.nav_learning'))
            ->assertDontSee(__('dashboard.learning_hub'));

        $this->actingAs($sa)->get(route('superadmin.index'))->assertOk();
        $this->actingAs($sa)->get(route('superadmin.features'))
            ->assertOk()
            ->assertSee(__('features.danger_title'))
            ->assertSee(__('features.learn_gates'));
    }

    #[Test]
    public function registration_off_404s_signup_and_hides_the_register_cta(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->get(route('auth.register'))->assertOk();
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(__('ui.home_cta_primary'));
        $this->get(route('auth.login'))
            ->assertOk()
            ->assertSee(__('ui.register'));

        $this->actingAs($sa)
            ->put(route('superadmin.features.update', 'registration'), ['enabled' => '0'])
            ->assertRedirect();

        $this->get(route('auth.register'))->assertNotFound();
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee(__('ui.home_cta_primary'))
            ->assertSee(__('ui.home_cta_secondary'));
        $this->get(route('auth.login'))
            ->assertOk()
            ->assertDontSee('href="'.route('auth.register').'"', false);
    }

    #[Test]
    public function features_page_has_entrances_from_existing_pages_and_rejects_outsiders(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();
        $adm = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($sa)->get(route('superadmin.features'))
            ->assertOk()
            ->assertSee(__('features.page_lead'))
            ->assertSee(__('features.page_help'))
            ->assertSee(__('features.group_public_help'))
            ->assertSee(__('features.what_it_gates'))
            ->assertSee(__('features.confirm_off'))
            ->assertSee(__('features.open_config'))
            ->assertSee(route('superadmin.config'), false);

        $this->actingAs($sa)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('features.dashboard_tile'))
            ->assertSee(__('features.dashboard_tile_hint'))
            ->assertSee(route('superadmin.features'), false);

        $this->actingAs($sa)->get(route('settings.edit'))
            ->assertOk()
            ->assertSee(__('features.entrance_from_settings'))
            ->assertSee(__('system_settings.entrance_from_settings'));

        $this->actingAs($sa)->get(route('hubs.learning'))
            ->assertOk()
            ->assertSee(__('features.entrance_from_learning'));

        $this->actingAs($sa)->get(route('hubs.admin'))
            ->assertOk()
            ->assertSee(__('features.entrance_title'));

        $this->actingAs($student)->get(route('superadmin.features'))->assertForbidden();
        $this->actingAs($adm)->get(route('superadmin.features'))->assertForbidden();
        $this->actingAs($student)->put(route('superadmin.features.update', 'learn'), ['enabled' => '0'])
            ->assertForbidden();
    }

    #[Test]
    public function unknown_feature_key_is_rejected(): void
    {
        $this->seed();
        $sa = User::query()->where('email', env('SUPERADMIN_EMAIL'))->firstOrFail();

        $this->actingAs($sa)
            ->putJson(route('superadmin.features.update', 'not-a-flag'), ['enabled' => true])
            ->assertUnprocessable();
    }

    private function enrolledOffering(User $student): CourseOffering
    {
        $course = Course::query()->create([
            'code' => 'SA3L',
            'title' => 'Learn Flag Course',
            'credit_hours' => 1,
            'active' => true,
        ]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week 1',
            'order' => 1,
        ]);
        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
            'progress_percent' => 0,
        ]);

        return $offering;
    }
}
