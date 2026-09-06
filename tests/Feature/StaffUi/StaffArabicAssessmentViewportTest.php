<?php

namespace Tests\Feature\StaffUi;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\InstructorGradingFixtures;
use Tests\TestCase;

class StaffArabicAssessmentViewportTest extends TestCase
{
    use InstructorGradingFixtures;
    use RefreshDatabase;
    use StaffArabicViewportAssertions;

    #[Test]
    public function arabic_attempts_page_is_rtl_and_keeps_the_confirm_dialog(): void
    {
        $bundle = $this->gradingBundle('ARAS');
        $instructor = $this->arabic($bundle['instructor']);
        $attempt = $this->submittedAttempt($instructor, $bundle['offering'], $bundle['student']);

        $page = $this->actingAs($instructor)
            ->get(route('teach.assessments.attempts', [$bundle['offering'], $attempt['assessment']]));
        $this->assertArabicShell($page);
        $page->assertSee(__('assessment.attempts'), false)
            ->assertSee(__('assessment.announce_results'), false)
            ->assertSee('announceResultsModal', false)
            ->assertSee('modal-dialog-scrollable', false)
            ->assertSee('spims-staff-row', false)
            ->assertSee($bundle['student']->first_name, false);
    }
}
