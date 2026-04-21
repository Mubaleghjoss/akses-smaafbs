<?php

namespace App\Jobs;

use App\Models\Prestasi;
use App\Support\GoogleDrive\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncPrestasiToGoogleDrive implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        public readonly int $prestasiId,
    ) {}

    public function handle(GoogleDriveService $googleDrive): void
    {
        $record = Prestasi::query()->find($this->prestasiId);

        if (! $record) {
            return;
        }

        $googleDrive->syncPrestasi($record);
    }
}
