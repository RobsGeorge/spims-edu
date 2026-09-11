<?php

namespace Tests\Feature\Import;

use App\Services\Import\ImportFileReader;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Proves the reader handles both formats a registrar will actually upload. The XLSX
 * case matters on its own: phpoffice/phpspreadsheet's zipstream dependency had to be
 * pinned to a PHP-8.2-compatible release (see composer.json), and reading a real
 * written-then-read-back file is what proves that pin didn't quietly break XLSX
 * support.
 */
class ImportFileReaderTest extends TestCase
{
    #[Test]
    public function it_reads_a_csv_file_with_a_bom(): void
    {
        Storage::fake('local');
        $content = "\xEF\xBB\xBFID,Name\n1001,Mina\n1002,George\n";
        Storage::disk('local')->put('imports/test.csv', $content);

        $result = (new ImportFileReader)->read('local', 'imports/test.csv');

        $this->assertSame(['ID', 'Name'], $result['headers']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame(['1001', 'Mina'], $result['rows'][0]);
    }

    #[Test]
    public function it_drops_entirely_blank_trailing_rows(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/test.csv', "ID,Name\n1001,Mina\n\n\n");

        $result = (new ImportFileReader)->read('local', 'imports/test.csv');

        $this->assertCount(1, $result['rows']);
    }

    #[Test]
    public function it_reads_a_real_xlsx_file(): void
    {
        Storage::fake('local');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Roster');
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('A2', '1001');
        $sheet->setCellValue('B2', 'Mina Boutros');

        $tmp = tempnam(sys_get_temp_dir(), 'spims-xlsx-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        Storage::disk('local')->put('imports/roster.xlsx', file_get_contents($tmp));
        unlink($tmp);

        $result = (new ImportFileReader)->read('local', 'imports/roster.xlsx');

        $this->assertSame(['ID', 'Name'], $result['headers']);
        $this->assertSame(['1001', 'Mina Boutros'], $result['rows'][0]);
    }

    #[Test]
    public function it_reads_a_named_worksheet_by_name(): void
    {
        Storage::fake('local');

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Sheet1');
        $second = $spreadsheet->createSheet();
        $second->setTitle('Export');
        $second->setCellValue('A1', 'Code');
        $second->setCellValue('A2', 'DIP-COPT');

        $tmp = tempnam(sys_get_temp_dir(), 'spims-xlsx-test-');
        (new Xlsx($spreadsheet))->save($tmp);
        Storage::disk('local')->put('imports/multi.xlsx', file_get_contents($tmp));
        unlink($tmp);

        $result = (new ImportFileReader)->read('local', 'imports/multi.xlsx', sheetName: 'Export');

        $this->assertSame(['Code'], $result['headers']);
        $this->assertSame(['DIP-COPT'], $result['rows'][0]);
    }
}
