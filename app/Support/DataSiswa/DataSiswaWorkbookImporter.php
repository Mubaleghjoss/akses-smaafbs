<?php

namespace App\Support\DataSiswa;

use App\Models\DataSiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class DataSiswaWorkbookImporter
{
    /** @var array<string, 'enum_ya_tidak'|'numeric'> */
    protected array $booleanStorageModes = [];

    /** @return array{created:int,updated:int,skipped:int} */
    public function import(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }

        $worksheet = $reader->load($path)->getSheet(0);
        $headings = $this->extractHeadings($worksheet);
        $allowed = array_flip(DataSiswaSupport::importableColumns());
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($worksheet, $headings, $allowed, &$result): void {
            for ($row = 2; $row <= $worksheet->getHighestDataRow(); $row++) {
                $payload = $this->rowPayload($worksheet, $headings, $allowed, $row);
                if ($payload === []) {
                    $result['skipped']++;
                    continue;
                }

                $existing = $this->findExisting($payload, $row);
                if (! $existing && blank($payload['nama'] ?? null)) {
                    throw ValidationException::withMessages(['import' => "Baris {$row}: nama wajib diisi untuk siswa baru."]);
                }

                $attributes = $this->attributesForSave($payload, $existing, $row);
                if ($existing) {
                    $existing->fill($attributes)->save();
                    $result['updated']++;
                } else {
                    DataSiswa::query()->create($attributes + ['status' => 'aktif']);
                    $result['created']++;
                }
            }
        });

        return $result;
    }

    /** @param array<string, string|null> $headings @param array<string, int> $allowed @return array<string, mixed> */
    protected function rowPayload(Worksheet $worksheet, array $headings, array $allowed, int $row): array
    {
        $payload = [];
        foreach ($headings as $letter => $heading) {
            if (! $heading || ! isset($allowed[$heading])) {
                continue;
            }
            // Keep null when a headed cell is blank: blank optional fields explicitly clear data.
            $payload[$heading] = $this->extractCellValue($worksheet, $letter, $row, $heading);
        }

        return collect($payload)->contains(fn ($value): bool => $value !== null) ? $payload : [];
    }

    /** @param array<string, mixed> $payload */
    protected function findExisting(array $payload, int $row): ?DataSiswa
    {
        $matchesByIdentity = [];

        foreach (['id', 'nisn', 'nipd'] as $field) {
            $value = $payload[$field] ?? null;
            if (blank($value)) {
                continue;
            }

            $matches = DataSiswa::query()->where($field, $value)->limit(2)->get();
            if ($matches->count() > 1) {
                throw ValidationException::withMessages(['import' => "Baris {$row}: {$field} '{$value}' terdaftar pada lebih dari satu siswa. Periksa data master terlebih dahulu."]);
            }
            if ($matches->isNotEmpty()) {
                $matchesByIdentity[$field] = $matches->first();
            }
        }

        $identityOwners = collect($matchesByIdentity)->unique('id')->values();
        if ($identityOwners->count() > 1) {
            $names = $identityOwners->pluck('nama')->filter()->map(fn ($name): string => $this->normalizeName($name))->unique();
            if ($names->count() > 1) {
                $details = collect($matchesByIdentity)
                    ->map(fn (DataSiswa $student, string $field): string => strtoupper($field).' milik "'.$student->nama.'" (ID '.$student->id.')')
                    ->unique()
                    ->implode('; ');
                throw ValidationException::withMessages(['import' => "Baris {$row}: identitas siswa tidak konsisten. {$details}. Tidak ada data yang ditimpa."]);
            }

            // A changed NISN/NIPD is resolved to its current owner instead of
            // attempting an update that would violate the unique index.
            foreach (['nisn', 'nipd', 'id'] as $field) {
                if (isset($matchesByIdentity[$field])) {
                    return $matchesByIdentity[$field];
                }
            }
        } elseif ($identityOwners->isNotEmpty()) {
            return $identityOwners->first();
        }

        $name = trim((string) ($payload['nama'] ?? ''));
        $rombel = trim((string) ($payload['rombel_saat_ini'] ?? ''));
        if ($name === '' || $rombel === '') {
            return null;
        }
        $matches = DataSiswa::query()->where('nama', $name)->where('rombel_saat_ini', $rombel)->limit(2)->get();
        if ($matches->count() > 1) {
            throw ValidationException::withMessages(['import' => "Baris {$row}: nama dan rombel cocok ke lebih dari satu siswa. Periksa identitas sebelum mengimpor."]);
        }

        return $matches->first();
    }

    protected function normalizeName(mixed $name): string
    {
        return strtolower(trim((string) preg_replace('/\\s+/', ' ', (string) $name)));
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    protected function attributesForSave(array $payload, ?DataSiswa $existing, int $row): array
    {
        unset($payload['id']);
        // Required name is never erased; an empty cell preserves the existing name.
        if (array_key_exists('nama', $payload) && blank($payload['nama'])) {
            if (! $existing) {
                throw ValidationException::withMessages(['import' => "Baris {$row}: nama wajib diisi."]);
            }
            unset($payload['nama']);
        }

        $status = strtolower((string) ($payload['status'] ?? $existing?->status ?? 'aktif'));
        if ($status !== '' && ! array_key_exists($status, DataSiswa::statusOptions())) {
            throw ValidationException::withMessages(['import' => "Baris {$row}: status '{$status}' tidak valid."]);
        }
        if ($status === '') {
            $payload['status'] = null;
        }
        if (DataSiswa::isNonActiveStatus($status)) {
            $payload['kategori_non_aktif'] = DataSiswa::resolveNonActiveCategory($status, $payload['kategori_non_aktif'] ?? $existing?->kategori_non_aktif);
        } elseif (array_key_exists('status', $payload)) {
            $payload['kategori_non_aktif'] = null;
            $payload['alasan_non_aktif'] = null;
            $payload['tanggal_non_aktif'] = null;
        }

        return $payload;
    }

    /** @return array<string, string|null> */
    protected function extractHeadings(Worksheet $worksheet): array
    {
        $headings = [];
        for ($i = 1; $i <= Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()); $i++) {
            $letter = Coordinate::stringFromColumnIndex($i);
            $heading = $this->normalizeHeading((string) $worksheet->getCell("{$letter}1")->getFormattedValue());
            $subheading = $this->normalizeHeading((string) $worksheet->getCell("{$letter}2")->getFormattedValue());
            if (in_array($heading, ['data_ayah', 'data_ibu', 'data_wali'], true) && $subheading) {
                $heading .= '_'.$subheading;
            }
            $headings[$letter] = $this->resolveHeadingAlias($heading);
        }
        return $headings;
    }

    protected function normalizeHeading(?string $heading): ?string
    {
        $value = trim((string) preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim((string) $heading))));
        return trim($value, '_') ?: null;
    }

    protected function resolveHeadingAlias(?string $heading): ?string
    {
        return ['jml_saudara_kandung' => 'jumlah_saudara', 'anak_ke_berapa' => 'anak_ke', 'jarak_rumah_ke_sekolah_km' => 'jarak_rumah', 'nomor_kip' => 'no_kip', 'nomor_kks' => 'no_kks', 'no_registrasi_akta_lahir' => 'no_akta_lahir', 'layak_pip_usulan_dari_sekolah' => 'layak_pip', 'hp' => 'wa_ortu', 'data_ayah_nama' => 'nama_ayah', 'data_ayah_tahun_lahir' => 'tahun_lahir_ayah', 'data_ayah_jenjang_pendidikan' => 'pendidikan_ayah', 'data_ayah_pekerjaan' => 'pekerjaan_ayah', 'data_ayah_penghasilan' => 'penghasilan_ayah', 'data_ayah_nik' => 'nik_ayah', 'data_ibu_nama' => 'nama_ibu', 'data_ibu_tahun_lahir' => 'tahun_lahir_ibu', 'data_ibu_jenjang_pendidikan' => 'pendidikan_ibu', 'data_ibu_pekerjaan' => 'pekerjaan_ibu', 'data_ibu_penghasilan' => 'penghasilan_ibu', 'data_ibu_nik' => 'nik_ibu', 'data_wali_nama' => 'nama_wali', 'data_wali_tahun_lahir' => 'tahun_lahir_wali', 'data_wali_jenjang_pendidikan' => 'pendidikan_wali', 'data_wali_pekerjaan' => 'pekerjaan_wali', 'data_wali_penghasilan' => 'penghasilan_wali', 'data_wali_nik' => 'nik_wali'][$heading] ?? $heading;
    }

    protected function extractCellValue(Worksheet $worksheet, string $letter, int $row, string $heading): mixed
    {
        $cell = $worksheet->getCell("{$letter}{$row}");
        $raw = $cell->getValue();
        if (in_array($heading, ['tanggal_lahir', 'tanggal_non_aktif'], true) && is_numeric($raw)) {
            try { return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d'); } catch (Throwable) {}
        }
        $value = trim((string) $cell->getFormattedValue());
        if ($value === '') return null;
        if (in_array($heading, ['penerima_kps', 'penerima_kip', 'layak_pip'], true)) return $this->normalizeBooleanLike($heading, $value);
        return match ($heading) { 'jk' => strtoupper($value), 'status', 'kategori_non_aktif' => strtolower($value), default => $value };
    }

    protected function normalizeBooleanLike(string $column, string $value): int|string
    {
        $normalized = strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
        $bool = match ($normalized) { '1', 'ya', 'yes', 'true', 'y' => true, '0', 'tidak', 'no', 'false', 'n' => false, default => null };
        if ($bool === null) return $value;
        return $this->booleanStorageMode($column) === 'enum_ya_tidak' ? ($bool ? 'Ya' : 'Tidak') : (int) $bool;
    }

    protected function booleanStorageMode(string $column): string
    {
        if (isset($this->booleanStorageModes[$column])) return $this->booleanStorageModes[$column];
        try { $definition = strtolower((string) (DB::selectOne("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'data_siswa' AND COLUMN_NAME = ? LIMIT 1", [$column])->COLUMN_TYPE ?? '')); } catch (Throwable) { $definition = ''; }
        return $this->booleanStorageModes[$column] = str_contains($definition, "enum('ya','tidak')") ? 'enum_ya_tidak' : 'numeric';
    }
}
