<?php

namespace App\Console\Commands;

use App\Models\Assessment\HomeroomAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menetapkan peran wali_kelas & kurikulum dari DATA PENUGASAN yang sudah ada,
 * bukan dari daftar nama yang ditulis manual.
 *
 * Latar belakang: izin penilaian sudah benar per peran, tetapi di produksi
 * TIDAK ADA akun berperan wali_kelas/kurikulum — semua guru hanya berperan
 * 'guru'. Akibatnya wali kelas tidak bisa mencetak rapor meski izinnya sudah
 * ada di peran wali_kelas.
 *
 * Sumber kebenaran:
 *   wali_kelas -> assessment_homeroom_assignments (penugasan wali kelas aktif)
 *   kurikulum  -> guru_tendik_tugas_tambahans yang memuat kata "KURIKULUM"
 *                 dan masa tugasnya belum berakhir (tst null / belum lewat)
 *
 * Peran DITAMBAHKAN, tidak menggantikan: guru yang juga wali kelas tetap
 * memegang peran 'guru' agar penugasan mapelnya tidak hilang.
 *
 * Idempoten & aman diulang. Default berjalan sebagai PRATINJAU (dry-run);
 * gunakan --terapkan untuk menyimpan.
 */
class SyncAssessmentRoles extends Command
{
    protected $signature = 'assessment:sync-roles
        {--terapkan : Simpan perubahan (tanpa opsi ini hanya pratinjau)}
        {--cabut : Cabut peran wali_kelas dari akun yang TIDAK lagi menjadi wali kelas}';

    protected $description = 'Tetapkan peran wali_kelas & kurikulum dari data penugasan (pratinjau secara bawaan)';

