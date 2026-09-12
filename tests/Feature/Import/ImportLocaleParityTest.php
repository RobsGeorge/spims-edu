<?php

namespace Tests\Feature\Import;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Hard rule 4: every user-facing string is localized ar/en/fr. This checks the keys
 * this feature added, the same way the project-wide LocaleParityTest (referenced in
 * CLAUDE.md) is meant to for every lang file — see docs/legacy-data-import-plan.md §11.13.
 */
class ImportLocaleParityTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $full));
            } else {
                $keys[] = $full;
            }
        }

        return $keys;
    }

    #[Test]
    public function import_lang_file_has_identical_keys_in_all_three_locales(): void
    {
        $en = $this->flattenKeys(require base_path('lang/en/import.php'));
        $ar = $this->flattenKeys(require base_path('lang/ar/import.php'));
        $fr = $this->flattenKeys(require base_path('lang/fr/import.php'));

        sort($en);
        sort($ar);
        sort($fr);

        $this->assertSame($en, $ar, 'ar/import.php is missing or has extra keys vs en/import.php: '.
            implode(', ', array_merge(array_diff($en, $ar), array_diff($ar, $en))));
        $this->assertSame($en, $fr, 'fr/import.php is missing or has extra keys vs en/import.php: '.
            implode(', ', array_merge(array_diff($en, $fr), array_diff($fr, $en))));
    }

    #[Test]
    public function import_lang_file_has_no_empty_values(): void
    {
        foreach (['ar', 'en', 'fr'] as $locale) {
            $flat = $this->flattenKeys(require base_path("lang/{$locale}/import.php"));
            foreach ($flat as $key) {
                $value = data_get(require base_path("lang/{$locale}/import.php"), $key);
                $this->assertNotSame('', trim((string) $value), "lang/{$locale}/import.php[{$key}] is empty");
            }
        }
    }

    #[Test]
    public function hubs_nav_entry_exists_in_all_three_locales(): void
    {
        foreach (['ar', 'en', 'fr'] as $locale) {
            $hubs = require base_path("lang/{$locale}/hubs.php");
            $this->assertArrayHasKey('imports', $hubs, "lang/{$locale}/hubs.php missing 'imports'");
            $this->assertArrayHasKey('imports_desc', $hubs, "lang/{$locale}/hubs.php missing 'imports_desc'");
        }
    }

    #[Test]
    public function archived_status_label_exists_in_all_three_locales(): void
    {
        foreach (['ar', 'en', 'fr'] as $locale) {
            $people = require base_path("lang/{$locale}/people.php");
            $this->assertArrayHasKey('status_ARCHIVED', $people, "lang/{$locale}/people.php missing 'status_ARCHIVED'");
        }
    }

    #[Test]
    public function every_row_level_error_code_the_service_emits_has_a_translation(): void
    {
        $codes = [
            'E_REQUIRED_FIELD_MISSING',
            'E_BAD_DATE',
            'E_ACTIVE_NO_EMAIL',
            'E_DUPLICATE_NATURAL_KEY',
            'W_NO_EMAIL_ALUMNUS',
            'W_UNKNOWN_PROGRAM_CODE',
            // L7 — mid-term cutover (MIDTERM_ENROLLMENT), §22.
            'E_OFFERING_NOT_FOUND',
            'E_OFFERING_AMBIGUOUS',
            'W_OFFERING_ID_MISMATCH',
            'E_UNKNOWN_COMPONENT',
            'E_AMBIGUOUS_COMPONENT',
            'E_BAD_SCORE',
            'E_SCORE_RANGE',
            'E_ENROLLMENT_ALREADY_EXISTS',
            'E_SCORE_ALREADY_EXISTS',
        ];

        foreach (['ar', 'en', 'fr'] as $locale) {
            app()->setLocale($locale);
            foreach ($codes as $code) {
                $translated = __('import.error_code.'.$code, [
                    'field' => 'x', 'column' => 'x', 'value' => 'x', 'legacy_id' => 'x', 'program_code' => 'x',
                    'course_code' => 'x', 'semester_name' => 'x', 'component_name' => 'x', 'reference' => 'x',
                ]);
                $this->assertNotSame('import.error_code.'.$code, $translated, "Missing translation for {$code} in {$locale}");
            }
        }
        app()->setLocale('en');
    }
}
