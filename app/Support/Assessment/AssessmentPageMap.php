<?php

namespace App\Support\Assessment;

use App\Enums\Assessment\AssessmentType;
use App\Filament\Pages\Assessment\AsasHomeroomRecap;
use App\Filament\Pages\Assessment\AsasHub;
use App\Filament\Pages\Assessment\AsasInputScores;
use App\Filament\Pages\Assessment\AsasReports;
use App\Filament\Pages\Assessment\AsasSubmissionStatus;
use App\Filament\Pages\Assessment\AsatHomeroomRecap;
use App\Filament\Pages\Assessment\AsatHub;
use App\Filament\Pages\Assessment\AsatInputScores;
use App\Filament\Pages\Assessment\AsatReports;
use App\Filament\Pages\Assessment\AsatSubmissionStatus;
use App\Filament\Pages\Assessment\AstsHomeroomRecap;
use App\Filament\Pages\Assessment\AstsHub;
use App\Filament\Pages\Assessment\AstsInputScores;
use App\Filament\Pages\Assessment\AstsReports;
use App\Filament\Pages\Assessment\AstsSubmissionStatus;

/**
 * SATU peta halaman per jenis penilaian (ASTS · ASAS · ASAT).
 *
 * Sebelumnya tujuan halaman ditentukan dengan pola `$type === ASTS ? A : B` yang
 * tersebar di belasan tempat (hub, input nilai, status, rapor, notifikasi
 * kegagalan, resource mapel). Pola dua cabang itu membuat jenis KETIGA (ASAT)
 * selalu jatuh ke halaman ASAS: guru ASAT diarahkan ke layar ASAS tanpa pesan
 * kesalahan. Dengan peta ini, jenis baru cukup ditambahkan satu baris dan
 * seluruh tautan aplikasi ikut benar.
 *
 * Kelas ini hanya memetakan jenis -> kelas halaman. Ia TIDAK memeriksa izin;
 * pemanggil tetap wajib memakai canAccess() masing-masing halaman.
 */
class AssessmentPageMap
{
    /**
     * Bagian halaman yang dikenal untuk setiap jenis penilaian.
     */
    public const SECTIONS = ['hub', 'input', 'status', 'recap', 'reports'];

    /**
     * @return array<string, array<string, class-string>>
     */
    public static function all(): array
    {
        return [
            AssessmentType::ASTS->value => [
                'hub' => AstsHub::class,
                'input' => AstsInputScores::class,
                'status' => AstsSubmissionStatus::class,
                'recap' => AstsHomeroomRecap::class,
                'reports' => AstsReports::class,
            ],
            AssessmentType::ASAS->value => [
                'hub' => AsasHub::class,
                'input' => AsasInputScores::class,
                'status' => AsasSubmissionStatus::class,
                'recap' => AsasHomeroomRecap::class,
                'reports' => AsasReports::class,
            ],
            AssessmentType::ASAT->value => [
                'hub' => AsatHub::class,
                'input' => AsatInputScores::class,
                'status' => AsatSubmissionStatus::class,
                'recap' => AsatHomeroomRecap::class,
                'reports' => AsatReports::class,
            ],
        ];
    }

    /**
     * Seluruh halaman untuk satu jenis penilaian.
     *
     * Jenis yang belum dipetakan sengaja jatuh ke ASTS (jenis paling dasar)
     * agar tautan tidak pernah kosong, bukan ke ASAS seperti perilaku lama.
     *
     * @return array<string, class-string>
     */
    public static function for(?AssessmentType $type): array
    {
        $peta = self::all();

        return $peta[$type?->value] ?? $peta[AssessmentType::ASTS->value];
    }

    /**
     * Satu halaman: AssessmentPageMap::page($type, 'status').
     *
     * @return class-string
     */
    public static function page(?AssessmentType $type, string $section): string
    {
        $pages = self::for($type);

        return $pages[$section] ?? $pages['hub'];
    }

    /**
     * Jenis penilaian dari nilai kolom `type` yang bisa berupa enum atau string.
     */
    public static function normalizeType(mixed $type): ?AssessmentType
    {
        if ($type instanceof AssessmentType) {
            return $type;
        }

        return blank($type) ? null : AssessmentType::tryFrom((string) $type);
    }

    /**
     * Bagian halaman yang sedang dibuka, dilihat dari kelasnya.
     */
    public static function sectionOf(string $class): ?string
    {
        foreach (self::all() as $pages) {
            $section = array_search($class, $pages, true);

            if ($section !== false) {
                return (string) $section;
            }
        }

        return null;
    }
}
