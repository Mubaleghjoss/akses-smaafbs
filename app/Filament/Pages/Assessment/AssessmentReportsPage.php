<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\ReportGenerationStatus;
use App\Filament\Pages\Assessment\Concerns\HasAssessmentTypeNavigation;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportGenerationRun;
use App\Models\Assessment\ReportShareLink;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use App\Models\User;
use App\Support\Assessment\Reporting\AssessmentReportShareService;
use App\Support\Assessment\Reporting\CreateReportSnapshotsAction;
use App\Support\Assessment\Reporting\RetryReportGenerationAction;
use App\Support\Assessment\Reporting\ScheduleReportClassesAction;
use App\Support\Assessment\Reporting\StopAssessmentReportQueueAction;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url as UrlAttribute;
use Throwable;

abstract class AssessmentReportsPage extends AssessmentPage
{
    use HasAssessmentTypeNavigation;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static string $assessmentPermission = 'penilaian.view';

    protected string $view = 'filament.pages.assessment.reports';

    protected static AssessmentType $assessmentType;

    #[UrlAttribute(as: 'period')]
    public ?int $periodId = null;

    #[UrlAttribute(as: 'template')]
    public ?int $templateId = null;

    public bool $regenerate = false;

    public string $regenerationReason = '';

    public int $shareExpiryDays = 1;

    public ?string $latestShareUrl = null;

    /** @var array<int, int|string> */
    public array $selectedClassIds = [];

    /** @var array<int, int|string> */
    public array $selectedShareSnapshotIds = [];

    /** @var array<int, string> */
    public array $latestShareLinks = [];

    public ?int $previewSnapshotId = null;

    public string $stopReason = '';

    public function mount(): void
    {
        $this->shareExpiryDays = AssessmentReportShareService::defaultExpiryDays();
        $periodIds = array_map('intval', array_keys($this->getPeriodOptions()));
        if (! $this->periodId || ! in_array($this->periodId, $periodIds, true)) {
            $this->periodId = $periodIds[0] ?? null;
        }
        $this->selectDefaultTemplate();
        $this->selectDefaultClassAndPreview();
    }

    public static function canAccess(): bool
    {
        if (! parent::canAccess()) {
            return false;
        }

        $user = auth()->user();

        return $user instanceof User
            && (
                $user->hasFullAdminAccess()
                || $user->can('penilaian.report.generate')
                || $user->can('penilaian.publish')
                || $user->can('penilaian.homeroom')
                || $user->hasRole('kepala_sekolah')
            );
    }

    public function getTitle(): string|Htmlable
    {
        return static::$assessmentType === AssessmentType::ASTS
            ? 'Cetak Rapor ASTS'
            : 'Cetak Rapor Semester';
    }

