<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Instructor staffed on offering A must not reach offering B's teach records.
 *
 * Expected status is documented per route. Existing teach controllers call
 * AuthorizeService and return 403 for out-of-scope resources. GET /teach/offerings
 * has no foreign id — it lists A's offerings (200) and must not include B.
 *
 * Adding a /api/v1/teach route without a row here fails test_every_registered_teach_route_has_a_scope_case.
 */
class InstructorApiScopeTest extends TestCase
{
    use InstructorApiFixtures;
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function teachRoutes(): array
    {
        return [
            // List has no B id — 200, filtered to staffed offerings.
            'GET teach offerings list' => ['GET', '/api/v1/teach/offerings', 200],
            'GET teach offering show' => ['GET', '/api/v1/teach/offerings/{offering}', 403],
            'GET teach offering student' => ['GET', '/api/v1/teach/offerings/{offering}/students/{student}', 403],
            'POST teach offering close' => ['POST', '/api/v1/teach/offerings/{offering}/close', 403],

            'POST teach announcements store' => ['POST', '/api/v1/teach/offerings/{offering}/announcements', 403],
            'PUT teach announcements update' => ['PUT', '/api/v1/teach/announcements/{announcement}', 403],
            'POST teach announcements publish' => ['POST', '/api/v1/teach/announcements/{announcement}/publish', 403],

            'GET teach offering sessions' => ['GET', '/api/v1/teach/offerings/{offering}/sessions', 403],
            'POST teach offering sessions' => ['POST', '/api/v1/teach/offerings/{offering}/sessions', 403],
            'GET teach session roster' => ['GET', '/api/v1/teach/sessions/{session}/roster', 403],
            'POST teach session attendance' => ['POST', '/api/v1/teach/sessions/{session}/attendance', 403],
            'POST teach session fill-missing' => ['POST', '/api/v1/teach/sessions/{session}/attendance/fill-missing', 403],
            'POST teach session close' => ['POST', '/api/v1/teach/sessions/{session}/close', 403],
            'POST teach session check-in-code' => ['POST', '/api/v1/teach/sessions/{session}/check-in-code', 403],
            'GET teach attendance report' => ['GET', '/api/v1/teach/offerings/{offering}/attendance/report', 403],
            'GET teach offering roster' => ['GET', '/api/v1/teach/offerings/{offering}/roster', 403],
            'GET teach offering birthdays' => ['GET', '/api/v1/teach/offerings/{offering}/birthdays', 403],

            'GET teach student notes' => ['GET', '/api/v1/teach/offerings/{offering}/students/{student}/notes', 403],
            'POST teach student notes' => ['POST', '/api/v1/teach/offerings/{offering}/students/{student}/notes', 403],
            'PUT teach module assessment' => ['PUT', '/api/v1/teach/offerings/{offering}/weeks/{week}/students/{student}/assessment', 403],

            'POST teach live quizzes' => ['POST', '/api/v1/teach/offerings/{offering}/live-quizzes', 403],
            'POST teach live-quiz host start' => ['POST', '/api/v1/teach/live-quiz/{liveQuiz}/host/start', 403],
            'POST teach live-quiz launch' => ['POST', '/api/v1/teach/live-quiz/sessions/{liveQuizSession}/launch', 403],
            'POST teach live-quiz close' => ['POST', '/api/v1/teach/live-quiz/sessions/{liveQuizSession}/close', 403],
            'POST teach live-quiz results' => ['POST', '/api/v1/teach/live-quiz/sessions/{liveQuizSession}/results', 403],
            'POST teach live-quiz end' => ['POST', '/api/v1/teach/live-quiz/sessions/{liveQuizSession}/end', 403],

            'GET teach project assessments' => ['GET', '/api/v1/teach/offerings/{offering}/project-assessments', 403],
            'GET teach project teams' => ['GET', '/api/v1/teach/project-assessments/{projectAssessment}/teams', 403],
            'POST teach project announce' => ['POST', '/api/v1/teach/project-assessments/{projectAssessment}/announce', 403],
            'GET teach project peer-evaluations' => ['GET', '/api/v1/teach/projects/{project}/peer-evaluations', 403],

            'GET teach gradebook' => ['GET', '/api/v1/teach/offerings/{offering}/gradebook', 403],
            'POST teach gradebook submit' => ['POST', '/api/v1/teach/offerings/{offering}/gradebook/submit', 403],
            'POST teach gradebook lock' => ['POST', '/api/v1/teach/offerings/{offering}/gradebook/lock', 403],
            'GET teach assignments' => ['GET', '/api/v1/teach/offerings/{offering}/assignments', 403],
            'GET teach assignment submissions' => ['GET', '/api/v1/teach/assignments/{assignment}/submissions', 403],
            'POST teach submission grade' => ['POST', '/api/v1/teach/submissions/{assignmentSubmission}/grade', 403],
            'POST teach submission mark-received' => ['POST', '/api/v1/teach/submissions/{assignmentSubmission}/mark-received', 403],
            'POST teach assignment remind' => ['POST', '/api/v1/teach/assignments/{assignment}/remind-unsubmitted', 403],
            'GET teach assessment attempts' => ['GET', '/api/v1/teach/assessments/{assessment}/attempts', 403],
            'POST teach answer grade' => ['POST', '/api/v1/teach/answers/{attemptAnswer}/grade', 403],
            'POST teach announce-results' => ['POST', '/api/v1/teach/assessments/{assessment}/announce-results', 403],

            'GET teach live-sessions' => ['GET', '/api/v1/teach/offerings/{offering}/live-sessions', 403],
            'POST teach live-session import' => ['POST', '/api/v1/teach/live-sessions/{liveSession}/attendance/import', 403],
            'POST teach project move' => ['POST', '/api/v1/teach/projects/{project}/members/move', 403],
            'POST teach project review' => ['POST', '/api/v1/teach/project-submissions/{projectDeliverableSubmission}/review', 403],
            'POST teach project grade' => ['POST', '/api/v1/teach/projects/{project}/grade', 403],
            'GET teach discussion threads' => ['GET', '/api/v1/teach/offerings/{offering}/discussions/threads', 403],
            'POST teach discussion moderate' => ['POST', '/api/v1/teach/discussions/threads/{discussionThread}/moderate', 403],
            'POST teach discussion grade' => ['POST', '/api/v1/teach/discussions/threads/{discussionThread}/grade', 403],
            'POST teach weeks store' => ['POST', '/api/v1/teach/offerings/{offering}/weeks', 403],
            'POST teach week items' => ['POST', '/api/v1/teach/weeks/{week}/items', 403],
            'PUT teach content item' => ['PUT', '/api/v1/teach/items/{contentItem}', 403],
            'DELETE teach content item' => ['DELETE', '/api/v1/teach/items/{contentItem}', 403],
        ];
    }

