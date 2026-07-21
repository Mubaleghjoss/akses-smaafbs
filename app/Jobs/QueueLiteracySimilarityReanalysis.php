<?php

namespace App\Jobs;

use App\Models\PerpustakaanLiterasiResponse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class QueueLiteracySimilarityReanalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly ?int $materialId = null)
    {
        $this->onQueue((string) config('literacy.similarity_queue', 'literacy-analysis'));
    }

    public function handle(): void
    {
        PerpustakaanLiterasiResponse::query()
            ->when($this->materialId, fn ($query) => $query->where('material_id', $this->materialId))
            ->select(['id', 'similarity_analysis_version'])
            ->chunkById(100, function ($responses): void {
                foreach ($responses as $response) {
                    AnalyzeLiteracyResponseSimilarity::queueFor($response);
                }
            });
    }
}
