<?php

namespace Tests\Feature\Assessment;

use App\Enums\AssessmentMode;
use App\Enums\AttemptStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\OfferingMode;
use App\Enums\QuestionType;
use App\Enums\ResultsVisibility;
use App\Enums\RoleType;
use App\Models\AssessmentAttempt;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\Assessment\AssessmentService;
use App\Services\Assessment\AttemptService;
use App\Services\Assessment\QuestionBankService;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExamRunnerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{instructor: User, student: User, other: User, offering: CourseOffering, course: Course}
     */
    private function world(): array
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $instructor = User::factory()->withRole(RoleType::Instructor)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $other = User::factory()->withRole(RoleType::Student)->create();

        $course = Course::query()->create([
            'code' => 'RUN1',
            'title' => 'Runner Course',
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

        foreach ([$student, $other] as $user) {
            Enrollment::query()->create([
                'student_id' => $user->id,
                'offering_id' => $offering->id,
                'status' => EnrollmentStatus::Enrolled,
                'enrolled_at' => now(),
            ]);
        }

        return compact('instructor', 'student', 'other', 'offering', 'course');
    }

    private function asApi(User $user)
    {
        Auth::forgetGuards();

        return $this->withToken($user->createToken('api', ['role:STUDENT'])->plainTextToken);
    }

    #[Test]
    public function runner_html_contains_inputs_for_multi_matching_ordering_and_file_upload(): void
    {
        $world = $this->world();
        $ins = $world['instructor'];
        $student = $world['student'];

        $bank = app(QuestionBankService::class)->createBank($ins, $world['course'], 'All types');
        $multi = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::McqMulti->value,
            'prompt' => 'Evens',
            'points' => 4,
            'options' => [
                ['text' => '2', 'is_correct' => true],
                ['text' => '3', 'is_correct' => false],
            ],
        ]);
        $matching = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::Matching->value,
            'prompt' => 'Capitals',
            'points' => 4,
            'options' => [
                ['text' => 'France', 'match_key' => 'Paris'],
                ['text' => 'Egypt', 'match_key' => 'Cairo'],
            ],
        ]);
        $ordering = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::Ordering->value,
            'prompt' => 'Steps',
            'points' => 4,
            'options' => [
                ['text' => 'One', 'order' => 0],
                ['text' => 'Two', 'order' => 1],
            ],
        ]);
        $upload = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::FileUpload->value,
            'prompt' => 'Upload notes',
            'points' => 4,
        ]);

        $assessment = app(AssessmentService::class)->create($ins, $world['offering'], [
            'title' => 'Full runner',
            'mode' => AssessmentMode::Exam->value,
            'time_limit_minutes' => 20,
            'max_points' => 16,
            'one_at_a_time' => true,
            'no_backtrack' => true,
            'enforce_full_screen' => true,
            'shuffle_questions' => false,
            'shuffle_options' => false,
        ]);
        foreach ([$multi, $matching, $ordering, $upload] as $question) {
            app(AssessmentService::class)->attachQuestion($ins, $assessment, $question);
        }
        app(AssessmentService::class)->release($ins, $assessment);

        $attempt = app(AttemptService::class)->start($student, $assessment);

        $html = $this->actingAs($student)
            ->get(route('assessments.runner', $attempt))
            ->assertOk()
            ->assertSee('MCQ_MULTI', false)
            ->assertSee('MATCHING', false)
            ->assertSee('ORDERING', false)
            ->assertSee('FILE_UPLOAD', false)
            ->assertSee('type="checkbox"', false)
            ->assertSee('type="file"', false)
            ->assertSee('exam-match-select', false)
            ->assertSee('exam-ordering-list', false)
            ->assertSee('oneAtATime: true', false)
            ->assertSee('noBacktrack: true', false)
            ->assertSee('enforceFullScreen: true', false)
            ->assertSee(__('assessment.choose_match'), false)
            ->assertSee(__('assessment.upload_file'), false)
            ->getContent();

        $this->assertStringNotContainsString('"match_key"', $html);
        $this->assertStringContainsString('match_choices', $html);
    }

    #[Test]
    public function matching_answer_round_trips_through_the_runner_and_scores(): void
    {
        $world = $this->world();
        $ins = $world['instructor'];
        $student = $world['student'];

        $bank = app(QuestionBankService::class)->createBank($ins, $world['course'], 'Match HTTP');
        $q = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::Matching->value,
            'prompt' => 'Match countries',
            'points' => 10,
            'options' => [
                ['text' => 'France', 'match_key' => 'Paris'],
                ['text' => 'England', 'match_key' => 'London'],
            ],
        ]);
        $assessment = app(AssessmentService::class)->create($ins, $world['offering'], [
            'title' => 'Match exam',
            'mode' => AssessmentMode::Quiz->value,
            'time_limit_minutes' => 15,
            'max_points' => 10,
            'results_visibility' => ResultsVisibility::Immediate->value,
            'shuffle_questions' => false,
            'shuffle_options' => false,
        ]);
        app(AssessmentService::class)->attachQuestion($ins, $assessment, $q);
        app(AssessmentService::class)->release($ins, $assessment);

        $attempt = app(AttemptService::class)->start($student, $assessment);
        $opts = $q->options()->orderBy('order')->get();

        $this->actingAs($student)
            ->postJson(route('assessments.save', $attempt), [
                'answers' => [
                    $q->id => ['matches' => [
                        $opts[0]->id => 'Paris',
                        $opts[1]->id => 'London',
                    ]],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', AttemptStatus::InProgress->value);

        $this->actingAs($student)
            ->post(route('assessments.submit', $attempt))
            ->assertRedirect(route('assessments.show', $assessment));

        $this->assertEquals(10.0, $attempt->fresh()->total_score);
        $this->assertSame(AttemptStatus::Graded, $attempt->fresh()->status);

        $this->actingAs($student)
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertSee(__('assessment.total_score'), false)
            ->assertSee('10', false);
    }

    #[Test]
    public function hidden_visibility_hides_scores_on_show_until_instructor_announces(): void
    {
        $world = $this->world();
        $ins = $world['instructor'];
        $student = $world['student'];

        $bank = app(QuestionBankService::class)->createBank($ins, $world['course'], 'Hide');
        $q = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::TrueFalse->value,
            'prompt' => 'Sky?',
            'points' => 41,
            'options' => [
                ['text' => 'True', 'is_correct' => true],
                ['text' => 'False', 'is_correct' => false],
            ],
        ]);
        $assessment = app(AssessmentService::class)->create($ins, $world['offering'], [
            'title' => 'Hidden quiz',
            'mode' => AssessmentMode::Quiz->value,
            'time_limit_minutes' => 10,
            'max_points' => 41,
            'results_visibility' => ResultsVisibility::OnRelease->value,
            'reveal_answers' => false,
            'shuffle_questions' => false,
        ]);
        app(AssessmentService::class)->attachQuestion($ins, $assessment, $q);
        app(AssessmentService::class)->release($ins, $assessment);

        $attempt = app(AttemptService::class)->start($student, $assessment);
        $opt = $q->options()->where('is_correct', true)->first();
        app(AttemptService::class)->autosave($student, $attempt, [$q->id => ['option_id' => $opt->id]]);
        app(AttemptService::class)->submit($student, $attempt);
        $this->assertEquals(41.0, $attempt->fresh()->total_score);

        $this->actingAs($student)
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertSee(__('assessment.results_hidden'), false)
            ->assertDontSee(__('assessment.total_score'), false)
            ->assertDontSee(__('assessment.correct_answer'), false);

        $this->asApi($student)
            ->getJson(route('api.v1.attempts.show', $attempt))
            ->assertOk()
            ->assertJsonPath('data.total_score', null)
            ->assertJsonPath('data.scores_visible', false);

        app(AssessmentService::class)->announceResults($ins, $assessment->fresh());

        $this->actingAs($student)
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertDontSee(__('assessment.results_hidden'), false)
            ->assertSee('41', false)
            ->assertSee(__('assessment.total_score'), false);

        $this->asApi($student)
            ->getJson(route('api.v1.attempts.show', $attempt))
            ->assertOk()
            ->assertJsonPath('data.total_score', 41)
            ->assertJsonPath('data.scores_visible', true);
    }

    #[Test]
    public function a_student_cannot_see_another_students_score_or_runner(): void
    {
        $world = $this->world();
        $ins = $world['instructor'];
        $student = $world['student'];
        $other = $world['other'];

        $bank = app(QuestionBankService::class)->createBank($ins, $world['course'], 'Peer');
        $q = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::Numeric->value,
            'prompt' => 'Value',
            'points' => 17,
            'config' => ['correct_value' => 17, 'tolerance' => 0],
        ]);
        $assessment = app(AssessmentService::class)->create($ins, $world['offering'], [
            'title' => 'Peer quiz',
            'mode' => AssessmentMode::Quiz->value,
            'time_limit_minutes' => 10,
            'max_points' => 17,
            'results_visibility' => ResultsVisibility::Immediate->value,
            'shuffle_questions' => false,
        ]);
        app(AssessmentService::class)->attachQuestion($ins, $assessment, $q);
        app(AssessmentService::class)->release($ins, $assessment);

        $attempt = app(AttemptService::class)->start($student, $assessment);
        app(AttemptService::class)->autosave($student, $attempt, [$q->id => ['value' => 17]]);
        app(AttemptService::class)->submit($student, $attempt);
        $this->assertEquals(17.0, $attempt->fresh()->total_score);

        $this->actingAs($other)
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertDontSee(__('assessment.total_score'), false)
            ->assertSee(__('assessment.attempts_empty'), false);

        $this->actingAs($other)
            ->get(route('assessments.runner', $attempt))
            ->assertForbidden();

        $this->asApi($other)
            ->getJson(route('api.v1.attempts.show', $attempt))
            ->assertNotFound();

        $this->assertSame(0, AssessmentAttempt::query()
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $other->id)
            ->count());
    }

    #[Test]
    public function file_upload_question_stores_through_object_storage(): void
    {
        Storage::fake('local');
        $world = $this->world();
        $ins = $world['instructor'];
        $student = $world['student'];

        $bank = app(QuestionBankService::class)->createBank($ins, $world['course'], 'Files');
        $q = app(QuestionBankService::class)->addQuestion($ins, $bank, [
            'type' => QuestionType::FileUpload->value,
            'prompt' => 'Upload essay',
            'points' => 5,
        ]);
        $assessment = app(AssessmentService::class)->create($ins, $world['offering'], [
            'title' => 'Upload quiz',
            'mode' => AssessmentMode::Quiz->value,
            'time_limit_minutes' => 10,
            'max_points' => 5,
            'shuffle_questions' => false,
        ]);
        app(AssessmentService::class)->attachQuestion($ins, $assessment, $q);
        app(AssessmentService::class)->release($ins, $assessment);

        $attempt = app(AttemptService::class)->start($student, $assessment);
        $file = UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf');

        $path = $this->actingAs($student)
            ->post(route('assessments.upload', $attempt), ['file' => $file], [
                'Accept' => 'application/json',
            ])
            ->assertCreated()
            ->json('path');

        $this->assertNotEmpty($path);
        $this->assertTrue(app(ObjectStorageService::class)->exists($path));

        $this->actingAs($student)
            ->postJson(route('assessments.save', $attempt), [
                'answers' => [$q->id => ['path' => $path, 'filename' => 'notes.pdf']],
            ])
            ->assertOk();

        $this->actingAs($student)
            ->post(route('assessments.submit', $attempt))
            ->assertRedirect();

        $this->assertSame(AttemptStatus::Submitted, $attempt->fresh()->status);
        $this->assertSame($path, $attempt->fresh()->answers()->first()->response['path']);
    }
}
