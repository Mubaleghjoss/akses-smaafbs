<?php

namespace App\Exports;

use App\Exports\Sheets\AssessmentArraySheetExport;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\Assessment\SubjectCategory;
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
                ['KODE_MAPEL', 'NAMA_MAPEL', 'DESKRIPSI', 'KELOMPOK_KODE', 'KELOMPOK_NAMA', 'URUTAN_KELOMPOK', 'URUTAN_MAPEL', 'AKTIF'],
                ['BIN', 'Bahasa Indonesia', '', 'A', 'Kelompok A (Umum)', 10, 10, 'YA'],
            ], 'MAPEL'),
            new AssessmentArraySheetExport([
                ['SEMESTER_KODE', 'MAPEL_KODE', 'NAMA_GURU', 'ID_GURU_SISTEM', 'NAMA_ROMBEL', 'ID_ROMBEL_SISTEM', 'AKTIF', 'KATEGORI_KODE'],
                ['2026-2027-GANJIL', 'BIN', '', '', '', '', 'YA', 'WAJIB'],
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
            new AssessmentArraySheetExport([
                ['KATEGORI_KODE', 'NAMA_DI_RAPOR', 'JENIS', 'URUTAN'],
                ...SubjectCategory::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (SubjectCategory $category): array => [
                        $category->code,
                        $category->name,
                        $category->type,
                        $category->sort_order,
                    ])->all(),
            ], 'REF_KATEGORI'),
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
            ['6', 'Pada PENUGASAN_GURU, isi KATEGORI_KODE sesuai REF_KATEGORI karena kategori rapor dapat berbeda pada setiap kelas.'],
            ['7', 'Nilai AKTIF menerima YA/TIDAK. Tanggal memakai format YYYY-MM-DD.'],
            ['8', 'Workbook lama tanpa KATEGORI_KODE tetap diterima dengan fallback kelompok mapel lama. Simpan sebagai .xlsx.'],
        ];
    }
}
