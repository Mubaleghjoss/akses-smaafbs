<?php

namespace App\Support\WifiAccount;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien BACA daftar akun hotspot dari API aplikasi MikroTik (read-only, bertanda tangan token).
 * Tidak menulis apa pun ke sumber. Aman: token tidak dibocorkan pada pesan error.
 */
class WifiAccountSyncClient
{
    /**
     * Ambil daftar akun WiFi dari API baca.
     *
     * @return array<int, array{username:string,profile:string,disabled:bool,comment:string,limit_uptime:string,password:?string}>
     */
    public function fetchAccounts(): array
    {
        $config = config('wifi_sync');

        if (! ($config['enabled'] ?? false)) {
            throw new RuntimeException('Sinkron WiFi belum diaktifkan.');
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $token = (string) ($config['token'] ?? '');

        if ($baseUrl === '' || $token === '') {
            throw new RuntimeException('Konfigurasi sinkron WiFi belum lengkap.');
        }

        // Wajib HTTPS untuk endpoint produksi (kecuali localhost pengujian).
        if (! str_starts_with($baseUrl, 'https://') && ! str_starts_with($baseUrl, 'http://127.0.0.1') && ! str_starts_with($baseUrl, 'http://localhost')) {
            throw new RuntimeException('URL sinkron WiFi harus menggunakan HTTPS.');
        }

        $response = Http::timeout((int) ($config['timeout'] ?? 30))
            ->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])
            ->get($baseUrl.'/api-hotspot.php');

        if ($response->status() === 401) {
            throw new RuntimeException('Token sinkron ditolak sumber (401).');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Gagal mengambil data dari sumber (HTTP '.$response->status().').');
        }

        $data = $response->json();

        if (! is_array($data) || ! ($data['ok'] ?? false) || ! is_array($data['accounts'] ?? null)) {
            throw new RuntimeException('Format data dari sumber tidak dikenali.');
        }

        $accounts = [];
        foreach ($data['accounts'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $username = trim((string) ($row['username'] ?? ''));
            if ($username === '') {
                continue;
            }
            $password = $row['password'] ?? null;
            $accounts[] = [
                'username' => $username,
                'profile' => (string) ($row['profile'] ?? ''),
                'disabled' => (bool) ($row['disabled'] ?? false),
                'comment' => (string) ($row['comment'] ?? ''),
                'limit_uptime' => (string) ($row['limit_uptime'] ?? ''),
                'password' => ($password !== null && $password !== '') ? (string) $password : null,
            ];
        }

        return $accounts;
    }

    /**
     * Hitung preview perubahan terhadap tabel lokal tanpa menulis apa pun.
     *
     * @param  array<int, array<string,mixed>>  $accounts
     * @return array{baru:int,berubah:int,sama:int,items:array<int, array{username:string,status:string}>}
     */
    public function diffPreview(array $accounts): array
    {
        $preview = ['baru' => 0, 'berubah' => 0, 'sama' => 0, 'items' => []];

        $existing = \App\Models\HotspotUser::query()
            ->get(['username', 'password', 'profile'])
            ->keyBy('username');

        foreach ($accounts as $row) {
            $username = (string) $row['username'];
            $current = $existing->get($username);

            if ($current === null) {
                $status = 'baru';
                $preview['baru']++;
            } else {
                $samePassword = $row['password'] === null || (string) $row['password'] === (string) $current->password;
                $sameProfile = (string) $row['profile'] === (string) $current->profile;
                if ($samePassword && $sameProfile) {
                    $status = 'sama';
                    $preview['sama']++;
                } else {
                    $status = 'berubah';
                    $preview['berubah']++;
                }
            }

            $preview['items'][] = ['username' => $username, 'status' => $status];
        }

        return $preview;
    }

    /**
     * Terapkan hasil sinkron ke tabel lokal (upsert by username). Tidak menghapus.
     *
     * @param  array<int, array<string,mixed>>  $accounts
     * @return array{created:int,updated:int}
     */
    public function apply(array $accounts): array
    {
        $result = ['created' => 0, 'updated' => 0];

        foreach ($accounts as $row) {
            $username = (string) $row['username'];
            $existing = \App\Models\HotspotUser::query()->where('username', $username)->first();

            $payload = [
                'username' => $username,
                'profile' => (string) ($row['profile'] ?? 'default') ?: 'default',
                'disabled' => (bool) ($row['disabled'] ?? false),
                'note' => (string) ($row['comment'] ?? ''),
                'input_mode' => 'otomatis',
                'source' => 'router',
            ];
            // Password hanya ditimpa bila sumber memberikannya.
            if (($row['password'] ?? null) !== null) {
                $payload['password'] = (string) $row['password'];
            }

            if ($existing) {
                $existing->fill($payload)->save();
                $result['updated']++;
            } else {
                $payload['password'] = $payload['password'] ?? '';
                $payload['role'] = 'siswa';
                \App\Models\HotspotUser::query()->create($payload);
                $result['created']++;
            }
        }

        return $result;
    }
}
