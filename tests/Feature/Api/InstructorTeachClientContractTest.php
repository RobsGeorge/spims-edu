<?php

namespace Tests\Feature\Api;

use App\Enums\ClassSessionMode;
use App\Enums\ContentItemType;
use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectGradingMode;
use App\Enums\ProjectStatus;
use App\Enums\RoleType;
use App\Enums\ThreadVisibility;
use App\Models\Announcement;
use App\Models\AttendanceCheckInCode;
use App\Models\ClassSession;
use App\Models\ContentItem;
use App\Models\DiscussionBoard;
use App\Models\DiscussionThread;
use App\Models\LiveQuiz;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\StudentNote;
use App\Models\User;
use App\Models\Week;
use App\Services\Live\AttendanceService;
use App\Services\LiveQuiz\LiveQuizHostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Instructor-client contract: every /api/v1/teach mutation is replay-safe.
 *
 * Completeness is enforced three ways so a new teach write cannot ship without
 * the header, the wrapper, and a documented parameter:
 *   1. every registered teach POST/PUT/DELETE is listed here
 *   2. each mutation method source contains IdempotencyStore::remember(
 *   3. every matching OpenAPI operation references IdempotencyKey
 */
class InstructorTeachClientContractTest extends TestCase
{
    use InstructorApiFixtures;
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    public static function teachMutationUris(): array
    {
        return [
            'POST /api/v1/teach/offerings/{offering}/announcements',
            'PUT /api/v1/teach/announcements/{announcement}',
            'POST /api/v1/teach/announcements/{announcement}/publish',
            'POST /api/v1/teach/offerings/{offering}/close',
            'POST /api/v1/teach/offerings/{offering}/sessions',
            'POST /api/v1/teach/sessions/{session}/attendance',
            'POST /api/v1/teach/sessions/{session}/attendance/fill-missing',
            'POST /api/v1/teach/sessions/{session}/close',
            'POST /api/v1/teach/sessions/{session}/check-in-code',
            'POST /api/v1/teach/offerings/{offering}/students/{student}/notes',
            'PUT /api/v1/teach/offerings/{offering}/weeks/{week}/students/{student}/assessment',
            'POST /api/v1/teach/offerings/{offering}/live-quizzes',
            'POST /api/v1/teach/live-quiz/{liveQuiz}/host/start',
            'POST /api/v1/teach/live-quiz/sessions/{liveQuizSession}/launch',
            'POST /api/v1/teach/live-quiz/sessions/{liveQuizSession}/close',
            'POST /api/v1/teach/live-quiz/sessions/{liveQuizSession}/results',
            'POST /api/v1/teach/live-quiz/sessions/{liveQuizSession}/end',
            'POST /api/v1/teach/project-assessments/{projectAssessment}/announce',
            'POST /api/v1/teach/offerings/{offering}/gradebook/submit',
            'POST /api/v1/teach/offerings/{offering}/gradebook/lock',
            'POST /api/v1/teach/submissions/{assignmentSubmission}/grade',
            'POST /api/v1/teach/submissions/{assignmentSubmission}/mark-received',
            'POST /api/v1/teach/assignments/{assignment}/remind-unsubmitted',
            'POST /api/v1/teach/answers/{attemptAnswer}/grade',
            'POST /api/v1/teach/assessments/{assessment}/announce-results',
            'POST /api/v1/teach/live-sessions/{liveSession}/attendance/import',
            'POST /api/v1/teach/projects/{project}/members/move',
            'POST /api/v1/teach/project-submissions/{projectDeliverableSubmission}/review',
            'POST /api/v1/teach/projects/{project}/grade',
            'POST /api/v1/teach/discussions/threads/{discussionThread}/moderate',
            'POST /api/v1/teach/discussions/threads/{discussionThread}/grade',
            'POST /api/v1/teach/offerings/{offering}/weeks',
            'POST /api/v1/teach/weeks/{week}/items',
            'PUT /api/v1/teach/items/{contentItem}',
            'DELETE /api/v1/teach/items/{contentItem}',
            'POST /api/v1/teach/items/{contentItem}/publish',
            'POST /api/v1/teach/items/{contentItem}/unpublish',
            'POST /api/v1/teach/items/{contentItem}/move-up',
            'POST /api/v1/teach/items/{contentItem}/move-down',
            'POST /api/v1/teach/items/{contentItem}/move',
        ];
    }

    #[Test]
    public function every_registered_teach_mutation_is_listed(): void
    {
        $listed = self::teachMutationUris();
        $registered = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/teach')) {
                continue;
            }
            foreach (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) as $method) {
                $registered[] = strtoupper($method).' /'.$route->uri();
            }
        }

        sort($listed);
        sort($registered);

        $this->assertSame(
            $registered,
            $listed,
            "InstructorTeachClientContractTest must list every teach mutation.\nMissing:\n"
            .implode("\n", array_diff($registered, $listed))
            ."\nExtra:\n".implode("\n", array_diff($listed, $registered))
        );
    }

    #[Test]
    public function every_teach_mutation_method_uses_idempotency_store(): void
    {
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/teach')) {
                continue;
            }
            if (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) === []) {
                continue;
            }

            $class = $route->getControllerClass();
            $method = $route->getActionMethod();
            $this->assertNotNull($class, $route->uri().' has no controller');
            $ref = new ReflectionMethod($class, $method);
            $lines = file($ref->getFileName());
            $source = implode('', array_slice(
                $lines,
                $ref->getStartLine() - 1,
                $ref->getEndLine() - $ref->getStartLine() + 1,
            ));

            if (! str_contains($source, 'remember(')) {
                $missing[] = $class.'::'.$method.' ('.$route->uri().')';
            }
        }

        $this->assertSame([], $missing, "Teach mutations missing IdempotencyStore::remember():\n".implode("\n", $missing));
    }

    #[Test]
    public function every_teach_mutation_is_documented_with_idempotency_key(): void
    {
        $spec = Yaml::parseFile(base_path('docs/api/openapi.yaml'));
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/teach')) {
                continue;
            }
            $path = '/'.preg_replace('#^api/v1/?#', '', $route->uri());
            $path = rtrim($path, '/') ?: '/';

            foreach (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) as $method) {
                $op = $spec['paths'][$path][strtolower($method)] ?? null;
                $this->assertIsArray($op, "OpenAPI missing $method $path");
                $has = false;
                foreach ($op['parameters'] ?? [] as $parameter) {
                    if (($parameter['$ref'] ?? null) === '#/components/parameters/IdempotencyKey') {
                        $has = true;
                        break;
                    }
                }
                if (! $has) {
                    $missing[] = strtoupper($method).' '.$path;
                }
            }
        }

        $this->assertSame([], $missing, "Teach mutations missing IdempotencyKey in OpenAPI:\n".implode("\n", $missing));
    }

    #[Test]
    public function store_session_replay_creates_one_row(): void
    {
        [$offering, $instructor] = $this->staffed();
        $body = [
            'title' => 'Retry lecture',
            'scheduled_start' => now()->addHour()->toIso8601String(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ];

        $first = $this->replay($instructor, 'post', '/api/v1/teach/offerings/'.$offering->id.'/sessions', $body, 'sess-1', 201);

        $this->assertSame('Retry lecture', $first['title']);
        $this->assertSame(1, ClassSession::query()->where('offering_id', $offering->id)->count());
    }

    #[Test]
    public function store_session_without_a_key_still_creates_and_validates(): void
    {
        [$offering, $instructor] = $this->staffed();
        $body = [
            'title' => 'No key',
            'scheduled_start' => now()->addHour()->toIso8601String(),
            'duration_minutes' => 60,
        ];

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/offerings/'.$offering->id.'/sessions', $body)
            ->assertCreated();
        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/offerings/'.$offering->id.'/sessions', $body)
            ->assertCreated();

        $this->assertSame(2, ClassSession::query()->where('offering_id', $offering->id)->count());

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/offerings/'.$offering->id.'/sessions', [
                'scheduled_start' => now()->toIso8601String(),
                'duration_minutes' => 60,
            ], ['Idempotency-Key' => 'invalid-sess'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    #[Test]
    public function check_in_code_replay_returns_the_same_code(): void
    {
        [$offering, $instructor] = $this->staffed();
        $session = app(AttendanceService::class)->openSession($instructor, $offering, [
            'title' => 'Code',
            'scheduled_start' => now(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ]);

        $first = $this->replay(
            $instructor,
            'post',
            '/api/v1/teach/sessions/'.$session->id.'/check-in-code',
            ['ttl_minutes' => 30],
            'code-1',
            201,
        );

        $this->assertNotEmpty($first['code']);
        $this->assertSame(1, AttendanceCheckInCode::query()->where('class_session_id', $session->id)->count());
    }

    #[Test]
    public function store_note_and_week_and_item_replays_write_once(): void
    {
        [$offering, $instructor, $student] = $this->staffed();

        $note = $this->replay(
            $instructor,
            'post',
            '/api/v1/teach/offerings/'.$offering->id.'/students/'.$student->id.'/notes',
            ['body' => 'Needs follow-up'],
            'note-1',
            201,
        );
        $this->assertSame('Needs follow-up', $note['body']);
        $this->assertSame(1, StudentNote::query()->where('offering_id', $offering->id)->count());

        $week = $this->replay(
            $instructor,
            'post',
            '/api/v1/teach/offerings/'.$offering->id.'/weeks',
            ['number' => 2, 'title' => 'Week 2'],
            'week-1',
            201,
        );
        $this->assertSame(1, Week::query()->where('offering_id', $offering->id)->where('number', 2)->count());

        $item = $this->replay(
            $instructor,
            'post',
            '/api/v1/teach/weeks/'.$week['id'].'/items',
            ['type' => ContentItemType::Text->value, 'title' => 'Reading'],
            'item-1',
            201,
        );
        $this->assertSame(1, ContentItem::query()->where('week_id', $week['id'])->count());

        $updated = $this->replay(
            $instructor,
            'put',
            '/api/v1/teach/items/'.$item['id'],
            ['title' => 'Reading revised'],
            'item-upd-1',
        );
        $this->assertSame('Reading revised', $updated['title']);
        $this->assertSame('Reading revised', ContentItem::query()->find($item['id'])?->title);
    }

    #[Test]
    public function announcement_store_and_update_replays_write_once(): void
    {
        [$offering, $instructor] = $this->staffed();

        $draft = $this->replay(
            $instructor,
            'post',
            '/api/v1/teach/offerings/'.$offering->id.'/announcements',
            ['title' => 'Draft', 'body' => 'Body'],
            'ann-1',
            201,
        );
        $this->assertSame(1, Announcement::query()->where('offering_id', $offering->id)->count());

        $updated = $this->replay(
            $instructor,
            'put',
            '/api/v1/teach/announcements/'.$draft['id'],
            ['title' => 'Draft v2', 'body' => 'Body v2'],
            'ann-upd-1',
        );
        $this->assertSame('Draft v2', $updated['title']);
        $this->assertSame('Draft v2', Announcement::query()->find($draft['id'])?->title);
    }

    #[Test]
    public function live_quiz_store_and_start_replays_do_not_conflict(): void
    {
        [$offering, $instructor] = $this->staffed();
        $questions = [[
            'prompt' => '2+2?',
            'time_limit_seconds' => 30,
            'points' => 10,
            'options' => [
                ['label' => '3', 'is_correct' => false],
                ['label' => '4', 'is_correct' => true],
            ],
        ]];

        $quiz = $this->replay(
            $instructor,
            'post',
            '/api/v1/teach/offerings/'.$offering->id.'/live-quizzes',
            ['title' => 'Warmup', 'questions' => $questions],
            'quiz-1',
            201,
        );
        $this->assertSame(1, LiveQuiz::query()->where('offering_id', $offering->id)->count());

        $session = $this->replay(
            $instructor,
            'post',
            '/api/v1/teach/live-quiz/'.$quiz['id'].'/host/start',
            [],
            'quiz-start-1',
            201,
        );
        $this->assertNotEmpty($session['join_code']);

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/live-quiz/'.$quiz['id'].'/host/start')
            ->assertStatus(409);
    }

    #[Test]
    public function live_quiz_launch_replay_does_not_replay_the_state_machine(): void
    {
        [$offering, $instructor] = $this->staffed();
        $quiz = app(LiveQuizHostService::class)->createQuiz($instructor, $offering, 'Host', [[
            'prompt' => 'Capital?',
            'time_limit_seconds' => 20,
            'points' => 5,
            'options' => [
                ['label' => 'Cairo', 'is_correct' => true],
                ['label' => 'Giza', 'is_correct' => false],
            ],
        ]]);
        $question = $quiz->questions()->firstOrFail();
        $session = app(LiveQuizHostService::class)->startSession($instructor, $quiz);

        $first = $this->replay(
            $instructor,
            'post',
            '/api/v1/teach/live-quiz/sessions/'.$session->id.'/launch',
            ['question_id' => $question->id],
            'launch-1',
        );

        $this->assertSame($question->id, $first['current_question']['id']);
        $this->assertSame('QUESTION_OPEN', $first['state']);
        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/live-quiz/sessions/'.$session->id.'/launch', [
                'question_id' => $question->id,
            ])
            ->assertStatus(409);
    }

    #[Test]
    public function module_rate_and_moderate_replays_are_stable(): void
    {
        [$offering, $instructor, $student] = $this->staffed();
        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week 1',
            'order' => 1,
        ]);

        $rated = $this->replay(
            $instructor,
            'put',
            '/api/v1/teach/offerings/'.$offering->id.'/weeks/'.$week->id.'/students/'.$student->id.'/assessment',
            ['rating' => 4, 'comment' => 'Solid'],
            'rate-1',
        );
        $this->assertSame(4, $rated['rating']);

        $board = DiscussionBoard::query()->firstOrCreate(
            ['offering_id' => $offering->id],
            ['allow_student_threads' => true]
        );
        $thread = DiscussionThread::query()->create([
            'board_id' => $board->id,
            'title' => 'Thread',
            'author_id' => $student->id,
            'visibility' => ThreadVisibility::Open,
            'is_graded' => false,
            'locked' => false,
            'pinned' => false,
        ]);

        $moderated = $this->replay(
            $instructor,
            'post',
            '/api/v1/teach/discussions/threads/'.$thread->id.'/moderate',
            ['locked' => true],
            'mod-1',
        );
        $this->assertTrue($moderated['locked']);
        $this->assertTrue($thread->fresh()->locked);
    }

    #[Test]
    public function project_grade_replay_writes_one_team_score(): void
    {
        [$offering, $instructor] = $this->staffed();
        $assessment = ProjectAssessment::query()->create([
            'offering_id' => $offering->id,
            'title' => 'Capstone',
            'team_size_min' => 1,
            'team_size_max' => 2,
            'join_opens_at' => now()->subHour(),
            'join_closes_at' => now()->addHour(),
            'allow_leave_once' => true,
            'grading_mode' => ProjectGradingMode::Rubric,
            'max_points' => 100,
            'status' => ProjectAssessmentStatus::Published,
        ]);
        $project = Project::query()->create([
            'project_assessment_id' => $assessment->id,
            'name' => 'Team A',
            'status' => ProjectStatus::Open,
        ]);

        $first = $this->replay(
            $instructor,
            'post',
            '/api/v1/teach/projects/'.$project->id.'/grade',
            ['team_score' => 91],
            'pgrade-1',
        );

        $this->assertSame(91.0, (float) $first['team']['score']);
        $this->assertSame(1, $project->grades()->count());
    }

    #[Test]
    public function a_second_actor_does_not_reuse_another_instructors_key(): void
    {
        [$offering, $instructor] = $this->staffed();
        $other = User::factory()->withRole(RoleType::Instructor)->create();
        $this->staffOffering($other, $offering);
        $body = [
            'title' => 'Shared key',
            'scheduled_start' => now()->addHour()->toIso8601String(),
            'duration_minutes' => 60,
        ];

        $this->asApi($instructor, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/offerings/'.$offering->id.'/sessions', $body, [
                'Idempotency-Key' => 'shared-key',
            ])
            ->assertCreated();

        $this->asApi($other, 'INSTRUCTOR')
            ->postJson('/api/v1/teach/offerings/'.$offering->id.'/sessions', $body, [
                'Idempotency-Key' => 'shared-key',
            ])
            ->assertCreated();

        $this->assertSame(2, ClassSession::query()->where('offering_id', $offering->id)->count());
    }

    /**
     * @return array{0: \App\Models\CourseOffering, 1: User, 2: User}
     */
    private function staffed(): array
    {
        $offering = $this->offering('S8C1');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        return [$offering, $instructor, $student];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function replay(User $actor, string $method, string $uri, array $body, string $key, int $status = 200): array
    {
        $verb = $method.'Json';
        $first = $this->asApi($actor, 'INSTRUCTOR')
            ->{$verb}($uri, $body, ['Idempotency-Key' => $key])
            ->assertStatus($status);
        $replay = $this->asApi($actor, 'INSTRUCTOR')
            ->{$verb}($uri, $body, ['Idempotency-Key' => $key])
            ->assertStatus($status);

        $this->assertSame($first->json('data'), $replay->json('data'));
        $this->assertNotNull($first->json('data'));

        return $first->json('data');
    }
}