    #[Test]
    public function every_registered_teach_route_has_a_scope_case(): void
    {
        $listed = [];
        foreach (self::teachRoutes() as $row) {
            $listed[] = strtoupper($row[0]).' '.$row[1];
        }

        $registered = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/teach')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $registered[] = strtoupper($method).' /'.$route->uri();
            }
        }

        sort($listed);
        sort($registered);

        $this->assertSame(
            $registered,
            $listed,
            "InstructorApiScopeTest must list every /api/v1/teach route.\nMissing:\n"
            .implode("\n", array_diff($registered, $listed))
            ."\nExtra:\n".implode("\n", array_diff($listed, $registered))
        );
    }

    #[DataProvider('teachRoutes')]
    public function test_instructor_staffed_on_a_is_denied_on_b(string $method, string $template, int $expected): void
    {
        $ids = $this->seedBIds();
        $path = $template;
        foreach ($ids as $key => $value) {
            if (! is_string($value)) {
                continue;
            }
            $path = str_replace('{'.$key.'}', $value, $path);
        }

        $this->assertStringNotContainsString('{', $path);

        $response = $this->withToken($ids['token'])->json(
            $method,
            $path,
            $this->payloadFor($method, $template, $ids),
        );

        $response->assertStatus($expected);
        if ($expected === 403) {
            $response->assertJsonPath('code', 'FORBIDDEN');
        }
        if ($expected === 404) {
            $response->assertJsonPath('code', 'NOT_FOUND');
        }
        if ($expected === 200 && $template === '/api/v1/teach/offerings') {
            $returned = collect($response->json('data'))->pluck('id');
            $this->assertTrue($returned->contains($ids['offering_a']));
            $this->assertFalse($returned->contains($ids['offering']));
        }
    }

    /**
     * @return array<string, string>
     */
    private function seedBIds(): array
    {
        $world = $this->staffTwoOfferings();

        return [
            'token' => $this->apiToken($world['instructorA'], 'INSTRUCTOR'),
            'offering' => $world['offeringB']->id,
            'offering_a' => $world['offeringA']->id,
            'student' => $world['studentB']->id,
            'session' => $world['sessionB']->id,
            'announcement' => $world['announcementB']->id,
            'week' => $world['weekB']->id,
            'liveQuiz' => $world['liveQuizB']->id,
            'liveQuizSession' => $world['liveQuizSessionB']->id,
            'projectAssessment' => $world['projectAssessmentB']->id,
            'project' => $world['projectB']->id,
            'question' => $world['liveQuizQuestionB']->id,
            'assignment' => $world['assignmentB']->id,
            'assignmentSubmission' => $world['assignmentSubmissionB']->id,
            'assessment' => $world['assessmentB']->id,
            'attemptAnswer' => $world['attemptAnswerB']->id,
            'liveSession' => $world['liveSessionB']->id,
            'discussionThread' => $world['discussionThreadB']->id,
            'contentItem' => $world['contentItemB']->id,
            'projectDeliverableSubmission' => $world['projectDeliverableSubmissionB']->id,
            'to_project' => $world['projectB2']->id,
        ];
    }

    /**
     * @param  array<string, string>  $ids
     * @return array<string, mixed>
     */
    private function payloadFor(string $method, string $template, array $ids): array
    {
        return match ($template) {
            '/api/v1/teach/offerings/{offering}/announcements',
            '/api/v1/teach/announcements/{announcement}' => [
                'title' => 'Hijack',
                'body' => 'nope',
            ],
            '/api/v1/teach/offerings/{offering}/sessions' => [
                'title' => 'Hijack',
                'scheduled_start' => now()->toIso8601String(),
                'duration_minutes' => 60,
            ],
            '/api/v1/teach/sessions/{session}/attendance' => [
                'lock_version' => 0,
                'marks' => [['student_id' => $ids['student'], 'status' => 'PRESENT']],
            ],
            '/api/v1/teach/offerings/{offering}/students/{student}/notes' => [
                'body' => 'nope',
            ],
            '/api/v1/teach/offerings/{offering}/weeks/{week}/students/{student}/assessment' => [
                'rating' => 3,
            ],
            '/api/v1/teach/offerings/{offering}/live-quizzes' => [
                'title' => 'Hijack',
            ],
            '/api/v1/teach/live-quiz/sessions/{liveQuizSession}/launch' => [
                'question_id' => $ids['question'],
            ],
            '/api/v1/teach/offerings/{offering}/close' => [
                'confirmation' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            ],
            '/api/v1/teach/offerings/{offering}/gradebook/lock',
            '/api/v1/teach/assessments/{assessment}/announce-results' => [
                'confirmation' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            ],
            '/api/v1/teach/submissions/{assignmentSubmission}/grade' => [
                'raw_score' => 50,
            ],
            '/api/v1/teach/answers/{attemptAnswer}/grade' => [
                'final_score' => 5,
            ],
            '/api/v1/teach/project-assessments/{projectAssessment}/announce' => [
                'confirmation' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            ],
            '/api/v1/teach/live-sessions/{liveSession}/attendance/import' => [
                'participants' => [['email' => 'nobody@example.com', 'minutes' => 10]],
            ],
            '/api/v1/teach/projects/{project}/members/move' => [
                'student_id' => $ids['student'],
                'to_project_id' => $ids['to_project'],
            ],
            '/api/v1/teach/project-submissions/{projectDeliverableSubmission}/review' => [
                'review_status' => 'ACCEPTED',
            ],
            '/api/v1/teach/projects/{project}/grade' => [
                'team_score' => 10,
            ],
            '/api/v1/teach/discussions/threads/{discussionThread}/moderate' => [
                'locked' => true,
            ],
            '/api/v1/teach/discussions/threads/{discussionThread}/grade' => [
                'student_id' => $ids['student'],
                'score' => 10,
            ],
            '/api/v1/teach/offerings/{offering}/weeks' => [
                'number' => 9,
                'title' => 'Hijack',
            ],
            '/api/v1/teach/weeks/{week}/items' => [
                'type' => 'TEXT',
                'title' => 'Hijack',
            ],
            '/api/v1/teach/items/{contentItem}' => [
                'title' => 'Hijack',
            ],
            default => in_array($method, ['POST', 'PUT'], true) ? [] : [],
        };
    }
}
