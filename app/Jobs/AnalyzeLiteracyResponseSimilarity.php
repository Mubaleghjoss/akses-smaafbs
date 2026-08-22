<?php

namespace App\Jobs;

use App\Models\PerpustakaanLiterasiResponse;
use App\Support\Perpustakaan\LiterasiSimilarityAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class AnalyzeLiteracyResponseSimilarity implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $responseId,
        public readonly int $requestedVersion,
    ) {
        $this->onQueue((string) config('literacy.similarity_queue', 'literacy-analysis'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('literacy-similarity-'.$this->responseId))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    public function handle(LiterasiSimilarityAnalyzer $analyzer): void
    {
        $response = PerpustakaanLiterasiResponse::query()->find($this->responseId);

        if (! $response) {
            return;
        }

        $targetVersion = max($this->requestedVersion, (int) $response->similarity_analysis_version);

        $response->forceFill([
            'similarity_analysis_status' => PerpustakaanLiterasiResponse::SIMILARITY_STATUS_PROCESSING,
            'similarity_analysis_error' => null,
        ])->save();

        $analyzer->analyzeResponse($response->fresh(['answers.question']));
        $response->refresh();

        if ((int) $response->similarity_analysis_version !== $targetVersion) {
            static::dispatch(
                $response->getKey(),
                (int) $response->similarity_analysis_version,
            )->afterCommit();

            return;
        }

        $response->forceFill([
            'similarity_analysis_status' => PerpustakaanLiterasiResponse::SIMILARITY_STATUS_COMPLETED,
            'similarity_analyzed_at' => now(),
            'similarity_analysis_error' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $response = PerpustakaanLiterasiResponse::query()->find($this->responseId);

        if (! $response) {
            return;
        }

        $response->forceFill([
            'similarity_analysis_status' => PerpustakaanLiterasiResponse::SIMILARITY_STATUS_FAILED,
            'similarity_analysis_error' => mb_substr((string) ($exception?->getMessage() ?: 'Analisis gagal tanpa pesan error.'), 0, 1000),
        ])->save();
    }

    public static function queueFor(PerpustakaanLiterasiResponse $response): void
    {
        $version = (int) $response->similarity_analysis_version + 1;

        $response->forceFill([
            'similarity_analysis_version' => $version,
            'similarity_analysis_status' => PerpustakaanLiterasiResponse::SIMILARITY_STATUS_PENDING,
            'similarity_analysis_queued_at' => now(),
            'similarity_analysis_error' => null,
        ])->save();

        static::dispatch($response->getKey(), $version)->afterCommit();
    }
}
