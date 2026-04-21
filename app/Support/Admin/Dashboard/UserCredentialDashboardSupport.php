<?php

namespace App\Support\Admin\Dashboard;

use App\Models\GuruTendik;
use App\Models\User;

class UserCredentialDashboardSupport
{
    protected static array $memo = [];

    public static function snapshot(): array
    {
        return static::remember('global', function (): array {
            $userSummary = User::query()
                ->selectRaw('count(*) as total')
                ->selectRaw('sum(case when uses_default_password = 1 then 1 else 0 end) as default_password')
                ->selectRaw('sum(case when uses_default_password = 0 then 1 else 0 end) as changed_password')
                ->selectRaw('sum(case when guru_tendik_id is not null then 1 else 0 end) as linked_guru')
                ->first();

            $guruSummary = GuruTendik::query()
                ->leftJoin('users', 'users.guru_tendik_id', '=', 'guru_tendik.id')
                ->selectRaw('count(distinct guru_tendik.id) as total_guru_tendik')
                ->selectRaw('count(distinct users.id) as punya_akun')
                ->selectRaw('sum(case when users.uses_default_password = 1 then 1 else 0 end) as default_password')
                ->selectRaw('sum(case when users.uses_default_password = 0 then 1 else 0 end) as changed_password')
                ->selectRaw('sum(case when users.id is null then 1 else 0 end) as belum_punya_akun')
                ->first();

            return [
                'user_summary' => [
                    'total' => (int) ($userSummary?->total ?? 0),
                    'default_password' => (int) ($userSummary?->default_password ?? 0),
                    'changed_password' => (int) ($userSummary?->changed_password ?? 0),
                    'linked_guru' => (int) ($userSummary?->linked_guru ?? 0),
                    'linked_guru_default' => User::query()
                        ->whereNotNull('guru_tendik_id')
                        ->where('uses_default_password', true)
                        ->count(),
                ],
                'guru_summary' => [
                    'total_guru_tendik' => (int) ($guruSummary?->total_guru_tendik ?? 0),
                    'punya_akun' => (int) ($guruSummary?->punya_akun ?? 0),
                    'default_password' => (int) ($guruSummary?->default_password ?? 0),
                    'changed_password' => (int) ($guruSummary?->changed_password ?? 0),
                    'belum_punya_akun' => (int) ($guruSummary?->belum_punya_akun ?? 0),
                ],
            ];
        });
    }

    public static function forget(): void
    {
        DashboardCacheSupport::forgetModule('user_credentials');
        static::$memo = [];
    }

    protected static function remember(string $scopeKey, \Closure $callback): mixed
    {
        $memoKey = 'user_credentials:'.$scopeKey;

        if (array_key_exists($memoKey, static::$memo)) {
            return static::$memo[$memoKey];
        }

        return static::$memo[$memoKey] = DashboardCacheSupport::remember('user_credentials', $scopeKey, $callback);
    }
}
