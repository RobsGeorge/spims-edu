<?php

namespace Tests\Feature\Offerings;

use App\Enums\ContentItemType;
use App\Enums\OfferingMode;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Models\Week;
use App\Services\Offerings\ContentGatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentGatingAndPricingTest extends TestCase
{
    use RefreshDatabase;

    private function makeSelfPacedOffering(): CourseOffering
    {
        $course = Course::query()->create([
            'code' => 'SP01',
            'title' => 'Self Paced',
            'credit_hours' => 2,
            'default_price_usd' => 2000,
            'default_price_egp' => 10000,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'price_usd_override' => 1500,
        ]);

        $week1 = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Intro',
            'order' => 1,
        ]);
        Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 2,
            'title' => 'Next',
            'order' => 2,
        ]);

        $week1->items()->create([
            'type' => ContentItemType::Video,
            'title' => 'Welcome video',
            'order' => 1,
            'vimeo_id' => '12345',
        ]);

        return $offering->fresh(['weeks.items', 'course']);
    }

    #[Test]
    public function public_preview_shows_week_one_and_all_titles(): void
    {
        $offering = $this->makeSelfPacedOffering();

        $this->getJson(route('api.offerings.preview', $offering))
            ->assertOk()
            ->assertJsonPath('week_one.0.title', 'Welcome video')
            ->assertJsonCount(2, 'week_titles');

        $this->get(route('offerings.preview', $offering))
            ->assertOk()
            ->assertSee('Welcome video')
            ->assertSee('Next');
    }

    #[Test]
    public function self_paced_gating_requires_prior_completion(): void
    {
        $offering = $this->makeSelfPacedOffering();
        $gating = app(ContentGatingService::class);
        $week1 = $offering->weeks->firstWhere('number', 1);
        $week2 = $offering->weeks->firstWhere('number', 2);

        $this->assertTrue($gating->isWeekUnlocked($offering, $week1, enrolled: true));
        $this->assertFalse($gating->isWeekUnlocked($offering, $week2, enrolled: true, completedWeekNumbers: []));
        $this->assertTrue($gating->isWeekUnlocked($offering, $week2, enrolled: true, completedWeekNumbers: [1]));
        $this->assertFalse($gating->isWeekUnlocked($offering, $week2, enrolled: false));
    }

    #[Test]
    public function cohort_gating_uses_unlock_date(): void
    {
        $course = Course::query()->create(['code' => 'C1', 'title' => 'C', 'credit_hours' => 1, 'active' => true]);
        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::Cohort,
        ]);
        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 2,
            'title' => 'Later',
            'order' => 2,
            'unlock_date' => now()->addDays(7),
        ]);

        $gating = app(ContentGatingService::class);
        $this->assertFalse($gating->isWeekUnlocked($offering, $week, enrolled: true, now: now()));
        $this->assertTrue($gating->isWeekUnlocked($offering, $week, enrolled: true, now: now()->addDays(8)));
    }

    #[Test]
    public function fin_can_set_pricing_overrides_and_api_resolves_region(): void
    {
        $fin = User::factory()->withRole(RoleType::FinancialAdmin)->create();
        $offering = $this->makeSelfPacedOffering();

        $this->actingAs($fin)->post(route('admin.offerings.pricing', $offering), [
            'price_usd_override' => 1800,
            'price_egp_override' => 9000,
        ])->assertRedirect();

        $this->getJson(route('api.offerings.pricing', $offering))
            ->assertOk()
            ->assertJsonPath('usd_minor', 1800)
            ->assertJsonPath('egp_minor', 9000)
            ->assertJsonPath('for_eg.amount_minor', 9000)
            ->assertJsonPath('for_us.amount_minor', 1800);
    }

    #[Test]
    public function aca_cannot_set_pricing(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $offering = $this->makeSelfPacedOffering();

        $this->actingAs($aca)->post(route('admin.offerings.pricing', $offering), [
            'price_usd_override' => 1,
        ])->assertForbidden();
    }

    #[Test]
    public function content_can_be_added_by_academic_admin(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $offering = $this->makeSelfPacedOffering();
        $week = $offering->weeks->firstWhere('number', 1);

        $this->actingAs($aca)->post(route('admin.weeks.items', $week), [
            'type' => ContentItemType::Text->value,
            'title' => 'Reading notes',
            'body' => 'Lesson body',
        ])->assertRedirect();

        $this->assertDatabaseHas('content_items', [
            'week_id' => $week->id,
            'title' => 'Reading notes',
        ]);
    }
}
