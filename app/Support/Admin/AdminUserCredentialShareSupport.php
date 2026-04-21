<?php

namespace App\Support\Admin;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdminUserCredentialShareSupport
{
    public const TTL_MINUTES = 30;

    /**
     * @param  array<int, array{name:string, username:string, password:string, created?:bool}>  $credentials
     * @param  array{generated_by?:string|null}  $context
     */
    public static function store(array $credentials, array $context = []): string
    {
        $token = (string) Str::uuid();

        Cache::put(
            self::cacheKey($token),
            [
                'credentials' => array_values($credentials),
                'generated_at' => now()->toIso8601String(),
                'generated_by' => $context['generated_by'] ?? auth()->user()?->name,
            ],
            now()->addMinutes(self::TTL_MINUTES),
        );

        return $token;
    }

    public static function payload(string $token): ?array
    {
        $payload = Cache::get(self::cacheKey($token));

        if (! is_array($payload)) {
            return null;
        }

        return $payload;
    }

    public static function printUrl(string $token): string
    {
        return route('admin.user-credentials.print', $token);
    }

    public static function exportUrl(string $token): string
    {
        return route('admin.user-credentials.export', $token);
    }

    public static function generatedAtLabel(?string $timestamp): string
    {
        if (blank($timestamp)) {
            return '-';
        }

        return Carbon::parse($timestamp)->translatedFormat('d F Y H:i');
    }

    public static function actionsHtml(string $token): string
    {
        $printUrl = e(self::printUrl($token));
        $exportUrl = e(self::exportUrl($token));
        $ttl = self::TTL_MINUTES;

        return <<<HTML
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{$printUrl}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-sky-300 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-800 transition hover:bg-sky-100">
                    Print Daftar Kredensial
                </a>
                <a href="{$exportUrl}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100">
                    Export Excel Kredensial
                </a>
            </div>
            <p class="mt-2 text-[11px] text-gray-500">Link ini aktif sekitar {$ttl} menit dan tidak menyimpan password secara permanen.</p>
        HTML;
    }

    protected static function cacheKey(string $token): string
    {
        return 'admin-user-credentials:'.$token;
    }
}
