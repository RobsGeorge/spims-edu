<?php

namespace Tests\Feature\Api;

use App\Services\Assessment\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstructorGradingScopeTest extends TestCase
{
    use InstructorGradingFixtures;
    use RefreshDatabase;

    #[Test]
    public function instructor_on_offering_a_cannot_grade_offering_b_submission(): void
    {
        $a = $this->gradingBundle('S8S1');

        $offeringB = $this->offering('S8S2');
        $this->staffedInstructor($offeringB);
        $studentB = $this->student();
        $this->enroll($studentB, $offeringB);
        $assignmentB = $this->assignmentOn($offeringB);
        $submissionB = app(AssignmentService::class)->submit($studentB, $assignmentB, 'theirs');

        $this->asApi($a['instructor'], 'INSTRUCTOR')
            ->postJson(route('api.v1.teach.submissions.grade', $submissionB), [
                'raw_score' => 50,
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    #[Test]
    public function instructor_on_offering_a_cannot_read_offering_b_gradebook(): void
    {
        $a = $this->gradingBundle('S8S3');
        $b = $this->offering('S8S4');
        $this->staffedInstructor($b);

        $this->asApi($a['instructor'], 'INSTRUCTOR')
            ->getJson(route('api.v1.teach.offerings.gradebook', $b))
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }
}
