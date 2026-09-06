<?php

namespace Tests\Feature\Api;

use App\Enums\ApplicationStatus;
use App\Enums\AssessmentMode;
use App\Enums\AttemptStatus;
use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProgramType;
use App\Enums\StudentProgramStatus;
use App\Enums\ThreadVisibility;
use App\Models\Application;
use App\Models\ApplicationForm;
use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\DiscussionBoard;
use App\Models\DiscussionThread;
use App\Models\GradingScheme;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Program;
use App\Models\StudentProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Student B guessing Student A's ULIDs must not leak records.
 *
 * Reads of someone else's records → 404.
 * Writes out of scope → 403.
 *
 * When adding a new id-bearing student route under /api/v1 (outside /teach),
 * add a case here. Skip:
 * - public/shared resources: GET /catalog, GET /catalog/courses/{course},
 *   POST /catalog/courses/{course}/interest, GET /application-forms/{applicationForm}
 * - pre-existing S1–S3 routes: announcements, notifications, sessions/{session}/check-in,
 *   offerings/{offering}/attendance/mine
 * - S4-owned completion/credential routes
 * - Wave E (projects, live quiz, events, surveys)
 */
class StudentApiScopeTest extends TestCase
{
    use RefreshDatabase;
    use StudentApiFixtures;

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function scopedEndpoints(): array
    {
        return [
            'offering show' => ['GET', '/api/v1/offerings/{offering}', 404],
            'offering weeks' => ['GET', '/api/v1/offerings/{offering}/weeks', 404],
            'week items' => ['GET', '/api/v1/offerings/{offering}/weeks/{week}/items', 404],
            'item show' => ['GET', '/api/v1/items/{item}', 404],
            'item complete' => ['POST', '/api/v1/items/{item}/complete', 403],
            'week complete' => ['POST', '/api/v1/offerings/{offering}/weeks/{week}/complete', 403],
            'offering grades' => ['GET', '/api/v1/offerings/{offering}/grades', 404],
            'degree audit' => ['GET', '/api/v1/degree-audit/{studentProgram}', 404],
            'assignments list' => ['GET', '/api/v1/offerings/{offering}/assignments', 404],
            'assignment show' => ['GET', '/api/v1/assignments/{assignment}', 404],
            'assignment submit' => ['POST', '/api/v1/assignments/{assignment}/submit', 403],
            'assignment resubmit' => ['POST', '/api/v1/assignments/{assignment}/resubmit', 403],
            'assessments list' => ['GET', '/api/v1/offerings/{offering}/assessments', 404],
            'assessment show' => ['GET', '/api/v1/assessments/{assessment}', 404],
            'assessment start' => ['POST', '/api/v1/assessments/{assessment}/start', 403],
            'attempt show' => ['GET', '/api/v1/attempts/{attempt}', 404],
            'attempt save' => ['POST', '/api/v1/attempts/{attempt}/save', 403],
            'attempt submit' => ['POST', '/api/v1/attempts/{attempt}/submit', 403],
            'attempt timer' => ['GET', '/api/v1/attempts/{attempt}/timer', 404],
            'attempt focus-loss' => ['POST', '/api/v1/attempts/{attempt}/focus-loss', 403],
            'discussions list' => ['GET', '/api/v1/offerings/{offering}/discussions', 404],
            'discussion thread' => ['GET', '/api/v1/discussions/threads/{thread}', 404],
            'discussion post' => ['POST', '/api/v1/discussions/threads/{thread}/posts', 403],
            'discussion create thread' => ['POST', '/api/v1/offerings/{offering}/discussions/threads', 403],
            'application show' => ['GET', '/api/v1/applications/{application}', 404],
            'application submit' => ['POST', '/api/v1/applications/{application}/submit', 403],
            'enrollment drop' => ['POST', '/api/v1/enrollments/{enrollment}/drop', 403],
            'enrollment withdraw' => ['POST', '/api/v1/enrollments/{enrollment}/withdraw', 403],
            'invoice show' => ['GET', '/api/v1/invoices/{invoice}', 404],
            'invoice checkout' => ['POST', '/api/v1/invoices/{invoice}/checkout', 403],
            'payment receipt' => ['GET', '/api/v1/payments/{payment}/receipt', 404],
        ];
    }

