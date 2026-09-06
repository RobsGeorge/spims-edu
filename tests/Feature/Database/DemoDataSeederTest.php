<?php

namespace Tests\Feature\Database;

use App\Enums\EventStatus;
use App\Enums\FeedbackSurveyStatus;
use App\Enums\LiveQuizSessionState;
use App\Enums\OfferingMode;
use App\Enums\ProjectAssessmentStatus;
use App\Enums\ProjectStatus;
use App\Models\Announcement;
use App\Models\ClassSession;
use App\Models\ContentItem;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Credential;
use App\Models\Enrollment;
use App\Models\Event;
use App\Models\FeedbackSurvey;
use App\Models\Invoice;
use App\Models\LiveQuiz;
use App\Models\LiveQuizSession;
use App\Models\OfferingStaff;
use App\Models\Project;
use App\Models\ProjectAssessment;
use App\Models\User;
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

    #[Test]
    public function th101_classroom_slice_fills_new_hub_tiles_and_leaves_credentials_empty(): void
    {
        $this->seed();

        $th101 = $this->cohortOffering('TH101');
        $this->assertNotNull($th101);
        $student1 = User::query()->where('email', 'student1@spims.test')->firstOrFail();
        $ins1 = User::query()->where('email', 'ins1@spims.test')->firstOrFail();

        $survey = FeedbackSurvey::query()
            ->where('offering_id', $th101->id)
            ->where('title', DemoDataSeeder::TH101_SURVEY_TITLE)
            ->first();
        $this->assertNotNull($survey);
        $this->assertSame(FeedbackSurveyStatus::Published, $survey->status);
        $this->assertGreaterThanOrEqual(1, $survey->questions()->count());

        $event = Event::query()->where('title', DemoDataSeeder::TH101_EVENT_TITLE)->first();
        $this->assertNotNull($event);
        $this->assertSame(EventStatus::Published, $event->status);

        $quiz = LiveQuiz::query()
            ->where('offering_id', $th101->id)
            ->where('title', DemoDataSeeder::TH101_LIVE_QUIZ_TITLE)
            ->first();
        $this->assertNotNull($quiz);
        $session = LiveQuizSession::query()
            ->where('quiz_id', $quiz->id)
            ->where('state', LiveQuizSessionState::Lobby)
            ->first();
        $this->assertNotNull($session);
        $this->assertNotSame('', (string) $session->join_code);

        $assessment = ProjectAssessment::query()
            ->where('offering_id', $th101->id)
            ->where('title', DemoDataSeeder::TH101_PROJECT_TITLE)
            ->first();
        $this->assertNotNull($assessment);
        $this->assertSame(ProjectAssessmentStatus::Published, $assessment->status);
        $team = Project::query()
            ->where('project_assessment_id', $assessment->id)
            ->where('name', DemoDataSeeder::TH101_PROJECT_TEAM)
            ->first();
        $this->assertNotNull($team);
        $this->assertSame(ProjectStatus::Open, $team->status);

        $this->assertSame(0, Credential::query()->count());

        $this->actingAs($student1)
            ->get(route('student.surveys.index'))
            ->assertOk()
            ->assertSee(DemoDataSeeder::TH101_SURVEY_TITLE);

        $this->actingAs($student1)
            ->get(route('student.surveys.show', $survey))
            ->assertOk()
            ->assertSee(__('feedback.submit'));

        $this->actingAs($student1)
            ->get(route('events.index'))
            ->assertOk()
            ->assertSee(DemoDataSeeder::TH101_EVENT_TITLE);

        $this->actingAs($student1)
            ->get(route('events.show', $event))
            ->assertOk()
            ->assertSee(__('events.reserve'));

        $this->actingAs($student1)
            ->get(route('student.projects.index', $th101))
            ->assertOk()
            ->assertSee(DemoDataSeeder::TH101_PROJECT_TITLE)
            ->assertSee(DemoDataSeeder::TH101_PROJECT_TEAM);

        $this->actingAs($student1)
            ->get(route('live-quiz.join'))
            ->assertOk();

        $this->actingAs($ins1)
            ->get(route('teach.live-quiz.session', [$th101, $session]))
            ->assertOk()
            ->assertSee($session->join_code, false);
    }

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
}
