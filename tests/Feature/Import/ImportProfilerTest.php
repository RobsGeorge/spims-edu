<?php

namespace Tests\Feature\Import;

use App\Services\Import\ImportProfilerService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportProfilerTest extends TestCase
{
    #[Test]
    public function it_infers_the_type_of_each_column(): void
    {
        $profiler = new ImportProfilerService;

        $headers = ['Populi ID', 'Email', 'Birthdate', 'Cum GPA', 'Notes'];
        $rows = [
            ['10432', 'mina@example.org', '14/03/1994', '3.42', 'Some notes'],
            ['10433', 'george@example.org', '02/11/1988', '2.90', ''],
            ['10434', 'sarah@example.org', '2020-01-05', '3.85', 'More notes'],
        ];

        $profile = collect($profiler->profile($headers, $rows))->keyBy('column');

        $this->assertSame('integer', $profile['Populi ID']['type']);
        $this->assertSame('email', $profile['Email']['type']);
        $this->assertSame('date', $profile['Birthdate']['type']);
        $this->assertSame('decimal', $profile['Cum GPA']['type']);
        $this->assertSame('text', $profile['Notes']['type']);
    }

    #[Test]
    public function it_computes_empty_percent_and_distinct_count(): void
    {
        $profiler = new ImportProfilerService;

        $headers = ['Phone'];
        $rows = [['+201001112223'], [''], ['+201001112223'], [null]];

        $profile = $profiler->profile($headers, $rows)[0];

        $this->assertSame(50.0, $profile['empty_percent']);
        $this->assertSame(1, $profile['distinct_count']);
    }

    #[Test]
    public function samples_are_capped_at_five_unique_values(): void
    {
        $profiler = new ImportProfilerService;

        $headers = ['Code'];
        $rows = array_map(fn ($i) => ["CODE-{$i}"], range(1, 10));

        $profile = $profiler->profile($headers, $rows)[0];

        $this->assertCount(5, $profile['samples']);
        $this->assertSame(10, $profile['distinct_count']);
    }

    #[Test]
    public function an_entirely_empty_column_is_reported_as_unknown_type(): void
    {
        $profiler = new ImportProfilerService;

        $profile = $profiler->profile(['Blank'], [[''], [null], ['']])[0];

        $this->assertSame('unknown', $profile['type']);
        $this->assertSame(100.0, $profile['empty_percent']);
        $this->assertSame(0, $profile['distinct_count']);
    }
}
