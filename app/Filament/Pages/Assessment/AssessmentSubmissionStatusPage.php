<?php

namespace App\Filament\Pages\Assessment;

use App\Actions\Assessment\ReturnAssessmentAssignmentAction;
use App\Actions\Assessment\VerifyAssessmentAssignmentAction;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Filament\Pages\Assessment\Concerns\HasAssessmentTypeNavigation;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\User;
use App\Support\Assessment\AssessmentActionFailureNotification;
use App\Support\Assessment\AssessmentStatusScope;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Throwable;

abstract class AssessmentSubmissionStatusPage extends AssessmentPage
{
    use HasAssessmentTypeNavigation;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string $assessmentPermission = 'penilaian.view';

    protected string $view = 'filament.pages.assessment.submission-status';

    protected static AssessmentType $assessmentType;

    #[Url(as: 'period')]
    public ?int $periodId = null;

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'rombel')]
    public ?int $activeRombelId = null;

    /** @var array<int, int|string> */
    public array $selectedAssignmentIds = [];

    /** @var array<int, int> */
    public array $returnTargetIds = [];

    public string $returnReason = '';

    public function mount(): void
    {
        $ids = array_map('intval', array_keys($this->getPeriodOptions()));
        if (! $this->periodId || ! in_array($this->periodId, $ids, true)) {
            $this->periodId = $ids[0] ?? null;
        }

        $this->normalizeActiveRombel();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Status Pengumpulan · '.static::$assessmentType->label();
    }

    public function getPeriodOptions(): array
    {
        return $this->scopePeriods(AssessmentPeriod::query())
            ->where('type', static::$assessmentType->value)
            ->latest('id')
            ->pluck('name', 'id')
            ->all();
    }

    public function getStatusOptions(): array
    {
        return ['all' => 'Semua Status'] + AssignmentStatus::options();
    }

    public function updatedPeriodId(): void
    {
        $this->selectedAssignmentIds = [];
        // null = "Semua Kelas". Kelas TIDAK dipilih otomatis supaya daftar &
        // aksi massal tetap mencakup seluruh kelas dalam cakupan pengguna.
        $this->activeRombelId = null;
        $this->normalizeActiveRombel();
    }

    public function updatedStatusFilter(): void
    {
        $this->selectedAssignmentIds = [];
    }

    public function selectRombel(int $rombelId): void
    {
        $accessibleIds = collect($this->getClassTabs())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);

        if (! $accessibleIds->contains($rombelId)) {
            return;
        }

        $this->activeRombelId = $rombelId;
        $this->selectedAssignmentIds = [];
    }

    /**
     * Kembali ke tampilan seluruh kelas dalam cakupan pengguna.
     */
    public function showAllRombels(): void
    {
        $this->activeRombelId = null;
        $this->selectedAssignmentIds = [];
    }

    public function showAllStatuses(): void
    {
        $this->statusFilter = 'all';
        $this->selectedAssignmentIds = [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClassTabs(): array
    {
        if (! $this->periodId) {
            return [];
        }

        $period = AssessmentPeriod::query()->find($this->periodId);
        if (! $period || ($period->type instanceof AssessmentType ? $period->type : AssessmentType::from($period->type)) !== static::$assessmentType) {
            return [];
        }

        return $this->scopeAssignments($period->assignments()->getQuery())
            ->select(['assessment_period_rombel_id', 'rombel_name_snapshot'])
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as pending_count',
                [AssignmentStatus::DRAFT->value, AssignmentStatus::RETURNED->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?, ?) THEN 1 ELSE 0 END) as completed_count',
                [AssignmentStatus::SUBMITTED->value, AssignmentStatus::VERIFIED->value, AssignmentStatus::LOCKED->value],
            )
            ->groupBy('assessment_period_rombel_id', 'rombel_name_snapshot')
            ->orderBy('rombel_name_snapshot')
            ->get()
            ->map(fn (AssessmentPeriodAssignment $assignment): array => [
                'id' => (int) $assignment->assessment_period_rombel_id,
                'name' => (string) $assignment->rombel_name_snapshot,
                'total' => (int) $assignment->total_count,
                'pending' => (int) $assignment->pending_count,
                'completed' => (int) $assignment->completed_count,
            ])
            ->all();
    }

    /**
     * @return array{mode: string, title: string, description: string, classes: string}
     */
    public function getScopeCard(): array
    {
        $user = auth()->user();
        $classNames = collect($this->getClassTabs())->pluck('name')->implode(', ');

        if (! $user instanceof User || ! $this->periodId) {
            return [
                'mode' => 'none',
                'title' => 'Belum Ada Cakupan',
                'description' => 'Pilih periode yang tersedia untuk melihat status pengumpulan.',
                'classes' => '-',
            ];
        }

        $mode = app(AssessmentStatusScope::class)->mode($user, $this->periodId);

        return match ($mode) {
            'all' => [
                'mode' => $mode,
                'title' => 'Seluruh Kelas',
                'description' => 'Anda dapat memantau status seluruh mapel pada periode ini sesuai hak akses pengelola.',
                'classes' => $classNames ?: 'Belum ada kelas',
            ],
            'homeroom' => [
                'mode' => $mode,
                'title' => 'Cakupan Wali Kelas',
                'description' => 'Status hanya memuat seluruh mapel pada kelas wali Anda. Mapel yang Anda ampu di kelas lain tetap tersedia di Input Nilai Saya.',
                'classes' => $classNames ?: 'Belum ada kelas wali',
            ],
            'teacher' => [
                'mode' => $mode,
                'title' => 'Cakupan Mapel Saya',
                'description' => 'Status hanya memuat mapel dan kelas yang tercatat di bawah penugasan Anda.',
                'classes' => $classNames ?: 'Belum ada kelas mengajar',
            ],
            default => [
                'mode' => 'none',
                'title' => 'Belum Ada Cakupan',
                'description' => 'Akun ini belum mempunyai penugasan guru atau wali kelas pada periode terpilih.',
                'classes' => '-',
            ],
        };
    }

    public function selectAllFilteredAssignments(): void
    {
        $this->selectedAssignmentIds = collect($this->getAssignmentRows())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function clearAssignmentSelection(): void
    {
        $this->selectedAssignmentIds = [];
    }

    public function verifySelectedAssignments(): void
    {
        $this->authorizeAssessment('penilaian.verify');

        try {
            $assignments = $this->selectedScopedAssignments();
            DB::transaction(function () use ($assignments): void {
                foreach ($assignments as $assignment) {
                    if ($this->assignmentStatus($assignment) !== AssignmentStatus::SUBMITTED) {
                        throw ValidationException::withMessages([
                            'assignments' => "{$assignment->rombel_name_snapshot} · {$assignment->subject_name_snapshot} belum berstatus Dikirim.",
                        ]);
                    }
                }

                foreach ($assignments as $assignment) {
                    app(VerifyAssessmentAssignmentAction::class)->execute(auth()->user(), $assignment);
                }
            }, 3);

            $count = $assignments->count();
            $this->selectedAssignmentIds = [];
            Notification::make()->title("{$count} penugasan terverifikasi")->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            AssessmentActionFailureNotification::send(
                $exception,
                'Verifikasi Penugasan Terpilih',
                AssessmentPeriod::query()->find($this->periodId),
            );
        }
    }

    public function prepareReturn(?int $assignmentId = null): void
    {
        $this->authorizeAssessment('penilaian.verify');
        $this->returnReason = '';
        $this->returnTargetIds = $assignmentId
            ? [$assignmentId]
            : collect($this->selectedAssignmentIds)->map(fn (mixed $id): int => (int) $id)->all();

        if ($this->returnTargetIds === []) {
            Notification::make()->title('Pilih minimal satu penugasan')->warning()->send();

            return;
        }

        $this->dispatch('open-modal', id: 'assessment-return-modal');
    }

    public function confirmReturnAssignments(): void
    {
        $this->authorizeAssessment('penilaian.verify');
        $reason = trim($this->returnReason);

        if (mb_strlen($reason) < 10) {
            $this->addError('returnReason', 'Alasan revisi minimal 10 karakter.');

            return;
        }

        try {
            $originalSelection = $this->selectedAssignmentIds;
            try {
                $this->selectedAssignmentIds = $this->returnTargetIds;
                $assignments = $this->selectedScopedAssignments();
            } finally {
                $this->selectedAssignmentIds = $originalSelection;
            }

            DB::transaction(function () use ($assignments, $reason): void {
                foreach ($assignments as $assignment) {
                    if (! in_array($this->assignmentStatus($assignment), [AssignmentStatus::SUBMITTED, AssignmentStatus::VERIFIED], true)) {
                        throw ValidationException::withMessages([
                            'assignments' => "{$assignment->rombel_name_snapshot} · {$assignment->subject_name_snapshot} tidak dapat dikembalikan pada status saat ini.",
                        ]);
                    }
                }

                foreach ($assignments as $assignment) {
                    app(ReturnAssessmentAssignmentAction::class)->execute(auth()->user(), $assignment, $reason);
                }
            }, 3);

            $count = $assignments->count();
            $this->selectedAssignmentIds = array_values(array_diff(
                array_map('intval', $this->selectedAssignmentIds),
                $assignments->modelKeys(),
            ));
            $this->returnTargetIds = [];
            $this->returnReason = '';
            $this->dispatch('close-modal', id: 'assessment-return-modal');
            Notification::make()->title("{$count} penugasan dikembalikan untuk revisi")->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            AssessmentActionFailureNotification::send(
                $exception,
                'Kembalikan Penugasan Terpilih',
                AssessmentPeriod::query()->find($this->periodId),
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAssignmentRows(): array
    {
        if (! $this->periodId) {
            return [];
        }

        $period = AssessmentPeriod::query()->find($this->periodId);
        if (! $period || ($period->type instanceof AssessmentType ? $period->type : AssessmentType::from($period->type)) !== static::$assessmentType) {
            return [];
        }

        // Kelas aktif hanya MEMPERSEMPIT tampilan. Bila belum dipilih
        // (null = Semua Kelas), seluruh kelas dalam cakupan pengguna ditampilkan.
        $activeRombelId = $this->resolvedActiveRombelId();

        $studentCounts = $period->students()
            ->selectRaw('assessment_period_rombel_id, COUNT(*) as aggregate')
            ->where('is_active', true)
            ->groupBy('assessment_period_rombel_id')
            ->pluck('aggregate', 'assessment_period_rombel_id');
        $query = $this->scopeAssignments(
            $period->assignments()->getQuery()->withCount([
                'results',
                'results as completed_results_count' => fn (Builder $builder): Builder => $builder->whereNotNull('final_score'),
            ]),
        );

        if ($activeRombelId) {
            $query->where('assessment_period_rombel_id', $activeRombelId);
        }

        if ($this->statusFilter !== 'all' && array_key_exists($this->statusFilter, AssignmentStatus::options())) {
            $query->where('status', $this->statusFilter);
        }

        return $query
            ->orderBy('rombel_name_snapshot')
            ->orderBy('subject_name_snapshot')
            ->get()
            ->map(function (AssessmentPeriodAssignment $assignment) use ($studentCounts): array {
                $status = $assignment->status instanceof AssignmentStatus
                    ? $assignment->status
                    : AssignmentStatus::from((string) $assignment->status);
                $studentCount = (int) ($studentCounts[$assignment->assessment_period_rombel_id] ?? 0);

                return [
                    'id' => (int) $assignment->getKey(),
                    'rombel' => (string) $assignment->rombel_name_snapshot,
                    'subject' => (string) $assignment->subject_name_snapshot,
                    'teacher' => (string) $assignment->teacher_name_snapshot,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'student_count' => $studentCount,
                    'completed_count' => (int) $assignment->completed_results_count,
                    'completion_percent' => $studentCount > 0
                        ? (int) round(((int) $assignment->completed_results_count / $studentCount) * 100)
                        : 0,
                    'submitted_at' => $assignment->submitted_at?->format('d/m/Y H:i'),
                    'returned_reason' => $assignment->returned_reason,
                    'review_url' => $this->scoreReviewUrl($assignment),
                ];
            })
            ->all();
    }

    public function verifyAssignment(int $assignmentId): void
    {
        $this->authorizeAssessment('penilaian.verify');
        $assignment = $this->scopedAssignment($assignmentId);

        try {
            app(VerifyAssessmentAssignmentAction::class)->execute(auth()->user(), $assignment);
            Notification::make()->title('Penugasan terverifikasi')->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            AssessmentActionFailureNotification::send(
                $exception,
                'Verifikasi Penugasan',
                AssessmentPeriod::query()->find($this->periodId),
            );
        }
    }

    public function returnAssignment(int $assignmentId, string $reason): void
    {
        $this->authorizeAssessment('penilaian.verify');
        $assignment = $this->scopedAssignment($assignmentId);

        try {
            app(ReturnAssessmentAssignmentAction::class)->execute(auth()->user(), $assignment, trim($reason));
            Notification::make()->title('Penugasan dikembalikan')->success()->send();
        } catch (Throwable $exception) {
            report($exception);
            AssessmentActionFailureNotification::send(
                $exception,
                'Kembalikan Penugasan',
                AssessmentPeriod::query()->find($this->periodId),
            );
        }
    }

    protected function scopedAssignment(int $id): AssessmentPeriodAssignment
    {
        // Pembatasan yang menentukan adalah CAKUPAN HAK AKSES (scopeAssignments),
        // bukan kelas aktif. Kelas aktif hanya alat bantu tampilan; membuka satu
        // penugasan di luar kelas aktif tetap sah selama masih dalam cakupan.
        return $this->scopeAssignments(
            AssessmentPeriodAssignment::query()
                ->where('assessment_period_id', $this->periodId)
                ->whereHas('period', fn (Builder $query): Builder => $query->where('type', static::$assessmentType->value)),
        )
            ->findOrFail($id);
    }

    protected function selectedScopedAssignments()
    {
        $ids = collect($this->selectedAssignmentIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'assignments' => 'Pilih minimal satu penugasan.',
            ]);
        }

        // Aksi massal TIDAK dibatasi kelas aktif: admin harus bisa memverifikasi
        // atau mengembalikan penugasan lintas kelas dalam satu langkah. Yang
        // membatasi tetap cakupan hak akses (scopeAssignments).
        $assignments = $this->scopeAssignments(
            AssessmentPeriodAssignment::query()
                ->where('assessment_period_id', $this->periodId)
                ->whereHas('period', fn (Builder $query): Builder => $query->where('type', static::$assessmentType->value)),
        )
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        if ($assignments->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'assignments' => 'Sebagian pilihan tidak valid atau tidak berada dalam cakupan Anda.',
            ]);
        }

        return $assignments;
    }

    protected function assignmentStatus(AssessmentPeriodAssignment $assignment): AssignmentStatus
    {
        return $assignment->status instanceof AssignmentStatus
            ? $assignment->status
            : AssignmentStatus::from((string) $assignment->status);
    }

    protected function scopeAssignments(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return app(AssessmentStatusScope::class)->apply(
            $query,
            $user,
            (int) ($this->periodId ?? 0),
        );
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
            $periods->whereHas(
                'assignments',
                fn (Builder $assignments): Builder => $assignments->where('teacher_id', $user->guru_tendik_id),
            );

            $periods->orWhereHas(
                'homerooms',
                fn (Builder $homerooms): Builder => $homerooms->where('teacher_id', $user->guru_tendik_id),
            );
        });
    }

    protected function scoreReviewUrl(AssessmentPeriodAssignment $assignment): string
    {
        $page = static::$assessmentType === AssessmentType::ASTS
            ? AstsInputScores::class
            : AsasInputScores::class;

        return $page::getUrl([
            'period' => $assignment->assessment_period_id,
            'assignment' => $assignment->getKey(),
            'mode' => 'review',
        ]);
    }

    protected function normalizeActiveRombel(): void
    {
        $ids = collect($this->getClassTabs())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);

        // activeRombelId null berarti "Semua Kelas" — biarkan apa adanya.
        // Hanya nilai yang TIDAK VALID (kelas di luar cakupan) yang dibuang.
        if ($this->activeRombelId && ! $ids->contains($this->activeRombelId)) {
            $this->activeRombelId = null;
        }
    }

    /**
     * Kelas aktif bila dipilih & masih dalam cakupan; null = seluruh kelas.
     */
    protected function resolvedActiveRombelId(): ?int
    {
        $ids = collect($this->getClassTabs())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);

        return ($this->activeRombelId && $ids->contains($this->activeRombelId))
            ? $this->activeRombelId
            : null;
    }
}
