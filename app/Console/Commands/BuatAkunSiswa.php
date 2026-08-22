<?php

namespace App\Console\Commands;

use App\Services\HotspotStudentAccounts;
use Illuminate\Console\Command;

class BuatAkunSiswa extends Command
{
    protected $signature = 'hotspot:buat-akun-siswa
        {--rombel= : Filter rombel (contoh: "X 1"); kosong = semua siswa aktif}
        {--profil=kelas : "kelas" = profil per rombel (otomatis dibuat, rate-limit), atau nama profil tetap}
        {--password=tanggal : username|nipd4|nisn4|tanggal (dd-mm-yyyy dari tanggal lahir)}
        {--prefix= : Awalan username, mis. siswa-}
        {--rate=1M/1M : Rate-limit profil kelas (digunakan saat --profil=kelas)}
        {--durasi=0 : Durasi hari (0 = unlimited)}
        {--dry-run : Hanya preview, tanpa membuat akun}';

    protected $description = 'Buat akun hotspot MikroTik dari nama unik siswa/i aktif';

    public function handle(): int
    {
        $students = HotspotStudentAccounts::candidates($this->option('rombel'));
        $usernames = HotspotStudentAccounts::buildUsernames($students, (string) $this->option('prefix'));
        $profil = (string) $this->option('profil');
        if ($profil === '') {
            $profil = 'kelas';
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
        $this->warn('Profil: '.($profil === 'kelas' ? 'per kelas (auto, rate '.$this->option('rate').')' : $profil));

        $noDob = count(array_filter($items, fn ($i): bool => $i['password'] === $i['username'] && $this->option('password') === 'tanggal'));
        if ($noDob > 0) {
            $this->warn("Catatan: {$noDob} siswa tanpa tanggal lahir → password = username.");
        }

        if ($items === []) {
            $this->error('Tidak ada siswa aktif ditemukan.');

            return self::FAILURE;
        }

        $preview = array_map(fn (array $i): array => [
            $i['nama'], $i['rombel'], $i['username'], $i['password'],
            $profil === 'kelas' ? HotspotStudentAccounts::classProfileName($i['rombel']) : $profil,
        ], array_slice($items, 0, 20));
        $this->table(['Nama', 'Rombel', 'Username', 'Password', 'Profil'], $preview);
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

        $r = HotspotStudentAccounts::createAccounts($items, $profil, (int) $this->option('durasi'), (string) $this->option('rate'));

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