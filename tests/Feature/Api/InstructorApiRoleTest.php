<?php

namespace Tests\Feature\Api;

use App\Enums\AnnouncementStatus;
use App\Enums\OfferingClosingStatus;
use App\Enums\RoleType;
use App\Models\StudentNote;
use App\Models\User;
use App\Services\Communications\AnnouncementService;
use App\Services\Completion\StudentNoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstructorApiRoleTest extends TestCase
{
    use InstructorApiFixtures;
    use RefreshDatabase;

    #[Test]
    public function a_student_token_cannot_list_teach_offerings(): void
    {
        $world = $this->staffTwoOfferings();
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->asApi($student, 'STUDENT')
            ->getJson('/api/v1/teach/offerings')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    #[Test]
    public function unauthenticated_teach_offerings_is_401(): void
    {
        $this->getJson('/api/v1/teach/offerings')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function instructor_staffed_on_a_lists_only_a_and_can_read_confirmation(): void
    {
        $world = $this->staffTwoOfferings();

        $list = $this->asApi($world['instructorA'], 'INSTRUCTOR')
            ->getJson('/api/v1/teach/offerings')
            ->assertOk();

        $ids = collect($list->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($world['offeringA']->id));
        $this->assertFalse($ids->contains($world['offeringB']->id));
        $this->assertSame(1, $list->json('meta.total'));

        $show = $this->asApi($world['instructorA'], 'INSTRUCTOR')
            ->getJson('/api/v1/teach/offerings/'.$world['offeringA']->id)
            ->assertOk();

        $confirmation = $show->json('data.confirmation');
        $this->assertIsArray($confirmation['gradebook.lock'] ?? null);
        $this->assertIsArray($confirmation['offering.close'] ?? null);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $confirmation['gradebook.lock']['confirmation_token']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $confirmation['offering.close']['confirmation_token']);
        $this->assertNotEmpty($confirmation['gradebook.lock']['consequences']);
        $this->assertNotEmpty($confirmation['offering.close']['consequences']);
    }

    #[Test]
    public function ta_is_denied_announcement_publish_and_offering_close(): void
    {
        $world = $this->staffTwoOfferings();
        $offering = $world['offeringA'];
        $ta = $world['taA'];

        $draft = app(AnnouncementService::class)->draft($ta, $offering, [
            'title' => 'TA draft',
            'body' => 'TA body',
        ]);

        $this->asApi($ta, 'TA')
            ->postJson('/api/v1/teach/announcements/'.$draft->id.'/publish')
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');

        $this->assertSame(AnnouncementStatus::Draft, $draft->fresh()->status);

        $this->asApi($ta, 'TA')
            ->postJson('/api/v1/teach/offerings/'.$offering->id.'/close', [
                'confirmation' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    #[Test]
    public function instructor_close_is_forbidden_without_credentials_issue(): void
    {
        $world = $this->staffTwoOfferings();
        $this->announceReady($world['admin'], $world['offeringA'], $world['studentA']);

        $token = $this->asApi($world['instructorA'], 'INSTRUCTOR')
            ->getJson('/api/v1/teach/offerings/'.$world['offeringA']->id)
            ->assertOk()
            ->json('data.confirmation')['offering.close']['confirmation_token'];

        $this->asApi($world['instructorA'], 'INSTRUCTOR')
            ->postJson('/api/v1/teach/offerings/'.$world['offeringA']->id.'/close', [
                'confirmation' => $token,
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    #[Test]
    public function academic_admin_can_close_with_credentials_issue(): void
    {
        $world = $this->staffTwoOfferings();
        $this->announceReady($world['admin'], $world['offeringA'], $world['studentA']);

        $token = $this->asApi($world['admin'], 'ACADEMIC_ADMIN')
            ->getJson('/api/v1/teach/offerings/'.$world['offeringA']->id)
            ->assertOk()
            ->json('data.confirmation')['offering.close']['confirmation_token'];

        $this->asApi($world['admin'], 'ACADEMIC_ADMIN')
            ->postJson('/api/v1/teach/offerings/'.$world['offeringA']->id.'/close', [
                'confirmation' => $token,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OfferingClosingStatus::Closed->value);
    }

    #[Test]
    public function student_drill_down_is_404_when_not_enrolled_and_does_not_leak_notes(): void
    {
        $world = $this->staffTwoOfferings();
        $secret = 'STAFF-ONLY-NOTE-BODY-'.$world['studentA']->id;
        app(StudentNoteService::class)->add(
            $world['instructorA'],
            $world['offeringA'],
            $world['studentA'],
            $secret,
        );

        $ok = $this->asApi($world['instructorA'], 'INSTRUCTOR')
            ->getJson('/api/v1/teach/offerings/'.$world['offeringA']->id.'/students/'.$world['studentA']->id)
            ->assertOk();

        $this->assertSame($world['studentA']->id, $ok->json('data.profile.id'));
        $this->assertSame($world['studentA']->email, $ok->json('data.profile.email'));
        $this->assertIsArray($ok->json('data.grades'));
        $this->assertArrayNotHasKey('notes', $ok->json('data'));
        $this->assertStringNotContainsString($secret, (string) $ok->getContent());
        $this->assertTrue(StudentNote::query()->where('body', $secret)->exists());

        $stranger = User::factory()->withRole(RoleType::Student)->create();
        $this->asApi($world['instructorA'], 'INSTRUCTOR')
            ->getJson('/api/v1/teach/offerings/'.$world['offeringA']->id.'/students/'.$stranger->id)
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }
}
