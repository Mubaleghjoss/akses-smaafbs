<?php

namespace App\Support\Uks;

use App\Models\UksRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class UksRecordWorkbookImporter
{
    protected ?bool $hasSiswaIdColumn = null;

    public function import(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);

        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $spreadsheet = $reader->load($path);
        $worksheet = $spreadsheet->getSheet(0);
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        $headings = $this->extractHeadings($worksheet);
        $allowedColumns = array_flip(UksRecordSupport::importableColumns());

        DB::transaction(function () use ($worksheet, $headings, $allowedColumns, &$result): void {
            $highestRow = $worksheet->getHighestDataRow();

            for ($rowIndex = 2; $rowIndex <= $highestRow; $rowIndex++) {
                $payload = [];

                foreach ($headings as $columnLetter => $heading) {
                    if (! $heading || ! array_key_exists($heading, $allowedColumns)) {
                        continue;
                    }

                    $value = $this->extractCellValue($worksheet, $columnLetter, $rowIndex, $heading);

                    if ($value !== null) {
                        $payload[$heading] = $value;
                    }
                }

                if ($payload === [] || blank($payload['nama_siswa'] ?? null) || blank($payload['tanggal_sakit'] ?? null)) {
                    $result['skipped']++;

                    continue;
                }

                $created = $this->upsertRecord($payload);
                $created ? $result['created']++ : $result['updated']++;
            }
        });

        return $result;
    }

    protected function extractHeadings(Worksheet $worksheet): array
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        $headings = [];

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
            $rawHeading = $worksheet->getCell("{$columnLetter}1")->getFormattedValue();
            $heading = strtolower(trim((string) preg_replace('/\s+/', '_', $rawHeading)));
            $headings[$columnLetter] = $heading !== '' ? $heading : null;
        }

        return $headings;
    }

    protected function extractCellValue(Worksheet $worksheet, string $columnLetter, int $rowIndex, string $heading): mixed
    {
        $cell = $worksheet->getCell("{$columnLetter}{$rowIndex}");
        $rawValue = $cell->getValue();

        if ($heading === 'tanggal_sakit' && is_numeric($rawValue)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $rawValue)->format('Y-m-d');
            } catch (Throwable) {
                // Fall through.
            }
        }

        if (in_array($heading, ['berat_badan', 'tinggi_badan', 'lingkar_kepala'], true)) {
            $numeric = $cell->getCalculatedValue();

            if ($numeric === null || $numeric === '') {
                return null;
            }

            return round((float) $numeric, 2);
        }

        $value = trim((string) $cell->getFormattedValue());

        return $value === '' ? null : $value;
    }

    protected function upsertRecord(array $payload): bool
    {
        if ($this->hasSiswaIdColumn()) {
            $payload['siswa_id'] = UksAnthropometrySupport::resolveStudentId(
                $payload['nama_siswa'] ?? null,
                $payload['kelas'] ?? null,
            );
        }

        $existing = UksRecord::query()
            ->where('nama_siswa', $payload['nama_siswa'])
            ->whereDate('tanggal_sakit', $payload['tanggal_sakit'])
            ->where('kategori', $payload['kategori'] ?? '')
            ->where('kelas', $payload['kelas'] ?? '')
            ->first();

        if ($existing) {
            $existing->fill($payload);
            $existing->save();

            return false;
        }

        UksRecord::query()->create($payload);

        return true;
    }

    protected function hasSiswaIdColumn(): bool
    {
        return $this->hasSiswaIdColumn ??= Schema::hasColumn('uks_records', 'siswa_id');
    }
}
