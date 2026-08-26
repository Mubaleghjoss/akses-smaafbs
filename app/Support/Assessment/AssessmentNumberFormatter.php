<?php

namespace App\Support\Assessment;

final class AssessmentNumberFormatter
{
    /**
     * Penanda nilai yang belum diisi guru.
     *
     * Dipakai di DOKUMEN RAPOR agar pembaca tahu nilainya belum ada, bukan
     * sekadar melihat tanda hubung yang bisa disalahartikan sebagai nol atau
     * mapel yang tidak diambil.
     */
    public const BELUM_DIISI = '(belum diisi)';

    public static function score(mixed $value, int $maximumDecimals = 2, string $empty = '-'): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }

        if (! is_numeric($value)) {
            return trim((string) $value) !== '' ? (string) $value : $empty;
        }

        $decimals = min(2, max(0, $maximumDecimals));
        $formatted = number_format((float) $value, $decimals, '.', '');

        if ($decimals === 0) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Nilai untuk dokumen rapor: kosong ditulis '(belum diisi)'.
     *
     * PENTING: nilai 0 adalah nilai SAH dan tetap tercetak '0'. Membedakan
     * keduanya mencegah siswa yang benar-benar mendapat nol terbaca sebagai
     * belum dinilai, dan sebaliknya.
     */
    public static function scoreRapor(mixed $value, int $maximumDecimals = 2): string
    {
        return static::score($value, $maximumDecimals, self::BELUM_DIISI);
    }
}
