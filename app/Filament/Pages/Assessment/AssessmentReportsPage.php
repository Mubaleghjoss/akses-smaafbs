<?php

namespace App\Filament\Pages\Assessment;

use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\ReportGenerationStatus;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportShareLink;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use App\Models\User;
use App\Support\Assessment\Reporting\AssessmentReportShareService;
use App\Support\Assessment\Reporting\CreateReportSnapshotsAction;
use App\Support\Assessment\Reporting\RetryReportGenerationAction;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url as UrlAttribute;
use Throwable;

abstract class AssessmentReportsPage extends AssessmentPage
{
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

    public function mount(): void
    {
        $this->shareExpiryDays = AssessmentReportShareService::defaultExpiryDays();
        $periodIds = array_map('intval', array_keys($this->getPeriodOptions()));
        if (! $this->periodId || ! in_array($this->periodId, $periodIds, true)) {
            $this->periodId = $periodIds[0] ?? null;
        }
        $this->selectDefaultTemplate();
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

    public function getSubheading(): string|Htmlable|null
    {
        return 'PDF dibuat dari snapshot immutable dan disimpan privat. PDF kelas hanya tersedia di panel.';
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
    }

    public function updatedTemplateId(): void
    {
        $this->latestShareUrl = null;
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
            $this->regenerate = false;
            $this->regenerationReason = '';
            Notification::make()
                ->title('Pembuatan rapor masuk antrean')
                ->body($snapshots->count().' rapor siswa dan PDF kelas akan diproses bertahap tanpa membebani submit Literasi.')
                ->success()
                ->duration(12000)
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Rapor belum dapat dibuat')->body($exception->getMessage())->danger()->duration(15000)->send();
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

                return [
                    'id' => (int) $artifact->getKey(),
                    'rombel' => (string) $artifact->periodRombel?->rombel_name_snapshot,
                    'revision' => (int) $artifact->revision,
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'generated_at' => $artifact->generated_at?->format('d/m/Y H:i'),
                    'error' => $artifact->error_message,
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
