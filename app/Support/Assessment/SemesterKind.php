<?php

namespace App\Support\Assessment;

use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\Semester;

/**
 * Mengenali semester GANJIL / GENAP dan mencocokkannya dengan jenis penilaian.
 *
 * Kebijakan sekolah: ASAT (Asesmen Sumatif Akhir Tahun) hanya boleh dibuka pada
 * semester GENAP. ASTS & ASAS berjalan di kedua semester.
 *
 * Basis data TIDAK punya kolom khusus penanda ganjil/genap; yang tersedia
 * `code` (mis. '2026-2027-GANJIL') dan `name` (mis. 'Semester Ganjil').
 * Pengenalan karena itu dilakukan dari teks, dengan cadangan bulan mulai
 * (Juli–Desember = ganjil, Januari–Juni = genap) bila teksnya tidak jelas.
 */
class SemesterKind
{
    public const GANJIL = 'ganjil';

    public const GENAP = 'genap';

    /**
     * @return string|null 'ganjil', 'genap', atau null bila tidak dapat dikenali
     */
    public function dari(?Semester $semester): ?string
    {
        if (! $semester) {
            return null;
        }

        $teks = mb_strtolower(trim(($semester->code ?? '').' '.($semester->name ?? '')));

        if (str_contains($teks, 'genap') || str_contains($teks, 'even')) {
            return self::GENAP;
        }

        if (str_contains($teks, 'ganjil') || str_contains($teks, 'odd')) {
            return self::GANJIL;
        }

        // Cadangan: tebak dari bulan mulai. Tahun ajaran Indonesia dimulai Juli,
        // jadi Juli–Desember semester ganjil, Januari–Juni semester genap.
        $bulan = $semester->starts_on?->month;

        if ($bulan === null) {
            return null;
        }

        return $bulan >= 7 ? self::GANJIL : self::GENAP;
    }

    /**
     * Bolehkah jenis penilaian ini dibuka pada semester tersebut?
     *
     * Semester yang TIDAK dapat dikenali diizinkan (tidak menghalangi pekerjaan
     * hanya karena penamaan semester tidak baku) — pembatasan hanya diterapkan
     * bila jenis semesternya benar-benar diketahui.
     */
    public function cocok(AssessmentType $jenis, ?Semester $semester): bool
    {
        $kind = $this->dari($semester);

        if ($kind === null) {
            return true;
        }

        return in_array($kind, $jenis->semesterYangDiizinkan(), true);
    }

    /**
     * Alasan penolakan yang bisa dibaca pengguna, atau null bila tidak ditolak.
     */
    public function alasanTidakCocok(AssessmentType $jenis, ?Semester $semester): ?string
    {
        if ($this->cocok($jenis, $semester)) {
            return null;
        }

        $diizinkan = collect($jenis->semesterYangDiizinkan())
            ->map(fn (string $k): string => 'Semester '.ucfirst($k))
            ->implode(' atau ');

        return sprintf(
            '%s (%s) hanya dapat dibuka pada %s. Semester terpilih: %s.',
            $jenis->label(),
            $jenis->namaPanjang(),
            $diizinkan,
            $semester?->name ?? '-',
        );
    }

    /**
     * Jenis penilaian yang boleh dipakai pada satu semester.
     *
     * @return array<string, string>  nilai enum => label
     */
    public function jenisUntukSemester(?Semester $semester): array
    {
        return collect(AssessmentType::cases())
            ->filter(fn (AssessmentType $jenis): bool => $this->cocok($jenis, $semester))
            ->mapWithKeys(fn (AssessmentType $jenis): array => [
                $jenis->value => $jenis->label().' — '.$jenis->namaPanjang(),
            ])
            ->all();
    }
}
