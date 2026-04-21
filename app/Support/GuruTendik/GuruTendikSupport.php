<?php

namespace App\Support\GuruTendik;

use App\Models\GuruTendik;

class GuruTendikSupport
{
    public static function exportableColumns(): array
    {
        return [
            'id',
            'nama',
            'nip',
            'nuptk',
            'nik',
            'jenis_ptk',
            'jk',
            'tempat_lahir',
            'tanggal_lahir',
            'status',
            'created_at',
            'updated_at',
        ];
    }

    public static function importableColumns(): array
    {
        return [
            'nama',
            'nip',
            'nuptk',
            'nik',
            'jenis_ptk',
            'jk',
            'tempat_lahir',
            'tanggal_lahir',
            'status',
        ];
    }

    public static function templateRows(): array
    {
        $columns = self::importableColumns();
        $exampleValues = [
            'nama' => 'Ustadz Ahmad Fauzi',
            'nip' => '198702012010011001',
            'nuptk' => '1234567890123456',
            'nik' => '3201010101900001',
            'jenis_ptk' => 'Guru',
            'jk' => 'L',
            'tempat_lahir' => 'Bogor',
            'tanggal_lahir' => '1987-02-01',
            'status' => 'aktif',
        ];

        return [
            $columns,
            array_map(fn (string $column): mixed => $exampleValues[$column] ?? null, $columns),
        ];
    }

    public static function guideRows(): array
    {
        return [
            ['PETUNJUK IMPORT GURU / TENDIK'],
            ['1', 'Gunakan nama kolom persis seperti sheet template.'],
            ['2', 'Nilai jenis_ptk yang didukung hanya: Guru atau Tendik.'],
            ['3', 'Nilai jk yang didukung hanya: L atau P.'],
            ['4', 'Sistem akan mencoba mencocokkan data berdasarkan NIP, lalu NUPTK, lalu NIK.'],
            ['5', 'Jika ketiga identifier kosong, fallback terakhir adalah nama + tanggal_lahir.'],
            ['6', 'Format tanggal_lahir yang aman: YYYY-MM-DD.'],
            ['7', 'Baris dengan jenis_ptk atau jk tidak valid akan dilewati.'],
        ];
    }

    public static function jenisPtkOptions(): array
    {
        return GuruTendik::jenisPtkOptions();
    }

    public static function jkOptions(): array
    {
        return GuruTendik::jkOptions();
    }
}
