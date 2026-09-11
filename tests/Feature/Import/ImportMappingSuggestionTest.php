<?php

namespace Tests\Feature\Import;

use App\Services\Import\ImportMappingSuggestionService;
use App\Services\Import\ImportProfilerService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportMappingSuggestionTest extends TestCase
{
    private function profileFor(array $headers, array $rows): array
    {
        return (new ImportProfilerService)->profile($headers, $rows);
    }

    #[Test]
    public function populi_id_matches_the_populi_synonym_dictionary_with_high_confidence(): void
    {
        $profile = $this->profileFor(['Populi ID'], [['10432'], ['10433']]);
        $suggestions = (new ImportMappingSuggestionService)->suggest($profile, 'POPULI');

        $this->assertSame('legacy_id', $suggestions[0]['target_field']);
        $this->assertSame('High', $suggestions[0]['confidence']);
    }

    #[Test]
    public function canvas_sis_user_id_matches_the_canvas_dictionary_with_high_confidence(): void
    {
        $profile = $this->profileFor(['SIS User ID'], [['88213']]);
        $suggestions = (new ImportMappingSuggestionService)->suggest($profile, 'CANVAS');

        $this->assertSame('legacy_id', $suggestions[0]['target_field']);
        $this->assertSame('High', $suggestions[0]['confidence']);
    }

    #[Test]
    public function last_first_column_maps_to_first_name_with_a_split_transform(): void
    {
        $profile = $this->profileFor(['Last, First'], [['Boutros, Mina'], ['Samuel, Mariam']]);
        $suggestions = (new ImportMappingSuggestionService)->suggest($profile, 'POPULI');

        $this->assertSame('first_name', $suggestions[0]['target_field']);
        $this->assertSame('name_part_first', $suggestions[0]['transform']);
        $this->assertSame('last_first', $suggestions[0]['options']['format']);
    }

    #[Test]
    public function an_unknown_source_falls_back_to_the_generic_dictionary_at_medium_confidence(): void
    {
        $profile = $this->profileFor(['Email'], [['a@example.org'], ['b@example.org']]);
        $suggestions = (new ImportMappingSuggestionService)->suggest($profile, 'SOME_OTHER_SCHOOL_SYSTEM');

        $this->assertSame('email', $suggestions[0]['target_field']);
        $this->assertSame('Medium', $suggestions[0]['confidence']);
    }

    #[Test]
    public function value_shape_detects_an_email_column_with_an_unrecognized_header_at_low_confidence(): void
    {
        $profile = $this->profileFor(['Contact Info'], [['a@example.org'], ['b@example.org'], ['c@example.org']]);
        $suggestions = (new ImportMappingSuggestionService)->suggest($profile, 'POPULI');

        $this->assertSame('email', $suggestions[0]['target_field']);
        $this->assertSame('Low', $suggestions[0]['confidence']);
    }

    #[Test]
    public function a_column_with_no_match_at_all_is_left_unmapped(): void
    {
        $profile = $this->profileFor(['Advisor Notes'], [['Doing well'], ['Needs a check-in']]);
        $suggestions = (new ImportMappingSuggestionService)->suggest($profile, 'POPULI');

        $this->assertNull($suggestions[0]['target_field']);
        $this->assertSame('None', $suggestions[0]['confidence']);
    }
}
