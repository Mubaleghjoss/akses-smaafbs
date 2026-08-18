<?php

namespace App\Console\Commands;

use App\Filament\Resources\HotspotUserResource;
use App\Services\HotspotStudentAccounts;
use Illuminate\Console\Command;

class BuatAkunSiswa extends Command
{
    protected $signature = 'hotspot:buat-akun-siswa
        {--rombel= : Filter rombel (contoh: "X 1 - 2025/2026"); kosong = semua siswa aktif}
        {--profil= : Profil hotspot; default diambil dari pengaturan/cache}
        {--password=username : username|nipd4|nisn4}
        {--prefix= : Awalan username, mis. siswa-}
        {--durasi=0 : Durasi hari (0 = unlimited)}
        {--dry-run : Hanya preview, tanpa membuat akun}';

    protected $description = 'Buat akun hotspot MikroTik dari nama unik siswa/i aktif';

    public function handle(): int
    {
        $students = HotspotStudentAccounts::candidates($this->option('rombel'));
        $usernames = HotspotStudentAccounts::buildUsernames($students, (string) $this->option('prefix'));
        $profil = (string) $this->option('profil');
        if ($profil === '') {
            $options = HotspotUserResource::profileOptions();
            $profil = (string) (array_key_first($options) ?: 'default');
        }

        $items = [];
        foreach ($students as $st) {
            $u = (string) ($usernames[$st->id] ?? '');
            if ($u === '') {
                continue;
            }
            $items[] = [
                'username' => $u,
                'password' => HotspotStudentAccounts::passwordFor($u, (string) $this->option('password'), $st),
                'nama' => (string) $st->nama,
                'rombel' => (string) ($st->rombel_saat_ini ?? ''),
            ];
        }

        $this->info('Kandidat: '.count($items).' siswa aktif'.($this->option('rombel') ? " (rombel: {$this->option('rombel')})" : ''));
        $this->warn('Profil: '.$profil);

        if ($items === []) {
            $this->error('Tidak ada siswa aktif ditemukan.');

            return self::FAILURE;
        }

        $this->table(['Nama', 'Rombel', 'Username', 'Password'], array_map(
            fn (array $i): array => [$i['nama'], $i['rombel'], $i['username'], $i['password']],
            array_slice($items, 0, 20)
        ));
        if (count($items) > 20) {
            $this->info('... dan '.(count($items) - 20).' lainnya');
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run: tidak ada akun yang dibuat.');

            return self::SUCCESS;
        }

        $first = $items[0]['username'] ?? '';
        if (! $this->confirm("Buat {$first} ... (".count($items).' akun) di router? Password mode: '.$this->option('password'))) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $r = HotspotStudentAccounts::createAccounts($items, $profil, (int) $this->option('durasi'));

        if (! ($r['connected'] ?? true)) {
            $this->error('Router tidak terhubung: '.($r['failed'][0] ?? ''));

            return self::FAILURE;
        }

        $this->info("Selesai: {$r['done']} dibuat, {$r['skipped']} sudah ada, ".count($r['failed']).' gagal');
        foreach (array_slice($r['failed'], 0, 5) as $f) {
            $this->error('  - '.$f);
        }

        return self::SUCCESS;
    }
}