    #[DataProvider('scopedEndpoints')]
    public function test_student_cannot_access_another_students_records(string $method, string $template, int $expected): void
    {
        $ids = $this->seedScopedRecords();
        $path = $template;
        foreach ($ids as $key => $value) {
            $path = str_replace('{'.$key.'}', $value, $path);
        }

        $this->assertStringNotContainsString('{', $path);

        $response = $this->withToken($ids['token_b'])->json($method, $path, [
            'title' => 'Hijack',
            'body' => 'nope',
            'answers' => ['x' => 1],
        ]);

        $response->assertStatus($expected);
        if ($expected === 404) {
            $response->assertJsonPath('code', 'NOT_FOUND');
        }
        if ($expected === 403) {
            $response->assertJsonPath('code', 'FORBIDDEN');
        }
    }

    /**
     * @return array<string, string>
     */
    private function seedScopedRecords(): array
    {
        $this->seed(\Database\Seeders\GradingSchemeSeeder::class);

        $bundle = $this->playerBundle('SCP1');
        $studentA = $bundle['student'];
        $offering = $bundle['offering'];
        $studentB = $this->student();

        $assignment = $this->assignmentOn($offering, title: 'Scoped essay');

        $assessment = Assessment::query()->create([
            'offering_id' => $offering->id,
            'title' => 'Scoped quiz',
            'mode' => AssessmentMode::Quiz,
            'released' => true,
            'attempts_allowed' => 2,
            'max_points' => 10,
            'time_limit_minutes' => 20,
        ]);

        $attempt = AssessmentAttempt::query()->create([
            'assessment_id' => $assessment->id,
            'student_id' => $studentA->id,
            'attempt_no' => 1,
            'started_at' => now(),
            'due_at' => now()->addMinutes(20),
            'status' => AttemptStatus::InProgress,
            'question_ids' => [],
            'exam_snapshot' => [],
        ]);

        $board = DiscussionBoard::query()->create([
            'offering_id' => $offering->id,
            'allow_student_threads' => true,
        ]);
        $thread = DiscussionThread::query()->create([
            'board_id' => $board->id,
            'author_id' => $studentA->id,
            'title' => 'A thread',
            'visibility' => ThreadVisibility::Open,
            'locked' => false,
            'pinned' => false,
        ]);

        $program = Program::query()->create([
            'code' => 'SCP',
            'name' => 'Scope Program',
            'type' => ProgramType::Diploma,
            'max_credits_per_semester' => 15,
            'max_courses_per_semester' => 5,
            'max_semesters_to_graduate' => 8,
            'elective_credits_required' => 0,
            'grading_scheme_id' => GradingScheme::query()->first()->id,
            'active' => true,
        ]);
        $studentProgram = StudentProgram::query()->create([
            'student_id' => $studentA->id,
            'program_id' => $program->id,
            'status' => StudentProgramStatus::Active,
            'enrolled_at' => now(),
        ]);

        $form = ApplicationForm::query()->create([
            'program_id' => $program->id,
            'name' => 'Scope Form',
            'active' => true,
        ]);
        $application = Application::query()->create([
            'applicant_id' => $studentA->id,
            'program_id' => $program->id,
            'form_id' => $form->id,
            'status' => ApplicationStatus::Draft,
        ]);

        $invoice = Invoice::query()->create([
            'student_id' => $studentA->id,
            'currency' => Currency::Usd,
            'total_minor' => 5000,
            'status' => InvoiceStatus::Open,
            'due_date' => now()->addWeek(),
        ]);
        $payment = Payment::query()->create([
            'student_id' => $studentA->id,
            'invoice_id' => $invoice->id,
            'currency' => Currency::Usd,
            'amount_minor' => 5000,
            'method' => PaymentMethod::Paypal,
            'status' => PaymentStatus::Completed,
            'receipt_serial' => 'R-SCOPE-1',
        ]);

        return [
            'offering' => $offering->id,
            'week' => $bundle['week1']->id,
            'item' => $bundle['video']->id,
            'assignment' => $assignment->id,
            'assessment' => $assessment->id,
            'attempt' => $attempt->id,
            'thread' => $thread->id,
            'studentProgram' => $studentProgram->id,
            'application' => $application->id,
            'enrollment' => $bundle['enrollment']->id,
            'invoice' => $invoice->id,
            'payment' => $payment->id,
            'token_b' => $this->apiToken($studentB),
        ];
    }
}
