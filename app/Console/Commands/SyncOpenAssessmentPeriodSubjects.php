<?php

namespace App\Console\Commands;

use App\Actions\Assessment\SyncOpenPeriodSubjectsAction;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\Subject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class SyncOpenAssessmentPeriodSubjects extends Command
{
    protected $signature = 'assessment:sync-open-period-subjects
        {period : ID periode Terbuka}
        {--all : Sinkronkan seluruh mapel aktif}
        {--subject=* : ID atau kode mapel tertentu; dapat diulang}
        {--source-scheme= : ID skema aktif yang disalin menjadi default periode}
        {--actor= : ID akun pelaksana, wajib pada mode apply}
        {--apply : Terapkan setelah preview bersih}';

    protected $description = 'Preview atau sinkronkan plotting mapel aktif ke periode Terbuka secara aditif.';

    public function handle(SyncOpenPeriodSubjectsAction $sync): int
    {
        $period = AssessmentPeriod::query()->find((int) $this->argument('period'));
        if (! $period) {
            $this->error('Periode tidak ditemukan.');

            return self::FAILURE;
        }

        if (! $this->option('all') && count((array) $this->option('subject')) === 0) {
            $this->error('Gunakan --all atau minimal satu --subject=ID/KODE.');

            return self::FAILURE;
        }

        $subjectIds = $this->resolveSubjectIds((bool) $this->option('all'), (array) $this->option('subject'));
        if ($subjectIds === null) {
            return self::FAILURE;
        }

        $sourceSchemeId = filled($this->option('source-scheme')) ? (int) $this->option('source-scheme') : null;

        try {
            $preview = $sync->preview($period, $subjectIds, $sourceSchemeId);
        } catch (Throwable $exception) {
            return $this->renderFailure($exception);
        }

        $this->renderSummary($preview, 'PREVIEW');
        if (! $this->option('apply')) {
            $this->info('Mode preview: database belum diubah. Tambahkan --apply --actor=ID setelah semua hitungan dan validasi sesuai.');

            return self::SUCCESS;
        }

        $actor = User::query()->find((int) $this->option('actor'));
        if (! $actor) {
            $this->error('Mode apply mewajibkan --actor=ID akun yang berwenang.');

            return self::FAILURE;
        }

        try {
            $summary = $sync->execute($actor, $period->fresh(), $subjectIds, $sourceSchemeId);
        } catch (Throwable $exception) {
            return $this->renderFailure($exception);
        }

        $this->renderSummary($summary, 'APPLY');
        $this->info('Selesai. Tidak ada assignment lama yang dihapus dan snapshot/PDF historis tidak diubah.');

        return self::SUCCESS;
    }

    /** @param array<int, string> $subjects @return array<int, int>|null */
    private function resolveSubjectIds(bool $all, array $subjects): ?array
    {
        if ($all) {
            return Subject::query()->where('is_active', true)->orderBy('sort_order')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        $ids = [];
        foreach ($subjects as $value) {
            $subject = Subject::query()
                ->where('is_active', true)
                ->where(function ($query) use ($value): void {
                    if (ctype_digit((string) $value)) {
                        $query->whereKey((int) $value)->orWhere('code', (string) $value);
                    } else {
                        $query->where('code', (string) $value);
                    }
                })
                ->first();
            if (! $subject) {
                $this->error("Mapel aktif '{$value}' tidak ditemukan.");

                return null;
            }
            $ids[] = (int) $subject->getKey();
        }

        return array_values(array_unique($ids));
    }

    /** @param array<string, mixed> $summary */
    private function renderSummary(array $summary, string $mode): void
    {
        $this->table(['Mode', 'Periode', 'Mapel', 'Kelas', 'Plotting', 'Dibuat', 'Diperbarui', 'Tetap', 'Diproteksi', 'Lama dipertahankan', 'Skema default'], [[
            $mode,
            $summary['period_name'],
            $summary['subject_count'],
            $summary['class_count'],
            $summary['plotting_count'],
            $summary['created'],
            $summary['updated'],
            $summary['unchanged'],
            $summary['protected'] ?? 0,
            $summary['retained'],
            ($summary['default_scheme_created'] ?? false) ? 'akan dibuat/dibuat' : 'sudah tersedia',
        ]]);
        $this->line('Skema sumber ID: '.($summary['source_scheme_id'] ?? $summary['source_scheme']?->getKey()));
    }

    private function renderFailure(Throwable $exception): int
    {
        if ($exception instanceof ValidationException) {
            foreach (collect($exception->errors())->flatten() as $message) {
                $this->error((string) $message);
            }
        } else {
            report($exception);
            $this->error($exception->getMessage() ?: 'Sinkronisasi gagal. Detail teknis sudah dicatat.');
        }

        return self::FAILURE;
    }
}
