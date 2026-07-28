<?php

namespace Tests\Feature\Database;

use App\Models\Language;
use App\Models\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigrationSeedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function migrations_create_all_core_tables(): void
    {
        $this->assertTrue(\Schema::hasTable('users'));
        $this->assertTrue(\Schema::hasTable('programs'));
        $this->assertTrue(\Schema::hasTable('course_offerings'));
        $this->assertTrue(\Schema::hasTable('assessments'));
        $this->assertTrue(\Schema::hasTable('audit_logs'));
        $this->assertTrue(\Schema::hasTable('themes'));
    }

    #[Test]
    public function seeder_populates_languages_theme_and_superadmin(): void
    {
        $this->seed();

        $this->assertSame(3, Language::query()->count());
        $this->assertNotNull(Theme::query()->where('is_active', true)->first());
        $this->assertNotNull(User::query()->where('email', env('SUPERADMIN_EMAIL', 'robeir.george@outlook.com'))->first());
    }
}
