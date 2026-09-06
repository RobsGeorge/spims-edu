<?php

namespace Tests\Feature\Api;

use App\Enums\CompletionCriterionKind;
use App\Enums\CompletionOutcome;
use App\Enums\RoleType;
use App\Models\CompletionResult;
use App\Models\ModuleStudentAssessment;
use App\Models\User;
use App\Models\Week;
use App\Services\Completion\CompletionService;
use App\Services\Credentials\CredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Completion\CompletionFixtures;
use Tests\TestCase;

class CompletionApiTest extends TestCase
{
    use CompletionFixtures;
    use RefreshDatabase;

    private function token(User $user, string $role): string
    {
        return $user->createToken('api', ["role:{$role}"])->plainTextToken;
    }

    private function addMinGradeAndEvaluate($offering, User $admin, User $student, float $percent = 90): void
    {
        $this->setGrade($offering, $student, $percent);
        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);
    }

    #[Test]
    public function a_student_sees_pending_then_own_result_after_evaluate(): void
    {
        $offering = $this->offering('S4A1');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $token = $this->token($student, 'STUDENT');
        $pending = $this->withToken($token)
            ->getJson(route('api.v1.offerings.completion', $offering));

        $pending->assertOk()
            ->assertJsonPath('data.outcome', CompletionOutcome::Pending->value)
            ->assertJsonPath('data.met_criteria', [])
            ->assertJsonPath('data.evaluated_at', null);

        Auth::forgetGuards();
        $this->addMinGradeAndEvaluate($offering, $admin, $student);

        $response = $this->withToken($token)
            ->getJson(route('api.v1.offerings.completion', $offering));

        $response->assertOk();
        $this->assertSame(CompletionOutcome::Completed->value, $response->json('data.outcome'));
        $this->assertIsArray($response->json('data.met_criteria'));
        $this->assertNotEmpty($response->json('data.met_criteria'));
        $this->assertNotNull($response->json('data.evaluated_at'));
        $this->assertFalse(array_is_list($response->json('data')));
    }

    #[Test]
    public function a_student_cannot_read_another_offerings_completion(): void
    {
        $mine = $this->offering('S4A2');
        $theirs = $this->offering('S4A3');
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $mine);

        $this->withToken($this->token($student, 'STUDENT'))
            ->getJson(route('api.v1.offerings.completion', $theirs))
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    #[Test]
    public function a_student_lists_only_their_own_credentials(): void
    {
        $offering = $this->offering('S4A4');
        $admin = $this->admin();
        $mine = User::factory()->withRole(RoleType::Student)->create();
        $peer = User::factory()->withRole(RoleType::Student)->create();
        $credentials = app(CredentialService::class);
        $own = $credentials->issueOfferingCompletion($admin, $mine, $offering);
        $other = $credentials->issueOfferingCompletion($admin, $peer, $offering);

        $response = $this->withToken($this->token($mine, 'STUDENT'))
            ->getJson(route('api.v1.me.credentials'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($own->id));
        $this->assertFalse($ids->contains($other->id));
        $this->assertSame(1, $ids->count());
        $this->assertSame($own->serial, $response->json('data.0.serial'));
        $this->assertNotNull($response->json('data.0.issued_at'));
    }

    #[Test]
    public function a_student_cannot_download_another_students_credential(): void
    {
        $offering = $this->offering('S4A5');
        $admin = $this->admin();
        $mine = User::factory()->withRole(RoleType::Student)->create();
        $peer = User::factory()->withRole(RoleType::Student)->create();
        $other = app(CredentialService::class)->issueOfferingCompletion($admin, $peer, $offering);

        $this->withToken($this->token($mine, 'STUDENT'))
            ->get(route('api.v1.credentials.download', $other))
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }

    #[Test]
    public function a_student_can_download_their_own_credential(): void
    {
        $offering = $this->offering('S4A6');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $credential = app(CredentialService::class)->issueOfferingCompletion($admin, $student, $offering);

        $response = $this->withToken($this->token($student, 'STUDENT'))
            ->get(route('api.v1.credentials.download', $credential));

        $response->assertOk();
        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString($credential->serial, $disposition);

        $body = (string) $response->getContent();
        $this->assertTrue(
            str_starts_with($body, '%PDF-') || str_contains($body, '<html') || str_contains($body, '<HTML'),
            'Expected a PDF or HTML credential body'
        );
    }

    #[Test]
    public function a_staffed_instructor_gets_the_cohort_not_an_own_result(): void
    {
        $offering = $this->offering('S4A7');
        $admin = $this->admin();
        $instructor = $this->instructorOn($offering);
        $alice = User::factory()->withRole(RoleType::Student)->create();
        $bob = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($alice, $offering);
        $this->enroll($bob, $offering);
        $this->setGrade($offering, $alice, 90);
        $this->setGrade($offering, $bob, 40);
        $service = app(CompletionService::class);
        $service->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);
        $service->evaluate($admin, $offering);

        $staff = $this->withToken($this->token($instructor, 'INSTRUCTOR'))
            ->getJson(route('api.v1.offerings.completion', $offering));

        $staff->assertOk();
        $this->assertIsList($staff->json('data'));
        $this->assertCount(2, $staff->json('data'));
        $ids = collect($staff->json('data'))->pluck('student_id');
        $this->assertTrue($ids->contains($alice->id));
        $this->assertTrue($ids->contains($bob->id));

        Auth::forgetGuards();

        $own = $this->withToken($this->token($alice, 'STUDENT'))
            ->getJson(route('api.v1.offerings.completion', $offering));

        $own->assertOk();
        $this->assertSame(CompletionOutcome::Completed->value, $own->json('data.outcome'));
        $this->assertArrayNotHasKey(0, $own->json('data'));
    }

    #[Test]
    public function an_unstaffed_instructor_is_denied_cohort_and_evaluate(): void
    {
        $offering = $this->offering('S4A8');
        $outsider = User::factory()->withRole(RoleType::Instructor)->create();
        $token = $this->token($outsider, 'INSTRUCTOR');

        $cohort = $this->withToken($token)
            ->getJson(route('api.v1.offerings.completion', $offering));
        $this->assertContains($cohort->status(), [403, 404]);

        Auth::forgetGuards();

        $evaluate = $this->withToken($token)
            ->postJson(route('api.v1.offerings.completion.evaluate', $offering));
        $this->assertContains($evaluate->status(), [403, 404]);
    }

    #[Test]
    public function an_academic_admin_can_evaluate_and_writes_completion_results(): void
    {
        $offering = $this->offering('S4A9');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $this->setGrade($offering, $student, 90);
        app(CompletionService::class)->addCriterion($admin, $offering->course, [
            'kind' => CompletionCriterionKind::MinGrade->value,
            'threshold' => 70,
            'is_required' => true,
        ]);

        $response = $this->withToken($this->token($admin, 'ACADEMIC_ADMIN'))
            ->postJson(route('api.v1.offerings.completion.evaluate', $offering));

        $response->assertOk();
        $this->assertIsList($response->json('data'));
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($student->id, $response->json('data.0.student_id'));
        $this->assertSame(CompletionOutcome::Completed->value, $response->json('data.0.outcome'));
        $this->assertSame(1, CompletionResult::query()->where('offering_id', $offering->id)->count());
        $this->assertSame(
            CompletionOutcome::Completed,
            CompletionResult::query()->where('offering_id', $offering->id)->first()->outcome
        );
    }

    #[Test]
    public function a_student_cannot_get_or_post_notes_about_themselves(): void
    {
        $offering = $this->offering('S4B1');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        app(\App\Services\Completion\StudentNoteService::class)
            ->add($instructor, $offering, $student, 'Staff only');

        $token = $this->token($student, 'STUDENT');
        $get = $this->withToken($token)
            ->getJson(route('api.v1.teach.offerings.students.notes', [
                'offering' => $offering,
                'student' => $student,
            ]));

        $this->assertContains($get->status(), [403, 404]);
        $this->assertNotSame(200, $get->status());
        $this->assertNull($get->json('data'));

        Auth::forgetGuards();

        $post = $this->withToken($token)
            ->postJson(route('api.v1.teach.offerings.students.notes.store', [
                'offering' => $offering,
                'student' => $student,
            ]), ['body' => 'Should never land']);

        $this->assertContains($post->status(), [403, 404]);
        $this->assertNotSame(200, $post->status());
        $this->assertNull($post->json('data'));
    }

    #[Test]
    public function a_staffed_instructor_can_post_and_get_a_note_but_the_student_cannot(): void
    {
        $offering = $this->offering('S4B2');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $staffToken = $this->token($instructor, 'INSTRUCTOR');

        $created = $this->withToken($staffToken)
            ->postJson(route('api.v1.teach.offerings.students.notes.store', [
                'offering' => $offering,
                'student' => $student,
            ]), ['body' => 'Needs follow-up']);

        $created->assertCreated()
            ->assertJsonPath('data.body', 'Needs follow-up')
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.author_id', $instructor->id);

        Auth::forgetGuards();

        $listed = $this->withToken($staffToken)
            ->getJson(route('api.v1.teach.offerings.students.notes', [
                'offering' => $offering,
                'student' => $student,
            ]));

        $listed->assertOk();
        $this->assertSame('Needs follow-up', $listed->json('data.0.body'));

        Auth::forgetGuards();

        $asStudent = $this->withToken($this->token($student, 'STUDENT'))
            ->getJson(route('api.v1.teach.offerings.students.notes', [
                'offering' => $offering,
                'student' => $student,
            ]));

        $this->assertContains($asStudent->status(), [403, 404]);
        $this->assertNull($asStudent->json('data'));
    }

    #[Test]
    public function a_staffed_instructor_can_rate_a_week_assessment_but_not_a_foreign_week(): void
    {
        $offering = $this->offering('S4B3');
        $other = $this->offering('S4B4');
        $instructor = $this->instructorOn($offering);
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);

        $week = Week::query()->create([
            'offering_id' => $offering->id,
            'number' => 1,
            'title' => 'Week 1',
            'order' => 1,
        ]);
        $foreignWeek = Week::query()->create([
            'offering_id' => $other->id,
            'number' => 1,
            'title' => 'Other week',
            'order' => 1,
        ]);

        $token = $this->token($instructor, 'INSTRUCTOR');
        $ok = $this->withToken($token)
            ->putJson(route('api.v1.teach.offerings.weeks.students.assessment', [
                'offering' => $offering,
                'week' => $week,
                'student' => $student,
            ]), ['rating' => 4, 'comment' => 'Solid work']);

        $ok->assertOk()
            ->assertJsonPath('data.rating', 4)
            ->assertJsonPath('data.comment', 'Solid work')
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.week_id', $week->id);

        $this->assertSame(1, ModuleStudentAssessment::query()->where('week_id', $week->id)->count());

        Auth::forgetGuards();

        $foreign = $this->withToken($token)
            ->putJson(route('api.v1.teach.offerings.weeks.students.assessment', [
                'offering' => $offering,
                'week' => $foreignWeek,
                'student' => $student,
            ]), ['rating' => 2]);
        $this->assertContains($foreign->status(), [404, 422]);
    }

    #[Test]
    public function unauthenticated_credentials_index_is_401_unauthenticated(): void
    {
        $this->getJson(route('api.v1.me.credentials'))
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED')
            ->assertJsonStructure(['message', 'code']);
    }

    #[Test]
    public function a_revoked_credential_is_omitted_from_the_student_list(): void
    {
        $offering = $this->offering('S4B5');
        $admin = $this->admin();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $live = app(CredentialService::class)->issueOfferingCompletion($admin, $student, $offering);
        $revoked = app(CredentialService::class)->issueTranscript($admin, $student);
        $revoked->update(['revoked_at' => now()]);

        $response = $this->withToken($this->token($student, 'STUDENT'))
            ->getJson(route('api.v1.me.credentials'));

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($live->id));
        $this->assertFalse($ids->contains($revoked->id));
    }
}
