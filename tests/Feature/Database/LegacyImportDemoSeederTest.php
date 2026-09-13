<?php

namespace Tests\Feature\Database;

use App\Models\ImportGradeMapping;
use App\Models\ImportSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Demo import sources + starter grade mapping for the legacy-import feature
 * (docs/legacy-data-import-plan.md) — so a fresh environment has something to upload
 * against without any manual setup.
 */
class LegacyImportDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeds_populi_and_canvas_sources_with_a_starter_grade_mapping(): void
    {
        $this->seed();

        $populi = ImportSource::query()->where('code', 'POPULI')->first();
        $this->assertNotNull($populi);
        $this->assertSame('SIS', $populi->kind->value);
        $this->assertTrue($populi->active);

        $canvas = ImportSource::query()->where('code', 'CANVAS')->first();
        $this->assertNotNull($canvas);
        $this->assertSame('LMS', $canvas->kind->value);

        $letters = ImportGradeMapping::query()->where('source_id', $populi->id)->pluck('spims_letter', 'legacy_letter');
        $this->assertSame(['A' => 'A', 'B' => 'B', 'C' => 'C', 'F' => 'F'], $letters->all());
    }

    #[Test]
    public function reseeding_is_idempotent(): void
    {
        $this->seed();
        $this->seed(\Database\Seeders\LegacyImportDemoSeeder::class);

        $this->assertSame(1, ImportSource::query()->where('code', 'POPULI')->count());
        $this->assertSame(4, ImportGradeMapping::query()->count());
    }
}
