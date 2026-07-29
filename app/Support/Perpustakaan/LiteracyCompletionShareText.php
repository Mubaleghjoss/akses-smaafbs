<?php

namespace App\Support\Perpustakaan;

use App\Models\PerpustakaanLiterasiMaterial;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class LiteracyCompletionShareText
{
    public static function make(
        PerpustakaanLiterasiMaterial $material,
        array $completion,
        ?CarbonInterface $generatedAt = null,
    ): string {
        $generatedAt ??= now();
        $classes = collect($completion['classes'] ?? [])
            ->map(fn (array $class): array => static::classRow($class))
            ->filter(fn (array $class): bool => $class['students']->isNotEmpty())
            ->sortBy('class', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $reasonCounts = $classes
            ->flatMap(fn (array $class): Collection => $class['students'])
            ->whereNotNull('reason')
            ->countBy('reason');
        $missingTotal = (int) $classes
            ->sum(fn (array $class): int => $class['students']->whereNull('reason')->count());
        $dispensationTotal = (int) $classes
            ->sum(fn (array $class): int => $class['students']->whereNotNull('reason')->count());

        $lines = [
            '*DAFTAR SISWA BELUM MENGISI*',
            '*Materi:* '.static::plainText($material->title),
            '*Diperbarui:* '.$generatedAt->format('d/m/Y H:i').' WIB',
            '*Ringkasan:* '.$missingTotal.' belum mengisi | '.$dispensationTotal.' dispensasi',
        ];

        if ($dispensationTotal > 0) {
            $lines[] = '*Kode:* [SAKIT] '.((int) ($reasonCounts['sick'] ?? 0))
                .' siswa | [TES MT] '.((int) ($reasonCounts['mt_test'] ?? 0)).' siswa';
        }

        $lines[] = '';

        if ($classes->isEmpty()) {
            $lines[] = 'Semua siswa sudah mengisi atau telah memiliki status yang sesuai.';
        } else {
            foreach ($classes as $class) {
                $lines[] = '*Kelas '.$class['class'].'*';

                foreach ($class['students'] as $index => $student) {
                    $code = static::reasonCode($student['reason']);
                    $lines[] = ($index + 1).'. '.$student['name'].($code !== null ? ' '.$code : '');
                }

                $lines[] = '';
            }

            $lines[] = 'Mohon siswa tanpa kode dispensasi segera mengisi materi. Terima kasih.';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @return array{class:string,students:Collection<int, array{name:string,reason:?string}>}
     */
    private static function classRow(array $class): array
    {
        $missing = collect($class['missing_students'] ?? [])
            ->map(fn (array $student): array => [
                'name' => static::plainText($student['name'] ?? '-'),
                'reason' => null,
            ]);
        $dispensated = collect($class['dispensated_students'] ?? [])
            ->map(fn (array $student): array => [
                'name' => static::plainText($student['name'] ?? '-'),
                'reason' => (string) ($student['reason'] ?? ''),
            ]);

        return [
            'class' => static::plainText($class['class'] ?? '-'),
            'students' => $missing
                ->concat($dispensated)
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
        ];
    }

    private static function reasonCode(?string $reason): ?string
    {
        return match ($reason) {
            'sick' => '[SAKIT]',
            'mt_test' => '[TES MT]',
            null, '' => null,
            default => '[DISPENSASI]',
        };
    }

    private static function plainText(mixed $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim((string) $value));

        return $normalized !== null && $normalized !== '' ? $normalized : '-';
    }
}
