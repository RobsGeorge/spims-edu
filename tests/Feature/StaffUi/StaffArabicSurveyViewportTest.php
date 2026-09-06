<?php

namespace Tests\Feature\StaffUi;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Feedback\FeedbackFixtures;
use Tests\TestCase;

class StaffArabicSurveyViewportTest extends TestCase
{
    use FeedbackFixtures;
    use RefreshDatabase;
    use StaffArabicViewportAssertions;

    #[Test]
    public function arabic_survey_pages_are_rtl_with_a_narrow_viewport_meta(): void
    {
        $actors = $this->offeringActors('ARSV');
        $this->arabic($actors['instructor']);
        $survey = $this->draftSurvey($actors['instructor'], $actors['offering']);

        $index = $this->actingAs($actors['instructor'])
            ->get(route('teach.surveys.index', $actors['offering']));
        $this->assertArabicShell($index);
        $index->assertSee(__('staff.surveys.title'), false)
            ->assertSee('spims-staff-row', false)
            ->assertSee('name="viewport" content="width=device-width, initial-scale=1"', false);

        $show = $this->actingAs($actors['instructor'])
            ->get(route('teach.surveys.show', [$actors['offering'], $survey]));
        $this->assertArabicShell($show);
        $show->assertSee(__('staff.surveys.publish'), false)
            ->assertSee(__('staff.surveys.add_question'), false);
    }
}
