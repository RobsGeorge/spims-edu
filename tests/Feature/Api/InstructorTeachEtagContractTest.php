<?php

namespace Tests\Feature\Api;

use App\Enums\AssessmentMode;
use App\Enums\ClassSessionMode;
use App\Enums\ContentItemType;
use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectGradingMode;
use App\Enums\ProjectStatus;
use App\Enums\RoleType;
use App\Enums\SubmissionType;
use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ContentItem;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\User;
use App\Models\Week;
use App\Services\Live\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Every safe teach GET is ETag / If-None-Match aware.
 * Confirmation-issuing GETs and CSV streams must not use ConditionalGet.
 */
class InstructorTeachEtagContractTest extends TestCase
{
    use InstructorApiFixtures;
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    public static function etagGets(): array
    {
        return [
            'GET /api/v1/teach/offerings',
            'GET /api/v1/teach/offerings/{offering}/students/{student}',
            'GET /api/v1/teach/offerings/{offering}/sessions',
            'GET /api/v1/teach/sessions/{session}/roster',
            'GET /api/v1/teach/offerings/{offering}/attendance/report',
            'GET /api/v1/teach/offerings/{offering}/roster',
            'GET /api/v1/teach/offerings/{offering}/birthdays',
            'GET /api/v1/teach/offerings/{offering}/students/{student}/notes',
            'GET /api/v1/teach/offerings/{offering}/project-assessments',
            'GET /api/v1/teach/projects/{project}/peer-evaluations',
            'GET /api/v1/teach/offerings/{offering}/assignments',
            'GET /api/v1/teach/assignments/{assignment}/submissions',
            'GET /api/v1/teach/offerings/{offering}/live-sessions',
            'GET /api/v1/teach/offerings/{offering}/discussions/threads',
        ];
    }

    /**
     * @return list<string>
     */
    public static function confirmationGets(): array
    {
        return [
            'GET /api/v1/teach/offerings/{offering}',
            'GET /api/v1/teach/offerings/{offering}/gradebook',
            'GET /api/v1/teach/assessments/{assessment}/attempts',
            'GET /api/v1/teach/project-assessments/{projectAssessment}/teams',
        ];
    }

