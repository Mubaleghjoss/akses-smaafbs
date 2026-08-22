<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheetExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BelajarIdImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $template = [
            ['NAMA', 'STATUS', 'EMAIL', 'PASSWORD'],
            ['Contoh Siswa', 'X IPA 1', 'siswa.contoh@belajar.id', 'password123'],
            ['Contoh Guru', 'guru', 'guru.contoh@belajar.id', 'password123'],
            ['Contoh Tendik', 'tendik', 'tendik.contoh@belajar.id', 'password123'],
        ];

        $guide = [
            ['Panduan Import Akun Belajar.id'],
            [''],
            ['Kolom wajib: NAMA, STATUS, EMAIL, PASSWORD.'],
            ['STATUS diisi "guru" atau "tendik" untuk akun guru/tendik (muncul di menu Guru).'],
            ['STATUS diisi nama kelas (mis. "X IPA 1") untuk akun siswa (muncul di menu Siswa).'],
            ['EMAIL harus unik dan berformat email valid; data dengan email sama akan diperbarui.'],
            ['Baris tanpa NAMA / EMAIL valid / PASSWORD akan dilewati.'],
        ];

        return [
            new ArraySheetExport($template, 'template_belajar_id'),
            new ArraySheetExport($guide, 'panduan'),
        ];
    }
}
