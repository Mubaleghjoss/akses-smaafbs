<?php

namespace App\Support\Assessment\Reporting;

use Illuminate\Validation\ValidationException;

final class AssessmentReportLayout
{
    public const VERSION = 2;

    /**
     * @return array<string, string>
     */
    public static function sectionOptions(): array
    {
        return [
            'identity' => 'Kop dan Identitas Siswa',
            'attitudes' => 'Sikap Spiritual dan Sosial',
            'subject_summary' => 'Ringkasan Nilai per Kelompok Mapel',
            'subject_competencies' => 'Rincian Capaian Kompetensi',
            'extracurricular' => 'Ekstrakurikuler',
            'achievements' => 'Prestasi',
            'attendance' => 'Ketidakhadiran',
            'homeroom_note' => 'Catatan Wali Kelas',
            'parent_response' => 'Tanggapan Orang Tua',
            'signatures' => 'Tanda Tangan',
        ];
    }

    /**
     * @return array<int, array{type:string,title:string,page:int,sort_order:int,enabled:bool}>
     */
    public static function threePageDefaults(): array
    {
        return [
            ['type' => 'identity', 'title' => 'Identitas Peserta Didik', 'page' => 1, 'sort_order' => 10, 'enabled' => true],
            ['type' => 'attitudes', 'title' => 'A. Sikap', 'page' => 1, 'sort_order' => 20, 'enabled' => true],
            ['type' => 'subject_summary', 'title' => 'B. Pengetahuan dan Keterampilan', 'page' => 1, 'sort_order' => 30, 'enabled' => true],
            ['type' => 'identity', 'title' => 'Identitas Peserta Didik', 'page' => 2, 'sort_order' => 10, 'enabled' => true],
            ['type' => 'subject_competencies', 'title' => 'Capaian Kompetensi', 'page' => 2, 'sort_order' => 20, 'enabled' => true],
            ['type' => 'identity', 'title' => 'Identitas Peserta Didik', 'page' => 3, 'sort_order' => 10, 'enabled' => true],
            ['type' => 'extracurricular', 'title' => 'C. Ekstrakurikuler', 'page' => 3, 'sort_order' => 20, 'enabled' => true],
            ['type' => 'achievements', 'title' => 'D. Prestasi', 'page' => 3, 'sort_order' => 30, 'enabled' => true],
            ['type' => 'attendance', 'title' => 'E. Ketidakhadiran', 'page' => 3, 'sort_order' => 40, 'enabled' => true],
            ['type' => 'homeroom_note', 'title' => 'F. Catatan Wali Kelas', 'page' => 3, 'sort_order' => 50, 'enabled' => true],
            ['type' => 'parent_response', 'title' => 'G. Tanggapan Orang Tua/Wali', 'page' => 3, 'sort_order' => 60, 'enabled' => true],
            ['type' => 'signatures', 'title' => 'Pengesahan', 'page' => 3, 'sort_order' => 70, 'enabled' => true],
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function validateAndNormalize(array $settings): array
    {
        $sections = data_get($settings, 'layout.sections');
        if (! is_array($sections) || $sections === []) {
            return $settings;
        }

        $normalized = collect($sections)
            ->map(function (mixed $section, int $index): array {
                $section = is_array($section) ? $section : [];
                $type = trim((string) ($section['type'] ?? ''));
                $page = (int) ($section['page'] ?? 0);
                $title = trim((string) ($section['title'] ?? ''));

                if (! array_key_exists($type, self::sectionOptions())) {
                    throw ValidationException::withMessages([
                        "data.settings.layout.sections.{$index}.type" => 'Jenis bagian rapor tidak dikenal.',
                    ]);
                }

                if ($page < 1 || $page > 3) {
                    throw ValidationException::withMessages([
                        "data.settings.layout.sections.{$index}.page" => 'Halaman harus antara 1 sampai 3.',
                    ]);
                }

                if (mb_strlen($title) > 120) {
                    throw ValidationException::withMessages([
                        "data.settings.layout.sections.{$index}.title" => 'Judul bagian maksimal 120 karakter.',
                    ]);
                }

                return [
                    'type' => $type,
                    'title' => $title !== '' ? $title : self::sectionOptions()[$type],
                    'page' => $page,
                    'sort_order' => max(0, min(999, (int) ($section['sort_order'] ?? (($index + 1) * 10)))),
                    'enabled' => (bool) ($section['enabled'] ?? true),
                ];
            })
            ->filter(fn (array $section): bool => $section['enabled'])
            ->values();

        foreach ($normalized->groupBy('page') as $page => $pageSections) {
            $duplicates = $pageSections->pluck('type')->duplicates()->values();
            if ($duplicates->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'data.settings.layout.sections' => 'Satu jenis bagian hanya boleh muncul sekali pada halaman yang sama.',
                ]);
            }
        }

        if (! $normalized->contains(fn (array $section): bool => $section['type'] === 'identity')) {
            throw ValidationException::withMessages([
                'data.settings.layout.sections' => 'Template resmi wajib memiliki Kop dan Identitas Siswa.',
            ]);
        }

        if (! $normalized->contains(fn (array $section): bool => in_array($section['type'], ['subject_summary', 'subject_competencies'], true))) {
            throw ValidationException::withMessages([
                'data.settings.layout.sections' => 'Template resmi wajib memiliki minimal satu bagian akademik.',
            ]);
        }

        if (! $normalized->contains(fn (array $section): bool => $section['type'] === 'signatures')) {
            throw ValidationException::withMessages([
                'data.settings.layout.sections' => 'Template resmi wajib memiliki bagian Tanda Tangan.',
            ]);
        }

        data_set($settings, 'layout.version', self::VERSION);
        data_set(
            $settings,
            'layout.sections',
            $normalized->sortBy([['page', 'asc'], ['sort_order', 'asc']])->values()->all(),
        );

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function pages(array $settings): array
    {
        $sections = data_get($settings, 'layout.sections', []);
        if (! is_array($sections) || $sections === []) {
            return [];
        }

        return collect($sections)
            ->filter(fn (mixed $section): bool => is_array($section) && (bool) ($section['enabled'] ?? true))
            ->sortBy([['page', 'asc'], ['sort_order', 'asc']])
            ->groupBy(fn (array $section): int => (int) ($section['page'] ?? 1))
            ->map(fn ($pageSections): array => $pageSections->values()->all())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function requiresAttitudes(array $settings): bool
    {
        return collect(data_get($settings, 'layout.sections', []))
            ->contains(fn (mixed $section): bool => is_array($section)
                && (bool) ($section['enabled'] ?? true)
                && ($section['type'] ?? null) === 'attitudes');
    }
}
