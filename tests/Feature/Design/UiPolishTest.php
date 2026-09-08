<?php

namespace Tests\Feature\Design;

use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\RoleType;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use App\Support\Ui\CourseCoverLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UiPolishTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_course_without_an_image_stores_an_unsplash_cover(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $this->actingAs($aca)->post(route('admin.courses.store'), [
            'code' => 'img101',
            'title' => 'Patristics without photo',
            'credit_hours' => 3,
            'default_price_usd' => 0,
            'default_price_egp' => 0,
            'is_standalone' => true,
        ])->assertRedirect(route('admin.courses.index'));

        $course = Course::query()->where('code', 'IMG101')->first();
        $this->assertNotNull($course);
        $this->assertNotEmpty($course->cover_image_url);
        $this->assertTrue(CourseCoverLibrary::isLibraryUrl($course->cover_image_url));
        $this->assertSame(CourseCoverLibrary::urlForSeed('IMG101'), $course->cover_image_url);
    }

    #[Test]
    public function creating_a_course_with_a_cover_url_keeps_it(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $custom = 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=1200&h=675&q=80';

        $this->actingAs($aca)->post(route('admin.courses.store'), [
            'code' => 'img202',
            'title' => 'With cover',
            'credit_hours' => 2,
            'cover_image_url' => $custom,
        ])->assertRedirect(route('admin.courses.index'));

        $course = Course::query()->where('code', 'IMG202')->first();
        $this->assertSame($custom, $course->cover_image_url);
    }

    #[Test]
    public function catalog_renders_course_photos_heading_icon_and_system_loader(): void
    {
        $this->seed();
        $course = Course::query()->create([
            'code' => 'POLISH1',
            'title' => 'Polish Featured Course',
            'credit_hours' => 2,
            'is_free' => true,
            'is_standalone' => true,
            'active' => true,
        ]);
        CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => OfferingStatus::Open,
        ]);

        $html = $this->get(route('catalog.index'))
            ->assertOk()
            ->assertSee('spims-loader', false)
            ->assertSee('js/spims-ui.js', false)
            ->assertSee('spims-heading-icon-wrap', false)
            ->assertSee('bi-grid', false)
            ->assertSee('images.unsplash.com', false)
            ->assertSee('catalog-card-media', false)
            ->assertSee('spims-cover-photo', false)
            ->assertSee('Polish Featured Course')
            ->getContent();

        $this->assertStringContainsString('id="spims-loader"', $html);
        $this->assertStringContainsString(__('ui.loader_label'), $html);
        $this->assertStringContainsString('spims.session.entered', $html);
    }

    #[Test]
    public function dashboard_uses_section_headings_and_loader_script(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Mariam',
        ]);

        $this->actingAs($student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('spims-section-heading', false)
            ->assertSee('spims-loader', false)
            ->assertSee('js/spims-ui.js', false)
            ->assertSee('bi-house', false);
    }

    #[Test]
    public function theme_css_defines_loader_and_session_enter_motion(): void
    {
        $css = file_get_contents(public_path('css/spims-theme.css'));

        $this->assertStringContainsString('.spims-loader', $css);
        $this->assertStringContainsString('.spims-enter', $css);
        $this->assertStringContainsString('spims-session-rise', $css);
        $this->assertStringContainsString('.spims-heading-icon-wrap', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    #[Test]
    public function page_header_auto_icon_renders_for_learning_hub(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)->get(route('hubs.learning'))
            ->assertOk()
            ->assertSee('spims-heading-icon-wrap', false)
            ->assertSee('bi-book-half', false)
            ->assertSee(__('hubs.learning_title'));
    }
}
