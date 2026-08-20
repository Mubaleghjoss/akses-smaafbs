<?php

namespace App\Support\StudentSync;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class StudentSyncBackupStore
{
    public function pathForRun(string $runId): string
    {
        $path = 'student-sync/backups/'.$runId.'.json.enc';
        $this->assertBackupPath($path);

        return $path;
    }

    /** @param array<string, mixed> $snapshot */
    public function write(string $runId, array $snapshot): string
    {
        $path = $this->pathForRun($runId);
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encrypted = Crypt::encryptString($json);

        if (! Storage::disk('local')->put($path, $encrypted)) {
            throw new RuntimeException('Student sync backup could not be written.');
        }

        return $path;
    }

    private function assertBackupPath(string $path): void
    {
        if (preg_match('/\Astudent-sync\/backups\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.json\.enc\z/i', $path) !== 1) {
            throw new InvalidArgumentException('Invalid student sync backup path.');
        }
    }
}
