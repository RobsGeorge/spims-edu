<?php

namespace Tests\Feature\Portal;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Repo-wide guard: scans all Blade templates for two banned patterns:
 *
 *  1. Raw enum ->value echoed as display output — {{ $status->value }}
 *     Map through a lang key or use <x-badge> instead.
 *
 *  2. Raw *_minor integer echoed as display output — {{ $invoice->total_minor }}
 *     Use <x-money :minor=… :currency=…> instead.
 *
 * Acceptable (not flagged):
 *  - value="{{ $enum->value }}"  — HTML form-submission attribute (machine data, not display)
 *  - @selected($foo === $bar->value)  — PHP-expression Blade directive context
 *  - in_array($foo->value, …)   — PHP logic context
 *
 * The test includes a self-verification method that injects a temp file with each
 * banned pattern and asserts the scanner correctly catches it.
 */
class RawEnumAndMinorUnitsGuardTest extends TestCase
{
    /** Directory that is scanned on every run. */
    private string $viewsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->viewsDir = base_path('resources/views');
    }

    // -------------------------------------------------------------------------
    //  Production guard — must stay GREEN on the real codebase
    // -------------------------------------------------------------------------

    #[Test]
    public function no_blade_file_displays_raw_enum_value(): void
    {
        $violations = $this->findRawEnumViolations($this->viewsDir);

        $this->assertEmpty(
            $violations,
            "Found raw enum ->value display output in Blade files.\n"
            . "Use <x-badge> or map through a lang key instead.\n\n"
            . implode("\n", $violations)
        );
    }

    #[Test]
    public function no_blade_file_displays_raw_minor_units(): void
    {
        $violations = $this->findRawMinorViolations($this->viewsDir);

        $this->assertEmpty(
            $violations,
            "Found raw *_minor integer display output in Blade files.\n"
            . "Use <x-money :minor=… :currency=…> instead.\n\n"
            . implode("\n", $violations)
        );
    }

    // -------------------------------------------------------------------------
    //  Self-test — demonstrates the scanner CAN fail when fed bad content
    // -------------------------------------------------------------------------

    #[Test]
    public function scanner_catches_raw_enum_display_in_injected_file(): void
    {
        $tempDir = $this->makeTempDir();

        // Bad: enum value echoed as paragraph text
        file_put_contents(
            $tempDir . '/bad_enum.blade.php',
            '<p class="status">{{ $status->value }}</p>'
        );

        $violations = $this->findRawEnumViolations($tempDir);

        $this->assertNotEmpty(
            $violations,
            'Scanner should have detected raw enum display output in injected file.'
        );

        // Good: same value in a form select option — must NOT be flagged
        file_put_contents(
            $tempDir . '/good_form.blade.php',
            '<option value="{{ $status->value }}" @selected($current === $status->value)>{{ __("status.{$status->value}") }}</option>'
        );

        // Only the bad_enum.blade.php should be in violations now (re-scan directory)
        $violations = $this->findRawEnumViolations($tempDir);
        $violatingFiles = array_unique(array_map(
            fn(string $v) => basename(explode(':', $v)[0]),
            $violations
        ));

        $this->assertContains('bad_enum.blade.php', $violatingFiles, 'Bad file should be flagged.');
        $this->assertNotContains('good_form.blade.php', $violatingFiles, 'Form-value attribute should not be flagged.');

        $this->removeTempDir($tempDir);
    }

    #[Test]
    public function scanner_catches_raw_minor_units_in_injected_file(): void
    {
        $tempDir = $this->makeTempDir();

        file_put_contents(
            $tempDir . '/bad_money.blade.php',
            '<td>{{ $invoice->total_minor }}</td>'
        );

        $violations = $this->findRawMinorViolations($tempDir);

        $this->assertNotEmpty(
            $violations,
            'Scanner should have detected raw *_minor display output in injected file.'
        );

        $this->removeTempDir($tempDir);
    }

    // -------------------------------------------------------------------------
    //  Scanning helpers
    // -------------------------------------------------------------------------

    /**
     * Returns an array of human-readable violation strings for raw enum display output.
     *
     * A line is flagged when it matches the Blade echo pattern for ->value but is
     * NOT inside an HTML value="" attribute (which is machine data, not display).
     *
     * @return list<string>  e.g. ["path/to/file.blade.php:42: {{ $status->value }}"]
     */
    private function findRawEnumViolations(string $dir): array
    {
        return $this->scanBladeFiles($dir, function (string $line): bool {
            // Must contain ->value }} somewhere
            if (! preg_match('/->value\s*\}\}/', $line)) {
                return false;
            }

            // Acceptable: inside an HTML attribute  value="…"  or  value='…'
            // Pattern: value=['"]{echo-expression}['"  so ->value appears after ="
            if (preg_match('/\bvalue\s*=\s*["\'](?:[^"\']*?)\{\{[^}]*->value\s*\}\}/', $line)) {
                return false;
            }

            return true;
        });
    }

    /**
     * Returns violation strings for raw *_minor integer display output.
     *
     * @return list<string>
     */
    private function findRawMinorViolations(string $dir): array
    {
        return $this->scanBladeFiles($dir, function (string $line): bool {
            return (bool) preg_match(
                '/\b(?:total|amount|price|balance|fee|cost)_minor\s*\}\}/',
                $line
            );
        });
    }

    /**
     * Walk $dir recursively for *.blade.php files, apply $check to each line,
     * and collect matching lines as "{relpath}:{lineNum}: {line}" strings.
     *
     * @param  callable(string): bool  $check
     * @return list<string>
     */
    private function scanBladeFiles(string $dir, callable $check): array
    {
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filename = $file->getFilename();
            if (! str_ends_with($filename, '.blade.php')) {
                continue;
            }

            $path = $file->getPathname();
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $lineIndex => $line) {
                if ($check($line)) {
                    $relPath = ltrim(str_replace($dir, '', $path), DIRECTORY_SEPARATOR);
                    $violations[] = "{$relPath}:" . ($lineIndex + 1) . ": {$line}";
                }
            }
        }

        return $violations;
    }

    // -------------------------------------------------------------------------
    //  Temp directory helpers (no test isolation needed — files are transient)
    // -------------------------------------------------------------------------

    private function makeTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/spims_guard_test_' . uniqid('', true);
        mkdir($dir, 0700, true);
        return $dir;
    }

    private function removeTempDir(string $dir): void
    {
        $files = glob($dir . '/*') ?: [];
        foreach ($files as $file) {
            is_file($file) ? unlink($file) : $this->removeTempDir($file);
        }
        rmdir($dir);
    }
}
