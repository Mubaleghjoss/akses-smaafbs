<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssignmentStatus;
use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AuditLog;
use App\Models\Assessment\Subject;
use App\Models\Assessment\TeachingAssignment;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class AssessmentDashboard extends AssessmentPage
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Dashboard Penilaian';

    protected static ?string $slug = 'penilaian';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.assessment.dashboard';

    #[Url(as: 'period')]
    public ?int $periodId = null;

    public function mount(): void
    {
        $ids = array_map('intval', array_keys($this->getPeriodOptions()));
        if (! $this->periodId || ! in_array($this->periodId, $ids, true)) {
            $this->periodId = $ids[0] ?? null;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Dashboard Penilaian';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Pantau kesiapan master, input guru, verifikasi, dan pembuatan rapor ASTS–ASAS.';
    }

    public function getPeriodOptions(): array
    {
        return $this->scopePeriods(AssessmentPeriod::query())
            ->latest('id')
            ->get()
            ->mapWithKeys(function (AssessmentPeriod $period): array {
                $type = $period->type instanceof AssessmentType ? $period->type->label() : strtoupper((string) $period->type);

                return [$period->getKey() => "{$type} · {$period->name}"];
            })
            ->all();
    }

    public function getReadiness(): array
    {
        return [
            ['label' => 'Tahun Pelajaran', 'count' => AcademicYear::query()->count(), 'ready' => AcademicYear::query()->exists()],
            ['label' => 'Mata Pelajaran', 'count' => Subject::query()->where('is_active', true)->count(), 'ready' => Subject::query()->where('is_active', true)->exists()],
            ['label' => 'Penugasan Guru', 'count' => TeachingAssignment::query()->where('is_active', true)->count(), 'ready' => TeachingAssignment::query()->where('is_active', true)->exists()],
            ['label' => 'Periode', 'count' => AssessmentPeriod::query()->count(), 'ready' => AssessmentPeriod::query()->exists()],
        ];
    }

    public function getMetrics(): array
    {
        $period = $this->scopePeriods(AssessmentPeriod::query())
            ->find($this->periodId);

        if (! $period) {
            return [
                'period' => null,
                'cards' => [],
                'student_count' => 0,
                'class_count' => 0,
                'assignment_count' => 0,
            ];
        }

        $assignmentQuery = $this->scopeAssignments(
            $period->assignments()->getQuery(),
            (int) $period->getKey(),
        );
        $counts = (clone $assignmentQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $rombelIds = (clone $assignmentQuery)
            ->pluck('assessment_period_rombel_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $cards = collect(AssignmentStatus::cases())
            ->map(fn (AssignmentStatus $status): array => [
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
                'status' => $status->value,
                'url' => $this->statusUrl($period, $status),
            ])
            ->all();

        return [
            'period' => $period,
            'cards' => $cards,
            'student_count' => $period->students()
                ->whereIn('assessment_period_rombel_id', $rombelIds)
                ->where('is_active', true)
                ->count(),
            'class_count' => $rombelIds->count(),
            'assignment_count' => (clone $assignmentQuery)->count(),
        ];
    }

    public function getRecentAuditRows(): array
    {
        $user = auth()->user();

        if (! $user instanceof User
            || (! $user->hasFullAdminAccess() && ! $user->can('penilaian.audit.view'))) {
            return [];
        }

        if (! $this->periodId) {
            return [];
        }

        $scopedPeriodId = $this->scopePeriods(AssessmentPeriod::query())
            ->whereKey($this->periodId)
            ->value('id');

        if (! $scopedPeriodId) {
            return [];
        }

        return AuditLog::query()
            ->where('assessment_period_id', $scopedPeriodId)
            ->with('actor')
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'event' => (string) $log->event,
                'actor' => (string) ($log->actor?->name ?: 'Sistem'),
                'time' => $log->created_at?->diffForHumans(),
                'reason' => $log->reason,
            ])
            ->all();
    }

    protected function statusUrl(AssessmentPeriod $period, AssignmentStatus $status): string
    {
        $type = $period->type instanceof AssessmentType
            ? $period->type
            : AssessmentType::from((string) $period->type);
        $page = $type === AssessmentType::ASTS
            ? AstsSubmissionStatus::class
            : AsasSubmissionStatus::class;

        return $page::getUrl([
            'period' => $period->getKey(),
            'status' => $status->value,
        ]);
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

        $homeroomRombelIds = $user->can('penilaian.homeroom')
            ? \App\Models\Assessment\AssessmentPeriodHomeroom::query()
                ->where('assessment_period_id', $periodId)
                ->where('teacher_id', $user->guru_tendik_id)
                ->pluck('assessment_period_rombel_id')
                ->all()
            : [];

        return $query->where(function (Builder $assignments) use ($user, $homeroomRombelIds): void {
            $assignments->where('teacher_id', $user->guru_tendik_id);

            if ($homeroomRombelIds !== []) {
                $assignments->orWhereIn('assessment_period_rombel_id', $homeroomRombelIds);
            }
        });
    }
}
