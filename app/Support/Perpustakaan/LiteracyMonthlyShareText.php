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
        return array_key_exists($scope, self::scopeOptions());
    }

    public static function title(string $scope): string
    {
        return 'Rekap Bulanan '.self::scope($scope)['label'];
    }

    public static function make(string $scope, ?CarbonInterface $generatedAt = null): string
    {
        $scopeData = self::scope($scope);
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
            '- Total responden: '.self::number($summary['responses'] ?? 0).' jawaban dari '.self::number($summary['unique_students'] ?? 0).' siswa unik',
            '- Basis responden: '.self::number($summary['respondent_base'] ?? 0).' siswa (setelah '.self::number($summary['excluded_total'] ?? 0).' dispensasi dikeluarkan)',
            '- Partisipasi: '.(($summary['participation_percentage'] ?? null) !== null ? $summary['participation_percentage'].'%' : '-').' ('.($summary['participation_ratio'] ?? '-').')',
            '- Sudah dinilai lengkap: '.self::number($summary['fully_graded_responses'] ?? 0).' respons',
            '- Belum dinilai/masih sebagian: '.self::number($summary['pending_grading_responses'] ?? 0).' respons',
            '- Plagiasi terkonfirmasi: '.self::number($summary['confirmed_plagiarism_students'] ?? 0).' siswa',
            '- Indikasi belum ditinjau: '.self::number($summary['pending_similarity_students'] ?? 0).' siswa',
        ];

        self::appendClassParticipation($lines, $analytics['class_participation'] ?? []);
        self::appendClassRanking($lines, 'RANKING KELAS TERSEDIKIT MENGISI', $analytics['least_class_response_ranking'] ?? []);
        self::appendClassRanking($lines, 'RANKING KELAS TERBANYAK MENGISI', $analytics['class_response_ranking'] ?? []);
        self::appendCorrectClassRanking($lines, $analytics['class_correct_ranking'] ?? []);
        self::appendStudentCorrectRanking($lines, $analytics['student_correct_ranking_by_class'] ?? []);
        self::appendWrongRanking($lines, $analytics['student_wrong_ranking'] ?? []);
        self::appendMissingStudents($lines, $analytics['missing_students'] ?? []);
        self::appendSimilarityClasses($lines, $analytics['plagiarism_class_ranking'] ?? []);
        self::appendSimilarityStudents($lines, $analytics['plagiarism_student_ranking'] ?? []);

        return trim(implode("\n", $lines));
    }

    private static function appendClassParticipation(array &$lines, array $rows): void
    {
        $rows = collect($rows)->sortBy('class', SORT_NATURAL | SORT_FLAG_CASE)->values();
        self::heading($lines, 'RESPONDEN PER KELAS');

        if ($rows->isEmpty()) {
            $lines[] = 'Belum ada responden pada periode ini.';

            return;
        }

        foreach ($rows as $index => $row) {
            $percentage = $row['percentage'] ?? null;
            $lines[] = ($index + 1).'. '.self::plain($row['class'] ?? '-')
                .' — '.self::number($row['total'] ?? 0).'/'.self::number($row['respondent_base'] ?? 0)
                .($percentage !== null ? ' ('.$percentage.'%)' : '')
                .(($row['excluded_total'] ?? 0) > 0 ? ', '.self::number($row['excluded_total']).' dispensasi tidak dihitung' : '');
        }
    }

    private static function appendClassRanking(array &$lines, string $title, array $rows): void
    {
        self::heading($lines, $title);

        if ($rows === []) {
            $lines[] = 'Belum ada data kelas pada periode ini.';

            return;
        }

        foreach ($rows as $index => $row) {
            $percentage = $row['percentage'] ?? null;
            $lines[] = ($index + 1).'. '.self::plain($row['class'] ?? '-')
                .' — '.self::number($row['total'] ?? 0).' partisipasi'
                .' dari '.self::number($row['active_total'] ?? 0).' siswa aktif'
                .($percentage === null ? '' : ' ('.self::percent($percentage).')');
        }
    }

    private static function appendCorrectClassRanking(array &$lines, array $rows): void
    {
        self::heading($lines, 'TOP 3 KELAS JAWABAN BENAR');

        if ($rows === []) {
            $lines[] = 'Belum ada kelas dengan jawaban yang sudah dinilai.';

            return;
        }

        foreach ($rows as $index => $row) {
            $lines[] = ($index + 1).'. '.self::plain($row['class'] ?? '-')
                .' — '.self::number($row['correct_answers'] ?? 0).' benar dari '
                .self::number($row['graded_answers'] ?? 0).' dinilai'
                .' ('.self::percent($row['accuracy'] ?? 0).')';
        }
    }

    private static function appendStudentCorrectRanking(array &$lines, array $classes): void
    {
        self::heading($lines, 'RANKING SISWA PER KELAS BERDASARKAN JAWABAN BENAR');

        if ($classes === []) {
            $lines[] = 'Belum ada siswa dengan jawaban yang sudah dinilai.';

            return;
        }

        foreach (collect($classes)->sortKeys(SORT_NATURAL) as $class => $rows) {
            $lines[] = '*Kelas '.self::plain($class).'*';

            foreach ($rows as $index => $row) {
                $lines[] = ($index + 1).'. '.self::plain($row['name'] ?? '-')
                    .' — '.self::number($row['correct_answers'] ?? 0).' benar dari '
                    .self::number($row['graded_answers'] ?? 0).' dinilai'
                    .' ('.self::percent($row['accuracy'] ?? 0).')';
            }
        }
    }

    private static function appendWrongRanking(array &$lines, array $rows): void
    {
        self::heading($lines, 'RANKING SISWA BANYAK SALAH');

        if ($rows === []) {
            $lines[] = 'Belum ada siswa dengan jawaban salah yang sudah dinilai.';

            return;
        }

        foreach ($rows as $index => $row) {
            $lines[] = ($index + 1).'. '.self::plain($row['name'] ?? '-')
                .' — '.self::plain($row['class'] ?? '-').' — '
                .self::number($row['wrong_answers'] ?? 0).' salah';
        }
    }

    private static function appendMissingStudents(array &$lines, array $rows): void
    {
        self::heading($lines, 'SISWA TIDAK MENGISI');
        $classes = collect($rows)->groupBy(fn (array $row): string => self::plain($row['class'] ?? '-'))->sortKeys(SORT_NATURAL);

        if ($classes->isEmpty()) {
            $lines[] = 'Semua siswa aktif sudah mempunyai respons atau dispensasi pada periode ini.';

            return;
        }

        foreach ($classes as $class => $students) {
            $lines[] = '*Kelas '.$class.'*';

            foreach ($students as $index => $student) {
                $lines[] = ($index + 1).'. '.self::plain($student['name'] ?? '-');
            }
        }
    }

    private static function appendSimilarityClasses(array &$lines, array $rows): void
    {
        self::heading($lines, 'KELAS DENGAN INDIKASI KEMIRIPAN');

        if ($rows === []) {
            $lines[] = 'Belum ada indikasi kemiripan aktif pada periode ini.';

            return;
        }

        foreach ($rows as $index => $row) {
            $lines[] = ($index + 1).'. '.self::plain($row['class'] ?? '-')
                .' — '.self::number($row['total'] ?? 0).' indikasi';
        }
    }

    private static function appendSimilarityStudents(array &$lines, array $rows): void
    {
        self::heading($lines, 'SISWA DENGAN INDIKASI KEMIRIPAN');

        if ($rows === []) {
            $lines[] = 'Belum ada siswa dengan indikasi kemiripan aktif pada periode ini.';

            return;
        }

        foreach ($rows as $index => $row) {
            $lines[] = ($index + 1).'. '.self::plain($row['name'] ?? '-')
                .' — '.self::plain($row['class'] ?? '-').' — '
                .self::number($row['total'] ?? 0).' indikasi';
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
        $scopeData = self::scopeOptions()[$scope] ?? null;

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
