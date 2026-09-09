<?php

namespace Tests\Feature\StaffUi;

use App\Enums\AssessmentMode;
use App\Enums\AttemptStatus;
use App\Enums\ComponentKind;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\QuestionType;
use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradebookComponentScore;
use App\Models\ProctorEvent;
use App\Models\User;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AttemptService;
use App\Services\Assessment\ProctorService;
use App\Services\Assessment\QuestionBankService;
use App\Services\Gradebook\GradebookService;
use Database\Seeders\GradingSchemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GradebookCellEntryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{offering: CourseOffering, instructor: User, student: User, enrollment: Enrollment, component: \App\Models\GradebookComponent}
     */
    private function cellBundle(string $code = 'C14'): array
    {
        $this->seed(GradingSchemeSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create([
            'first_name' => 'Miriam',
            'last_name' => 'Hanna',
        ]);

        $course = Course::query()->create([
            'code' => $code,
            'title' => "Test Course $code",
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        $this->staffOffering($instructor, $offering);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $component = app(GradebookService::class)->addComponent($instructor, $offering, [
            'name' => 'Participation',
            'weight_percent' => 100,
            'kind' => ComponentKind::Other->value,
        ]);

        return compact('offering', 'instructor', 'student', 'enrollment', 'component');
    }

    // ─── Part A: Locked gradebook ─────────────────────────────────────────

    #[Test]
    public function locked_gradebook_banner_appears_when_offering_has_gradebook_locked_at(): void
    {
        $b = $this->cellBundle('L14A');
        $b['offering']->update(['gradebook_locked_at' => now()]);

        $this->actingAs($b['instructor'])
            ->get(route('admin.gradebook.show', $b['offering']))
            ->assertOk()
            ->assertSee(__('assessment.gradebook_locked_banner_title'))
            ->assertSee('data-gradebook-locked-banner', false);
    }

    #[Test]
    public function no_locked_banner_when_gradebook_is_open(): void
    {
        $b = $this->cellBundle('L14B');

        $this->actingAs($b['instructor'])
            ->get(route('admin.gradebook.show', $b['offering']))
            ->assertOk()
            ->assertDontSee('data-gradebook-locked-banner', false);
    }

    #[Test]
    public function locked_gradebook_rejects_cell_write_with_422(): void
    {
        $b = $this->cellBundle('L14C');
        // Directly set offering-level lock (simulates what lockGrades() now does).
        $b['offering']->update(['gradebook_locked_at' => now()]);

        $this->actingAs($b['instructor'])
            ->postJson(route('admin.gradebook.cell', $b['offering']), [
                'student_id' => $b['student']->id,
                'component_id' => $b['component']->id,
                'score' => 80.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', __('assessment.gradebook_locked_banner_title'));
    }

    #[Test]
    public function lock_grades_sets_offering_gradebook_locked_at(): void
    {
        $b = $this->cellBundle('L14D');
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $this->assertNull($b['offering']->fresh()->gradebook_locked_at);

        app(GradebookService::class)->lockGrades($admin, $b['offering']);

        $this->assertNotNull($b['offering']->fresh()->gradebook_locked_at);
    }

    #[Test]
    public function reopen_clears_offering_gradebook_locked_at(): void
    {
        $b = $this->cellBundle('L14E');
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();

        $b['offering']->update(['gradebook_locked_at' => now()]);

        app(GradebookService::class)->reopen($admin, $b['offering']);

        $this->assertNull($b['offering']->fresh()->gradebook_locked_at);
    }

    // ─── Part A: Per-cell edit ────────────────────────────────────────────

    #[Test]
    public function cell_edit_stores_score_and_writes_audit_log_with_before_after(): void
    {
        $b = $this->cellBundle('C14A');

        $this->actingAs($b['instructor'])
            ->postJson(route('admin.gradebook.cell', $b['offering']), [
                'student_id' => $b['student']->id,
                'component_id' => $b['component']->id,
                'score' => 78.5,
            ])
            ->assertOk()
            ->assertJsonPath('message', __('assessment.cell_score_saved'));

        // Score stored as override
        $override = GradebookComponentScore::query()
            ->where('component_id', $b['component']->id)
            ->where('student_id', $b['student']->id)
            ->first();
        $this->assertNotNull($override);
        $this->assertEquals(78.5, $override->score);

        // Audit log created with before/after
        $log = AuditLog::query()->where('action', 'gradebook.cell_update')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->before['score']); // first write — no old value
        $this->assertEquals(78.5, $log->after['score']);
    }

    #[Test]
    public function second_cell_edit_records_old_value_in_audit_before(): void
    {
        $b = $this->cellBundle('C14B');

        // First write
        $this->actingAs($b['instructor'])
            ->postJson(route('admin.gradebook.cell', $b['offering']), [
                'student_id' => $b['student']->id,
                'component_id' => $b['component']->id,
                'score' => 60.0,
            ])
            ->assertOk();

        // Second write — old value should appear in `before`
        $this->actingAs($b['instructor'])
            ->postJson(route('admin.gradebook.cell', $b['offering']), [
                'student_id' => $b['student']->id,
                'component_id' => $b['component']->id,
                'score' => 90.0,
            ])
            ->assertOk();

        $secondLog = AuditLog::query()
            ->where('action', 'gradebook.cell_update')
            ->orderByDesc('created_at')
            ->first();

        $this->assertNotNull($secondLog);
        $this->assertEquals(60.0, $secondLog->before['score']);
        $this->assertEquals(90.0, $secondLog->after['score']);
    }

    #[Test]
    public function cell_edit_percent_and_letter_recompute_against_grading_scheme(): void
    {
        $b = $this->cellBundle('C14C');

        // A = 90-100, B = 80-89.99 per GradingSchemeSeeder
        $response = $this->actingAs($b['instructor'])
            ->postJson(route('admin.gradebook.cell', $b['offering']), [
                'student_id' => $b['student']->id,
                'component_id' => $b['component']->id,
                'score' => 85.0, // should resolve to letter B
            ])
            ->assertOk()
            ->json();

        $this->assertEquals(85.0, $response['percent']);
        $this->assertSame('B', $response['letter']);
    }

    #[Test]
    public function cell_edit_for_a_score_above_100_is_rejected(): void
    {
        $b = $this->cellBundle('C14D');

        $this->actingAs($b['instructor'])
            ->postJson(route('admin.gradebook.cell', $b['offering']), [
                'student_id' => $b['student']->id,
                'component_id' => $b['component']->id,
                'score' => 150.0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['score']);
    }

    #[Test]
    public function cell_edit_for_a_negative_score_is_rejected(): void
    {
        $b = $this->cellBundle('C14E');

        $this->actingAs($b['instructor'])
            ->postJson(route('admin.gradebook.cell', $b['offering']), [
                'student_id' => $b['student']->id,
                'component_id' => $b['component']->id,
                'score' => -5.0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['score']);
    }

    // ─── Part B: Proctor review view ─────────────────────────────────────

    /**
     * @return array{attempt: \App\Models\AssessmentAttempt, admin: User, instructor: User}
     */
    private function proctorBundle(): array
    {
        config(['assessment.proctor_termination_threshold' => 5]);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $course = Course::query()->create([
            'code' => 'P14A',
            'title' => 'Proctor Test Course',
            'credit_hours' => 3,
            'is_standalone' => true,
            'active' => true,
        ]);

        $offering = CourseOffering::query()->create([
            'course_id' => $course->id,
            'mode' => OfferingMode::SelfPaced,
            'status' => 'OPEN',
        ]);
        $this->staffOffering($instructor, $offering);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'offering_id' => $offering->id,
            'status' => EnrollmentStatus::Enrolled,
            'enrolled_at' => now(),
        ]);

        $bank = app(QuestionBankService::class)->createBank($instructor, $course, 'P14 bank');
        $question = app(QuestionBankService::class)->addQuestion($instructor, $bank, [
            'type' => QuestionType::Essay->value,
            'prompt' => 'Explain.',
            'points' => 10,
        ]);

        $assessment = app(AssessmentService::class)->create($instructor, $offering, [
            'title' => 'Proctor exam',
            'mode' => AssessmentMode::Exam->value,
            'time_limit_minutes' => 60,
            'attempts_allowed' => 1,
            'max_points' => 10,
        ]);
        app(AssessmentService::class)->attachQuestion($instructor, $assessment, $question);
        app(AssessmentService::class)->release($instructor, $assessment);

        $attempt = app(AttemptService::class)->start($student, $assessment);
        // Record two proctor events.
        app(ProctorService::class)->recordEvent($student, $attempt, 'FOCUS_LOSS');
        $attempt = $attempt->fresh();
        app(ProctorService::class)->recordEvent($student, $attempt, 'TAB_SWITCH');

        return compact('attempt', 'admin', 'instructor');
    }

    #[Test]
    public function proctor_view_shows_localized_event_timeline_with_no_hardcoded_english(): void
    {
        $b = $this->proctorBundle();

        $response = $this->actingAs($b['admin'])
            ->get(route('admin.attempts.proctor', $b['attempt']))
            ->assertOk();

        $html = $response->getContent();

        // Spec requirement: these exact hardcoded strings must be gone.
        $this->assertStringNotContainsString('terminated for cheating', $html, 'hardcoded "terminated for cheating" must be gone');
        $this->assertStringNotContainsString('<th>Proctor events</th>', $html, 'hardcoded "Proctor events" table header must be gone');
        $this->assertStringNotContainsString('<th>type</th>', $html, 'hardcoded "type" table header must be gone');
        $this->assertStringNotContainsString('<th>at</th>', $html, 'hardcoded "at" table header must be gone');

        // Localized strings must appear (locale is 'en' in testing).
        $this->assertStringContainsString(__('assessment.proctor_events'), $html);
        $this->assertStringContainsString(__('assessment.proctor_event_type_FOCUS_LOSS'), $html);
        $this->assertStringContainsString(__('assessment.proctor_event_type_TAB_SWITCH'), $html);
    }

    #[Test]
    public function proctor_view_no_raw_event_type_strings_from_db(): void
    {
        $b = $this->proctorBundle();

        $response = $this->actingAs($b['admin'])
            ->get(route('admin.attempts.proctor', $b['attempt']))
            ->assertOk();

        $html = $response->getContent();

        // Raw DB string values must not appear verbatim in any locale context.
        $this->assertStringNotContainsString('>FOCUS_LOSS<', $html, 'raw FOCUS_LOSS enum value must not appear verbatim');
        $this->assertStringNotContainsString('>TAB_SWITCH<', $html, 'raw TAB_SWITCH enum value must not appear verbatim');
    }

    #[Test]
    public function proctor_view_renders_terminated_badge_localized(): void
    {
        $b = $this->proctorBundle();
        // Force termination flag.
        $b['attempt']->update(['terminated_for_cheating' => true]);

        $response = $this->actingAs($b['admin'])
            ->get(route('admin.attempts.proctor', $b['attempt']))
            ->assertOk();

        $html = $response->getContent();

        // Hardcoded "terminated for cheating" must not appear.
        $this->assertStringNotContainsString('terminated for cheating', $html);

        // The localized string must appear.
        $this->assertStringContainsString(__('assessment.terminated_for_cheating'), $html);
    }
}
