<?php

namespace App\Jobs;

use App\Models\ProfilSekolah;
use App\Support\GoogleDrive\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncProfilSekolahToGoogleDrive implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        public readonly int $profilSekolahId,
    ) {}

    public function handle(GoogleDriveService $googleDrive): void
    {
        $record = ProfilSekolah::query()->find($this->profilSekolahId);

        if (! $record) {
            return;
        }

        $googleDrive->syncProfilSekolah($record);
    }
}
