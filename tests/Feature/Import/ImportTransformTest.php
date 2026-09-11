<?php

namespace Tests\Feature\Import;

use App\Services\Import\ImportTransformService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportTransformTest extends TestCase
{
    #[Test]
    public function trim_and_case_transforms(): void
    {
        $t = new ImportTransformService;

        $this->assertSame('mina', $t->apply('trim', '  mina  ')['value']);
        $this->assertSame('mina@example.org', $t->apply('lower', 'MINA@Example.ORG')['value']);
        $this->assertSame('MINA', $t->apply('upper', 'mina')['value']);
    }

    #[Test]
    public function date_transform_parses_the_declared_format_and_normalizes_to_iso(): void
    {
        $t = new ImportTransformService;

        $result = $t->apply('date', '14/03/1994', ['format' => 'd/m/Y']);
        $this->assertTrue($result['ok']);
        $this->assertSame('1994-03-14', $result['value']);
    }

    #[Test]
    public function date_transform_reports_failure_for_a_mismatched_format(): void
    {
        $t = new ImportTransformService;

        // 14 is not a valid month, so m/d/Y parsing of a d/m/Y value fails cleanly.
        $result = $t->apply('date', '14/03/1994', ['format' => 'm/d/Y']);
        $this->assertFalse($result['ok']);
        $this->assertNull($result['value']);
    }

    #[Test]
    public function date_transform_treats_an_empty_value_as_ok(): void
    {
        $t = new ImportTransformService;

        $result = $t->apply('date', '', ['format' => 'd/m/Y']);
        $this->assertTrue($result['ok']);
        $this->assertNull($result['value']);
    }

    #[Test]
    public function name_part_splits_last_first_format_on_the_comma(): void
    {
        $t = new ImportTransformService;

        $first = $t->apply('name_part_first', 'Boutros, Mina', ['format' => 'last_first']);
        $last = $t->apply('name_part_last', 'Boutros, Mina', ['format' => 'last_first']);

        $this->assertSame('Mina', $first['value']);
        $this->assertSame('Boutros', $last['value']);
    }

    #[Test]
    public function name_part_splits_first_last_format_on_space_when_no_comma(): void
    {
        $t = new ImportTransformService;

        $first = $t->apply('name_part_first', 'Mina Boutros', ['format' => 'first_last']);
        $last = $t->apply('name_part_last', 'Mina Boutros', ['format' => 'first_last']);

        $this->assertSame('Mina', $first['value']);
        $this->assertSame('Boutros', $last['value']);
    }

    #[Test]
    public function normalize_arabic_strips_tashkeel_without_transliterating(): void
    {
        $t = new ImportTransformService;

        // "مُحَمَّد" with diacritics -> "محمد" without. The letters themselves are untouched.
        $result = $t->normalizeArabic('مُحَمَّد');

        $this->assertSame('محمد', $result);
    }

    #[Test]
    public function normalize_arabic_unifies_alef_and_ta_marbuta_variants(): void
    {
        $t = new ImportTransformService;

        $this->assertSame('ابراهيم', $t->normalizeArabic('إبراهيم'));
        $this->assertSame('مدرسه', $t->normalizeArabic('مدرسة'));
    }

    #[Test]
    public function constant_transform_ignores_the_raw_value(): void
    {
        $t = new ImportTransformService;

        $result = $t->apply('constant', 'anything at all', ['value' => 'en']);
        $this->assertSame('en', $result['value']);
    }
}
