<?php

namespace Tests\Feature\Design;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lightweight Phase C copy lint: known admin/discussion hotspots must not
 * regress to hardcoded English placeholders/labels outside __().
 */
class CopyHotspotI18nTest extends TestCase
{
    #[Test]
    public function phase_c_hotspots_avoid_hardcoded_english_labels(): void
    {
        $hotspots = [
            'resources/views/admin/assessments/create.blade.php',
            'resources/views/admin/assessments/banks.blade.php',
            'resources/views/admin/assessments/show.blade.php',
            'resources/views/admin/credentials/index.blade.php',
            'resources/views/admin/live/index.blade.php',
            'resources/views/admin/offerings/show.blade.php',
            'resources/views/admin/users/index.blade.php',
            'resources/views/admin/finance/index.blade.php',
            'resources/views/discussions/board.blade.php',
            'resources/views/discussions/thread.blade.php',
            'resources/views/assignments/show.blade.php',
        ];

        $forbidden = [
            'placeholder="Title"',
            'placeholder="Minutes"',
            'placeholder="Draw N"',
            'placeholder="Bank name"',
            'placeholder="Option A"',
            'placeholder="Option B"',
            'placeholder="Prompt"',
            'placeholder="question ULID"',
            'placeholder="student ULID"',
            'placeholder="program ULID"',
            'placeholder="offering ULID"',
            'placeholder="USD minor"',
            'placeholder="EGP minor"',
            'placeholder="Vimeo ID"',
            'placeholder="PDF URL"',
            'placeholder="Thread title"',
            'placeholder="Opening post"',
            'placeholder="file url"',
            '>Create bank<',
            '>Add question<',
            '>Attach<',
            '>New thread<',
            '>Submit<',
            'Fixed questions',
            'No component',
            'correct index',
            '$role->value }}"> {{ $role->value }}',
            "pluck('value')->join",
        ];

        foreach ($hotspots as $relative) {
            $path = base_path($relative);
            $this->assertFileExists($path, $relative);
            $contents = file_get_contents($path);
            $this->assertIsString($contents);

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $contents,
                    "{$relative} still contains hardcoded English: {$needle}"
                );
            }

            $this->assertStringContainsString(
                '__',
                $contents,
                "{$relative} should use localization helpers"
            );
        }
    }

    #[Test]
    public function role_labels_exist_for_all_role_types_in_three_locales(): void
    {
        $roles = [
            'SUPER_ADMIN',
            'ADMINISTRATIVE_ADMIN',
            'ACADEMIC_ADMIN',
            'FINANCIAL_ADMIN',
            'INSTRUCTOR',
            'TA',
            'STUDENT',
        ];

        foreach (['en', 'ar', 'fr'] as $locale) {
            $lines = include lang_path("{$locale}/roles_hub.php");
            foreach ($roles as $role) {
                $key = 'role_'.$role;
                $this->assertArrayHasKey($key, $lines, "Missing {$locale}.roles_hub.{$key}");
                $this->assertNotSame($role, $lines[$key], "{$locale}.roles_hub.{$key} should be human-readable");
            }
        }
    }

    #[Test]
    public function minor_units_and_ulid_helpers_mirrored_in_locales(): void
    {
        foreach (['en', 'ar', 'fr'] as $locale) {
            $finance = include lang_path("{$locale}/finance.php");
            $offerings = include lang_path("{$locale}/offerings.php");
            $assessment = include lang_path("{$locale}/assessment.php");
            $credentials = include lang_path("{$locale}/credentials.php");
            $live = include lang_path("{$locale}/live.php");

            $this->assertNotEmpty($finance['minor_units_hint'] ?? null);
            $this->assertNotEmpty($offerings['minor_units_hint'] ?? null);
            $this->assertNotEmpty($assessment['draw_hint'] ?? null);
            $this->assertNotEmpty($assessment['question_ulid_hint'] ?? null);
            $this->assertNotEmpty($credentials['ulid_hint'] ?? null);
            $this->assertNotEmpty($live['student_ulid_hint'] ?? null);
        }
    }
}
