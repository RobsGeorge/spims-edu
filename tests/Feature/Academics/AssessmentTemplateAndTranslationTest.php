<?php

namespace Tests\Feature\Academics;

use App\Enums\ComponentKind;
use App\Enums\RoleType;
use App\Enums\TranslationSource;
use App\Models\AssessmentTemplate;
use App\Models\Translation;
use App\Models\User;
use App\Services\Academics\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssessmentTemplateAndTranslationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function template_weights_must_sum_to_100(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $this->actingAs($aca)->post(route('admin.assessment-templates.store'), [
            'name' => 'Bad',
            'components' => [
                ['name' => 'Exam', 'weight_percent' => 50, 'kind' => ComponentKind::Exam->value],
                ['name' => 'Quiz', 'weight_percent' => 20, 'kind' => ComponentKind::Quiz->value],
            ],
        ])->assertSessionHasErrors('components');
    }

    #[Test]
    public function valid_template_is_created_with_components(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $this->actingAs($aca)->post(route('admin.assessment-templates.store'), [
            'name' => 'Default Rollup',
            'is_default' => true,
            'components' => [
                ['name' => 'Exam', 'weight_percent' => 60, 'kind' => ComponentKind::Exam->value],
                ['name' => 'Attendance', 'weight_percent' => 20, 'kind' => ComponentKind::Attendance->value],
                ['name' => 'Assignments', 'weight_percent' => 20, 'kind' => ComponentKind::Assignment->value],
            ],
        ])->assertRedirect();

        $template = AssessmentTemplate::query()->where('name', 'Default Rollup')->first();
        $this->assertTrue($template->is_default);
        $this->assertCount(3, $template->components);
        $this->assertEquals(100, $template->components->sum('weight_percent'));
    }

    #[Test]
    public function translation_can_be_saved_and_verified(): void
    {
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $this->actingAs($aca)->post(route('admin.translations.store'), [
            'entity_type' => 'Course',
            'entity_id' => '01TESTENTITY000000000000000',
            'field' => 'title',
            'locale' => 'ar',
            'value' => 'تاريخ الكنيسة',
            'verified' => false,
        ])->assertRedirect();

        $translation = Translation::query()->first();
        $this->assertSame(TranslationSource::Human, $translation->source);
        $this->assertFalse($translation->verified);

        $this->actingAs($aca)->post(route('admin.translations.verify', $translation))->assertRedirect();
        $this->assertTrue($translation->fresh()->verified);
    }

    #[Test]
    public function ai_translation_degrades_without_api_key(): void
    {
        config(['services.gemini.key' => null]);
        $aca = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $result = app(TranslationService::class)->requestAiTranslation(
            $aca,
            'Course',
            '01TESTENTITY000000000000000',
            'title',
            'en',
            'ar',
            'Church History'
        );

        $this->assertNull($result);
        $this->assertSame(0, Translation::query()->count());
    }
}