    #[Test]
    public function every_teach_get_is_classified_as_etag_or_confirmation(): void
    {
        $listed = array_merge(self::etagGets(), self::confirmationGets());
        $registered = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/teach')) {
                continue;
            }
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $registered[] = 'GET /'.$route->uri();
        }

        sort($listed);
        sort($registered);

        $this->assertSame(
            $registered,
            $listed,
            "InstructorTeachEtagContractTest must classify every teach GET.\nMissing:\n"
            .implode("\n", array_diff($registered, $listed))
            ."\nExtra:\n".implode("\n", array_diff($listed, $registered))
        );
    }

    #[Test]
    public function etag_get_methods_use_conditional_get_and_confirmation_gets_do_not(): void
    {
        $etagMissing = [];
        $confirmationLeaked = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/teach') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $listed = 'GET /'.$route->uri();
            $source = $this->methodSource($route->getControllerClass(), $route->getActionMethod());
            $uses = str_contains($source, 'conditional->json(') || str_contains($source, 'conditional->json (');

            if (in_array($listed, self::etagGets(), true) && ! $uses) {
                $etagMissing[] = $listed;
            }
            if (in_array($listed, self::confirmationGets(), true) && $uses) {
                $confirmationLeaked[] = $listed;
            }
        }

        $this->assertSame([], $etagMissing, "Safe teach GETs missing ConditionalGet:\n".implode("\n", $etagMissing));
        $this->assertSame([], $confirmationLeaked, "Confirmation GETs must not use ConditionalGet:\n".implode("\n", $confirmationLeaked));
    }

    #[Test]
    public function every_safe_teach_get_returns_etag_and_honors_if_none_match(): void
    {
        $ids = $this->etagWorld();

        foreach (self::etagGets() as $template) {
            $path = $this->bind($template, $ids);
            $this->flushHeaders();
            $first = $this->asApi($ids['instructor'], 'INSTRUCTOR')
                ->getJson($path)
                ->assertOk();

            $etag = $first->headers->get('ETag');
            $this->assertNotEmpty($etag, $path.' must send ETag');
            $this->assertArrayHasKey('data', $first->json(), $path.' must wrap data');

            $this->asApi($ids['instructor'], 'INSTRUCTOR')
                ->withHeaders(['If-None-Match' => $etag])
                ->getJson($path)
                ->assertStatus(304);
            $this->flushHeaders();
        }
    }

    #[Test]
    public function confirmation_gets_do_not_send_an_etag(): void
    {
        $ids = $this->etagWorld();

        foreach (self::confirmationGets() as $template) {
            $path = $this->bind($template, $ids);
            $this->flushHeaders();
            $response = $this->asApi($ids['instructor'], 'INSTRUCTOR')
                ->getJson($path)
                ->assertOk();

            $this->assertEmpty($response->headers->get('ETag'), $path.' must not send ETag');
            $this->asApi($ids['instructor'], 'INSTRUCTOR')
                ->withHeaders(['If-None-Match' => '"deadbeef"'])
                ->getJson($path)
                ->assertOk();
        }
    }

    #[Test]
    public function csv_roster_and_report_stay_streams_without_etag(): void
    {
        $ids = $this->etagWorld();

        foreach ([
            '/api/v1/teach/offerings/'.$ids['offering'].'/roster?format=csv',
            '/api/v1/teach/offerings/'.$ids['offering'].'/attendance/report?format=csv',
        ] as $path) {
            $this->flushHeaders();
            $response = $this->asApi($ids['instructor'], 'INSTRUCTOR')
                ->get($path)
                ->assertOk();

            $this->assertEmpty($response->headers->get('ETag'), $path.' CSV must not send ETag');
            $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
            $this->asApi($ids['instructor'], 'INSTRUCTOR')
                ->withHeaders(['If-None-Match' => '"abc"'])
                ->get($path)
                ->assertOk();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function etagWorld(): array
    {
        $offering = $this->offering('S8ET');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $session = app(AttendanceService::class)->openSession($instructor, $offering, [
            'title' => 'ETag lecture',
            'scheduled_start' => now(),
            'duration_minutes' => 60,
            'mode' => ClassSessionMode::InPerson->value,
        ]);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week 1',
            'order' => 1,
        ]);
        $item = ContentItem::query()->create([
            'week_id' => $week->id,
            'type' => ContentItemType::Assignment,
            'title' => 'Essay',
            'order' => 1,
        ]);
        $assignment = Assignment::query()->create([
            'content_item_id' => $item->id,
            'instructions' => 'Write.',
            'submission_type' => SubmissionType::Both,
            'allowed_file_types' => ['pdf'],
            'max_points' => 100,
            'released' => true,
            'allow_resubmission' => true,
        ]);
        AssignmentSubmission::query()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'text_body' => 'Draft',
            'submitted_at' => now(),
            'is_late' => false,
            'attempt_no' => 1,
        ]);

        $assessment = Assessment::query()->create([
            'offering_id' => $offering->id,
            'mode' => AssessmentMode::Quiz,
            'title' => 'Quiz',
            'max_points' => 10,
            'released' => true,
        ]);

        $projectAssessment = ProjectAssessment::query()->create([
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
            'project_assessment_id' => $projectAssessment->id,
            'name' => 'Team A',
            'status' => ProjectStatus::Open,
        ]);

        return [
            'instructor' => $instructor,
            'offering' => $offering->id,
            'student' => $student->id,
            'session' => $session->id,
            'assignment' => $assignment->id,
            'assessment' => $assessment->id,
            'project' => $project->id,
            'projectAssessment' => $projectAssessment->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $ids
     */
    private function bind(string $template, array $ids): string
    {
        $path = preg_replace('/^GET /', '', $template);
        foreach ($ids as $key => $value) {
            if (is_string($value)) {
                $path = str_replace('{'.$key.'}', $value, $path);
            }
        }

        $this->assertStringNotContainsString('{', $path, $template);

        return $path;
    }

    private function methodSource(string $class, string $method): string
    {
        $ref = new ReflectionMethod($class, $method);
        $lines = file($ref->getFileName());

        return implode('', array_slice(
            $lines,
            $ref->getStartLine() - 1,
            $ref->getEndLine() - $ref->getStartLine() + 1,
        ));
    }
}
