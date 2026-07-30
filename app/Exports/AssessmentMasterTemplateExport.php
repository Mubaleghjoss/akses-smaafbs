<?php

namespace App\Exports;

use App\Exports\Sheets\AssessmentArraySheetExport;
use App\Models\GuruTendik;
use App\Models\Rombel;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AssessmentMasterTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        $teachers = GuruTendik::query()
            ->with('userAccount:id,guru_tendik_id')
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get();

        $rombels = Rombel::query()
            ->where('is_active', true)
            ->orderBy('nama')
            ->get();

        return [
            new AssessmentArraySheetExport($this->guideRows(), 'PETUNJUK', freezeHeader: false),
            new AssessmentArraySheetExport([
                ['TAHUN_KODE', 'TAHUN_NAMA', 'TAHUN_MULAI', 'TAHUN_SELESAI', 'SEMESTER_KODE', 'SEMESTER_NAMA', 'SEMESTER_MULAI', 'SEMESTER_SELESAI', 'AKTIF'],
                ['2026-2027', 'Tahun Pelajaran 2026/2027', '2026-07-01', '2027-06-30', '2026-2027-GANJIL', 'Semester Ganjil', '2026-07-01', '2026-12-31', 'YA'],
            ], 'TAHUN_SEMESTER'),
            new AssessmentArraySheetExport([
                ['KODE_MAPEL', 'NAMA_MAPEL', 'DESKRIPSI', 'URUTAN', 'AKTIF'],
                ['BIN', 'Bahasa Indonesia', '', 10, 'YA'],
            ], 'MAPEL'),
            new AssessmentArraySheetExport([
                ['SEMESTER_KODE', 'MAPEL_KODE', 'NAMA_GURU', 'ID_GURU_SISTEM', 'NAMA_ROMBEL', 'ID_ROMBEL_SISTEM', 'AKTIF'],
                ['2026-2027-GANJIL', 'BIN', '', '', '', '', 'YA'],
            ], 'PENUGASAN_GURU', ['D', 'F']),
            new AssessmentArraySheetExport([
                ['SEMESTER_KODE', 'NAMA_GURU', 'ID_GURU_SISTEM', 'NAMA_ROMBEL', 'ID_ROMBEL_SISTEM', 'AKTIF'],
                ['2026-2027-GANJIL', '', '', '', '', 'YA'],
            ], 'WALI_KELAS', ['C', 'E']),
            new AssessmentArraySheetExport([
                ['NAMA_GURU', 'ID_GURU_SISTEM', 'NIY/NIP', 'AKUN_TERTAUT'],
                ...$teachers->map(fn (GuruTendik $teacher): array => [
                    (string) $teacher->nama,
                    (int) $teacher->getKey(),
                    (string) ($teacher->niy ?? $teacher->nip ?? ''),
                    $teacher->userAccount ? 'YA' : 'TIDAK',
                ])->all(),
            ], 'REF_GURU', ['B']),
            new AssessmentArraySheetExport([
                ['NAMA_ROMBEL', 'ID_ROMBEL_SISTEM', 'ANGKATAN'],
                ...$rombels->map(fn (Rombel $rombel): array => [
                    (string) $rombel->nama,
                    (int) $rombel->getKey(),
                    (string) ($rombel->angkatan ?? ''),
                ])->all(),
            ], 'REF_ROMBEL', ['B']),
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function guideRows(): array
    {
        return [
            ['PANDUAN IMPOR MASTER PENILAIAN ASTS–ASAS'],
            ['1', 'Isi atau perbarui empat sheet: TAHUN_SEMESTER, MAPEL, PENUGASAN_GURU, dan WALI_KELAS.'],
            ['2', 'Gunakan nama guru dan rombel persis seperti sheet referensi. Kolom ID sistem disembunyikan agar tidak terubah tanpa sengaja.'],
            ['3', 'Satu guru tanpa akun boleh dipreview, tetapi harus dibuatkan akun sebelum periode dibuka.'],
            ['4', 'Impor selalu masuk tahap pratinjau. Database baru berubah setelah tombol Terapkan Impor ditekan.'],
            ['5', 'Baris yang tidak ada di workbook tidak dihapus dan tidak otomatis dinonaktifkan.'],
            ['6', 'Nilai AKTIF menerima YA/TIDAK. Tanggal memakai format YYYY-MM-DD.'],
            ['7', 'Jangan mengganti nama sheet atau judul kolom. Simpan sebagai .xlsx.'],
        ];
    }
}
