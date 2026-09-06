<?php

namespace Tests\Feature\StaffUi;

use App\Models\FeedbackSurvey;
use App\Services\Feedback\FeedbackSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Feedback\FeedbackFixtures;
use Tests\TestCase;

class StaffSurveyUiTest extends TestCase
{
    use FeedbackFixtures;
    use RefreshDatabase;

    #[Test]
    public function instructor_creates_and_publishes_a_survey_on_a_staffed_offering(): void
    {
        $actors = $this->offeringActors('S8SV');

        $this->actingAs($actors['instructor'])
            ->post(route('teach.surveys.store', $actors['offering']), [
                'title' => 'Midterm eval',
                'anonymous_default' => 1,
            ])
            ->assertRedirect();

        $survey = FeedbackSurvey::query()->where('title', 'Midterm eval')->firstOrFail();
        $this->assertTrue($survey->isDraft());
        $this->assertSame($actors['offering']->id, $survey->offering_id);

        $this->actingAs($actors['instructor'])
            ->post(route('teach.surveys.questions.store', [$actors['offering'], $survey]), [
                'prompt' => 'How was the course?',
                'kind' => 'TEXT',
                'required' => 1,
            ])
            ->assertRedirect();

        $this->actingAs($actors['instructor'])
            ->post(route('teach.surveys.publish', [$actors['offering'], $survey]))
            ->assertRedirect();

        $this->assertTrue($survey->fresh()->isPublished());

        $this->actingAs($actors['instructor'])
            ->get(route('teach.surveys.show', [$actors['offering'], $survey]))
            ->assertOk()
            ->assertSee('Midterm eval')
            ->assertSee('How was the course?');
    }

    #[Test]
    public function student_cannot_get_the_manage_page(): void
    {
        $actors = $this->offeringActors('S8S2');
        $survey = $this->draftSurvey($actors['instructor'], $actors['offering']);

        $this->actingAs($actors['student'])
            ->get(route('teach.surveys.index', $actors['offering']))
            ->assertForbidden();

        $this->actingAs($actors['student'])
            ->get(route('teach.surveys.show', [$actors['offering'], $survey]))
            ->assertForbidden();
    }

    #[Test]
    public function report_does_not_include_student_id(): void
    {
        $actors = $this->offeringActors('S8S3');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $question = $this->firstQuestion($survey);
        app(FeedbackSubmissionService::class)->submit($actors['student'], $survey, [
            $question->id => 'Great course',
        ]);

        $this->actingAs($actors['instructor'])
            ->get(route('teach.surveys.report', [$actors['offering'], $survey]))
            ->assertOk()
            ->assertSee('Great course')
            ->assertDontSee($actors['student']->id, false)
            ->assertDontSee('student_id', false);
    }

    #[Test]
    public function staff_locale_keys_exist_in_ar_en_fr(): void
    {
        foreach (['ar', 'en', 'fr'] as $locale) {
            $this->assertTrue(Lang::has('staff.surveys.title', $locale), $locale);
            $this->assertNotSame('staff.surveys.title', __('staff.surveys.title', [], $locale));
        }
    }
}
