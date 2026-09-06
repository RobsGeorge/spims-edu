<?php

namespace Tests\Feature\Feedback;

use App\Enums\FeedbackQuestionKind;
use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\FeedbackSubmission;
use App\Models\User;
use App\Services\Feedback\FeedbackSurveyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentSurveyUiTest extends TestCase
{
    use FeedbackFixtures;
    use RefreshDatabase;

    #[Test]
    public function student_routes_are_distinct_from_admin_surveys(): void
    {
        $this->assertSame(url('/surveys'), route('student.surveys.index'));
        $this->assertSame(url('/admin/surveys'), route('admin.surveys.index'));
        $this->assertNotSame(route('student.surveys.index'), route('admin.surveys.index'));
        $this->assertNotSame(route('student.surveys.show', 'x'), route('admin.surveys.show', 'x'));
        $this->assertFalse(str_starts_with('student.surveys.index', 'admin.surveys'));
    }

    #[Test]
    public function student_lists_open_surveys_and_not_drafts(): void
    {
        $actors = $this->offeringActors('WS1');
        $open = $this->publishedSurvey($actors['instructor'], $actors['offering'], 'Midterm eval');
        $draft = $this->draftSurvey($actors['instructor'], $actors['offering'], 'Hidden draft');
        $schoolWide = $this->publishedSurvey($actors['admin'], null, 'School climate');

        $this->actingAs($actors['student'])
            ->get(route('student.surveys.index'))
            ->assertOk()
            ->assertSee('Midterm eval')
            ->assertSee('School climate')
            ->assertDontSee('Hidden draft')
            ->assertSee(route('student.surveys.show', $open), false)
            ->assertSee(route('student.surveys.show', $schoolWide), false)
            ->assertDontSee($actors['student']->id, false);

        $this->assertSame(0, FeedbackSubmission::query()->count());
        $this->assertSame(0, AuditLog::query()->where('action', 'feedback.submit')->count());
    }

    #[Test]
    public function student_can_show_and_submit_a_published_survey(): void
    {
        $actors = $this->offeringActors('WS2');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering'], 'Wave web eval', [
            'prompt' => 'Comments',
            'kind' => FeedbackQuestionKind::Text->value,
        ]);
        $question = $this->firstQuestion($survey);

        $this->actingAs($actors['student'])
            ->get(route('student.surveys.show', $survey))
            ->assertOk()
            ->assertSee('Wave web eval')
            ->assertSee('Comments')
            ->assertSee(__('feedback.anonymous_notice'))
            ->assertDontSee($actors['student']->id, false);

        $this->assertSame(0, FeedbackSubmission::query()->count());

        $this->actingAs($actors['student'])
            ->post(route('student.surveys.submit', $survey), [
                'answers' => [$question->id => 'Helpful course'],
            ])
            ->assertRedirect(route('student.surveys.show', $survey));

        $this->assertSame(1, FeedbackSubmission::query()->where('survey_id', $survey->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'feedback.submit')->count());
        $this->assertTrue(FeedbackSubmission::query()->first()->is_anonymous);

        $this->actingAs($actors['student'])
            ->get(route('student.surveys.show', $survey))
            ->assertOk()
            ->assertSee('Helpful course')
            ->assertSee(__('feedback.already_submitted_notice'))
            ->assertDontSee($actors['student']->id, false);
    }

    #[Test]
    public function get_never_writes_domain_data(): void
    {
        $actors = $this->offeringActors('WS3');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $auditsBefore = AuditLog::query()->count();

        $this->actingAs($actors['student'])->get(route('student.surveys.index'))->assertOk();
        $this->actingAs($actors['student'])->get(route('student.surveys.show', $survey))->assertOk();

        $this->assertSame(0, FeedbackSubmission::query()->count());
        $this->assertSame($auditsBefore, AuditLog::query()->count());
    }

    #[Test]
    public function draft_and_out_of_scope_surveys_are_404(): void
    {
        $actors = $this->offeringActors('WS4');
        $draft = $this->draftSurvey($actors['instructor'], $actors['offering']);
        $other = $this->offeringActors('WS4B');
        $foreign = $this->publishedSurvey($other['instructor'], $other['offering'], 'Other section');

        $this->actingAs($actors['student'])
            ->get(route('student.surveys.show', $draft))
            ->assertNotFound();

        $this->actingAs($actors['student'])
            ->get(route('student.surveys.show', $foreign))
            ->assertNotFound();

        $this->actingAs($actors['student'])
            ->post(route('student.surveys.submit', $foreign), [
                'answers' => [$this->firstQuestion($foreign)->id => 'Nope'],
            ])
            ->assertNotFound();
    }

    #[Test]
    public function closed_survey_rejects_submit_and_already_submitted_is_conflict(): void
    {
        $actors = $this->offeringActors('WS5');
        $service = app(FeedbackSurveyService::class);
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);
        $question = $this->firstQuestion($survey);

        $this->actingAs($actors['student'])
            ->post(route('student.surveys.submit', $survey), [
                'answers' => [$question->id => 'First'],
            ])
            ->assertRedirect(route('student.surveys.show', $survey));

        $this->actingAs($actors['student'])
            ->from(route('student.surveys.show', $survey))
            ->post(route('student.surveys.submit', $survey), [
                'answers' => [$question->id => 'Second'],
            ])
            ->assertRedirect(route('student.surveys.show', $survey))
            ->assertSessionHas('error', __('feedback.already_submitted'));

        $this->assertSame(1, FeedbackSubmission::query()->count());
        $this->assertSame('First', \App\Models\FeedbackAnswer::query()->first()->value);

        $closed = $this->publishedSurvey($actors['instructor'], $actors['offering'], 'Late eval');
        $closedQuestion = $this->firstQuestion($closed);
        $service->close($actors['instructor'], $closed);

        $this->actingAs($actors['student'])
            ->get(route('student.surveys.show', $closed))
            ->assertOk()
            ->assertSee(__('feedback.closed'));

        $this->actingAs($actors['student'])
            ->from(route('student.surveys.show', $closed))
            ->post(route('student.surveys.submit', $closed), [
                'answers' => [$closedQuestion->id => 'Too late'],
            ])
            ->assertRedirect(route('student.surveys.show', $closed))
            ->assertSessionHasErrors();
    }

    #[Test]
    public function required_question_is_validated(): void
    {
        $actors = $this->offeringActors('WS6');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);

        $this->actingAs($actors['student'])
            ->from(route('student.surveys.show', $survey))
            ->post(route('student.surveys.submit', $survey), [
                'answers' => [],
            ])
            ->assertRedirect(route('student.surveys.show', $survey))
            ->assertSessionHasErrors();

        $this->assertSame(0, FeedbackSubmission::query()->count());
    }

    #[Test]
    public function guest_is_redirected_and_student_cannot_manage_admin_surveys(): void
    {
        $actors = $this->offeringActors('WS7');
        $survey = $this->publishedSurvey($actors['instructor'], $actors['offering']);

        $this->get(route('student.surveys.index'))->assertRedirect();
        $this->get(route('student.surveys.show', $survey))->assertRedirect();
        $this->post(route('student.surveys.submit', $survey), ['answers' => []])->assertRedirect();

        $this->actingAs($actors['student'])
            ->get(route('admin.surveys.index'))
            ->assertForbidden();
    }

    #[Test]
    public function learning_hub_dashboard_and_player_expose_student_surveys(): void
    {
        $actors = $this->offeringActors('WS8');

        $this->actingAs($actors['student'])
            ->get(route('hubs.learning'))
            ->assertOk()
            ->assertSee(__('hubs.surveys'))
            ->assertSee(route('student.surveys.index'), false);

        $this->actingAs($actors['student'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.surveys'))
            ->assertSee(route('student.surveys.index'), false);

        $this->actingAs($actors['student'])
            ->get(route('courses.player', $actors['offering']))
            ->assertOk()
            ->assertSee(__('learning.surveys'))
            ->assertSee(route('student.surveys.index'), false);

        $this->actingAs($actors['student'])
            ->get(route('learn.offering', $actors['offering']))
            ->assertOk()
            ->assertSee(__('learning.surveys'))
            ->assertSee(route('student.surveys.index'), false);
    }

    #[Test]
    public function student_locale_keys_exist_in_ar_en_fr(): void
    {
        foreach (['ar', 'en', 'fr'] as $locale) {
            foreach (['feedback.inbox_title', 'feedback.submit', 'hubs.surveys', 'dashboard.surveys'] as $key) {
                $this->assertTrue(Lang::has($key, $locale), "{$locale}:{$key}");
                $this->assertNotSame($key, __($key, [], $locale), "{$locale}:{$key}");
            }
        }

        $this->assertSame('الاستبيانات', __('feedback.inbox_title', [], 'ar'));
        $this->assertNotSame(
            __('feedback.inbox_title', [], 'en'),
            __('feedback.inbox_title', [], 'ar')
        );
    }

    #[Test]
    public function user_without_feedback_view_is_forbidden(): void
    {
        $this->forgetAuthz();
        $outsider = User::factory()->withRole(RoleType::FinancialAdmin)->create();

        $this->actingAs($outsider)
            ->get(route('student.surveys.index'))
            ->assertForbidden();
    }
}
