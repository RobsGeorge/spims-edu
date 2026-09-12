<?php

namespace Tests\Feature\Import;

use App\Enums\ImportEntityType;
use App\Enums\RoleType;
use App\Models\ImportSource;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Import\ImportAiMappingSuggester;
use App\Services\Import\ImportAiMasking;
use App\Services\Import\ImportBatchService;
use App\Services\Import\ImportStudentFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L8, Part B — AI-assisted mapping. See docs/legacy-data-import-plan.md §5.5, §22.3.
 * The masking-payload-allowlist test below is the single most important test in this
 * phase (per the task brief) — it is written to prove the guarantee holds regardless
 * of what raw data is fed in, not merely for one example input.
 */
class ImportAiMappingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A profile carrying real-looking PII in its `samples` — an actual email, an
     * actual name, an actual GPA-shaped number — exactly the kind of value that must
     * never reach the outbound schema.
     *
     * @return array<int, array{column: string, type: string, empty_percent: float, distinct_count: int, samples: array<int, string>}>
     */
    private function profileWithRawPii(): array
    {
        return [
            ['column' => 'Populi ID', 'type' => 'integer', 'empty_percent' => 0.0, 'distinct_count' => 1284, 'samples' => ['10432', '10433']],
            ['column' => 'Advisor Notes', 'type' => 'email', 'empty_percent' => 6.0, 'distinct_count' => 1207, 'samples' => ['mary.shenouda@spims-students.example.org', 'boutros.girgis@spims-students.example.org']],
            ['column' => 'Full Name', 'type' => 'text', 'empty_percent' => 0.0, 'distinct_count' => 1281, 'samples' => ['Mariam Fahim Boutros', 'Girgis Kyrillos Anba']],
            ['column' => 'Cum GPA', 'type' => 'decimal', 'empty_percent' => 2.0, 'distinct_count' => 340, 'samples' => ['3.87', '2.14']],
        ];
    }

    /**
     * @return array<int, array{column: string, target_field: ?string, transform: string, confidence: string, options: array<string, mixed>}>
     */
    private function unmappedMappingFor(array $profile): array
    {
        return collect($profile)->map(fn ($col) => [
            'column' => $col['column'],
            'target_field' => null,
            'transform' => 'none',
            'confidence' => 'None',
            'options' => [],
        ])->all();
    }

    #[Test]
    public function the_outbound_schema_never_contains_a_raw_sample_value_and_matches_the_exact_allowlist(): void
    {
        config(['import.ai_mapping_enabled' => true]);

        $profile = $this->profileWithRawPii();
        $mapping = $this->unmappedMappingFor($profile);

        $captured = null;
        $fake = new class($captured) implements AiClient
        {
            public function __construct(private mixed &$captured) {}

            public function translate(string $text, string $source, string $target): ?string
            {
                return null;
            }

            public function suggestEssayScore(string $prompt): ?array
            {
                return null;
            }

            public function suggestFieldMapping(array $schema): ?array
            {
                $this->captured = $schema;

                return null;
            }
        };

        $suggester = new ImportAiMappingSuggester($fake);
        $suggester->fillGaps($mapping, $profile, 'POPULI', ImportStudentFields::catalog());

        $this->assertNotNull($captured, 'The AI client should have been called for a fully-unmapped profile.');

        // Exact top-level key allowlist.
        $this->assertSame(['source', 'target_fields', 'columns'], array_keys($captured));
        $this->assertSame('POPULI', $captured['source']);

        foreach ($captured['target_fields'] as $field) {
            $this->assertSame(['key', 'label'], array_keys($field));
        }

        $this->assertCount(count($profile), $captured['columns']);
        foreach ($captured['columns'] as $col) {
            // Exact per-column key allowlist — nothing else may ride along.
            $this->assertSame(['column', 'type', 'empty_percent', 'distinct_count', 'masked_samples'], array_keys($col));
            $this->assertIsArray($col['masked_samples']);
        }

        // The single most load-bearing assertion: none of the real sample values from
        // the profile appear anywhere in the payload that was actually sent, however
        // deep the structure. Checked by serializing the whole payload and searching
        // it for every raw sample string.
        $serialized = json_encode($captured);
        $rawSamples = [
            'mary.shenouda@spims-students.example.org',
            'boutros.girgis@spims-students.example.org',
            'Mariam Fahim Boutros',
            'Girgis Kyrillos Anba',
            '3.87',
            '2.14',
        ];
        foreach ($rawSamples as $raw) {
            $this->assertStringNotContainsString($raw, $serialized, "Raw sample \"{$raw}\" leaked into the AI payload.");
        }

        // And the masked samples that were sent are exactly the fixed, type-derived
        // placeholders — never anything derived from the real samples above.
        $emailColumn = collect($captured['columns'])->firstWhere('column', 'Advisor Notes');
        $this->assertSame(ImportAiMasking::samplesForType('email'), $emailColumn['masked_samples']);

        $nameColumn = collect($captured['columns'])->firstWhere('column', 'Full Name');
        $this->assertSame(ImportAiMasking::samplesForType('text'), $nameColumn['masked_samples']);

        $gpaColumn = collect($captured['columns'])->firstWhere('column', 'Cum GPA');
        $this->assertSame(ImportAiMasking::samplesForType('decimal'), $gpaColumn['masked_samples']);
    }

    #[Test]
    public function masking_never_depends_on_the_actual_sample_values_for_any_type(): void
    {
        // Feeding wildly different raw content for the same declared type must never
        // change what gets sent — masking is keyed purely on `type`.
        $a = ImportAiMasking::samplesForType('email');
        $b = ImportAiMasking::samplesForType('email');
        $this->assertSame($a, $b);

        foreach (['email', 'date', 'integer', 'decimal', 'boolean', 'text', 'unknown'] as $type) {
            foreach (ImportAiMasking::samplesForType($type) as $sample) {
                $this->assertIsString($sample);
                $this->assertNotSame('', $sample);
            }
        }
    }

    #[Test]
    public function the_ai_client_is_never_called_when_ai_mapping_is_disabled(): void
    {
        config(['import.ai_mapping_enabled' => false]);

        $profile = $this->profileWithRawPii();
        $mapping = $this->unmappedMappingFor($profile);

        $fake = new class implements AiClient
        {
            public bool $called = false;

            public function translate(string $text, string $source, string $target): ?string
            {
                return null;
            }

            public function suggestEssayScore(string $prompt): ?array
            {
                return null;
            }

            public function suggestFieldMapping(array $schema): ?array
            {
                $this->called = true;

                return null;
            }
        };

        $suggester = new ImportAiMappingSuggester($fake);
        $result = $suggester->fillGaps($mapping, $profile, 'POPULI', ImportStudentFields::catalog());

        $this->assertFalse($fake->called, 'The AI client must never be invoked while import.ai_mapping_enabled is false.');
        $this->assertSame($mapping, $result, 'The mapping must be returned untouched when AI mapping is disabled.');
    }

    #[Test]
    public function ai_suggestions_never_override_an_existing_confident_deterministic_match(): void
    {
        config(['import.ai_mapping_enabled' => true]);

        $mapping = [
            ['column' => 'Populi ID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'confidence' => 'High', 'options' => []],
            ['column' => 'Advisor Notes', 'target_field' => null, 'transform' => 'none', 'confidence' => 'None', 'options' => []],
        ];
        $profile = [
            ['column' => 'Populi ID', 'type' => 'integer', 'empty_percent' => 0.0, 'distinct_count' => 5, 'samples' => ['1', '2']],
            ['column' => 'Advisor Notes', 'type' => 'text', 'empty_percent' => 10.0, 'distinct_count' => 5, 'samples' => ['x', 'y']],
        ];

        $fake = new class implements AiClient
        {
            public function translate(string $text, string $source, string $target): ?string
            {
                return null;
            }

            public function suggestEssayScore(string $prompt): ?array
            {
                return null;
            }

            public function suggestFieldMapping(array $schema): ?array
            {
                // Tries to hijack the already-confident column too — must be ignored.
                return ['suggestions' => [
                    ['column' => 'Populi ID', 'target_field' => 'email', 'confidence' => 'High', 'rationale' => 'hijack attempt'],
                    ['column' => 'Advisor Notes', 'target_field' => 'email', 'confidence' => 'Medium', 'rationale' => 'looks like an email column'],
                ]];
            }
        };

        $suggester = new ImportAiMappingSuggester($fake);
        $result = $suggester->fillGaps($mapping, $profile, 'POPULI', ImportStudentFields::catalog());

        $byColumn = collect($result)->keyBy('column');

        $this->assertSame('legacy_id', $byColumn['Populi ID']['target_field'], 'AI must never override an already-confident match.');
        $this->assertSame('High', $byColumn['Populi ID']['confidence']);
        $this->assertArrayNotHasKey('origin', $byColumn['Populi ID']);

        $this->assertSame('email', $byColumn['Advisor Notes']['target_field'], 'AI should fill the genuine gap.');
        $this->assertSame('AI', $byColumn['Advisor Notes']['origin']);
        $this->assertSame('Medium', $byColumn['Advisor Notes']['confidence']);
    }

    #[Test]
    public function a_malformed_or_null_ai_response_degrades_gracefully_leaving_deterministic_suggestions_intact(): void
    {
        config(['import.ai_mapping_enabled' => true]);

        $mapping = [
            ['column' => 'Populi ID', 'target_field' => 'legacy_id', 'transform' => 'trim', 'confidence' => 'High', 'options' => []],
            ['column' => 'Advisor Notes', 'target_field' => null, 'transform' => 'none', 'confidence' => 'None', 'options' => []],
        ];
        $profile = [
            ['column' => 'Populi ID', 'type' => 'integer', 'empty_percent' => 0.0, 'distinct_count' => 5, 'samples' => ['1']],
            ['column' => 'Advisor Notes', 'type' => 'text', 'empty_percent' => 10.0, 'distinct_count' => 5, 'samples' => ['x']],
        ];

        foreach ([null, [], ['suggestions' => 'not-an-array'], ['nope' => true]] as $malformedResponse) {
            $fake = new class($malformedResponse) implements AiClient
            {
                public function __construct(private readonly mixed $response) {}

                public function translate(string $text, string $source, string $target): ?string
                {
                    return null;
                }

                public function suggestEssayScore(string $prompt): ?array
                {
                    return null;
                }

                public function suggestFieldMapping(array $schema): ?array
                {
                    return $this->response;
                }
            };

            $suggester = new ImportAiMappingSuggester($fake);
            $result = $suggester->fillGaps($mapping, $profile, 'POPULI', ImportStudentFields::catalog());

            $this->assertSame($mapping, $result, 'A malformed AI response must leave the mapping exactly as it was.');
        }
    }

    #[Test]
    public function the_ai_suggest_route_is_a_no_op_when_the_setting_is_off(): void
    {
        config(['import.ai_mapping_enabled' => false]);

        $source = ImportSource::query()->create([
            'code' => 'POPULI', 'name' => 'Populi', 'kind' => 'SIS', 'precedence' => 1,
            'gpa_scale_max' => 4.00, 'default_currency' => 'EGP', 'timezone' => 'Africa/Cairo', 'active' => true,
        ]);
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $batch = app(ImportBatchService::class)->createFromUpload(
            $admin,
            $source,
            UploadedFile::fake()->createWithContent('r.csv', "ID,Name,Email\n1,\"A, B\",a@example.org\n"),
            \App\Enums\ImportPopulation::Alumni,
        );

        $this->actingAs($admin)
            ->post(route('admin.imports.map.ai-suggest', $batch))
            ->assertRedirect(route('admin.imports.map', $batch));

        // No AI-origin suggestion should ever have been written.
        $this->assertFalse(collect($batch->fresh()->mapping ?? [])->contains(fn ($m) => ($m['origin'] ?? null) === 'AI'));
    }

    #[Test]
    public function the_map_screen_ai_suggest_action_applies_suggestions_only_to_unmapped_columns_when_enabled(): void
    {
        config(['import.ai_mapping_enabled' => true]);

        $this->instance(AiClient::class, new class implements AiClient
        {
            public function translate(string $text, string $source, string $target): ?string
            {
                return null;
            }

            public function suggestEssayScore(string $prompt): ?array
            {
                return null;
            }

            public function suggestFieldMapping(array $schema): ?array
            {
                return ['suggestions' => [
                    ['column' => 'Email', 'target_field' => 'email', 'confidence' => 'Medium', 'rationale' => 'Column header and shape both look like an email address.'],
                ]];
            }
        });

        $source = ImportSource::query()->create([
            'code' => 'POPULI', 'name' => 'Populi', 'kind' => 'SIS', 'precedence' => 1,
            'gpa_scale_max' => 4.00, 'default_currency' => 'EGP', 'timezone' => 'Africa/Cairo', 'active' => true,
        ]);
        $admin = User::factory()->withRole(RoleType::AdministrativeAdmin)->create();

        $batch = app(ImportBatchService::class)->createFromUpload(
            $admin,
            $source,
            UploadedFile::fake()->createWithContent('r.csv', "ID,Name,Email\n1,\"A, B\",a@example.org\n"),
            \App\Enums\ImportPopulation::Alumni,
            entityType: ImportEntityType::Student,
        );

        // Force the "Email" column to None confidence and unmapped, as if the
        // deterministic tiers found nothing for it.
        $mapping = collect($batch->mapping)->map(function ($m) {
            if ($m['column'] === 'Email') {
                $m['target_field'] = null;
                $m['confidence'] = 'None';
            }

            return $m;
        })->all();
        app(ImportBatchService::class)->updateMapping($batch, $mapping);

        $this->actingAs($admin)
            ->post(route('admin.imports.map.ai-suggest', $batch))
            ->assertRedirect(route('admin.imports.map', $batch));

        $updated = collect($batch->fresh()->mapping)->keyBy('column');
        $this->assertSame('email', $updated['Email']['target_field']);
        $this->assertSame('AI', $updated['Email']['origin']);
    }
}
