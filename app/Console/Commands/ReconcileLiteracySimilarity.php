<?php

namespace App\Console\Commands;

use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiQuestion;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
use App\Support\Perpustakaan\LiterasiSimilarityAnalyzer;
use Illuminate\Console\Command;

class ReconcileLiteracySimilarity extends Command
{
    protected $signature = 'literacy:similarity-reconcile
        {--material= : Batasi ke ID materi tertentu}
        {--batch=25 : Jumlah respons per batch}
        {--dry-run : Hitung perubahan tanpa menyimpan}
        {--apply : Terapkan perbaikan}';

    protected $description = 'Ringkas indikasi kemiripan menjadi satu pembanding terkuat dan sesuaikan batas karakter kunci jawaban';

    public function handle(LiterasiSimilarityAnalyzer $analyzer): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Pilih salah satu: --dry-run atau --apply.');

            return self::INVALID;
        }

        $apply = (bool) $this->option('apply');
        $batch = min(100, max(1, (int) $this->option('batch')));
        $materialId = filled($this->option('material')) ? (int) $this->option('material') : null;

        if ($materialId && ! PerpustakaanLiterasiMaterial::query()->whereKey($materialId)->exists()) {
            $this->error('Materi #'.$materialId.' tidak ditemukan atau berada di Sampah.');

            return self::FAILURE;
        }

        $responseQuery = PerpustakaanLiterasiResponse::query()
            ->when($materialId, fn ($query) => $query->where('material_id', $materialId));
        $matchQuery = PerpustakaanLiterasiSimilarityMatch::query()
            ->when($materialId, fn ($query) => $query->where('material_id', $materialId));
        $questionQuery = PerpustakaanLiterasiQuestion::query()
            ->when($materialId, fn ($query) => $query->where('material_id', $materialId))
            ->whereNotNull('answer_key')
            ->where('answer_key', '!=', '');

        $before = (clone $matchQuery)->count();
        $responses = (clone $responseQuery)->count();
        $limitsToAdjust = 0;

        (clone $questionQuery)
            ->orderBy('id')
            ->chunkById($batch, function ($questions) use ($apply, &$limitsToAdjust): void {
                foreach ($questions as $question) {
                    $limits = PerpustakaanLiterasiQuestion::adjustedCharacterLimits(
                        $question->answer_key,
                        $question->min_characters,
                        $question->max_characters,
                    );

                    if (! $limits['adjusted']) {
                        continue;
                    }

                    $limitsToAdjust++;

                    if ($apply) {
                        $question->forceFill([
                            'min_characters' => $limits['min'],
                            'max_characters' => $limits['max'],
                        ])->save();
                    }
                }
            });

        $summary = [
            'answers' => 0,
            'indications' => 0,
            'candidates' => 0,
            'below_threshold' => 0,
            'answer_key_exclusions' => 0,
        ];

        (clone $responseQuery)
            ->with('answers.question')
            ->orderBy('id')
            ->chunkById($batch, function ($responseBatch) use ($analyzer, $apply, &$summary): void {
                foreach ($responseBatch as $response) {
                    $result = $apply
                        ? $analyzer->analyzeResponse($response)
                        : $analyzer->previewResponse($response);

                    foreach ($summary as $key => $value) {
                        $summary[$key] = $value + $result[$key];
                    }

                    if ($apply) {
                        $response->forceFill([
                            'similarity_analysis_status' => PerpustakaanLiterasiResponse::SIMILARITY_STATUS_COMPLETED,
                            'similarity_analyzed_at' => now(),
                            'similarity_analysis_error' => null,
                        ])->save();
                    }
                }
            });

        $after = $apply ? (clone $matchQuery)->count() : $summary['indications'];

        $this->newLine();
        $this->table(
            ['Mode', 'Materi', 'Respons', 'Indikasi lama', 'Perkiraan/hasil', 'Batas soal disesuaikan'],
            [[
                $apply ? 'APPLY' : 'DRY-RUN',
                $materialId ?: 'Semua',
                $responses,
                $before,
                $after,
                $limitsToAdjust,
            ]],
        );
        $this->table(
            ['Jawaban diperiksa', 'Pasangan kandidat', 'Di bawah ambang', 'Pengecualian kunci', 'Redundan berkurang'],
            [[
                $summary['answers'],
                $summary['candidates'],
                $summary['below_threshold'],
                $summary['answer_key_exclusions'],
                max(0, $before - $after),
            ]],
        );

        if (! $apply) {
            $this->info('Dry-run selesai. Belum ada data yang diubah. Tambahkan --apply setelah hasil diperiksa.');
        } else {
            $this->info('Rekonsiliasi selesai. Respons, isi jawaban, nilai, dan kode edit tidak diubah.');
        }

        return self::SUCCESS;
    }
}
