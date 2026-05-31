<?php

namespace App\Support\Boarding;

class BoardingRapotSheetRows
{
    /**
     * @return array<int, array<int, string>>
     */
    public static function materiBoardingRows(array $payload): array
    {
        return [
            ['', 'Materi', 'Keterangan'],
            ["Al-Qur'an", 'Bacaan', static::bacaanQuranLabel($payload)],
            ['', 'Makna', static::maknaQuranLabel($payload)],
            ['Al-Hadist', 'Makna', static::maknaHaditsLabel($payload)],
            ['Pengetesan Makna', '', static::materiBoardingManualGrade($payload, 'Pengetesan Makna')],
            ['Hafalan', 'Pegon Bacaan', static::hafalanCompletionLabel($payload, 'pegon_bacaan')],
            ['', 'Lambatan', static::hafalanCompletionLabel($payload, 'lambatan')],
            ['', 'Cepatan', static::hafalanCompletionLabel($payload, 'cepatan')],
            ['', 'Materi Tambahan', static::hafalanCompletionLabel($payload, 'materi_tambahan_hafalan')],
            ['Kedisiplinan', '', static::materiBoardingManualGrade($payload, 'Kedisiplinan')],
            ['Ketertiban', '', static::materiBoardingManualGrade($payload, 'Ketertiban')],
            ['Akhlak', '', static::materiBoardingManualGrade($payload, 'Akhlak')],
            ['Kesemangatan', '', static::materiBoardingManualGrade($payload, 'Kesemangatan')],
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function mtRows(array $payload): array
    {
        return [
            ['Materi', 'Keterangan'],
            ['Hadist Muslim Jilid 1', static::mtProgressLabel($payload, 'Muslim Jilid 1', 'khatam')],
            ['Hadist Muslim Jilid 2', static::mtProgressLabel($payload, 'Muslim Jilid 2', 'khatam')],
            ['Hadist Muslim Jilid 3', static::mtProgressLabel($payload, 'Muslim Jilid 3', 'khatam')],
            ['Hadist Muslim Jilid 4', static::mtProgressLabel($payload, 'Muslim Jilid 4', 'khatam')],
            ['Materi Tambahan', static::mtSupplementLabel($payload)],
            ['Hafalan - Juz 1', static::mtProgressLabel($payload, 'Hafalan Surat Quran Juz 1', 'hafal')],
            ['Hafalan - Dalil 29 Karakter', static::mtProgressLabel($payload, 'Hafalan Dalil 29 Karakter Luhur', 'hafal')],
            ['Kedisiplinan', static::mtGradeLabel($payload, 'Kedisiplinan')],
            ['Ketertiban', static::mtGradeLabel($payload, 'Ketertiban')],
            ['Akhlak', static::mtGradeLabel($payload, 'Akhlak')],
            ['Kesemangatan', static::mtGradeLabel($payload, 'Kesemangatan')],
        ];
    }

    protected static function bacaanQuranLabel(array $payload): string
    {
        return static::materiBoarding($payload)['bacaan_quran']['class_label'] ?? 'Kelas A / B / C';
    }

    protected static function maknaQuranLabel(array $payload): string
    {
        $summary = static::materiBoarding($payload)['makna_quran'] ?? [];
        $khatam = (int) ($summary['khatam'] ?? 0);
        $total = (int) ($summary['total'] ?? 30);

        return "Sudah khatam {$khatam} juz dari {$total} juz";
    }

    protected static function maknaHaditsLabel(array $payload): string
    {
        $summary = static::materiBoarding($payload)['makna_hadits'] ?? [];
        $khatam = (int) ($summary['khatam'] ?? 0);
        $total = (int) ($summary['total'] ?? 0);

        return "Sudah khatam {$khatam} hadits dari {$total} hadits";
    }

    protected static function materiBoardingManualGrade(array $payload, string $targetName): string
    {
        $row = static::findRowsByTargetName(static::materiBoarding($payload)['manual_groups'] ?? [], $targetName);

        return static::appendNotes($row['grade'] ?? 'Baik / Cukup / Kurang', $row);
    }

    protected static function hafalanCompletionLabel(array $payload, string $materiKey): string
    {
        $rows = static::materiBoarding($payload)['hafalan'] ?? [];

        foreach ($rows as $row) {
            if (($row['materi_key'] ?? null) !== $materiKey) {
                continue;
            }

            if (filled($row['grade'] ?? null)) {
                return 'Tuntas';
            }

            $assessed = (int) ($row['assessed'] ?? 0);
            $total = (int) ($row['total'] ?? 0);

            return "Belum Tuntas - sudah hafal {$assessed} materi dari {$total} materi";
        }

        return 'Belum Tuntas - sudah hafal 0 materi dari 0 materi';
    }

    protected static function mtProgressLabel(array $payload, string $targetName, string $verb): string
    {
        $row = static::findRowsByTargetName(static::mt($payload)['groups'] ?? [], $targetName);
        $unit = $row['unit_label'] ?? ($verb === 'hafal' ? 'item' : 'lembar');
        $progress = filled($row['progress_value'] ?? null) ? (int) $row['progress_value'] : '-';
        $target = filled($row['target_total'] ?? null) ? (int) $row['target_total'] : '-';

        return static::appendNotes("Sudah {$verb} {$progress} {$unit} dari {$target} {$unit}", $row);
    }

    protected static function mtSupplementLabel(array $payload): string
    {
        $row = static::findRowsByTargetName(static::mt($payload)['groups'] ?? [], 'Tugas Praktek');

        return static::appendNotes($row['grade'] ?? 'Baik / Cukup', $row);
    }

    protected static function mtGradeLabel(array $payload, string $targetName): string
    {
        $row = static::findRowsByTargetName(static::mt($payload)['groups'] ?? [], $targetName);

        return static::appendNotes($row['grade'] ?? 'Baik / Cukup / Kurang', $row);
    }

    protected static function appendNotes(string $label, ?array $row): string
    {
        $notes = trim((string) ($row['notes'] ?? ''));

        return $notes !== '' ? "{$label} - {$notes}" : $label;
    }

    protected static function findRowsByTargetName(array $groups, string $targetName): ?array
    {
        foreach ($groups as $group) {
            foreach ($group['rows'] ?? [] as $row) {
                if (($row['target_name'] ?? null) === $targetName) {
                    return $row;
                }
            }
        }

        return null;
    }

    protected static function materiBoarding(array $payload): array
    {
        return $payload['pencapaian']['materi_boarding'] ?? [];
    }

    protected static function mt(array $payload): array
    {
        return $payload['pencapaian']['mt'] ?? [];
    }
}
