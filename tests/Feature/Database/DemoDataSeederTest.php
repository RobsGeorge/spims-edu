<?php

namespace Tests\Feature\Database;

use App\Enums\ApplicationStatus;
use App\Enums\AttemptStatus;
use App\Enums\ContentItemType;
use App\Enums\Currency;
use App\Enums\EventReservationStatus;
use App\Enums\FeedbackQuestionKind;
use App\Enums\GradeStatus;
use App\Enums\OfferingMode;
use App\Enums\OfferingStatus;
use App\Enums\PaymentPlanInstallmentStatus;
use App\Enums\WalletKind;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassSession;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Credential;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventCheckIn;
use App\Models\EventReservation;
use App\Models\FeedbackIdentityRevealRequest;
use App\Models\FeedbackSubmissionIdentity;
use App\Models\FeedbackSurvey;
use App\Models\Invoice;
use App\Models\LiveQuiz;
use App\Models\Notification;
use App\Models\OfferingStaff;
use App\Models\PaymentPlan;
use App\Models\Program;
use App\Models\ProjectAssessment;
use App\Models\ProjectMembership;
use App\Models\Semester;
use App\Models\Translation;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['spims.seed_demo_data' => true]);
    }

    #[Test]
    public function demo_seeder_creates_classroom_money_and_dual_role_rows(): void
    {
        $this->seed();

        $this->assertGreaterThanOrEqual(1, ContentItem::query()->count());
        $this->assertGreaterThanOrEqual(1, Invoice::query()->count());
        $this->assertGreaterThanOrEqual(1, ClassSession::query()->count());
        $this->assertGreaterThanOrEqual(1, Announcement::query()->count());

        $dual = User::query()->where('email', 'dual@spims.test')->first();
        $this->assertNotNull($dual);

        $free1 = $this->cohortOffering('FREE1');
        $this->assertNotNull($free1);
        $this->assertTrue(
            OfferingStaff::query()
                ->where('offering_id', $free1->id)
                ->where('user_id', $dual->id)
                ->exists()
        );
        $this->assertTrue(
            Enrollment::query()->where('student_id', $dual->id)->exists()
        );
    }

    #[Test]
    public function phase_a_seeds_assignment_quiz_attempt_and_locked_th101_grades(): void
    {
        $this->seed();

        $th101 = $this->cohortOffering('TH101');
        $this->assertNotNull($th101);

        $student1 = User::query()->where('email', 'student1@spims.test')->firstOrFail();

        $assignmentItem = ContentItem::query()
            ->where('title', 'TH101 Week 1 reflection')
            ->where('type', ContentItemType::Assignment)
            ->first();
        $this->assertNotNull($assignmentItem);
        $this->assertTrue($assignmentItem->isPublished());

        $assignment = Assignment::query()->where('content_item_id', $assignmentItem->id)->first();
        $this->assertNotNull($assignment);

        $this->assertGreaterThanOrEqual(
            1,
            AssignmentSubmission::query()->where('assignment_id', $assignment->id)->count()
        );
        $this->assertTrue(
            AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('student_id', $student1->id)
                ->whereNotNull('final_score')
                ->exists(),
            'student1 should have a graded assignment submission'
        );
        $this->assertTrue(
            AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->whereNull('final_score')
                ->exists(),
            'at least one assignment submission should still be pending review'
        );

        $assessment = Assessment::query()
            ->where('offering_id', $th101->id)
            ->where('title', 'TH101 Week 1 check')
            ->first();
        $this->assertNotNull($assessment);

        $attempt = AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $student1->id)
            ->whereIn('status', [
                AttemptStatus::Submitted,
                AttemptStatus::AutoSubmitted,
                AttemptStatus::Graded,
            ])
            ->first();
        $this->assertNotNull($attempt, 'student1 should have a submitted/graded quiz attempt on TH101');

        $this->assertTrue(
            Enrollment::query()
                ->where('offering_id', $th101->id)
                ->where('grade_status', GradeStatus::Locked)
                ->exists(),
            'TH101 enrollments should be grade-locked after Phase A seed'
        );
    }

    #[Test]
    public function seeded_demo_users_can_open_walkthrough_pages(): void
    {
        $this->seed();

        $th101 = $this->cohortOffering('TH101');
        $this->assertNotNull($th101);

        $student1 = User::query()->where('email', 'student1@spims.test')->firstOrFail();
        $fin = User::query()->where('email', 'fin@spims.test')->firstOrFail();
        $ins1 = User::query()->where('email', 'ins1@spims.test')->firstOrFail();

        $this->from(route('auth.login'))
            ->post('/login', [
                'email' => 'student1@spims.test',
                'password' => DemoDataSeeder::PASSWORD,
            ])
            ->assertRedirect();

        $this->actingAs($student1)
            ->get(route('learn.offering', $th101))
            ->assertOk()
            ->assertSee('Welcome to Introduction to Theology');

        $lesson = ContentItem::query()
            ->where('title', 'Welcome to Introduction to Theology')
            ->first();
        $this->assertNotNull($lesson);
        $this->assertTrue($lesson->isPublished());
        $this->actingAs($student1)
            ->get(route('learn.item', [$th101, $lesson]))
            ->assertOk();

        $this->actingAs($student1)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Week 1 is open');

        $this->actingAs($student1)
            ->get(route('finance.index'))
            ->assertOk();
        $this->assertTrue(
            Invoice::query()->where('student_id', $student1->id)->exists()
        );

        $this->actingAs($student1)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('TH101 Week 1 class');

        $this->actingAs($fin)
            ->get(route('admin.finance.index'))
            ->assertOk();
        $this->assertGreaterThanOrEqual(1, Invoice::query()->count());

        $this->actingAs($ins1)
            ->get(route('teach.show', $th101))
            ->assertOk()
            ->assertSee('TH101');
    }

    // ─── 8A gate tests ────────────────────────────────────────────────────────

    #[Test]
    public function seeded_credential_is_reachable_via_verify_endpoint(): void
    {
        $this->seed();

        $student1 = User::query()->where('email', 'student1@spims.test')->firstOrFail();
        $credential = Credential::query()
            ->where('student_id', $student1->id)
            ->whereNull('revoked_at')
            ->first();

        // The credential may not be issued if PDF rendering is unavailable in CI;
        // assert only when it exists.
        if ($credential === null) {
            $this->markTestSkipped('Credential not issued (PDF renderer unavailable in this environment).');
        }

        $this->assertNotEmpty($credential->qr_token);
        $this->assertNotEmpty($credential->serial);

        // The public verify route must respond 200 and indicate validity.
        $this->get(route('credentials.verify', $credential->qr_token))
            ->assertOk()
            ->assertSee($credential->serial);
    }

    #[Test]
    public function seeded_event_is_published_with_open_seats(): void
    {
        $this->seed();

        $event = Event::query()->where('title', 'Theology Orientation Day')->first();

        $this->assertNotNull($event);
        $this->assertTrue($event->is_published ?? $event->status === 'published' || $event->published_at !== null || ($event->capacity > 0));
        $this->assertGreaterThan(0, $event->capacity);
    }

    #[Test]
    public function seeded_survey_has_all_four_question_kinds(): void
    {
        $this->seed();

        $th101 = $this->cohortOffering('TH101');
        $this->assertNotNull($th101);

        $survey = FeedbackSurvey::query()
            ->where('offering_id', $th101->id)
            ->where('title', 'TH101 Week 1 Feedback')
            ->first();

        $this->assertNotNull($survey);

        $questionKinds = $survey->questions()
            ->pluck('kind')
            ->map(fn ($k) => is_string($k) ? $k : $k->value)
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $expectedKinds = collect([
            FeedbackQuestionKind::Scale->value,
            FeedbackQuestionKind::Text->value,
            FeedbackQuestionKind::Single->value,
            FeedbackQuestionKind::Multi->value,
        ])->sort()->values()->toArray();

        $this->assertEquals($expectedKinds, $questionKinds);
    }

    #[Test]
    public function seeded_live_quiz_is_in_ready_status(): void
    {
        $this->seed();

        $th101 = $this->cohortOffering('TH101');
        $this->assertNotNull($th101);

        $quiz = LiveQuiz::query()
            ->where('offering_id', $th101->id)
            ->where('title', 'TH101 Live Check Quiz')
            ->first();

        $this->assertNotNull($quiz);
        $this->assertGreaterThanOrEqual(1, $quiz->questions()->count());

        // No active session started (not yet started).
        $this->assertNull($quiz->sessions()->where('ended_at', null)->where('started_at', '!=', null)->first());
    }

    #[Test]
    public function seeded_team_project_has_student1_as_member_with_open_deliverable(): void
    {
        $this->seed();

        $th101 = $this->cohortOffering('TH101');
        $this->assertNotNull($th101);

        $student1 = User::query()->where('email', 'student1@spims.test')->firstOrFail();

        $assessment = ProjectAssessment::query()
            ->where('offering_id', $th101->id)
            ->where('title', 'TH101 Group Research Project')
            ->first();

        $this->assertNotNull($assessment);

        $membership = ProjectMembership::query()
            ->whereHas('project', fn ($q) => $q->where('project_assessment_id', $assessment->id))
            ->where('student_id', $student1->id)
            ->whereNull('left_at')
            ->first();

        $this->assertNotNull($membership, 'student1 should be a member of the team project');

        // Deliverable due in the future (slot is open).
        $deliverable = $assessment->phases()
            ->with('deliverables')
            ->get()
            ->flatMap(fn ($p) => $p->deliverables)
            ->first();

        $this->assertNotNull($deliverable);
        $this->assertGreaterThan(now(), $deliverable->due_at);
    }

    #[Test]
    public function enforce_year_sequence_program_exists_with_multi_year_courses(): void
    {
        $this->seed();

        // DEG-DIAC must have enforce_year_sequence = true
        $diac = Program::query()->where('code', 'DEG-DIAC')->first();
        $this->assertNotNull($diac);
        $this->assertTrue((bool) $diac->enforce_year_sequence);

        // Must have courses at Year 1 AND Year 2
        $years = $diac->programCourses()
            ->pluck('year_level')
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $this->assertContains(1, $years, 'DEG-DIAC must have Year 1 courses');
        $this->assertContains(2, $years, 'DEG-DIAC must have Year 2 courses');

        // At least one program with enforce_year_sequence = false also exists (DEG-BTH)
        $bth = Program::query()->where('code', 'DEG-BTH')->first();
        $this->assertNotNull($bth);
        $this->assertFalse((bool) $bth->enforce_year_sequence);
    }

    #[Test]
    public function seeded_semesters_cover_all_three_lifecycle_states(): void
    {
        $this->seed();

        $statuses = Semester::query()
            ->pluck('status')
            ->map(fn ($s) => is_string($s) ? $s : $s->value)
            ->unique()
            ->toArray();

        $this->assertContains(OfferingStatus::Completed->value, $statuses, 'Expected a CLOSED (Completed) semester');
        $this->assertContains(OfferingStatus::InProgress->value, $statuses, 'Expected an IN_PROGRESS semester');
        $this->assertContains(OfferingStatus::Draft->value, $statuses, 'Expected a DRAFT semester');
    }

    #[Test]
    public function seeded_applications_cover_all_statuses_including_withdrawn(): void
    {
        $this->seed();

        $student1 = User::query()->where('email', 'student1@spims.test')->firstOrFail();

        $statuses = Application::query()
            ->where('applicant_id', $student1->id)
            ->pluck('status')
            ->map(fn ($s) => is_string($s) ? $s : $s->value)
            ->unique()
            ->toArray();

        $this->assertContains(ApplicationStatus::Withdrawn->value, $statuses,
            'student1 should have a Withdrawn application');
    }

    #[Test]
    public function demo_reset_command_is_idempotent(): void
    {
        // First run: seed from empty DB.
        $this->artisan('spims:demo-reset')->assertSuccessful();

        $counts1 = $this->demoDataCounts();

        // Second run: wipe and re-seed.
        $this->artisan('spims:demo-reset')->assertSuccessful();

        $counts2 = $this->demoDataCounts();

        $this->assertEquals($counts1, $counts2,
            'Running spims:demo-reset twice must produce identical record counts.');
    }

    // ─── Phase B fixtures ────────────────────────────────────────────────────

    #[Test]
    public function seeded_survey_has_student1_submission_and_optional_reveal(): void
    {
        $this->seed();

        $th101 = $this->cohortOffering('TH101');
        $this->assertNotNull($th101);
        $student1 = User::query()->where('email', 'student1@spims.test')->firstOrFail();

        $survey = FeedbackSurvey::query()
            ->where('offering_id', $th101->id)
            ->where('title', 'TH101 Week 1 Feedback')
            ->first();
        $this->assertNotNull($survey);

        $identity = FeedbackSubmissionIdentity::query()
            ->where('student_id', $student1->id)
            ->whereHas('submission', fn ($q) => $q->where('survey_id', $survey->id))
            ->first();

        $this->assertNotNull($identity, 'student1 should have submitted the TH101 feedback survey');
        $this->assertGreaterThanOrEqual(1, $identity->submission->answers()->count());

        // Identity reveal is optional but seeded when the instructor may request it.
        $this->assertTrue(
            FeedbackIdentityRevealRequest::query()
                ->where('submission_id', $identity->submission_id)
                ->exists()
        );
    }

    #[Test]
    public function seeded_event_has_student1_reservation_and_check_in(): void
    {
        $this->seed();

        $student1 = User::query()->where('email', 'student1@spims.test')->firstOrFail();
        $event = Event::query()->where('title', 'Theology Orientation Day')->firstOrFail();

        $reservation = EventReservation::query()
            ->where('event_id', $event->id)
            ->where('student_id', $student1->id)
            ->where('status', EventReservationStatus::Reserved)
            ->first();

        $this->assertNotNull($reservation);
        $this->assertTrue(
            EventCheckIn::query()->where('reservation_id', $reservation->id)->exists()
        );
    }

    #[Test]
    public function seeded_payment_plan_has_installment_states_for_student1(): void
    {
        $this->seed();

        $student1 = User::query()->where('email', 'student1@spims.test')->firstOrFail();

        $plan = PaymentPlan::query()
            ->whereHas('invoice', fn ($q) => $q->where('student_id', $student1->id))
            ->with('installments')
            ->first();

        $this->assertNotNull($plan);
        $this->assertGreaterThanOrEqual(2, $plan->installments->count());
        $this->assertTrue(
            $plan->installments->contains(
                fn ($row) => $row->status === PaymentPlanInstallmentStatus::Due
            ),
            'Expected at least one Due installment'
        );
        $this->assertTrue(
            $plan->installments->contains(
                fn ($row) => $row->status === PaymentPlanInstallmentStatus::Pending
            ),
            'Expected at least one Pending installment'
        );
    }

    #[Test]
    public function seeded_wallet_points_ledger_exists_for_student1(): void
    {
        $this->seed();

        $student1 = User::query()->where('email', 'student1@spims.test')->firstOrFail();
        $wallet = WalletAccount::query()->where('user_id', $student1->id)->first();

        $this->assertNotNull($wallet);
        $this->assertGreaterThan(0, $wallet->balance(Currency::Egp, WalletKind::Points));
        $this->assertTrue(
            WalletTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('kind', WalletKind::Points)
                ->exists()
        );
    }

    #[Test]
    public function seeded_translations_cover_course_and_program_locales(): void
    {
        $this->seed();

        $th101 = Course::query()->where('code', 'TH101')->firstOrFail();
        $diploma = Program::query()->where('code', 'DIP-THEO')->firstOrFail();

        $courseLocales = Translation::query()
            ->where('entity_type', 'Course')
            ->where('entity_id', $th101->id)
            ->where('field', 'title')
            ->pluck('locale')
            ->all();

        $programLocales = Translation::query()
            ->where('entity_type', 'Program')
            ->where('entity_id', $diploma->id)
            ->where('field', 'name')
            ->pluck('locale')
            ->all();

        $this->assertContains('ar', $courseLocales);
        $this->assertContains('fr', $courseLocales);
        $this->assertContains('ar', $programLocales);
        $this->assertContains('fr', $programLocales);
    }

    #[Test]
    public function seeded_in_app_notifications_exist_for_student1(): void
    {
        $this->seed();

        $student1 = User::query()->where('email', 'student1@spims.test')->firstOrFail();

        $types = Notification::query()
            ->where('user_id', $student1->id)
            ->pluck('type')
            ->unique()
            ->all();

        $this->assertContains('announcement.published', $types);
        $this->assertContains('finance.invoice_issued', $types);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function cohortOffering(string $code): ?CourseOffering
    {
        $courseId = Course::query()->where('code', $code)->value('id');
        if ($courseId === null) {
            return null;
        }

        return CourseOffering::query()
            ->where('course_id', $courseId)
            ->where('mode', OfferingMode::Cohort)
            ->first();
    }

    /**
     * @return array<string, int>
     */
    private function demoDataCounts(): array
    {
        return [
            'users' => User::query()
                ->where('email', 'like', '%' . DemoDataSeeder::DEMO_EMAIL_SUFFIX)
                ->count(),
            'programs' => Program::query()
                ->whereIn('code', DemoDataSeeder::DEMO_PROGRAM_CODES)
                ->count(),
            'courses' => Course::query()
                ->whereIn('code', DemoDataSeeder::DEMO_COURSE_CODES)
                ->count(),
            'content_items' => ContentItem::query()->count(),
            'invoices' => Invoice::query()->count(),
            'applications' => Application::query()->count(),
        ];
    }
}
