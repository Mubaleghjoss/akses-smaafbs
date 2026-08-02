<?php

namespace App\Console\Commands;

use App\Actions\Assessment\CancelOpenReportRevisionsAction;
use App\Actions\Assessment\SetPrimaryReportTemplateAction;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\ReportTemplate;
use App\Models\User;
use App\Support\Assessment\Reporting\CreateReportSnapshotsAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileAssessmentReportTemplates extends Command
{
    protected $signature = 'assessment:reconcile-report-templates
        {--apply : Terapkan perubahan; tanpa opsi ini hanya dry-run}
        {--actor= : ID admin/kurikulum untuk audit dan authorization}
        {--period= : ID periode yang revisi terbukanya akan dihentikan}
        {--cancel-open : Hentikan revisi prepared/running/failed pada periode terpilih}
        {--prepare-new : Siapkan revisi baru sesudah rekonsiliasi tanpa menjadwalkan job PDF}';

    protected $description = 'Audit dan rekonsiliasi template utama ASTS-ASAS tanpa menghapus snapshot historis';

    /** @var list<string> */
    private const SHARED_IDENTITY_KEYS = [
        'school_name',
        'school_address',
        'place',
        'principal_name',
        'principal_identifier',
        'homeroom_title',
        'watermark_enabled',
        'watermark_path',
        'watermark_opacity',
        'watermark_position',
        'watermark_width',
    ];

    public function handle(): int
    {
        $defaults = collect(InstallAssessmentDefaults::defaultTemplates());
        $missing = $defaults
            ->filter(fn (array $attributes): bool => ! ReportTemplate::query()
                ->where('code', $attributes['code'])
                ->where('version', $attributes['version'])
                ->exists())
            ->pluck('code')
            ->values();
        $astsPrimary = ReportTemplate::query()
            ->where('code', 'ASTS-SMAAFBS-3P')
            ->latest('version')
            ->first();
        $openRunCount = $this->openRunCount();

        $this->table(
            ['Pemeriksaan', 'Hasil'],
            [
                ['Template bawaan belum ada', $missing->isEmpty() ? 'Tidak ada' : $missing->implode(', ')],
                ['Sumber identitas ASTS tiga halaman', $astsPrimary?->name ?: 'Belum tersedia'],
                ['Revisi terbuka periode', (string) $openRunCount],
                ['Siapkan revisi baru', $this->option('prepare-new') ? 'Ya, tanpa antrean PDF' : 'Tidak'],
                ['Mode', $this->option('apply') ? 'APPLY' : 'DRY-RUN'],
            ],
        );

        if (! $this->option('apply')) {
            $this->components->info('Dry-run selesai. Tidak ada data yang diubah.');

            return self::SUCCESS;
        }

        $actorId = (int) $this->option('actor');
        $actor = $actorId > 0 ? User::query()->find($actorId) : null;
        if (! $actor) {
            $this->components->error('Opsi --actor wajib menunjuk akun admin/kurikulum yang valid saat --apply.');

            return self::FAILURE;
        }

        try {
            $preparedRevision = DB::transaction(function () use ($defaults, $actor): ?int {
                foreach ($defaults as $attributes) {
                    ReportTemplate::query()->firstOrCreate(
                        ['code' => $attributes['code'], 'version' => $attributes['version']],
                        $attributes,
                    );
                }

                $asts = ReportTemplate::query()
                    ->where('code', 'ASTS-SMAAFBS-3P')
                    ->latest('version')
                    ->lockForUpdate()
                    ->firstOrFail();
                $asas = ReportTemplate::query()
                    ->where('code', 'ASAS-SMAAFBS-3P')
                    ->latest('version')
                    ->lockForUpdate()
                    ->firstOrFail();
                $astsSettings = is_array($asts->settings) ? $asts->settings : [];
                $asasSettings = is_array($asas->settings) ? $asas->settings : [];

                foreach (self::SHARED_IDENTITY_KEYS as $key) {
                    if (filled(data_get($astsSettings, $key))) {
                        data_set($asasSettings, $key, data_get($astsSettings, $key));
                    }
                }
                $asas->forceFill(['settings' => $asasSettings])->save();

                app(SetPrimaryReportTemplateAction::class)->execute($actor, $asts);
                app(SetPrimaryReportTemplateAction::class)->execute($actor, $asas);

                if ($this->option('cancel-open')) {
                    $period = AssessmentPeriod::query()->findOrFail((int) $this->option('period'));
                    $periodTemplate = $period->type->value === 'asas' ? $asas : $asts;
                    app(CancelOpenReportRevisionsAction::class)->execute(
                        $actor,
                        $period,
                        $periodTemplate,
                        'Rekonsiliasi template utama; revisi uji lama digantikan sebelum pilot PDF.',
                    );
                }

                if (! $this->option('prepare-new')) {
                    return null;
                }

                $periodId = (int) $this->option('period');
                if ($periodId < 1) {
                    throw new \InvalidArgumentException('Opsi --period wajib diisi ketika memakai --prepare-new.');
                }

                $period = AssessmentPeriod::query()->findOrFail($periodId);
                $periodTemplate = $period->type->value === 'asas' ? $asas : $asts;
                $snapshots = app(CreateReportSnapshotsAction::class)->execute(
                    $period,
                    $periodTemplate,
                    (int) $actor->getKey(),
                    regenerate: true,
                    reason: 'Menyiapkan revisi baru setelah rekonsiliasi template utama.',
                );

                return (int) $snapshots->firstOrFail()->revision;
            }, 3);
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $message = 'Template direkonsiliasi. Snapshot/PDF historis tidak dihapus dan tidak ada job yang dijadwalkan.';
        if ($preparedRevision !== null) {
            $message .= " Revisi {$preparedRevision} telah disiapkan dan menunggu pemilihan kelas.";
        }
        $this->components->info($message);

        return self::SUCCESS;
    }

    private function openRunCount(): int
    {
        $periodId = (int) $this->option('period');
        if (! $this->option('cancel-open') || $periodId < 1) {
            return 0;
        }

        return \App\Models\Assessment\ReportGenerationRun::query()
            ->where('assessment_period_id', $periodId)
            ->whereIn('status', ['prepared', 'running', 'failed'])
            ->count();
    }
}
