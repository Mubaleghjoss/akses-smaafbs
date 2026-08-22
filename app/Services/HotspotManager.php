<?php

namespace App\Services;

use App\Models\HotspotUser;

/**
 * Layanan tingkat-tinggi untuk manajemen hotspot MikroTik.
 * Router = sumber kebenaran; tabel hotspot_users adalah MIRROR lokal (pola Mikhmon).
 */
class HotspotManager
{
    private ?RouterOS $ros = null;

    private string $lastError = '';

    public function error(): string
    {
        return $this->lastError;
    }

    /** Buka koneksi ke router. Sumber config: hh_settings (via UI) dengan fallback config/hotspot.php (.env). */
    public function connect(): bool
    {
        $this->lastError = '';
        $s = static::settings();
        $this->ros = new RouterOS(
            (string) $s['host'],
            (int) $s['port'],
            (string) $s['user'],
            (string) $s['pass'],
        );
        if ($this->ros->connect()) {
            return true;
        }
        $this->lastError = $this->ros->lastError();
        $this->ros = null;

        return false;
    }

    /** Pengaturan koneksi efektif: hh_settings (dari UI) menimpa config/hotspot.php (.env). */
    public static function settings(): array
    {
        return [
            'host' => \App\Models\HhSetting::get('mt_host', (string) config('hotspot.host')),
            'port' => (int) (\App\Models\HhSetting::get('mt_port', (string) config('hotspot.port')) ?: config('hotspot.port')),
            'user' => \App\Models\HhSetting::get('mt_user', (string) config('hotspot.user')),
            'pass' => \App\Models\HhSetting::get('mt_pass', (string) config('hotspot.pass')),
        ];
    }

    /** Tes koneksi ke router dengan parameter tertentu (untuk tombol Tes Koneksi). */
    public static function testConnection(string $host, int $port, string $user, string $pass): array
    {
        $ros = new RouterOS($host, $port, $user, $pass);
        if (! $ros->connect()) {
            return ['ok' => false, 'error' => $ros->lastError()];
        }
        $info = $ros->systemInfo();
        $ros->close();

        return ['ok' => true, 'identity' => $info['identity'] ?? '?', 'version' => $info['version'] ?? '?'];
    }

    public function ros(): ?RouterOS
    {
        return $this->ros;
    }

    public function close(): void
    {
        $this->ros?->close();
        $this->ros = null;
    }

    // ---------- User hotspot ----------

    /** Semua akun dari router, name => row. */
    public function routerUsers(): array
    {
        if ($this->ros === null) {
            return [];
        }
        $res = $this->ros->hotspotUsers();
        $out = [];
        if ($res['ok']) {
            foreach ($res['rows'] as $row) {
                $out[(string) ($row['name'] ?? '')] = $row;
            }
        }

        return $out;
    }

    /** User yang sedang online di router. */
    public function routerActive(): array
    {
        if ($this->ros === null) {
            return [];
        }
        $res = $this->ros->hotspotActive();

        return $res['ok'] ? $res['rows'] : [];
    }

    /** Daftar profil (grup bandwidth) di router. */
    public function profiles(): array
    {
        return $this->ros?->hotspotProfilesAll() ?? [];
    }

    /** Pastikan profil ada dengan rate-limit benar; buat/update bila perlu. */
    public function ensureProfile(string $name, string $rateLimit = '1M/1M'): array
    {
        $res = $this->ros?->hotspotProfilesAll() ?? ['ok' => false, 'rows' => []];
        if (! ($res['ok'] ?? false)) {
            return ['ok' => false, 'msg' => $res['error'] ?? 'Gagal baca profil'];
        }
        $existing = null;
        foreach (($res['rows'] ?? []) as $row) {
            if ((string) ($row['name'] ?? '') === $name) {
                $existing = $row;

                break;
            }
        }
        if ($existing !== null) {
            $cur = (string) ($existing['rate-limit'] ?? '');
            if ($cur !== $rateLimit) {
                $set = $this->ros->hotspotProfileSet((string) ($existing['.id'] ?? ''), ['rate-limit' => $rateLimit]);
                if (! ($set['ok'] ?? false)) {
                    return ['ok' => false, 'msg' => $set['error'] ?? "Gagal set rate-limit {$name}"];
                }

                return ['ok' => true, 'created' => false, 'updated' => true];
            }

            return ['ok' => true, 'created' => false, 'updated' => false];
        }
        $add = $this->ros->hotspotProfileAdd($name, $rateLimit);
        if (! ($add['ok'] ?? false)) {
            return ['ok' => false, 'msg' => $add['error'] ?? "Gagal buat profil {$name}"];
        }

        return ['ok' => true, 'created' => true, 'updated' => false];
    }

