<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Filament\Pages\Assessment\Concerns\HasAssessmentTypeNavigation;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

abstract class AssessmentTypeHubPage extends AssessmentPage
{
    use HasAssessmentTypeNavigation;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static AssessmentType $assessmentType;

    protected string $view = 'filament.pages.assessment.type-hub';

    #[Url(as: 'period')]
    public ?int $periodId = null;

    public function mount(): void
    {
        $periodIds = array_map('intval', array_keys($this->getPeriodOptions()));

        if (! $this->periodId || ! in_array($this->periodId, $periodIds, true)) {
            $this->periodId = $periodIds[0] ?? null;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return static::$assessmentType->label();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return static::$assessmentType === AssessmentType::ASTS
            ? 'Kelola input nilai, pengumpulan, rekap wali kelas, dan rapor tengah semester dari satu halaman.'
            : 'Kelola input nilai, pengumpulan, rekap wali kelas, dan rapor semester dari satu halaman.';
    }

    public function getAssessmentTypeLabel(): string
    {
        return static::$assessmentType->label();
    }

    /**
     * @return array<int, string>
     */
    public function getPeriodOptions(): array
    {
        return $this->scopePeriods(AssessmentPeriod::query())
            ->where('type', static::$assessmentType->value)
            ->latest('id')
            ->get()
            ->mapWithKeys(fn (AssessmentPeriod $period): array => [
                $period->getKey() => $period->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getHubData(): array
    {
        $period = $this->scopePeriods(AssessmentPeriod::query())
            ->where('type', static::$assessmentType->value)
            ->find($this->periodId);

        if (! $period) {
            return [
                'period' => null,
                'student_count' => 0,
                'class_count' => 0,
                'assignment_count' => 0,
                'completed_count' => 0,
                'remaining_count' => 0,
                'homeroom_count' => 0,
                'completion_percentage' => 0,
                'cards' => $this->actionCards(null, 0, 0, 0, 0, 0),
            ];
        }

        $assignmentQuery = $this->scopeAssignments(
            $period->assignments()->getQuery(),
            (int) $period->getKey(),
        );
        $assignmentCount = (clone $assignmentQuery)->count();
        $completedCount = (clone $assignmentQuery)
            ->whereIn('status', [
                AssignmentStatus::SUBMITTED->value,
                AssignmentStatus::VERIFIED->value,
                AssignmentStatus::LOCKED->value,
            ])
            ->count();
        $rombelIds = (clone $assignmentQuery)
            ->pluck('assessment_period_rombel_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $studentCount = $period->students()
            ->whereIn('assessment_period_rombel_id', $rombelIds)
            ->where('is_active', true)
            ->count();
        $classCount = $rombelIds->count();
        $homeroomCount = $this->scopeHomerooms(
            $period->homerooms()->getQuery(),
        )->count();
        $remainingCount = max(0, $assignmentCount - $completedCount);
        $completionPercentage = $assignmentCount > 0
            ? (int) round(($completedCount / $assignmentCount) * 100)
            : 0;

        return [
            'period' => $period,
            'student_count' => $studentCount,
            'class_count' => $classCount,
            'assignment_count' => $assignmentCount,
            'completed_count' => $completedCount,
            'remaining_count' => $remainingCount,
            'homeroom_count' => $homeroomCount,
            'completion_percentage' => $completionPercentage,
            'cards' => $this->actionCards(
                $period,
                $studentCount,
                $classCount,
                $assignmentCount,
                $completedCount,
                $homeroomCount,
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function actionCards(
        ?AssessmentPeriod $period,
        int $studentCount,
        int $classCount,
        int $assignmentCount,
        int $completedCount,
        int $homeroomCount,
    ): array {
        $isAsts = static::$assessmentType === AssessmentType::ASTS;
        $inputPage = $isAsts ? AstsInputScores::class : AsasInputScores::class;
        $statusPage = $isAsts ? AstsSubmissionStatus::class : AsasSubmissionStatus::class;
        $homeroomPage = $isAsts ? AstsHomeroomRecap::class : AsasHomeroomRecap::class;
        $reportsPage = $isAsts ? AstsReports::class : AsasReports::class;
        $parameters = $period ? ['period' => $period->getKey()] : [];

        return [
            [
                'title' => 'Input Nilai Saya',
                'description' => 'Isi nilai per kelas, simpan draf, lalu kirim untuk diverifikasi.',
                'icon' => 'heroicon-o-pencil-square',
                'tone' => 'primary',
                'value' => number_format($assignmentCount, 0, ',', '.'),
                'caption' => max(0, $assignmentCount - $completedCount).' masih perlu dilengkapi',
                'url' => $inputPage::canAccess() ? $inputPage::getUrl($parameters) : null,
            ],
            [
                'title' => 'Status Pengumpulan',
                'description' => 'Pantau penugasan yang masih draf, dikirim, dikembalikan, atau sudah dikunci.',
                'icon' => 'heroicon-o-clipboard-document-check',
                'tone' => 'success',
                'value' => "{$completedCount}/{$assignmentCount}",
                'caption' => "{$completedCount} dikirim · ".max(0, $assignmentCount - $completedCount).' belum dikirim',
                'url' => $statusPage::canAccess() ? $statusPage::getUrl($parameters) : null,
            ],
            [
                'title' => 'Rekap Wali Kelas',
                'description' => 'Lengkapi absensi, ekstrakurikuler, prestasi, dan catatan wali kelas.',
                'icon' => 'heroicon-o-user-group',
                'tone' => 'warning',
                'value' => number_format($homeroomCount, 0, ',', '.'),
                'caption' => 'kelas wali dalam cakupan',
                'url' => $homeroomPage::canAccess() ? $homeroomPage::getUrl($parameters) : null,
            ],
            [
                'title' => $isAsts ? 'Cetak Rapor ASTS' : 'Cetak Rapor Semester',
                'description' => 'Buat PDF privat, pantau proses, dan kelola tautan rapor yang aman.',
                'icon' => 'heroicon-o-printer',
                'tone' => 'info',
                'value' => number_format($studentCount, 0, ',', '.'),
                'caption' => 'siswa dalam cakupan',
                'url' => $reportsPage::canAccess() ? $reportsPage::getUrl($parameters) : null,
            ],
        ];
    }

    protected function scopePeriods(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasFullAdminAccess() || $user->can('penilaian.verify') || $user->hasRole('kepala_sekolah')) {
            return $query;
        }

        if (! $user->guru_tendik_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $periods) use ($user): void {
            $periods
                ->whereHas('assignments', fn (Builder $assignments): Builder => $assignments->where('teacher_id', $user->guru_tendik_id))
                ->orWhereHas('homerooms', fn (Builder $homerooms): Builder => $homerooms->where('teacher_id', $user->guru_tendik_id));
        });
    }

    protected function scopeAssignments(Builder $query, int $periodId): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasFullAdminAccess() || $user->can('penilaian.verify') || $user->hasRole('kepala_sekolah')) {
            return $query;
        }

        if (! $user->guru_tendik_id) {
            return $query->whereRaw('1 = 0');
        }

        $homeroomRombelIds = AssessmentPeriodHomeroom::query()
            ->where('assessment_period_id', $periodId)
            ->where('teacher_id', $user->guru_tendik_id)
            ->pluck('assessment_period_rombel_id')
            ->all();

        return $query->where(function (Builder $assignments) use ($user, $homeroomRombelIds): void {
            $assignments->where('teacher_id', $user->guru_tendik_id);

            if ($homeroomRombelIds !== []) {
                $assignments->orWhereIn('assessment_period_rombel_id', $homeroomRombelIds);
            }
        });
    }

    protected function scopeHomerooms(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasFullAdminAccess() || $user->can('penilaian.verify') || $user->hasRole('kepala_sekolah')) {
            return $query;
        }

        return $user->guru_tendik_id
            ? $query->where('teacher_id', $user->guru_tendik_id)
            : $query->whereRaw('1 = 0');
    }
}
