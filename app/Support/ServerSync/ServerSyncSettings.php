<?php

namespace App\Support\ServerSync;

use Illuminate\Support\Facades\File;
use RuntimeException;

class ServerSyncSettings
{
    protected const SECTION_MARKER = '# Sinkron data API - dikelola dari halaman admin';

    /**
     * @return array<string, mixed>
     */
    public function formState(): array
    {
        return [
            'server_sync_api_enabled' => (bool) config('server_sync.api.enabled', true),
            'server_sync_domain' => config('server_sync.api.domain', 'https://app.smaafbs.sch.id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveFromForm(array $data): void
    {
        $values = $this->envValuesFromForm($data);

        $this->writeEnv($values);
        $this->applyRuntimeConfig($values);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function envValuesFromForm(array $data): array
    {
        return [
            'SERVER_SYNC_API_ENABLED' => (bool) ($data['server_sync_api_enabled'] ?? true),
            'SERVER_SYNC_DOMAIN' => $this->normalizeDomain($data['server_sync_domain'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function writeEnv(array $values): void
    {
        $path = (string) config('server_sync.env_path', base_path('.env'));
        $contents = File::exists($path) ? File::get($path) : '';

        if (! str_contains($contents, self::SECTION_MARKER)) {
            $contents = rtrim($contents).PHP_EOL.PHP_EOL.self::SECTION_MARKER.PHP_EOL;
        }

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->formatEnvValue($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents) === 1) {
                $contents = preg_replace_callback($pattern, fn (): string => $line, $contents) ?? $contents;

                continue;
            }

            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }

        File::ensureDirectoryExists(dirname($path));

        if (File::put($path, rtrim($contents).PHP_EOL) === false) {
            throw new RuntimeException('Tidak bisa menulis file .env. Pastikan permission file .env bisa ditulis oleh aplikasi.');
        }
    }

    protected function formatEnvValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = $this->nullableString($value);

        if ($value === null) {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_.:@\/,-]+$/', $value) === 1) {
            return $value;
        }

        return '"'.str_replace(
            ["\\", '"', "\r", "\n"],
            ["\\\\", '\"', '\r', '\n'],
            $value,
        ).'"';
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function applyRuntimeConfig(array $values): void
    {
        config([
            'server_sync.api.enabled' => (bool) $values['SERVER_SYNC_API_ENABLED'],
            'server_sync.api.domain' => $values['SERVER_SYNC_DOMAIN'],
        ]);
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function normalizeDomain(mixed $value): string
    {
        $value = $this->nullableString($value) ?: 'https://app.smaafbs.sch.id';

        if (! preg_match('/^https?:\/\//i', $value)) {
            $value = 'https://'.$value;
        }

        return rtrim($value, '/');
    }
}
