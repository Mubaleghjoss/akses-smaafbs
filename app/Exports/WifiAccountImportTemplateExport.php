<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheetExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class WifiAccountImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $template = [
            ['USERNAME', 'PASSWORD', 'PROFIL', 'KELAS', 'ROLE'],
            ['siswa001', '01-01-2010', 'default', 'X IPA 1', 'siswa'],
            ['guru.budi', 'rahasia123', 'default', 'guru', 'guru'],
        ];

        $guide = [
            ['Panduan Import Akun WiFi (jembatan)'],
            [''],
            ['Kolom: USERNAME, PASSWORD (wajib); PROFIL, KELAS, ROLE (opsional).'],
            ['ROLE "guru" => akun tampil di menu Guru; selain itu (kosong/siswa) => menu Siswa.'],
            ['KELAS untuk siswa diisi kelas; untuk guru bisa diisi guru/tendik.'],
            ['PROFIL opsional; kosong dianggap "default".'],
            ['Baris tanpa USERNAME atau PASSWORD dilewati. Upsert berdasar USERNAME.'],
            ['Akun hasil import ditandai sumber "otomatis".'],
        ];

        return [
            new ArraySheetExport($template, 'template_wifi'),
            new ArraySheetExport($guide, 'panduan'),
        ];
    }
}
