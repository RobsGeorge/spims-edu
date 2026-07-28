<?php

namespace Tests\Feature\Hardening;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HardeningReleaseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function health_endpoint_reports_ok_when_database_is_up(): void
    {
        $response = $this->getJson(route('health'));

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', true);
    }

    #[Test]
    public function security_headers_are_present_on_home(): void
    {
        $this->seed();

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    #[Test]
    public function seed_creates_super_admin_and_sample_curriculum(): void
    {
        $this->seed();

        $this->assertNotNull(
            User::query()->where('email', env('SUPERADMIN_EMAIL'))->first()
        );
        $this->assertNotNull(Program::query()->where('code', 'DEMO-DIP')->first());
        $this->assertNotNull(Course::query()->where('code', 'DEMO101')->first());
        $this->assertTrue(CourseOffering::query()->exists());
    }

    #[Test]
    public function login_is_rate_limited(): void
    {
        $this->seed();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('auth.login'), [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('auth.login'), [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    #[Test]
    public function backup_command_writes_sqlite_marker_in_memory(): void
    {
        $dir = storage_path('app/backups-test-'.uniqid());
        File::ensureDirectoryExists($dir);

        $this->artisan('spims:backup-database', [
            '--path' => $dir,
            '--keep' => 14,
        ])->assertSuccessful();

        $files = File::files($dir);
        $this->assertNotEmpty($files);

        File::deleteDirectory($dir);
    }
}
