<?php

namespace Tests\Feature\StaffUi;

use App\Enums\OfferingClosingStatus;
use App\Enums\OfferingStaffRole;
use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Completion\OfferingClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\InstructorApiFixtures;
use Tests\TestCase;

class StaffTeachCloseUiTest extends TestCase
{
    use InstructorApiFixtures;
    use RefreshDatabase;

    #[Test]
    public function admin_with_permission_closes_after_a_one_time_token(): void
    {
        [$offering, $admin] = $this->announcedOffering();

        $this->actingAs($admin)
            ->from(route('teach.completion.show', $offering))
            ->post(route('teach.offerings.close', $offering))
            ->assertInvalid(['confirmation_token']);

        $this->assertSame(
            OfferingClosingStatus::Announced,
            app(OfferingClosingService::class)->statusFor($offering),
        );

        $page = $this->actingAs($admin)->get(route('teach.completion.show', $offering));
        $page->assertOk()
            ->assertSee(__('teach.close_offering'))
            ->assertSee(__('completion.close_confirm_title'))
            ->assertSee(__('completion.close_confirm_body'), false);
        $token = $this->tokenFrom($page->getContent());

        $this->actingAs($admin)
            ->from(route('teach.completion.show', $offering))
            ->post(route('teach.offerings.close', $offering), [
                'confirmation_token' => $token,
            ])
            ->assertRedirect(route('teach.completion.show', $offering));

        $this->assertSame(
            OfferingClosingStatus::Closed,
            app(OfferingClosingService::class)->statusFor($offering),
        );
        $this->assertTrue(AuditLog::query()->where('action', 'offering_closing.close')->exists());

        $this->actingAs($admin)
            ->from(route('teach.completion.show', $offering))
            ->post(route('teach.offerings.close', $offering), [
                'confirmation_token' => $token,
            ])
            ->assertInvalid(['confirmation_token']);
    }

    #[Test]
    public function instructor_with_offering_close_sees_the_confirm_dialog(): void
    {
        [$offering, $admin, $instructor] = $this->announcedOffering();

        $this->actingAs($instructor)
            ->get(route('teach.show', $offering))
            ->assertOk()
            ->assertSee(__('teach.close_offering'))
            ->assertSee('name="confirmation_token"', false);

        $page = $this->actingAs($instructor)
            ->get(route('teach.completion.show', $offering));
        $page->assertOk()
            ->assertSee(__('teach.close_offering'))
            ->assertSee(__('completion.close_confirm_body'), false);

        // offering.close is granted (dialog + token), but close() still
        // requires credentials.issue — Academic Admin only.
        $this->actingAs($instructor)
            ->from(route('teach.completion.show', $offering))
            ->post(route('teach.offerings.close', $offering), [
                'confirmation_token' => $this->tokenFrom($page->getContent()),
            ])
            ->assertForbidden();

        $this->assertSame(
            OfferingClosingStatus::Announced,
            app(OfferingClosingService::class)->statusFor($offering),
        );
    }

    #[Test]
    public function ta_cannot_see_or_post_close(): void
    {
        [$offering] = $this->announcedOffering();
        $ta = User::factory()->withRole(RoleType::Ta)->create();
        $this->staffOffering($ta, $offering, OfferingStaffRole::Ta);

        $this->actingAs($ta)
            ->get(route('teach.completion.show', $offering))
            ->assertOk()
            ->assertDontSee(__('teach.close_offering'))
            ->assertDontSee('name="confirmation_token"', false);

        $this->actingAs($ta)
            ->from(route('teach.completion.show', $offering))
            ->post(route('teach.offerings.close', $offering), [
                'confirmation_token' => 'deadbeefdeadbeefdeadbeefdeadbeef',
            ])
            ->assertForbidden();

        $this->assertSame(
            OfferingClosingStatus::Announced,
            app(OfferingClosingService::class)->statusFor($offering),
        );
        $this->assertFalse(AuditLog::query()->where('action', 'offering_closing.close')->exists());
    }

    /**
     * @return array{0: \App\Models\CourseOffering, 1: User, 2: User}
     */
    private function announcedOffering(): array
    {
        $offering = $this->offering('TCHCL');
        $instructor = $this->instructorOn($offering);
        $admin = User::factory()->withRole(RoleType::AcademicAdmin)->create();
        $student = User::factory()->withRole(RoleType::Student)->create();
        $this->enroll($student, $offering);
        $this->announceReady($admin, $offering, $student);

        return [$offering, $admin, $instructor];
    }

    private function tokenFrom(string $html): string
    {
        $this->assertSame(1, preg_match('/name="confirmation_token" value="([a-f0-9]+)"/', $html, $matches));

        return $matches[1];
    }
}
