<?php

namespace App\Jobs;

use App\Models\BerkasGuru;
use App\Support\GoogleDrive\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncBerkasGuruToGoogleDrive implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        public readonly int $recordId,
    ) {}

    public function handle(GoogleDriveService $googleDrive): void
    {
        $record = BerkasGuru::query()->find($this->recordId);

        if (! $record) {
            return;
        }

        $googleDrive->syncBerkasGuru($record);
    }
}
