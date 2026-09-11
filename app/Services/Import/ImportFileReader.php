<?php

namespace App\Services\Import;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Reads a staged CSV or XLSX file into headers + row arrays. Everything downstream
 * (profiling, mapping preview, validation, commit) works off this same shape, so the
 * pipeline never cares which format the registrar uploaded.
 */
class ImportFileReader
{
    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, string|null>>, sheet_names: array<int, string>}
     */
    public function read(string $disk, string $path, ?string $sheetName = null, int $headerRow = 1): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->readCsv($disk, $path, $headerRow),
            'xlsx', 'xls' => $this->readSpreadsheet($disk, $path, $sheetName, $headerRow),
            default => throw new RuntimeException("Unsupported import file type: .{$extension}"),
        };
    }

    /**
     * @return array<int, string>
     */
    public function sheetNames(string $disk, string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return [];
        }

        $localPath = $this->localCopy($disk, $path);
        $reader = IOFactory::createReaderForFile($localPath);
        if (method_exists($reader, 'listWorksheetNames')) {
            return $reader->listWorksheetNames($localPath);
        }

        return [];
    }

    private function readCsv(string $disk, string $path, int $headerRow): array
    {
        $contents = Storage::disk($disk)->get($path);
        if ($contents === null) {
            throw new RuntimeException('Import file not found on disk.');
        }

        // Strip a UTF-8 BOM, which Excel-exported CSVs commonly carry and which would
        // otherwise corrupt the first header's name.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

        $lines = preg_split("/\r\n|\r|\n/", (string) $contents);
        $allRows = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            $allRows[] = str_getcsv($line);
        }

        return $this->splitHeaderAndRows($allRows, $headerRow);
    }

    private function readSpreadsheet(string $disk, string $path, ?string $sheetName, int $headerRow): array
    {
        $localPath = $this->localCopy($disk, $path);
        $reader = IOFactory::createReaderForFile($localPath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($localPath);

        $sheet = $sheetName !== null && $sheetName !== ''
            ? $spreadsheet->getSheetByName($sheetName)
            : $spreadsheet->getActiveSheet();

        if ($sheet === null) {
            throw new RuntimeException("Worksheet not found: {$sheetName}");
        }

        $allRows = $sheet->toArray(null, true, true, false);
        $allRows = array_map(
            fn (array $row) => array_map(fn ($cell) => $cell === null ? null : trim((string) $cell), $row),
            $allRows,
        );

        return $this->splitHeaderAndRows($allRows, $headerRow);
    }

    /**
     * @param  array<int, array<int, string|null>>  $allRows
     */
    private function splitHeaderAndRows(array $allRows, int $headerRow): array
    {
        $allRows = array_values($allRows);
        $headerIndex = max(0, $headerRow - 1);

        $headers = array_map(
            fn ($h) => trim((string) ($h ?? '')),
            $allRows[$headerIndex] ?? [],
        );

        $dataRows = array_slice($allRows, $headerIndex + 1);
        // Drop rows that are entirely empty (a trailing blank line, a stray footer row).
        $dataRows = array_values(array_filter(
            $dataRows,
            fn (array $row) => collect($row)->contains(fn ($v) => $v !== null && trim((string) $v) !== '')
        ));

        return ['headers' => $headers, 'rows' => $dataRows, 'sheet_names' => []];
    }

    private function localCopy(string $disk, string $path): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'spims-import-');
        file_put_contents($tmp, Storage::disk($disk)->get($path));

        return $tmp;
    }
}
