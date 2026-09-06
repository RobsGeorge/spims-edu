<?php

namespace Tests\Feature\Portal;

use App\Enums\RoleType;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\ThemeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettingsProfilePictureTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function settings_page_shows_the_profile_picture_form(): void
    {
        $this->seed(ThemeSeeder::class);
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee(__('learning.profile_picture'))
            ->assertSee(__('learning.profile_picture_choose'))
            ->assertSee('name="picture"', false)
            ->assertSee(route('settings.picture'), false);
    }

    #[Test]
    public function authenticated_student_can_upload_a_profile_picture(): void
    {
        Storage::fake('local');
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)
            ->from(route('settings.edit'))
            ->post(route('settings.picture'), [
                'picture' => UploadedFile::fake()->image('avatar.jpg', 40, 40),
            ])
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHas('status', __('learning.profile_picture_saved'));

        $fresh = $student->fresh();
        $this->assertNotNull($fresh->avatar_path);
        $this->assertStringStartsWith('uploads/', $fresh->avatar_path);
        $this->assertStringContainsString((string) $student->id, $fresh->avatar_path);
        Storage::disk('local')->assertExists($fresh->avatar_path);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $student->id,
            'action' => 'profile.update',
            'entity_type' => 'User',
            'entity_id' => $student->id,
        ]);
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'profile.update')->count());
    }

    #[Test]
    public function authenticated_instructor_can_upload_a_profile_picture(): void
    {
        Storage::fake('local');
        $instructor = User::factory()->withRole(RoleType::Instructor)->create();

        $this->actingAs($instructor)
            ->post(route('settings.picture'), [
                'picture' => UploadedFile::fake()->image('avatar.png', 32, 32),
            ])
            ->assertRedirect();

        $fresh = $instructor->fresh();
        $this->assertNotNull($fresh->avatar_path);
        $this->assertStringStartsWith('uploads/', $fresh->avatar_path);
        Storage::disk('local')->assertExists($fresh->avatar_path);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $instructor->id,
            'action' => 'profile.update',
            'entity_type' => 'User',
        ]);
    }

    #[Test]
    public function guest_cannot_upload_a_profile_picture(): void
    {
        Storage::fake('local');

        $this->post(route('settings.picture'), [
            'picture' => UploadedFile::fake()->image('avatar.jpg', 40, 40),
        ])->assertRedirect(route('auth.login'));

        $this->assertSame(0, AuditLog::query()->where('action', 'profile.update')->count());
    }

    #[Test]
    public function user_without_profile_permission_is_forbidden(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.picture'), [
                'picture' => UploadedFile::fake()->image('avatar.jpg', 40, 40),
            ])
            ->assertForbidden();

        $this->assertNull($user->fresh()->avatar_path);
        $this->assertSame(0, AuditLog::query()->where('action', 'profile.update')->count());
    }

    #[Test]
    public function missing_picture_fails_validation_with_422(): void
    {
        $student = User::factory()->withRole(RoleType::Student)->create();

        $this->actingAs($student)
            ->postJson(route('settings.picture'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('picture');

        $this->assertNull($student->fresh()->avatar_path);
        $this->assertSame(0, AuditLog::query()->where('action', 'profile.update')->count());
    }
}
