<?php

namespace App\Filament\Pages\Assessment\Concerns;

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
use App\Filament\Pages\Assessment\AssessmentDashboard;
use App\Filament\Pages\Assessment\AstsHomeroomRecap;
use App\Filament\Pages\Assessment\AstsHub;
use App\Filament\Pages\Assessment\AstsInputScores;
use App\Filament\Pages\Assessment\AstsReports;
use App\Filament\Pages\Assessment\AstsSubmissionStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\User;

trait HasAssessmentTypeNavigation
{
    /**
     * PETA halaman per jenis penilaian — satu tempat, bukan if/else bercabang.
     *
     * Sebelumnya navigasi memakai `$isAsts ? A : B`, yang berarti setiap jenis
     * baru harus menyunting logika navigasi dan mudah terlewat. Dengan peta ini,
     * menambah jenis cukup menambah satu baris.
     *
     * @return array<string, array<string, class-string>>
     */
    protected static function assessmentPageMap(): array
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
     * @return array<string, class-string>
     */
    protected static function assessmentPagesFor(AssessmentType $type): array
    {
        return static::assessmentPageMap()[$type->value]
            ?? static::assessmentPageMap()[AssessmentType::ASTS->value];
    }

    /**
     * @return array<string, mixed>
     */
    public function getAssessmentNavigationData(): array
    {
        $pages = static::assessmentPagesFor(static::$assessmentType);
        $hubPage = $pages['hub'];
        $periodParameters = $this->periodId ? ['period' => $this->periodId] : [];

        $items = [
            ['label' => 'Beranda', 'icon' => 'heroicon-o-home', 'page' => $hubPage],
            ['label' => 'Input Nilai', 'icon' => 'heroicon-o-pencil-square', 'page' => $pages['input']],
            ['label' => 'Status', 'icon' => 'heroicon-o-clipboard-document-check', 'page' => $pages['status']],
            ['label' => 'Rekap Wali', 'icon' => 'heroicon-o-user-group', 'page' => $pages['recap']],
            ['label' => 'Rapor', 'icon' => 'heroicon-o-printer', 'page' => $pages['reports']],
        ];

        return [
            'dashboard_url' => AssessmentDashboard::getUrl(),
            'hub_url' => $hubPage::getUrl($periodParameters),
            'is_hub' => static::class === $hubPage,
            'type_label' => static::$assessmentType->label(),
            'type_long_label' => static::$assessmentType->namaPanjang(),
            'type_tabs' => $this->getAssessmentTypeTabs(),
            'items' => collect($items)
                ->map(fn (array $item): array => [
                    ...$item,
                    'active' => static::class === $item['page'],
                    'url' => $item['page']::canAccess()
                        ? $item['page']::getUrl($periodParameters)
                        : null,
                ])
                ->all(),
        ];
    }

    /**
     * Tab pemilih JENIS penilaian (ASTS · ASAS · ASAT).
     *
     * Berpindah jenis mempertahankan HALAMAN yang sedang dibuka: dari Status
     * ASTS ke Status ASAS, bukan kembali ke beranda. Periode TIDAK dibawa
     * karena periode terikat pada satu jenis.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAssessmentTypeTabs(): array
    {
        $bagian = $this->currentAssessmentSection();

        return collect(AssessmentType::cases())
            ->map(function (AssessmentType $type) use ($bagian): array {
                $pages = static::assessmentPagesFor($type);
                $target = $pages[$bagian] ?? $pages['hub'];

                return [
                    'value' => $type->value,
                    'label' => $type->label(),
                    'long_label' => $type->namaPanjang(),
                    'active' => $type === static::$assessmentType,
                    'url' => $target::canAccess() ? $target::getUrl() : null,
                ];
            })
            ->all();
    }

    /**
     * Bagian halaman yang sedang dibuka (hub/input/status/recap/reports).
     */
    protected function currentAssessmentSection(): string
    {
        $pages = static::assessmentPagesFor(static::$assessmentType);

        foreach ($pages as $bagian => $kelas) {
            if (static::class === $kelas) {
                return $bagian;
            }
        }

        return 'hub';
    }

    /**
     * @return array<string, mixed>
     */
    public function getAssessmentAccessSummary(): array
    {
        $user = auth()->user();
        $period = $this->periodId
            ? AssessmentPeriod::query()
                ->whereKey($this->periodId)
                ->where('type', static::$assessmentType->value)
                ->first()
            : null;

        if (! $user instanceof User || ! $period) {
            return [
                'mode' => 'empty',
                'title' => 'Cakupan akun belum tersedia',
                'description' => 'Pilih periode untuk melihat mapel, kelas, dan penugasan wali kelas akun ini.',
                'subjects' => [],
                'homerooms' => [],
            ];
        }

        if ($user->hasFullAdminAccess() || $user->can('penilaian.verify') || $user->hasRole('kepala_sekolah')) {
            return [
                'mode' => 'all',
                'title' => 'Akses pengelola',
                'description' => 'Akun ini dapat meninjau seluruh mapel dan kelas pada '.$period->name.'.',
                'subjects' => [],
                'homerooms' => [],
            ];
        }

        if (! $user->guru_tendik_id) {
            return [
                'mode' => 'empty',
                'title' => 'Akun belum tertaut ke data guru',
                'description' => 'Hubungkan akun login ke Guru & Tendik agar cakupan mapel dan wali kelas dapat dikenali.',
                'subjects' => [],
                'homerooms' => [],
            ];
        }

        $assignments = AssessmentPeriodAssignment::query()
            ->where('assessment_period_id', $period->getKey())
            ->where('teacher_id', $user->guru_tendik_id)
            ->orderBy('subject_name_snapshot')
            ->orderBy('rombel_name_snapshot')
            ->get(['subject_name_snapshot', 'rombel_name_snapshot']);

        $subjects = $assignments
            ->groupBy('subject_name_snapshot')
            ->map(fn ($rows, string $subject): array => [
                'subject' => $subject,
                'classes' => $rows->pluck('rombel_name_snapshot')->unique()->values()->all(),
            ])
            ->values()
            ->all();

        $homerooms = AssessmentPeriodHomeroom::query()
            ->where('assessment_period_id', $period->getKey())
            ->where('teacher_id', $user->guru_tendik_id)
            ->orderBy('rombel_name_snapshot')
            ->pluck('rombel_name_snapshot')
            ->unique()
            ->values()
            ->all();

        return [
            'mode' => ($subjects !== [] || $homerooms !== []) ? 'scoped' : 'empty',
            'title' => 'Cakupan Saya · '.$period->name,
            'description' => ($subjects !== [] || $homerooms !== [])
                ? 'Cakupan di bawah berasal dari snapshot penugasan periode, bukan label bebas pada akun.'
                : 'Belum ada penugasan mapel atau wali kelas untuk akun ini pada periode yang dipilih.',
            'subjects' => $subjects,
            'homerooms' => $homerooms,
        ];
    }

    protected static function currentUserOwnsAssessmentHomeroom(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $user->guru_tendik_id) {
            return false;
        }

        return AssessmentPeriodHomeroom::query()
            ->where('teacher_id', $user->guru_tendik_id)
            ->whereHas('period', fn ($query) => $query->where('type', static::$assessmentType->value))
            ->exists();
    }
}