    public function profileNames(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($p) => (string) ($p['name'] ?? ''),
            $this->profiles(),
        ))));
    }

    /** Sinkronkan mirror lokal dari router (upsert semua akun router). */
    public function syncUsersToLocal(): array
    {
        $router = $this->routerUsers();
        $synced = 0;
        foreach ($router as $name => $row) {
            HotspotUser::updateOrCreate(
                ['username' => $name],
                [
                    'password' => (string) ($row['password'] ?? ''),
                    'profile' => (string) ($row['profile'] ?? 'default'),
                    'durasi' => $this->durasiFromLimit((string) ($row['limit-uptime'] ?? '0s')),
                    'note' => (string) ($row['comment'] ?? ''),
                    'disabled' => ($row['disabled'] ?? 'false') === 'true',
                    'source' => 'router',
                ],
            );
            $synced++;
        }

        return ['router' => count($router), 'synced' => $synced];
    }

    /** Tambah akun: router + mirror lokal. */
    public function addUser(array $u): array
    {
        $u['username'] = trim((string) $u['username']);
        $u['password'] = (string) ($u['password'] ?? '');
        $u['profile'] = trim((string) ($u['profile'] ?? '')) !== '' ? trim((string) $u['profile']) : 'default';
        $u['durasi'] = max(0, (int) ($u['durasi'] ?? 0));
        $u['note'] = (string) ($u['note'] ?? '');

        if ($this->ros === null) {
            return ['ok' => false, 'msg' => 'Router tidak terhubung: ' . $this->lastError];
        }
        $limit = $u['durasi'] > 0 ? $this->limitFromDurasi($u['durasi']) : null;
        $r = $this->ros->hotspotUserAdd(
            $u['username'], $u['password'], $u['profile'], $u['note'], $limit,
        );
        if (!$r['ok']) {
            return ['ok' => false, 'msg' => $r['error']];
        }
        HotspotUser::updateOrCreate(
            ['username' => $u['username']],
            [...$u, 'disabled' => false, 'source' => 'both'],
        );

        return ['ok' => true, 'msg' => ''];
    }

    /** Update akun (router + mirror). $old = username lama. */
    public function updateUser(string $old, array $u): array
    {
        if ($this->ros === null) {
            return ['ok' => false, 'msg' => 'Router tidak terhubung: ' . $this->lastError];
        }
        $routerRows = $this->routerUsers();
        if (!isset($routerRows[$old]) || empty($routerRows[$old]['.id'])) {
            return ['ok' => false, 'msg' => "Akun '$old' tidak ditemukan di router."];
        }
        $fields = [
            'name' => trim((string) $u['username']),
            'password' => (string) ($u['password'] ?? ''),
            'comment' => (string) ($u['note'] ?? ''),
        ];
        $profile = trim((string) ($u['profile'] ?? ''));
        if ($profile !== '') {
            $fields['profile'] = $profile;
        }
        $durasi = max(0, (int) ($u['durasi'] ?? 0));
        $limit = $durasi > 0 ? $this->limitFromDurasi($durasi) : null;
        if ($limit !== null) {
            $fields['limit-uptime'] = $limit;
        }
        $r = $this->ros->hotspotUserSet((string) $routerRows[$old]['.id'], $fields);
        if (!$r['ok']) {
            return ['ok' => false, 'msg' => $r['error']];
        }
        HotspotUser::updateOrCreate(
            ['username' => $fields['name']],
            [
                'password' => $fields['password'],
                'profile' => $profile !== '' ? $profile : 'default',
                'durasi' => $durasi,
                'note' => $fields['comment'],
                'source' => 'both',
            ],
        );
        if ($old !== $fields['name']) {
            HotspotUser::where('username', $old)->delete();
        }

        return ['ok' => true, 'msg' => ''];
    }

    /** Hapus akun dari router (+ mirror lokal). */
    public function deleteUser(string $username, bool $onlyLocal = false): array
    {
        if (! $onlyLocal && $this->ros !== null) {
            $rows = $this->routerUsers();
            if (isset($rows[$username]) && ! empty($rows[$username]['.id'])) {
                $r = $this->ros->hotspotUserRemove((string) $rows[$username]['.id']);
                if (! $r['ok']) {
                    return ['ok' => false, 'msg' => $r['error']];
                }
            }
        }
        HotspotUser::where('username', $username)->delete();

        return ['ok' => true, 'msg' => ''];
    }

    /** Aktifkan / nonaktifkan akun di router. */
    public function setEnabled(string $username, bool $enabled): array
    {
        if ($this->ros === null) {
            return ['ok' => false, 'msg' => 'Router tidak terhubung: ' . $this->lastError];
        }
        $rows = $this->routerUsers();
        if (! isset($rows[$username]) || empty($rows[$username]['.id'])) {
            return ['ok' => false, 'msg' => "Akun '$username' tidak ditemukan di router."];
        }
        $id = (string) $rows[$username]['.id'];
        $r = $enabled
            ? $this->ros->hotspotUserEnable($id)
            : $this->ros->hotspotUserDisable($id);
        if (! $r['ok']) {
            return ['ok' => false, 'msg' => $r['error']];
        }
        HotspotUser::where('username', $username)->update(['disabled' => ! $enabled]);

        return ['ok' => true, 'msg' => ''];
    }

    // ---------- Helper ----------

    /** 'limit-uptime' RouterOS (mis. '1d2h') -> durasi hari. */
    public function durasiFromLimit(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '' || $limit === '0s') {
            return 0;
        }
        if (! preg_match_all('/(\d+)([dhms])/', $limit, $m)) {
            return 0;
        }
        $sec = 0;
        foreach ($m[1] as $i => $v) {
            $v = (int) $v;
            $sec += match ($m[2][$i]) {
                'd' => $v * 86400,
                'h' => $v * 3600,
                'm' => $v * 60,
                default => $v,
            };
        }

        return (int) ceil($sec / 86400);
    }

    /** Durasi hari -> 'limit-uptime' RouterOS ('3d' dst). */
    public function limitFromDurasi(int $days): string
    {
        return $days >= 1 ? "{$days}d" : '';
    }
}