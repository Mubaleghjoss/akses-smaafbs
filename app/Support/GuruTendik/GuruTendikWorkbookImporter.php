<?php

namespace App\Support\GuruTendik;

use App\Models\GuruTendik;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class GuruTendikWorkbookImporter
{
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
        $allowedColumns = array_flip(GuruTendikSupport::importableColumns());

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

                if (blank($payload['nama'] ?? null)) {
                    $result['skipped']++;

                    continue;
                }

                if (! blank($payload['jenis_ptk'] ?? null) && ! array_key_exists($payload['jenis_ptk'], GuruTendikSupport::jenisPtkOptions())) {
                    $result['skipped']++;

                    continue;
                }

                if (! blank($payload['jk'] ?? null) && ! array_key_exists($payload['jk'], GuruTendikSupport::jkOptions())) {
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
            $heading = match ($heading) {
                'niy', 'nomor_induk_yayasan' => 'nip',
                default => $heading,
            };
            $headings[$columnLetter] = $heading !== '' ? $heading : null;
        }

        return $headings;
    }

    protected function extractCellValue(Worksheet $worksheet, string $columnLetter, int $rowIndex, string $heading): mixed
    {
        $cell = $worksheet->getCell("{$columnLetter}{$rowIndex}");
        $rawValue = $cell->getValue();

        if ($heading === 'tanggal_lahir' && is_numeric($rawValue)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $rawValue)->format('Y-m-d');
            } catch (Throwable) {
            }
        }

        $value = trim((string) $cell->getFormattedValue());

        if ($value === '') {
            return null;
        }

        if ($heading === 'jenis_ptk') {
            return match (strtolower($value)) {
                'guru' => 'Guru',
                'tendik' => 'Tendik',
                'pamong' => 'Pamong',
                default => $value,
            };
        }

        if ($heading === 'jk') {
            $normalized = strtoupper($value);

            return in_array($normalized, ['L', 'P'], true) ? $normalized : $value;
        }

        return $value;
    }

    protected function upsertRecord(array $payload): bool
    {
        $existing = null;

        foreach (['nip', 'nuptk', 'nik'] as $identifier) {
            if (blank($payload[$identifier] ?? null)) {
                continue;
            }

            $existing = GuruTendik::query()->where($identifier, $payload[$identifier])->first();

            if ($existing) {
                break;
            }
        }

        if (! $existing && filled($payload['nama'] ?? null) && filled($payload['tanggal_lahir'] ?? null)) {
            $existing = GuruTendik::query()
                ->where('nama', $payload['nama'])
                ->whereDate('tanggal_lahir', $payload['tanggal_lahir'])
                ->first();
        }

        if ($existing) {
            $existing->fill($payload);
            $existing->save();

            return false;
        }

        GuruTendik::query()->create($payload);

        return true;
    }
}
