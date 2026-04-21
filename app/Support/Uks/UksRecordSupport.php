<?php

namespace App\Support\Uks;

use Illuminate\Support\Facades\Schema;

class UksRecordSupport
{
    protected static ?bool $tableAvailable = null;

    /**
     * @var array<int, string>|null
     */
    protected static ?array $exportableColumnsCache = null;

    public static function exportableColumns(): array
    {
        if (static::$exportableColumnsCache !== null) {
            return static::$exportableColumnsCache;
        }

        if (static::tableAvailable()) {
            return static::$exportableColumnsCache = array_values(array_filter(
                Schema::getColumnListing('uks_records'),
                fn (string $column): bool => $column !== 'siswa_id',
            ));
        }

        return static::$exportableColumnsCache = [
            'id',
            'nama_siswa',
            'kelas',
            'tanggal_sakit',
            'kategori',
            'penanganan',
            'berat_badan',
            'tinggi_badan',
            'lingkar_kepala',
            'catatan',
            'admin_id',
            'created_at',
            'updated_at',
        ];
    }

    public static function importableColumns(): array
    {
        return array_values(array_filter(
            self::exportableColumns(),
            fn (string $column): bool => ! in_array($column, ['id', 'created_at', 'updated_at'], true),
        ));
    }

    public static function templateRows(): array
    {
        $columns = self::importableColumns();
        $exampleValues = [
            'nama_siswa' => 'ABIEL KHIAR SHAHREZA',
            'kelas' => 'X.I / 2025-2026',
            'tanggal_sakit' => '2026-03-26',
            'kategori' => 'Demam',
            'penanganan' => 'Istirahat dan observasi UKS',
            'berat_badan' => 45.5,
            'tinggi_badan' => 162.0,
            'lingkar_kepala' => 53.0,
            'catatan' => 'Keluhan pusing sejak pagi.',
        ];

        return [
            $columns,
            array_map(fn (string $column): mixed => $exampleValues[$column] ?? null, $columns),
        ];
    }

    public static function guideRows(): array
    {
        return [
            ['PETUNJUK IMPORT DATA UKS'],
            ['1', 'Gunakan nama kolom persis seperti template.'],
            ['2', 'Kolom minimal yang dianjurkan: nama_siswa, kelas, tanggal_sakit, kategori.'],
            ['3', 'Jika nama_siswa, kelas, tanggal_sakit, dan kategori sama persis, baris akan diperbarui.'],
            ['4', 'Format tanggal_sakit yang aman: YYYY-MM-DD.'],
            ['5', 'Kolom berat_badan, tinggi_badan, dan lingkar_kepala boleh dikosongkan jika belum diukur.'],
            ['6', 'Template ini otomatis mengikuti kolom yang tersedia pada tabel uks_records saat ini.'],
        ];
    }

    protected static function tableAvailable(): bool
    {
        return static::$tableAvailable ??= Schema::hasTable('uks_records');
    }
}
