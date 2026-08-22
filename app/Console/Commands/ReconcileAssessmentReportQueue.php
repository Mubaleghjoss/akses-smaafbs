<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Assessment\Reporting\StopAssessmentReportQueueAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileAssessmentReportQueue extends Command
{
    protected $signature = 'assessment:reports-reconcile-queue
        {--apply : Terapkan penghentian dan pembersihan queue assessment-reports}
        {--actor= : ID user admin/kurikulum untuk audit}
        {--reason=Rekonsiliasi antrean PDF lama setelah pemasangan pipeline per kelas : Alasan audit}';

    protected $description = 'Audit atau hentikan job PDF assessment lama tanpa menyentuh queue Literasi/default';

    public function handle(): int
    {
        $queue = (string) config('assessment.reports.queue', 'assessment-reports');
        $table = (string) config('queue.connections.database.table', 'jobs');
        $summary = [
            'queue' => $queue,
            'jobs' => DB::table($table)->where('queue', $queue)->count(),
            'pending_snapshots' => DB::table('assessment_report_snapshots')->where('generation_status', 'pending')->count(),
            'processing_snapshots' => DB::table('assessment_report_snapshots')->where('generation_status', 'processing')->count(),
            'pending_classes' => DB::table('assessment_class_report_artifacts')->where('generation_status', 'pending')->count(),
            'processing_classes' => DB::table('assessment_class_report_artifacts')->where('generation_status', 'processing')->count(),
        ];

        $this->table(array_keys($summary), [array_values($summary)]);
        $suggestedActor = User::query()
            ->get()
            ->first(fn (User $user): bool => $user->hasFullAdminAccess() || $user->can('penilaian.report.generate'));
        $this->line($suggestedActor
            ? "Aktor audit yang dapat dipakai: --actor={$suggestedActor->getKey()} ({$suggestedActor->name})"
            : 'Tidak ditemukan admin/kurikulum yang berhak menghentikan pipeline.');

        if (! $this->option('apply')) {
            $this->info('Dry-run selesai. Tidak ada data atau job yang diubah.');

            return self::SUCCESS;
        }

        $actor = User::query()->find((int) $this->option('actor'));
        if (! $actor) {
            $this->error('Gunakan --actor=ID user admin/kurikulum yang valid.');

            return self::FAILURE;
        }

        $result = app(StopAssessmentReportQueueAction::class)->execute(
            $actor,
            (string) $this->option('reason'),
        );
        $this->info(sprintf(
            'Selesai: %d job dihapus, %d run, %d snapshot, dan %d kelas dihentikan.',
            $result['jobs'],
            $result['runs'],
            $result['snapshots'],
            $result['classes'],
        ));
        $this->line('Queue Literasi/default tidak diubah.');

        return self::SUCCESS;
    }
}
