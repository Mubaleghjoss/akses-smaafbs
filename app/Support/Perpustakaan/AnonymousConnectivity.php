<?php

namespace App\Support\Perpustakaan;

use App\Models\PerpustakaanLiterasiNetworkCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class AnonymousConnectivity
{
    public static function hashClient(string $clientId): string
    {
        return hash_hmac('sha256', $clientId, (string) config('app.key'));
    }

    public static function hashIp(?string $ip): ?string
    {
        $normalized = trim((string) $ip);

        return $normalized === ''
            ? null
            : hash_hmac('sha256', $normalized, (string) config('app.key'));
    }

    public static function networkScope(?string $ipHash): string
    {
        if (! $ipHash || ! Schema::hasTable('perpustakaan_literasi_network_checks')) {
            return 'unknown';
        }

        $freshAfter = now()->subMinutes((int) config('literacy.school_monitor.stale_minutes', 3));
        $latest = PerpustakaanLiterasiNetworkCheck::query()
            ->where('checked_at', '>=', $freshAfter)
            ->latest('checked_at')
            ->first(['context']);

        if (! $latest) {
            return 'unknown';
        }

        return hash_equals((string) data_get($latest->context, 'source_ip_hash', ''), $ipHash)
            ? 'school'
            : 'other';
    }

    public static function offlineDuration(mixed $occurredAt, mixed $recoveredAt): ?int
    {
        if (blank($recoveredAt)) {
            return null;
        }

        $occurred = Carbon::parse($occurredAt);
        $recovered = Carbon::parse($recoveredAt);

        return max(0, min(86400, $occurred->diffInSeconds($recovered, false)));
    }
}
