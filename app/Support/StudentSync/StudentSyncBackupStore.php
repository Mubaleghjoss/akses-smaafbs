<?php

namespace App\Support\StudentSync;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class StudentSyncBackupStore
{
    /** @param array<string, mixed> $snapshot */
    public function write(string $runId, array $snapshot): string
    {
        $path = 'student-sync/backups/'.$runId.'.json.enc';
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encrypted = Crypt::encryptString($json);

        if (! Storage::disk('local')->put($path, $encrypted)) {
            throw new RuntimeException('Student sync backup could not be written.');
        }

        return $path;
    }
}
