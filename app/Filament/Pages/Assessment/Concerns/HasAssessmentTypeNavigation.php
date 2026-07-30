<?php

namespace App\Filament\Pages\Assessment\Concerns;

use App\Enums\Assessment\AssessmentType;
use App\Filament\Pages\Assessment\AsasHomeroomRecap;
use App\Filament\Pages\Assessment\AsasHub;
use App\Filament\Pages\Assessment\AsasInputScores;
use App\Filament\Pages\Assessment\AsasReports;
use App\Filament\Pages\Assessment\AsasSubmissionStatus;
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
     * @return array<string, mixed>
     */
    public function getAssessmentNavigationData(): array
    {
        $isAsts = static::$assessmentType === AssessmentType::ASTS;
        $hubPage = $isAsts ? AstsHub::class : AsasHub::class;
        $periodParameters = $this->periodId ? ['period' => $this->periodId] : [];

        $items = [
            ['label' => 'Beranda', 'icon' => 'heroicon-o-home', 'page' => $hubPage],
            ['label' => 'Input Nilai', 'icon' => 'heroicon-o-pencil-square', 'page' => $isAsts ? AstsInputScores::class : AsasInputScores::class],
            ['label' => 'Status', 'icon' => 'heroicon-o-clipboard-document-check', 'page' => $isAsts ? AstsSubmissionStatus::class : AsasSubmissionStatus::class],
            ['label' => 'Rekap Wali', 'icon' => 'heroicon-o-user-group', 'page' => $isAsts ? AstsHomeroomRecap::class : AsasHomeroomRecap::class],
            ['label' => 'Rapor', 'icon' => 'heroicon-o-printer', 'page' => $isAsts ? AstsReports::class : AsasReports::class],
        ];

        return [
            'dashboard_url' => AssessmentDashboard::getUrl(),
            'hub_url' => $hubPage::getUrl($periodParameters),
            'type_label' => static::$assessmentType->label(),
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
