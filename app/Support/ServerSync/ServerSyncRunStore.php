<?php

namespace App\Support\ServerSync;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Carbon;

class ServerSyncRunStore
{
    /**
     * @return array<string, mixed>
     */
    public function queue(string $runId, string $operation): array
    {
        $run = [
            'id' => $runId,
            'operation' => $operation,
            'status' => 'queued',
            'started_at' => null,
            'finished_at' => null,
            'duration_seconds' => null,
            'logs' => [[
                'time' => now()->format('H:i:s'),
                'level' => 'info',
                'message' => $this->operationLabel($operation).' masuk antrean.',
            ]],
            'result' => null,
        ];

        $this->write($runId, $run);
        $this->writeJson($this->requestPath($runId), [
            'id' => $runId,
            'operation' => $operation,
        ]);

        return $run;
    }

    public function markRunning(string $runId): void
    {
        $this->update($runId, function (array $run): array {
            $run['status'] = 'running';
            $run['started_at'] = now()->toIso8601String();

            return $run;
        });
    }

    public function append(string $runId, string $level, string $message): void
    {
        $message = trim($message);

        if ($message === '') {
            return;
        }

        $this->update($runId, function (array $run) use ($level, $message): array {
            $logs = (array) ($run['logs'] ?? []);
            $logs[] = [
                'time' => now()->format('H:i:s'),
                'level' => $level,
                'message' => $message,
            ];
            $run['logs'] = array_slice($logs, -120);

            return $run;
        });
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    public function finish(string $runId, string $status, ?array $result = null): void
    {
        $this->update($runId, function (array $run) use ($status, $result): array {
            $run['status'] = $status;
            $run['finished_at'] = now()->toIso8601String();
            $run['result'] = $result;

            if (filled($run['started_at'] ?? null)) {
                $run['duration_seconds'] = round(
                    now()->diffInMilliseconds(Carbon::parse($run['started_at']), absolute: true) / 1000,
                    2,
                );
            }

            return $run;
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $runId): ?array
    {
        $path = $this->runPath($runId);

        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode(File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        $paths = File::glob($this->runsPath().'/*.json');

        usort(
            $paths,
            static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left),
        );

        foreach ($paths as $path) {
            $decoded = json_decode(File::get($path), true);

            if (is_array($decoded) && filled($decoded['id'] ?? null)) {
                return $decoded;
            }
        }

        return null;
    }

    public function requestPath(string $runId): string
    {
        return $this->rootPath()."/requests/{$runId}.json";
    }

    protected function runPath(string $runId): string
    {
        return $this->runsPath()."/{$runId}.json";
    }

    protected function runsPath(): string
    {
        return $this->rootPath().'/runs';
    }

    protected function rootPath(): string
    {
        if (app()->runningUnitTests()) {
            return storage_path('framework/testing/server-sync');
        }

        return storage_path('app/server-sync');
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $callback
     */
    protected function update(string $runId, callable $callback): void
    {
        $run = $this->get($runId) ?? [
            'id' => $runId,
            'operation' => 'unknown',
            'status' => 'queued',
            'logs' => [],
        ];

        $this->write($runId, $callback($run));
    }

    /**
     * @param  array<string, mixed>  $run
     */
    protected function write(string $runId, array $run): void
    {
        $this->writeJson($this->runPath($runId), $run);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function writeJson(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            true,
        );
    }

    protected function operationLabel(string $operation): string
    {
        return $operation === 'pull' ? 'Tarik data server' : 'Uji koneksi server';
    }
}
