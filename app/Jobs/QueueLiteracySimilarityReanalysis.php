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

    public function __construct(
        public readonly ?int $materialId = null,
        public readonly ?int $afterResponseId = null,
    ) {
        $this->onQueue((string) config('literacy.similarity_queue', 'literacy-analysis'));
    }

    public function handle(): void
    {
        $afterResponse = $this->afterResponseId
            ? PerpustakaanLiterasiResponse::query()->find($this->afterResponseId)
            : null;

        if ($this->afterResponseId && ! $afterResponse) {
            return;
        }

        PerpustakaanLiterasiResponse::query()
            ->when($this->materialId, fn ($query) => $query->where('material_id', $this->materialId))
            ->when($afterResponse, function ($query) use ($afterResponse): void {
                $submittedAt = $afterResponse->submitted_at;

                $query->where(function ($later) use ($afterResponse, $submittedAt): void {
                    if ($submittedAt) {
                        $later
                            ->where('submitted_at', '>', $submittedAt)
                            ->orWhere(function ($sameTime) use ($afterResponse, $submittedAt): void {
                                $sameTime
                                    ->where('submitted_at', $submittedAt)
                                    ->where('id', '>', $afterResponse->getKey());
                            });

                        return;
                    }

                    $later->where('id', '>', $afterResponse->getKey());
                });
            })
            ->select(['id', 'similarity_analysis_version'])
            ->chunkById(100, function ($responses): void {
                foreach ($responses as $response) {
                    AnalyzeLiteracyResponseSimilarity::queueFor($response);
                }
            });
    }
}
