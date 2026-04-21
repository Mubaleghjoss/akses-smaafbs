<?php

namespace App\Jobs;

use App\Models\BerkasSiswa;
use App\Support\GoogleDrive\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncBerkasSiswaToGoogleDrive implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        public readonly int $recordId,
    ) {}

    public function handle(GoogleDriveService $googleDrive): void
    {
        $record = BerkasSiswa::query()->find($this->recordId);

        if (! $record) {
            return;
        }

        $googleDrive->syncBerkasSiswa($record);
    }
}
