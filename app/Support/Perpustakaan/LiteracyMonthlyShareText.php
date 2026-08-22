<?php

namespace App\Support\Perpustakaan;

use App\Models\PerpustakaanLiterasiMaterial;
use Carbon\CarbonInterface;
use InvalidArgumentException;

final class LiteracyMonthlyShareText
{
    public const SCOPE_ALL = 'all';

    /**
     * @return array<string, array{label:string,button:string,category:?string}>
     */
    public static function scopeOptions(): array
    {
        return [
            self::SCOPE_ALL => [
                'label' => 'Keseluruhan',
                'button' => 'Rekap Bulanan Total',
                'category' => null,
            ],
            PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER => [
                'label' => 'SIGAP 29 Karakter',
                'button' => 'Rekap SIGAP 29 Karakter',
                'category' => PerpustakaanLiterasiMaterial::CATEGORY_SIGAP_29_KARAKTER,
            ],
            PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION => [
                'label' => 'Literasi',
                'button' => 'Rekap Literasi',
                'category' => PerpustakaanLiterasiMaterial::CATEGORY_LITERACY_HABITUATION,
            ],
            PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE => [
                'label' => 'Numerasi',
                'button' => 'Rekap Numerasi',
                'category' => PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE,
            ],
        ];
    }

    public static function validScope(string $scope): bool
    {
        return array_key_exists($scope, static::scopeOptions());
    }

    public static function title(string $scope): string
    {
        return 'Rekap Bulanan '.static::scope($scope)['label'];
    }

    public static function make(string $scope, ?CarbonInterface $generatedAt = null): string
    {
        $scopeData = static::scope($scope);
        $analytics = LiterasiAnalytics::monthlyShare($scopeData['category']);
        $summary = $analytics['grading_summary'] ?? [];
        $generatedAt = ($generatedAt ?? now())->copy()->setTimezone('Asia/Jakarta');
        $lines = [
            '*REKAP BULANAN LITERASI NUMERASI*',
            '*Lingkup:* '.$scopeData['label'],
            '*Periode:* '.($analytics['period_label'] ?? '-'),
            '*Dibuat:* '.$generatedAt->format('d/m/Y H:i').' WIB',
            '',
            '*RINGKASAN*',
            '- Total responden: '.static::number($summary['responses'] ?? 0).' partisipasi dari '.static::number($summary['unique_students'] ?? 0).' siswa unik',
            '- Rincian: '.static::number($summary['response_records'] ?? 0).' jawaban + '.static::number($summary['dispensations'] ?? 0).' dispensasi',
            '- Sudah dinilai lengkap: '.static::number($summary['fully_graded_responses'] ?? 0).' respons',
            '- Belum dinilai/masih sebagian: '.static::number($summary['pending_grading_responses'] ?? 0).' respons',
            '- Plagiasi terkonfirmasi: '.static::number($summary['confirmed_plagiarism_students'] ?? 0).' siswa',
            '- Indikasi belum ditinjau: '.static::number($summary['pending_similarity_students'] ?? 0).' siswa',
        ];

        static::appendClassParticipation($lines, $analytics['class_participation'] ?? []);
        static::appendClassRanking($lines, 'RANKING KELAS TERSEDIKIT MENGISI', $analytics['least_class_response_ranking'] ?? []);
        static::appendClassRanking($lines, 'RANKING KELAS TERBANYAK MENGISI', $analytics['class_response_ranking'] ?? []);
        static::appendCorrectClassRanking($lines, $analytics['class_correct_ranking'] ?? []);
        static::appendStudentCorrectRanking($lines, $analytics['student_correct_ranking_by_class'] ?? []);
        static::appendWrongRanking($lines, $analytics['student_wrong_ranking'] ?? []);
        static::appendMissingStudents($lines, $analytics['missing_students'] ?? []);
        static::appendSimilarityClasses($lines, $analytics['plagiarism_class_ranking'] ?? []);
        static::appendSimilarityStudents($lines, $analytics['plagiarism_student_ranking'] ?? []);

        return trim(implode("\n", $lines));
    }

    private static function appendClassParticipation(array &$lines, array $rows): void
    {
        $rows = collect($rows)->sortBy('class', SORT_NATURAL | SORT_FLAG_CASE)->values();
        static::heading($lines, 'RESPONDEN PER KELAS');

        if ($rows->isEmpty()) {
            $lines[] = 'Belum ada responden pada periode ini.';

            return;
        }

        foreach ($rows as $index => $row) {
            $lines[] = ($index + 1).'. '.static::plain($row['class'] ?? '-')
                .' — '.static::number($row['total'] ?? 0).' partisipasi ('
                .static::number($row['response_total'] ?? 0).' jawaban + '
                .static::number($row['dispensation_total'] ?? 0).' dispensasi)';
        }
    }

    private static function appendClassRanking(array &$lines, string $title, array $rows): void
    {
        static::heading($lines, $title);

        if ($rows === []) {
            $lines[] = 'Belum ada data kelas pada periode ini.';

            return;
        }

        foreach ($rows as $index => $row) {
            $percentage = $row['percentage'] ?? null;
            $lines[] = ($index + 1).'. '.static::plain($row['class'] ?? '-')
                .' — '.static::number($row['total'] ?? 0).' partisipasi'
                .' dari '.static::number($row['active_total'] ?? 0).' siswa aktif'
                .($percentage === null ? '' : ' ('.static::percent($percentage).')');
        }
    }

