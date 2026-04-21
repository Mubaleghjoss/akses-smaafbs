<?php

namespace App\Jobs;

use App\Models\KomiteDocument;
use App\Support\GoogleDrive\GoogleDriveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncKomiteDocumentToGoogleDrive implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        public readonly int $documentId,
    ) {}

    public function handle(GoogleDriveService $googleDrive): void
    {
        $record = KomiteDocument::query()->find($this->documentId);

        if (! $record) {
            return;
        }

        $googleDrive->syncKomiteDocument($record);
    }
}
