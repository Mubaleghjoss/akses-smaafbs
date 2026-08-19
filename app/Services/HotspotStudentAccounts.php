<?php

namespace App\Services;

use App\Models\HotspotUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Generate & buat akun hotspot MikroTik dari data siswa (data_siswa status aktif).
 * Username dibuat dari nama (slug, max 15 huruf) + penomoran jika duplikat.
 */
class HotspotStudentAccounts
{
    /** Kandidat siswa aktif; optional filter rombel. */
    public static function candidates(?string $rombel = null): Collection
    {
        $q = DB::table('data_siswa')
            ->where('status', 'aktif')
            ->orderBy('nama')
            ->get(['id', 'nama', 'rombel_saat_ini', 'nipd', 'nisn', 'tanggal_lahir']);

        if (filled($rombel)) {
            $q = $q->filter(fn ($r): bool => (string) ($r->rombel_saat_ini ?? '') === $rombel)->values();
        }

        return $q;
    }

    /** Rombel siswa aktif yang tersedia. */
    public static function rombelOptions(): array
    {
        return DB::table('data_siswa')
            ->where('status', 'aktif')
            ->whereNotNull('rombel_saat_ini')
            ->where('rombel_saat_ini', '<>', '')
            ->distinct()
            ->orderBy('rombel_saat_ini')
            ->pluck('rombel_saat_ini')
            ->mapWithKeys(fn (string $r): array => [$r => $r])
            ->all();
    }

    /** Username yang sudah terpakai (mirror DB). */
    public static function existingUsernames(): array
    {
        return HotspotUser::pluck('username')->all();
    }

    /** Slug aman untuk RouterOS: huruf kecil + angka saja. */
    public static function slugify(string $nama): string
    {
        $s = strtolower(trim($nama));
        $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);

        return (string) preg_replace('/[^a-z0-9]/', '', $t !== false ? $t : $s);
    }

    /**
     * Bangun username unik untuk setiap siswa (terhadap batch + yang sudah ada).
     * Return: id_siswa => username.
     */
    public static function buildUsernames(Collection $students, string $prefix = ''): array
    {
        $used = array_fill_keys(self::existingUsernames(), true);
        $out = [];
        foreach ($students as $st) {
            $base = $prefix.substr(self::slugify((string) $st->nama), 0, 15);
            if ($base === '') {
                $base = 'siswa';
            }
            $u = $base;
            $i = 2;
            while (isset($used[$u])) {
                $u = $base.$i;
                $i++;
            }
            $used[$u] = true;
            $out[$st->id] = $u;
        }

        return $out;
    }

    /** Password sesuai mode: username | nipd4 | nisn4 | tanggal (dd-mm-yyyy dari tanggal_lahir). */
    public static function passwordFor(string $username, string $mode, object $st): string
    {
        if ($mode === 'tanggal') {
            $dob = (string) ($st->tanggal_lahir ?? '');
            if ($dob !== '') {
                $ts = strtotime($dob);
                if ($ts !== false) {
                    return date('d-m-Y', $ts);
                }
            }

            return $username; // fallback: tanpa tanggal lahir → password = username
        }

        return match ($mode) {
            'nipd4' => (string) substr((string) $st->nipd, -4) ?: $username,
            'nisn4' => (string) substr((string) $st->nisn, -4) ?: $username,
            default => $username,
        };
    }

    /** Nama profil hotspot dari rombel (di-trim). */
    public static function classProfileName(string $rombel): string
    {
        $c = trim((string) $rombel);

        return $c !== '' ? $c : 'Siswa';
    }

    /**
     * Buat akun di router + mirror lokal.
     * $items: array [['username','password','nama','rombel'], ...]
     * Return: ['done'=>int,'skipped'=>int,'failed'=>array].
     */
    public static function createAccounts(array $items, string $profile, int $durasi = 0, string $rateLimit = '1M/1M'): array
    {
        $m = new HotspotManager();
        if (! $m->connect()) {
            return ['done' => 0, 'skipped' => 0, 'failed' => [$m->error()], 'connected' => false];
        }
        $routerUsers = $m->routerUsers();
        $done = 0;
        $skipped = 0;
        $failed = [];
        try {
            // Mode 'kelas': pastikan profil per rombel ada + rate-limitnya benar
            $perClass = $profile === 'kelas';
            if ($perClass) {
                $classes = [];
                foreach ($items as $item) {
                    $classes[] = self::classProfileName((string) ($item['rombel'] ?? ''));
                }
                foreach (array_unique(array_filter($classes)) as $class) {
                    $r = $m->ensureProfile($class, $rateLimit);
                    if (! $r['ok']) {
                        $failed[] = "profil {$class}: ".($r['msg'] ?? 'gagal');
                    }
                }
            }

            foreach ($items as $item) {
                $username = (string) ($item['username'] ?? '');
                if ($username === '') {
                    continue;
                }
                if (isset($routerUsers[$username])) {
                    $skipped++;

                    continue;
                }
                $p = $perClass ? self::classProfileName((string) ($item['rombel'] ?? '')) : $profile;
                $r = $m->addUser([
                    'username' => $username,
                    'password' => (string) ($item['password'] ?? $username),
                    'profile' => $p,
                    'durasi' => $durasi,
                ]);
                if ($r['ok']) {
                    HotspotUser::create([
                        'username' => $username,
                        'password' => (string) ($item['password'] ?? $username),
                        'profile' => $p,
                        'durasi' => $durasi,
                        'source' => 'both',
                        'note' => (string) ($item['nama'] ?? ''), // nama siswa lengkap di catatan
                    ]);
                    $done++;
                } else {
                    $failed[] = $username.': '.($r['msg'] ?? 'gagal');
                }
            }
        } finally {
            $m->close();
        }

        return ['done' => $done, 'skipped' => $skipped, 'failed' => $failed, 'connected' => true];
    }
}