    private static function appendCorrectClassRanking(array &$lines, array $rows): void
    {
        static::heading($lines, 'TOP 3 KELAS JAWABAN BENAR');

        if ($rows === []) {
            $lines[] = 'Belum ada kelas dengan jawaban yang sudah dinilai.';

            return;
        }

        foreach ($rows as $index => $row) {
            $lines[] = ($index + 1).'. '.static::plain($row['class'] ?? '-')
                .' — '.static::number($row['correct_answers'] ?? 0).' benar dari '
                .static::number($row['graded_answers'] ?? 0).' dinilai'
                .' ('.static::percent($row['accuracy'] ?? 0).')';
        }
    }

    private static function appendStudentCorrectRanking(array &$lines, array $classes): void
    {
        static::heading($lines, 'RANKING SISWA PER KELAS BERDASARKAN JAWABAN BENAR');

        if ($classes === []) {
            $lines[] = 'Belum ada siswa dengan jawaban yang sudah dinilai.';

            return;
        }

        foreach (collect($classes)->sortKeys(SORT_NATURAL) as $class => $rows) {
            $lines[] = '*Kelas '.static::plain($class).'*';

            foreach ($rows as $index => $row) {
                $lines[] = ($index + 1).'. '.static::plain($row['name'] ?? '-')
                    .' — '.static::number($row['correct_answers'] ?? 0).' benar dari '
                    .static::number($row['graded_answers'] ?? 0).' dinilai'
                    .' ('.static::percent($row['accuracy'] ?? 0).')';
            }
        }
    }

    private static function appendWrongRanking(array &$lines, array $rows): void
    {
        static::heading($lines, 'RANKING SISWA BANYAK SALAH');

        if ($rows === []) {
            $lines[] = 'Belum ada siswa dengan jawaban salah yang sudah dinilai.';

            return;
        }

        foreach ($rows as $index => $row) {
            $lines[] = ($index + 1).'. '.static::plain($row['name'] ?? '-')
                .' — '.static::plain($row['class'] ?? '-').' — '
                .static::number($row['wrong_answers'] ?? 0).' salah';
        }
    }

    private static function appendMissingStudents(array &$lines, array $rows): void
    {
        static::heading($lines, 'SISWA TIDAK MENGISI');
        $classes = collect($rows)->groupBy(fn (array $row): string => static::plain($row['class'] ?? '-'))->sortKeys(SORT_NATURAL);

        if ($classes->isEmpty()) {
            $lines[] = 'Semua siswa aktif sudah mempunyai respons atau dispensasi pada periode ini.';

            return;
        }

        foreach ($classes as $class => $students) {
            $lines[] = '*Kelas '.$class.'*';

            foreach ($students as $index => $student) {
                $lines[] = ($index + 1).'. '.static::plain($student['name'] ?? '-');
            }
        }
    }

    private static function appendSimilarityClasses(array &$lines, array $rows): void
    {
        static::heading($lines, 'KELAS DENGAN INDIKASI KEMIRIPAN');

        if ($rows === []) {
            $lines[] = 'Belum ada indikasi kemiripan aktif pada periode ini.';

            return;
        }

        foreach ($rows as $index => $row) {
            $lines[] = ($index + 1).'. '.static::plain($row['class'] ?? '-')
                .' — '.static::number($row['total'] ?? 0).' indikasi';
        }
    }

    private static function appendSimilarityStudents(array &$lines, array $rows): void
    {
        static::heading($lines, 'SISWA DENGAN INDIKASI KEMIRIPAN');

        if ($rows === []) {
            $lines[] = 'Belum ada siswa dengan indikasi kemiripan aktif pada periode ini.';

            return;
        }

        foreach ($rows as $index => $row) {
            $lines[] = ($index + 1).'. '.static::plain($row['name'] ?? '-')
                .' — '.static::plain($row['class'] ?? '-').' — '
                .static::number($row['total'] ?? 0).' indikasi';
        }
    }

    private static function heading(array &$lines, string $title): void
    {
        $lines[] = '';
        $lines[] = '*'.$title.'*';
    }

    /**
     * @return array{label:string,button:string,category:?string}
     */
    private static function scope(string $scope): array
    {
        $scopeData = static::scopeOptions()[$scope] ?? null;

        if ($scopeData === null) {
            throw new InvalidArgumentException('Lingkup rekap bulanan tidak valid.');
        }

        return $scopeData;
    }

    private static function number(mixed $value): string
    {
        return number_format((int) $value, 0, ',', '.');
    }

    private static function percent(mixed $value): string
    {
        return number_format((float) $value, 1, ',', '.').'%';
    }

    private static function plain(mixed $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim(strip_tags((string) $value)));
        $normalized = str_replace(['*', '_', '~', '`'], '', (string) $normalized);

        return $normalized !== '' ? $normalized : '-';
    }
}