    public function canGenerateReports(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->hasFullAdminAccess() || $user->can('penilaian.report.generate'));
    }

    public function canPublishReports(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->hasFullAdminAccess() || $user->can('penilaian.publish'));
    }

    public function getPeriodOptions(): array
    {
        return $this->scopePeriods(AssessmentPeriod::query())
            ->where('type', static::$assessmentType->value)
            ->latest('id')
            ->pluck('name', 'id')
            ->all();
    }

    public function getTemplateOptions(): array
    {
        return ReportTemplate::query()
            ->where('type', static::$assessmentType->value)
            ->orderByDesc('is_active')
            ->orderByDesc('version')
            ->get()
            ->mapWithKeys(fn (ReportTemplate $template): array => [
                $template->getKey() => "{$template->name} · v{$template->version}".($template->is_active ? ' · aktif' : ''),
            ])
            ->all();
    }

    public function updatedPeriodId(): void
    {
        $this->latestShareUrl = null;
        $this->latestShareLinks = [];
        $this->selectedClassIds = [];
        $this->selectedShareSnapshotIds = [];
        $this->selectDefaultClassAndPreview();
    }

    public function updatedTemplateId(): void
    {
        $this->latestShareUrl = null;
        $this->latestShareLinks = [];
        $this->selectedShareSnapshotIds = [];
        $this->selectDefaultClassAndPreview();
    }

    public function getClassOptions(): array
    {
        $period = $this->selectedPeriod();

        return $period
            ? $period->periodRombels()->orderBy('rombel_name_snapshot')->pluck('rombel_name_snapshot', 'id')->all()
            : [];
    }

    public function getPreviewOptions(): array
    {
        if (! $this->periodId || ! $this->templateId) {
            return [];
        }

        $latestRevision = (int) $this->snapshotQuery()->max('revision');

        return $latestRevision > 0
            ? $this->snapshotQuery()
                ->where('revision', $latestRevision)
                ->with('student')
                ->get()
                ->sortBy(fn (ReportSnapshot $snapshot): string => $snapshot->student?->rombel_name_snapshot.'|'.$snapshot->student?->student_name_snapshot)
                ->mapWithKeys(fn (ReportSnapshot $snapshot): array => [
                    $snapshot->getKey() => "{$snapshot->student?->rombel_name_snapshot} · {$snapshot->student?->student_name_snapshot}",
                ])
                ->all()
            : [];
    }

    public function previewUrl(): ?string
    {
        return $this->previewSnapshotId
            ? route('assessment.reports.preview', ['reportSnapshot' => $this->previewSnapshotId])
            : null;
    }

    public function generateReports(): void
    {
        $this->authorizeAssessment('penilaian.report.generate');
        $period = $this->selectedPeriod();
        $template = $this->selectedTemplate();

        if (! $period || ! $template) {
            Notification::make()->title('Pilih periode dan template')->warning()->send();

            return;
        }

        try {
            $snapshots = app(CreateReportSnapshotsAction::class)->execute(
                $period,
                $template,
                (int) auth()->id(),
                $this->regenerate,
                $this->regenerate ? $this->regenerationReason : null,
            );
            $run = app(ScheduleReportClassesAction::class)->execute(
                auth()->user(),
                $period,
                $template,
                $this->selectedClassIds,
            );
            $this->regenerate = false;
            $this->regenerationReason = '';
            $this->selectDefaultClassAndPreview();
            Notification::make()
                ->title('Pipeline kelas masuk antrean')
                ->body(count($this->selectedClassIds).' kelas diproses bertahap dari '.$snapshots->count().' snapshot. Run revisi '.$run->revision.' tidak membuat job per siswa.')
                ->success()
                ->duration(12000)
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Rapor belum dapat dibuat')->body($exception->getMessage())->danger()->duration(15000)->send();
        }
    }

    public function selectAllClasses(): void
    {
        $this->selectedClassIds = array_map('intval', array_keys($this->getClassOptions()));
    }

    public function clearClassSelection(): void
    {
        $this->selectedClassIds = [];
    }

    public function stopAllReportJobs(): void
    {
        $this->authorizeAssessment('penilaian.report.generate');

        try {
            $result = app(StopAssessmentReportQueueAction::class)->execute(auth()->user(), $this->stopReason);
            $this->stopReason = '';
            $this->dispatch('close-modal', id: 'assessment-stop-reports-modal');
            Notification::make()
                ->title('Seluruh antrean PDF dihentikan')
                ->body("{$result['jobs']} job assessment-reports dihapus; {$result['snapshots']} PDF siswa dan {$result['classes']} kelas ditandai dihentikan. Queue Literasi/default tidak disentuh.")
                ->warning()
                ->duration(15000)
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Antrean belum dihentikan')->body($exception->getMessage())->danger()->send();
        }
    }

    public function retrySnapshot(int $snapshotId): void
    {
        $this->authorizeAssessment('penilaian.report.generate');
        $snapshot = $this->snapshotQuery()->findOrFail($snapshotId);
        app(RetryReportGenerationAction::class)->retrySnapshot(auth()->user(), $snapshot);
        Notification::make()->title('Rapor siswa dijadwalkan ulang')->success()->send();
    }

    public function retryClass(int $artifactId): void
    {
        $this->authorizeAssessment('penilaian.report.generate');
        $artifact = $this->artifactQuery()->findOrFail($artifactId);
        app(RetryReportGenerationAction::class)->retryClass(auth()->user(), $artifact);
        Notification::make()->title('PDF kelas dijadwalkan ulang')->success()->send();
    }

    public function issueShareLink(int $snapshotId): void
    {
        $this->authorizeAssessment('penilaian.publish');
        $snapshot = $this->snapshotQuery()->findOrFail($snapshotId);

        try {
            $issued = app(AssessmentReportShareService::class)->issue(
                $snapshot,
                (int) auth()->id(),
                $this->shareExpiryDays,
            );
            $this->latestShareUrl = route('assessment.reports.shared.download', ['token' => $issued['token']]);
            Notification::make()
                ->title('Tautan sementara dibuat')
                ->body('Salin tautan yang muncul di atas daftar. Token hanya ditampilkan kali ini.')
                ->success()
                ->duration(12000)
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Tautan tidak dapat dibuat')->body($exception->getMessage())->danger()->send();
        }
    }

    public function selectAllShareableSnapshots(): void
    {
        $this->selectedShareSnapshotIds = collect($this->getSnapshotRows())
            ->filter(fn (array $row): bool => $row['status'] === 'completed')
            ->take(50)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function clearShareSelection(): void
    {
        $this->selectedShareSnapshotIds = [];
    }

    public function issueSelectedShareLinks(): void
    {
        $this->authorizeAssessment('penilaian.publish');
        $ids = collect($this->selectedShareSnapshotIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty() || $ids->count() > 50) {
            Notification::make()
                ->title('Pilih 1 sampai 50 siswa')
                ->body('Tautan dibuat langsung tanpa queue agar tetap ringan.')
                ->warning()
                ->send();

            return;
        }

        $snapshots = $this->snapshotQuery()
            ->whereIn('id', $ids)
            ->where('generation_status', 'completed')
            ->with('student')
            ->get()
            ->sortBy(fn (ReportSnapshot $snapshot): string => mb_strtolower(
                (string) $snapshot->student?->rombel_name_snapshot.'|'.(string) $snapshot->student?->student_name_snapshot,
            ))
            ->values();

        if ($snapshots->count() !== $ids->count()) {
            Notification::make()->title('Sebagian PDF belum siap atau di luar cakupan')->danger()->send();

            return;
        }

        try {
            $links = DB::transaction(function () use ($snapshots): array {
                $issuedLinks = [];
                foreach ($snapshots as $snapshot) {
                    $issued = app(AssessmentReportShareService::class)->issue(
                        $snapshot,
                        (int) auth()->id(),
                        $this->shareExpiryDays,
                    );
                    $issuedLinks[] = "{$snapshot->student?->student_name_snapshot} · {$snapshot->student?->rombel_name_snapshot}\n"
                        .route('assessment.reports.shared.download', ['token' => $issued['token']]);
                }

                return $issuedLinks;
            }, 3);
            $this->latestShareLinks = $links;
            $this->latestShareUrl = null;
            $this->selectedShareSnapshotIds = [];
            Notification::make()->title(count($links).' tautan sementara dibuat')->success()->duration(12000)->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Tautan belum dapat dibuat')->body($exception->getMessage())->danger()->send();
        }
    }

    public function revokeShareLinks(int $snapshotId): void
    {
        $this->authorizeAssessment('penilaian.publish');
        $snapshot = $this->snapshotQuery()->findOrFail($snapshotId);
        $count = app(AssessmentReportShareService::class)->revokeForSnapshot(
            $snapshot,
            (int) auth()->id(),
            'Dicabut dari panel Penilaian',
        );
        Notification::make()->title("{$count} tautan aktif dicabut")->success()->send();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSnapshotRows(): array
    {
        $latestRevision = (int) $this->snapshotQuery()->max('revision');
        if ($latestRevision < 1) {
            return [];
        }

        return $this->snapshotQuery()
            ->where('revision', $latestRevision)
            ->with(['student', 'shareLinks'])
            ->get()
            ->sortBy(fn (ReportSnapshot $snapshot): string => $snapshot->student?->rombel_name_snapshot.'|'.$snapshot->student?->student_name_snapshot)
            ->map(function (ReportSnapshot $snapshot): array {
                $status = $snapshot->generation_status instanceof ReportGenerationStatus
                    ? $snapshot->generation_status
                    : ReportGenerationStatus::from((string) $snapshot->generation_status);
                $activeLinks = $snapshot->shareLinks
                    ->filter(fn (ReportShareLink $link): bool => $link->revoked_at === null && $link->expires_at?->isFuture())
                    ->count();

                return [
                    'id' => (int) $snapshot->getKey(),
                    'student' => (string) $snapshot->student?->student_name_snapshot,
                    'rombel' => (string) $snapshot->student?->rombel_name_snapshot,
                    'revision' => (int) $snapshot->revision,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'generated_at' => $snapshot->generated_at?->format('d/m/Y H:i'),
                    'error' => $snapshot->error_message,
                    'active_links' => $activeLinks,
                    'download_url' => $status === ReportGenerationStatus::COMPLETED
                        ? route('assessment.reports.snapshot.download', $snapshot)
                        : null,
                    'preview_url' => route('assessment.reports.preview', ['reportSnapshot' => $snapshot]),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClassRows(): array
    {
        $latestRevision = (int) $this->artifactQuery()->max('revision');
        if ($latestRevision < 1) {
            return [];
        }

        return $this->artifactQuery()
            ->where('revision', $latestRevision)
            ->with('periodRombel')
            ->get()
            ->sortBy('periodRombel.rombel_name_snapshot')
            ->map(function (ClassReportArtifact $artifact): array {
                $status = $artifact->generation_status instanceof ReportGenerationStatus
                    ? $artifact->generation_status
                    : ReportGenerationStatus::from((string) $artifact->generation_status);

                $studentQuery = ReportSnapshot::query()
                    ->where('assessment_period_id', $artifact->assessment_period_id)
                    ->where('assessment_report_template_id', $artifact->assessment_report_template_id)
                    ->where('revision', $artifact->revision)
                    ->whereHas('student', fn ($students) => $students
                        ->where('assessment_period_rombel_id', $artifact->assessment_period_rombel_id)
                        ->where('is_active', true));

                return [
                    'id' => (int) $artifact->getKey(),
                    'rombel' => (string) $artifact->periodRombel?->rombel_name_snapshot,
                    'revision' => (int) $artifact->revision,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'generated_at' => $artifact->generated_at?->format('d/m/Y H:i'),
                    'error' => $artifact->error_message,
                    'completed_students' => (clone $studentQuery)->where('generation_status', 'completed')->count(),
                    'student_count' => $studentQuery->count(),
                    'download_url' => $status === ReportGenerationStatus::COMPLETED
                        ? route('assessment.reports.class.download', $artifact)
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    protected function selectDefaultTemplate(): void
    {
        $ids = array_map('intval', array_keys($this->getTemplateOptions()));
        if (! $this->templateId || ! in_array($this->templateId, $ids, true)) {
            $this->templateId = $ids[0] ?? null;
        }
    }

    protected function selectDefaultClassAndPreview(): void
    {
        $classIds = array_map('intval', array_keys($this->getClassOptions()));
        if ($this->selectedClassIds === [] && $classIds !== []) {
            $this->selectedClassIds = [$classIds[0]];
        }

        $previewIds = array_map('intval', array_keys($this->getPreviewOptions()));
        if (! $this->previewSnapshotId || ! in_array($this->previewSnapshotId, $previewIds, true)) {
            $this->previewSnapshotId = $previewIds[0] ?? null;
        }
    }

    public function getGenerationRun(): ?ReportGenerationRun
    {
        if (! $this->periodId || ! $this->templateId) {
            return null;
        }

        return ReportGenerationRun::query()
            ->where('assessment_period_id', $this->periodId)
            ->where('assessment_report_template_id', $this->templateId)
            ->latest('revision')
            ->first();
    }

    public function selectedPeriodIsPublished(): bool
    {
        $period = $this->selectedPeriod();
        $status = $period?->status;
        $status = $status instanceof \BackedEnum ? $status->value : (string) $status;

        return $status === 'published';
    }

    protected function selectedPeriod(): ?AssessmentPeriod
    {
        return $this->scopePeriods(AssessmentPeriod::query())
            ->where('type', static::$assessmentType->value)
            ->find($this->periodId);
    }

    protected function selectedTemplate(): ?ReportTemplate
    {
        return ReportTemplate::query()
            ->where('type', static::$assessmentType->value)
            ->find($this->templateId);
    }

    protected function snapshotQuery()
    {
        return $this->scopeSnapshots(ReportSnapshot::query())
            ->where('assessment_period_id', $this->periodId)
            ->where('assessment_report_template_id', $this->templateId);
    }

    protected function artifactQuery()
    {
        return $this->scopeArtifacts(ClassReportArtifact::query())
            ->where('assessment_period_id', $this->periodId)
            ->where('assessment_report_template_id', $this->templateId);
    }

    protected function scopePeriods($query)
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasFullAdminAccess()
            || $user->can('penilaian.report.generate')
            || $user->can('penilaian.publish')
            || $user->hasRole('kepala_sekolah')) {
            return $query;
        }

        return $user->guru_tendik_id
            ? $query->whereHas('homerooms', fn ($homerooms) => $homerooms->where('teacher_id', $user->guru_tendik_id))
            : $query->whereRaw('1 = 0');
    }

    protected function scopeSnapshots($query)
    {
        $rombelIds = $this->visiblePeriodRombelIds();

        return $rombelIds === null
            ? $query
            : $query->whereHas('student', fn ($students) => $students->whereIn('assessment_period_rombel_id', $rombelIds));
    }

    protected function scopeArtifacts($query)
    {
        $rombelIds = $this->visiblePeriodRombelIds();

        return $rombelIds === null
            ? $query
            : $query->whereIn('assessment_period_rombel_id', $rombelIds);
    }

    /**
     * Null berarti seluruh kelas boleh terlihat.
     *
     * @return array<int, int>|null
     */
    protected function visiblePeriodRombelIds(): ?array
    {
        $user = auth()->user();

        if ($user instanceof User
            && (
                $user->hasFullAdminAccess()
                || $user->can('penilaian.report.generate')
                || $user->can('penilaian.publish')
                || $user->hasRole('kepala_sekolah')
            )) {
            return null;
        }

        if (! $user instanceof User || ! $user->guru_tendik_id) {
            return [];
        }

        return AssessmentPeriodHomeroom::query()
            ->where('assessment_period_id', $this->periodId)
            ->where('teacher_id', $user->guru_tendik_id)
            ->pluck('assessment_period_rombel_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