    public function handle(): int
    {
        $terapkan = (bool) $this->option('terapkan');

        if (! Schema::hasTable('assessment_homeroom_assignments') || ! Schema::hasTable('users')) {
            $this->components->error('Tabel penugasan wali kelas atau users belum tersedia.');

            return self::FAILURE;
        }

        $this->components->info($terapkan ? 'MODE: TERAPKAN (menyimpan)' : 'MODE: PRATINJAU (tidak menyimpan)');

        $rencanaWali = $this->rencanaWaliKelas();
        $rencanaKurikulum = $this->rencanaKurikulum();

        $this->tampilkan('wali_kelas', $rencanaWali);
        $this->tampilkan('kurikulum', $rencanaKurikulum);

        $cabut = $this->option('cabut') ? $this->rencanaCabutWali($rencanaWali['user_ids']) : [];
        if ($this->option('cabut')) {
            $this->newLine();
            $this->components->twoColumnDetail('<fg=yellow>Cabut wali_kelas</>', (string) count($cabut).' akun');
            foreach ($cabut as $baris) {
                $this->components->twoColumnDetail('  '.$baris['nama'], 'tidak lagi wali kelas');
            }
        }

        if (! $terapkan) {
            $this->newLine();
            $this->components->warn('Pratinjau saja. Jalankan ulang dengan --terapkan untuk menyimpan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($rencanaWali, $rencanaKurikulum, $cabut): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            foreach ($rencanaWali['tambah'] as $baris) {
                User::find($baris['user_id'])?->assignRole('wali_kelas');
            }

            foreach ($rencanaKurikulum['tambah'] as $baris) {
                User::find($baris['user_id'])?->assignRole('kurikulum');
            }

            foreach ($cabut as $baris) {
                User::find($baris['user_id'])?->removeRole('wali_kelas');
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->components->info(sprintf(
            'Selesai. wali_kelas +%d, kurikulum +%d, dicabut %d.',
            count($rencanaWali['tambah']),
            count($rencanaKurikulum['tambah']),
            count($cabut),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{tambah:array<int, array<string, mixed>>, sudah:int, tanpa_akun:array<int, string>, user_ids:array<int, int>}
     */
    private function rencanaWaliKelas(): array
    {
        $query = HomeroomAssignment::query();

        if (Schema::hasColumn('assessment_homeroom_assignments', 'is_active')) {
            $query->where('is_active', true);
        }

        $penugasan = $query->get(['teacher_id', 'teacher_name_snapshot', 'rombel_name_snapshot']);

        $tambah = [];
        $tanpaAkun = [];
        $userIds = [];
        $sudah = 0;

        foreach ($penugasan->groupBy('teacher_id') as $teacherId => $baris) {
            $user = User::query()->where('guru_tendik_id', $teacherId)->first();
            $nama = (string) ($baris->first()->teacher_name_snapshot ?? ('guru #'.$teacherId));
            $rombel = $baris->pluck('rombel_name_snapshot')->filter()->unique()->implode(', ');

            if (! $user) {
                $tanpaAkun[] = $nama.' ('.$rombel.')';

                continue;
            }

            $userIds[] = (int) $user->getKey();

            if ($user->hasRole('wali_kelas')) {
                $sudah++;

                continue;
            }

            $tambah[] = [
                'user_id' => (int) $user->getKey(),
                'nama' => $nama,
                'keterangan' => $rombel,
            ];
        }

        return ['tambah' => $tambah, 'sudah' => $sudah, 'tanpa_akun' => $tanpaAkun, 'user_ids' => $userIds];
    }

    /**
     * @return array{tambah:array<int, array<string, mixed>>, sudah:int, tanpa_akun:array<int, string>, user_ids:array<int, int>}
     */
    private function rencanaKurikulum(): array
    {
        if (! Schema::hasTable('guru_tendik_tugas_tambahans')) {
            return ['tambah' => [], 'sudah' => 0, 'tanpa_akun' => [], 'user_ids' => []];
        }

        $tugas = DB::table('guru_tendik_tugas_tambahans')
            ->where('tugas_tambahan', 'like', '%kurikulum%')
            // Masa tugas belum berakhir: tst kosong atau masih di depan.
            ->where(fn ($q) => $q->whereNull('tst')->orWhere('tst', '>=', now()->toDateString()))
            ->get(['guru_tendik_id', 'tugas_tambahan', 'tst']);

        $tambah = [];
        $tanpaAkun = [];
        $userIds = [];
        $sudah = 0;

        foreach ($tugas->groupBy('guru_tendik_id') as $guruId => $baris) {
            $user = User::query()->where('guru_tendik_id', $guruId)->first();
            $nama = DB::table('guru_tendik')->where('id', $guruId)->value('nama') ?? ('guru #'.$guruId);
            $label = $baris->pluck('tugas_tambahan')->unique()->implode(', ');

            if (! $user) {
                $tanpaAkun[] = $nama.' ('.$label.')';

                continue;
            }

            $userIds[] = (int) $user->getKey();

            if ($user->hasRole('kurikulum')) {
                $sudah++;

                continue;
            }

            $tambah[] = [
                'user_id' => (int) $user->getKey(),
                'nama' => $nama,
                'keterangan' => Str::limit($label, 40),
            ];
        }

        return ['tambah' => $tambah, 'sudah' => $sudah, 'tanpa_akun' => $tanpaAkun, 'user_ids' => $userIds];
    }

    /**
     * @param  array<int, int>  $userIdsMasihWali
     * @return array<int, array<string, mixed>>
     */
    private function rencanaCabutWali(array $userIdsMasihWali): array
    {
        return User::query()
            ->role('wali_kelas')
            ->whereNotIn('id', $userIdsMasihWali ?: [0])
            ->get(['id', 'name'])
            ->map(fn (User $u): array => ['user_id' => (int) $u->getKey(), 'nama' => (string) $u->name])
            ->all();
    }

    /**
     * @param  array{tambah:array<int, array<string, mixed>>, sudah:int, tanpa_akun:array<int, string>}  $rencana
     */
    private function tampilkan(string $peran, array $rencana): void
    {
        $this->newLine();
        $this->components->twoColumnDetail(
            "<fg=cyan>Peran {$peran}</>",
            sprintf('%d ditambahkan · %d sudah punya · %d tanpa akun',
                count($rencana['tambah']), $rencana['sudah'], count($rencana['tanpa_akun'])),
        );

        foreach ($rencana['tambah'] as $baris) {
            $this->components->twoColumnDetail('  + '.$baris['nama'], (string) $baris['keterangan']);
        }

        foreach ($rencana['tanpa_akun'] as $nama) {
            $this->components->twoColumnDetail('  <fg=yellow>! '.$nama.'</>', 'belum punya akun login');
        }
    }
}
