<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Services\Finance\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentWaveATest extends TestCase
{
    use RefreshDatabase;
    use StudentApiFixtures;

    #[Test]
    public function preferences_persist_and_write_an_audit_row(): void
    {
        $student = $this->student(['preferred_locale' => 'en', 'notify_email' => true]);

        $this->withToken($this->apiToken($student))
            ->putJson(route('api.v1.me.preferences'), [
                'locale' => 'ar',
                'theme' => 'DARK',
                'notify_email' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.preferred_locale', 'ar')
            ->assertJsonPath('data.theme_preference', 'DARK')
            ->assertJsonPath('data.notify_email', false);

        $this->assertSame('ar', $student->fresh()->preferred_locale);
        $this->assertFalse($student->fresh()->notify_email);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $student->id,
            'action' => 'profile.update',
            'entity_type' => 'User',
        ]);
        $this->assertGreaterThan(0, AuditLog::query()->where('action', 'profile.update')->count());
    }

    #[Test]
    public function picture_stores_and_user_resource_shows_avatar_url(): void
    {
        Storage::fake('local');
        $student = $this->student();

        $this->withToken($this->apiToken($student))
            ->post(route('api.v1.me.picture'), [
                'picture' => UploadedFile::fake()->image('avatar.jpg', 40, 40),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.id', $student->id);

        $fresh = $student->fresh();
        $this->assertNotNull($fresh->avatar_path);
        $this->assertStringStartsWith('uploads/', $fresh->avatar_path);
        Storage::disk('local')->assertExists($fresh->avatar_path);

        $url = $this->withToken($this->apiToken($student))
            ->getJson(route('api.v1.me'))
            ->assertOk()
            ->json('data.avatar_url');
        $this->assertNotNull($url);
        $this->assertStringContainsString($fresh->avatar_path, $url);
    }

    #[Test]
    public function dashboard_returns_200_with_money_objects_for_an_enrolled_student(): void
    {
        $bundle = $this->playerBundle('DASH');
        app(WalletService::class)->ensureWallet($bundle['student']);

        $response = $this->withToken($this->apiToken($bundle['student']))
            ->getJson(route('api.v1.dashboard'))
            ->assertOk();

        $wallet = $response->json('data.wallet.egp_money');
        $this->assertIsArray($wallet);
        $this->assertArrayHasKey('minor_units', $wallet);
        $this->assertArrayHasKey('currency', $wallet);
        $this->assertArrayHasKey('formatted', $wallet);
        $this->assertSame('EGP', $wallet['currency']);
        $this->assertIsInt($wallet['minor_units']);
        $this->assertIsNotFloat($wallet['minor_units']);
        $this->assertNotEmpty($response->json('data.offerings'));
    }

    #[Test]
    public function dashboard_is_401_without_a_token(): void
    {
        $this->getJson(route('api.v1.dashboard'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function preferences_are_401_without_a_token(): void
    {
        $this->putJson(route('api.v1.me.preferences'), [
            'locale' => 'en',
            'theme' => 'LIGHT',
        ])->assertUnauthorized();
    }
}
