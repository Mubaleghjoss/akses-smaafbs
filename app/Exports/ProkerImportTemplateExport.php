<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheetExport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProkerImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ArraySheetExport($this->getTemplateRows(), 'template_import'),
            new ArraySheetExport($this->getGuideRows(), 'panduan'),
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getTemplateRows(): array
    {
        return [
            [
                'periode_tahun',
                'periode_label',
                'point_dari',
                'nomor_urut',
                'nama_proker',
                'penanggung_jawab',
                'jadwal_jun_2026',
                'jadwal_jul_2026',
                'jadwal_aug_2026',
                'jadwal_sep_2026',
                'jadwal_oct_2026',
                'jadwal_nov_2026',
                'jadwal_dec_2026',
                'jadwal_jan_2027',
                'jadwal_feb_2027',
                'jadwal_mar_2027',
                'jadwal_apr_2027',
                'jadwal_may_2027',
                'jadwal_jun_2027',
                'jadwal_jul_2027',
                'waktu_pelaksanaan',
                'rab_global',
                'keterangan',
                'deskripsi',
                'output_target',
                'status',
                'prioritas',
            ],
            [
                2026,
                '2026-2027',
                'KURIKULUM',
                1,
                'PEMBUATAN KSP DAN ADM GURU',
                'Tim Kurikulum',
                '20',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                'Rapat kerja awal tahun ajaran',
                'Rp 2.500.000',
                'Dokumen menyesuaikan kebutuhan tim kurikulum',
                'Penyusunan dokumen awal tahun ajaran',
                'Dokumen kurikulum dan administrasi guru selesai',
                'draft',
                'tinggi',
            ],
            [
                2026,
                '2026-2027',
                'SARPRAS',
                85,
                'PENGADAAN RAB DAN LPJ SETIAP BULAN',
                'Tim Sarpras',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                'Setiap awal bulan',
                null,
                null,
                null,
                null,
                'draft',
                'sedang',
            ],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function getGuideRows(): array
    {
        return [
            ['PETUNJUK IMPORT PROKER'],
            ['1', 'Kolom wajib minimal: periode_tahun, point_dari, nama_proker.'],
            ['2', 'Kolom jadwal bulanan boleh dikosongkan. Jika kosong, jadwal di Proker juga kosong.'],
            ['3', 'Kolom rab_global dan keterangan bersifat opsional. Jika kosong, nilainya akan disimpan kosong.'],
            ['4', 'Nilai status yang didukung: draft, berjalan, terkendala, selesai.'],
            ['5', 'Nilai prioritas yang didukung: rendah, sedang, tinggi.'],
            ['6', 'Template ini format datar. File matrix sekolah lama juga tetap didukung saat import.'],
            ['7', 'Setelah import, data masih bisa diedit manual di menu /admin/prokers.'],
        ];
    }
}
