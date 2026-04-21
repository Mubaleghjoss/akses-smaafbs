<?php

namespace App\Support\DataSiswa;

use App\Models\DataSiswa;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class DataSiswaWorkbookImporter
{
    /**
     * @var array<string, 'enum_ya_tidak'|'numeric'>
     */
    protected array $booleanStorageModes = [];

    /**
     * @return array{created:int,updated:int,skipped:int}
     */
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
        $allowedColumns = array_flip(DataSiswaSupport::importableColumns());

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

                if ($payload === [] || blank($payload['nama'] ?? null)) {
                    $result['skipped']++;

                    continue;
                }

                $created = $this->upsertDataSiswa($payload);

                if ($created) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }
            }
        });

        return $result;
    }

    /**
     * @return array<string, string|null>
     */
    protected function extractHeadings(Worksheet $worksheet): array
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestDataColumn());
        $headings = [];

        for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
            $rawHeading = (string) $worksheet->getCell("{$columnLetter}1")->getFormattedValue();
            $subHeading = (string) $worksheet->getCell("{$columnLetter}2")->getFormattedValue();

            $heading = $this->normalizeHeading($rawHeading);
            $normalizedSubHeading = $this->normalizeHeading($subHeading);

            if (in_array($heading, ['data_ayah', 'data_ibu', 'data_wali'], true) && $normalizedSubHeading !== null) {
                $heading = $heading.'_'.$normalizedSubHeading;
            }

            $heading = $this->resolveHeadingAlias($heading);

            $headings[$columnLetter] = $heading !== '' ? $heading : null;
        }

        return $headings;
    }

    protected function normalizeHeading(?string $heading): ?string
    {
        if ($heading === null) {
            return null;
        }

        $normalized = strtolower(trim($heading));
        $normalized = preg_replace('/[^a-z0-9]+/i', '_', $normalized);
        $normalized = trim((string) $normalized, '_');

        return $normalized !== '' ? $normalized : null;
    }

    protected function resolveHeadingAlias(?string $heading): ?string
    {
        if ($heading === null) {
            return null;
        }

        $aliases = [
            'jml_saudara_kandung' => 'jumlah_saudara',
            'anak_ke_berapa' => 'anak_ke',
            'jarak_rumah_ke_sekolah_km' => 'jarak_rumah',
            'nomor_kip' => 'no_kip',
            'nomor_kks' => 'no_kks',
            'no_registrasi_akta_lahir' => 'no_akta_lahir',
            'layak_pip_usulan_dari_sekolah' => 'layak_pip',
            'hp' => 'wa_ortu',
            'data_ayah_nama' => 'nama_ayah',
            'data_ayah_tahun_lahir' => 'tahun_lahir_ayah',
            'data_ayah_jenjang_pendidikan' => 'pendidikan_ayah',
            'data_ayah_pekerjaan' => 'pekerjaan_ayah',
            'data_ayah_penghasilan' => 'penghasilan_ayah',
            'data_ayah_nik' => 'nik_ayah',
            'data_ibu_nama' => 'nama_ibu',
            'data_ibu_tahun_lahir' => 'tahun_lahir_ibu',
            'data_ibu_jenjang_pendidikan' => 'pendidikan_ibu',
            'data_ibu_pekerjaan' => 'pekerjaan_ibu',
            'data_ibu_penghasilan' => 'penghasilan_ibu',
            'data_ibu_nik' => 'nik_ibu',
            'data_wali_nama' => 'nama_wali',
            'data_wali_tahun_lahir' => 'tahun_lahir_wali',
            'data_wali_jenjang_pendidikan' => 'pendidikan_wali',
            'data_wali_pekerjaan' => 'pekerjaan_wali',
            'data_wali_penghasilan' => 'penghasilan_wali',
            'data_wali_nik' => 'nik_wali',
        ];

        return $aliases[$heading] ?? $heading;
    }

    protected function extractCellValue(Worksheet $worksheet, string $columnLetter, int $rowIndex, string $heading): mixed
    {
        $cell = $worksheet->getCell("{$columnLetter}{$rowIndex}");
        $rawValue = $cell->getValue();

        if (in_array($heading, ['tanggal_lahir', 'tanggal_non_aktif'], true) && is_numeric($rawValue)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $rawValue)->format('Y-m-d');
            } catch (Throwable) {
                // Fall through to formatted value.
            }
        }

        $value = trim((string) $cell->getFormattedValue());

        if ($value === '') {
            return null;
        }

        if (in_array($heading, ['penerima_kps', 'penerima_kip', 'layak_pip'], true)) {
            return $this->normalizeBooleanLike($heading, $value);
        }

        if ($heading === 'jk') {
            return strtoupper($value);
        }

        if ($heading === 'status' || $heading === 'kategori_non_aktif') {
            return strtolower($value);
        }

        return $value;
    }

    protected function normalizeBooleanLike(string $column, string $value): int|string
    {
        $normalized = strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));

        $booleanValue = match ($normalized) {
            '1', 'ya', 'yes', 'true', 'y' => true,
            '0', 'tidak', 'no', 'false', 'n' => false,
            default => null,
        };

        if ($booleanValue === null) {
            return $value;
        }

        return $this->booleanStorageMode($column) === 'enum_ya_tidak'
            ? ($booleanValue ? 'Ya' : 'Tidak')
            : ($booleanValue ? 1 : 0);
    }

    protected function booleanStorageMode(string $column): string
    {
        if (array_key_exists($column, $this->booleanStorageModes)) {
            return $this->booleanStorageModes[$column];
        }

        $mode = 'numeric';

        try {
            $columnType = DB::selectOne(
                "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'data_siswa' AND COLUMN_NAME = ? LIMIT 1",
                [$column],
            );

            $definition = strtolower((string) ($columnType->COLUMN_TYPE ?? ''));

            if (str_contains($definition, "enum('ya','tidak')")) {
                $mode = 'enum_ya_tidak';
            }
        } catch (Throwable) {
            // Keep numeric fallback for unsupported drivers/test environments.
        }

        $this->booleanStorageModes[$column] = $mode;

        return $mode;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function upsertDataSiswa(array $payload): bool
    {
        $existing = null;

        if (filled($payload['nipd'] ?? null)) {
            $existing = DataSiswa::query()->where('nipd', $payload['nipd'])->first();
        }

        if (! $existing && filled($payload['nisn'] ?? null)) {
            $existing = DataSiswa::query()->where('nisn', $payload['nisn'])->first();
        }

        if (! $existing && filled($payload['nama'] ?? null) && filled($payload['tanggal_lahir'] ?? null)) {
            $existing = DataSiswa::query()
                ->where('nama', $payload['nama'])
                ->whereDate('tanggal_lahir', $payload['tanggal_lahir'])
                ->first();
        }

        $filteredPayload = array_filter(
            $payload,
            fn ($value): bool => $value !== null && $value !== '',
        );

        $status = strtolower((string) ($filteredPayload['status'] ?? $existing?->status ?? 'aktif'));

        if (DataSiswa::isNonActiveStatus($status)) {
            $filteredPayload['kategori_non_aktif'] = DataSiswa::resolveNonActiveCategory(
                $status,
                $filteredPayload['kategori_non_aktif'] ?? $existing?->kategori_non_aktif,
            );
        } else {
            $filteredPayload['kategori_non_aktif'] = null;
            $filteredPayload['alasan_non_aktif'] = null;
            $filteredPayload['tanggal_non_aktif'] = null;
        }

        if ($existing) {
            $existing->fill($filteredPayload);
            $existing->save();

            return false;
        }

        DataSiswa::query()->create([
            ...$filteredPayload,
            'status' => $filteredPayload['status'] ?? 'aktif',
        ]);

        return true;
    }
}
