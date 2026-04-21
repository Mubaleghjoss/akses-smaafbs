<?php

namespace Tests\Feature;

use App\Filament\Resources\BoardingKeuanganSiswaResource;
use App\Filament\Resources\BoardingPencapaianResource;
use App\Filament\Resources\DataSiswaResource;
use App\Filament\Resources\UserResource;
use App\Models\BoardingKeuanganSiswa;
use App\Models\BoardingKonselingMt;
use App\Models\BoardingPencapaian;
use App\Models\BoardingRapot;
use App\Models\DataSiswa;
use App\Models\User;
use Database\Seeders\InitialAdminSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BoardingWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->runUserMigrations();
        $this->runPermissionMigration();
        $this->createDataSiswaTable();
        $this->runBoardingMigrations();
        (new InitialAdminSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_pamong_scope_supports_multiple_classes_and_only_owned_boarding_records(): void
    {
        $putraScope = DataSiswa::query()->create([
            'nama' => 'Putra Scope',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $putraScopeKelasDua = DataSiswa::query()->create([
            'nama' => 'Putra Scope Kelas Dua',
            'rombel_saat_ini' => 'XI.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $putraOwnerLain = DataSiswa::query()->create([
            'nama' => 'Putra Milik Pamong Lain',
            'rombel_saat_ini' => 'XI.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $putriScope = DataSiswa::query()->create([
            'nama' => 'Putri Scope',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $bedaAngkatan = DataSiswa::query()->create([
            'nama' => 'Putra Beda Angkatan',
            'rombel_saat_ini' => 'XII.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $pamong = User::query()->create([
            'name' => 'Pamong Putra 2025',
            'username' => 'pamong-putra',
            'password' => 'secret123',
            'boarding_rombel_scope' => ['X.I / 2025-2026', 'XI.I / 2025-2026'],
        ]);
        $pamong->assignRole('pamong_putra');

        $pamongLain = User::query()->create([
            'name' => 'Pamong Putra Lain',
            'username' => 'pamong-putra-lain',
            'password' => 'secret123',
            'boarding_rombel_scope' => ['XI.I / 2025-2026'],
        ]);
        $pamongLain->assignRole('pamong_putra');

        BoardingPencapaian::query()->create([
            'siswa_id' => $putraScope->id,
            'pamong_user_id' => $pamong->id,
            'status_pencapaian' => 'proses',
        ]);
        BoardingPencapaian::query()->create([
            'siswa_id' => $putraScopeKelasDua->id,
            'pamong_user_id' => $pamong->id,
            'status_pencapaian' => 'proses',
        ]);
        BoardingPencapaian::query()->create([
            'siswa_id' => $putraOwnerLain->id,
            'pamong_user_id' => $pamongLain->id,
            'status_pencapaian' => 'proses',
        ]);
        BoardingPencapaian::query()->create([
            'siswa_id' => $putriScope->id,
            'pamong_user_id' => $pamong->id,
            'status_pencapaian' => 'proses',
        ]);
        BoardingPencapaian::query()->create([
            'siswa_id' => $bedaAngkatan->id,
            'pamong_user_id' => $pamong->id,
            'status_pencapaian' => 'proses',
        ]);

        BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $putraScope->id,
            'pamong_user_id' => $pamong->id,
            'pamong_nama' => $pamong->name,
            'kategori_asrama' => 'putra',
        ]);
        BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $putraScopeKelasDua->id,
            'pamong_user_id' => $pamong->id,
            'pamong_nama' => $pamong->name,
            'kategori_asrama' => 'putra',
        ]);
        BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $putraOwnerLain->id,
            'pamong_user_id' => $pamongLain->id,
            'pamong_nama' => $pamongLain->name,
            'kategori_asrama' => 'putra',
        ]);

        $this->actingAs($pamong);

        $visibleStudents = DataSiswa::applyVisibleScope(DataSiswa::query(), $pamong)->pluck('nama')->all();
        sort($visibleStudents);

        $visiblePencapaian = BoardingPencapaianResource::getEloquentQuery()->with('siswa')->get()->pluck('siswa.nama')->all();
        $visibleKeuangan = BoardingKeuanganSiswaResource::getEloquentQuery()->with('siswa')->get()->pluck('siswa.nama')->all();

        $this->assertSame(['Putra Milik Pamong Lain', 'Putra Scope', 'Putra Scope Kelas Dua'], $visibleStudents);
        $this->assertSame(['Putra Scope', 'Putra Scope Kelas Dua'], $visiblePencapaian);
        $this->assertSame(['Putra Scope', 'Putra Scope Kelas Dua'], $visibleKeuangan);
        $this->assertContains('Manajemen Boarding', $pamong->resolvedNavigationGroups());
        $this->assertFalse(DataSiswaResource::canViewAny());
        $this->assertFalse(UserResource::canViewAny());
    }

    public function test_boarding_keuangan_query_auto_syncs_visible_students_for_pamong(): void
    {
        $siswaVisible = DataSiswa::query()->create([
            'nama' => 'Santri Baru Putra',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        DataSiswa::query()->create([
            'nama' => 'Santri Putri',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $pamong = User::query()->create([
            'name' => 'Pamong Auto Sync',
            'username' => 'pamong-auto-sync',
            'password' => 'secret123',
            'boarding_rombel_scope' => ['X.I / 2025-2026'],
        ]);
        $pamong->assignRole('pamong_putra');

        $this->actingAs($pamong);

        $visibleKeuangan = BoardingKeuanganSiswaResource::getEloquentQuery()->with('siswa')->get();

        $this->assertCount(1, $visibleKeuangan);
        $this->assertSame('Santri Baru Putra', $visibleKeuangan->first()?->siswa?->nama);
        $this->assertDatabaseHas('boarding_keuangan_siswas', [
            'siswa_id' => $siswaVisible->id,
            'pamong_user_id' => $pamong->id,
            'pamong_nama' => 'Pamong Auto Sync',
            'kategori_asrama' => 'putra',
        ]);
    }

    public function test_boarding_pencapaian_details_and_updates_sync_recap_automatically(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Murid Target',
            'rombel_saat_ini' => 'XI.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $pamong = User::query()->create([
            'name' => 'Pamong Target',
            'username' => 'pamong-target',
            'password' => 'secret123',
            'boarding_rombel_scope' => ['XI.I / 2025-2026'],
        ]);
        $pamong->assignRole('pamong_putra');

        $pencapaian = BoardingPencapaian::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_user_id' => $pamong->id,
            'status_pencapaian' => 'proses',
        ]);

        $pencapaian->details()->createMany([
            [
                'kategori_detail' => 'surat_quran_tuntas',
                'nama_target' => 'An-Naba sampai Al-Lail',
                'target_nilai' => 5,
                'capaian_nilai' => 5,
                'satuan' => 'surat',
                'status_detail' => 'tuntas',
                'tuntas_at' => '2026-03-20',
            ],
            [
                'kategori_detail' => 'hafalan_doa',
                'nama_target' => 'Doa Harian Pilihan',
                'target_nilai' => 4,
                'capaian_nilai' => 4,
                'satuan' => 'doa',
                'status_detail' => 'tuntas',
                'tuntas_at' => '2026-03-21',
            ],
            [
                'kategori_detail' => 'hafalan_hadits',
                'nama_target' => 'Hadits Adab Menuntut Ilmu',
                'target_nilai' => 2,
                'capaian_nilai' => 2,
                'satuan' => 'hadits',
                'status_detail' => 'tuntas',
                'tuntas_at' => '2026-03-22',
            ],
            [
                'kategori_detail' => 'bahasa_dan_literasi',
                'nama_target' => 'Mufrodat Pekanan',
                'target_nilai' => 1,
                'capaian_nilai' => 1,
                'satuan' => 'paket',
                'status_detail' => 'tuntas',
                'tuntas_at' => '2026-03-22',
                'detail' => 'Kosakata pekanan selesai disetor.',
            ],
        ]);

        $pencapaian->updates()->createMany([
            [
                'tanggal_update' => '2026-03-23',
                'kategori_update' => 'target_berikutnya',
                'judul_capaian' => 'Murajaah Juz 30',
                'jumlah_tambahan' => 0,
                'status_update' => 'progres',
                'pamong_nama' => 'Pamong Target',
                'detail' => 'Fokus konsistensi murojaah dan kelancaran.',
            ],
            [
                'tanggal_update' => '2026-03-24',
                'kategori_update' => 'catatan_pembinaan',
                'judul_capaian' => 'Perlu menjaga ritme hafalan',
                'jumlah_tambahan' => 0,
                'status_update' => 'butuh_lanjutan',
                'pamong_nama' => 'Pamong Target',
                'detail' => 'Masih perlu konsisten pada murajaah malam.',
            ],
        ]);

        $pencapaian->refresh();

        $this->assertSame(5, $pencapaian->target_jumlah_surat);
        $this->assertSame(4, $pencapaian->target_jumlah_doa);
        $this->assertSame(2, $pencapaian->target_jumlah_hadits);
        $this->assertSame(5, $pencapaian->jumlah_surat_dihafal);
        $this->assertSame(4, $pencapaian->jumlah_doa_dihafal);
        $this->assertSame(2, $pencapaian->jumlah_hadits_dihafal);
        $this->assertSame('tercapai_sebagian', $pencapaian->status_pencapaian);
        $this->assertStringContainsString('An-Naba sampai Al-Lail', (string) $pencapaian->surat_quran_tuntas);
        $this->assertStringContainsString('Doa Harian Pilihan', (string) $pencapaian->hafalan_doa);
        $this->assertStringContainsString('Hadits Adab Menuntut Ilmu', (string) $pencapaian->hadits_tuntas);
        $this->assertStringContainsString('Mufrodat Pekanan', (string) $pencapaian->hafalan_lainnya);
        $this->assertStringContainsString('Murajaah Juz 30', (string) $pencapaian->target_berikutnya);
        $this->assertStringContainsString('konsisten', (string) $pencapaian->catatan);
    }

    public function test_boarding_rapot_syncs_owned_sources_and_preview_preserves_manual_notes(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Abiel Shahreza',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $pamong = User::query()->create([
            'name' => 'Pamong X',
            'username' => 'pamong-x',
            'password' => 'secret123',
            'boarding_rombel_scope' => ['X.I / 2025-2026'],
        ]);
        $pamong->assignRole('pamong_putra');

        $pamongLain = User::query()->create([
            'name' => 'Pamong Y',
            'username' => 'pamong-y',
            'password' => 'secret123',
            'boarding_rombel_scope' => ['X.I / 2025-2026'],
        ]);
        $pamongLain->assignRole('pamong_putra');

        $pencapaian = BoardingPencapaian::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_user_id' => $pamong->id,
            'status_pencapaian' => 'proses',
        ]);

        $pencapaian->details()->createMany([
            [
                'kategori_detail' => 'surat_quran_tuntas',
                'nama_target' => 'An-Naba sampai Asy-Syams',
                'target_nilai' => 8,
                'capaian_nilai' => 8,
                'satuan' => 'surat',
                'status_detail' => 'tuntas',
                'tuntas_at' => '2026-03-20',
            ],
            [
                'kategori_detail' => 'hafalan_doa',
                'nama_target' => 'Doa Harian',
                'target_nilai' => 6,
                'capaian_nilai' => 4,
                'satuan' => 'doa',
                'status_detail' => 'proses',
            ],
            [
                'kategori_detail' => 'hafalan_hadits',
                'nama_target' => 'Hadits Pilihan',
                'target_nilai' => 4,
                'capaian_nilai' => 2,
                'satuan' => 'hadits',
                'status_detail' => 'proses',
            ],
        ]);

        $pencapaian->updates()->createMany([
            [
                'tanggal_update' => '2026-03-24',
                'kategori_update' => 'target_berikutnya',
                'judul_capaian' => 'Melanjutkan murajaah An-Naziat',
                'jumlah_tambahan' => 0,
                'status_update' => 'progres',
                'pamong_nama' => 'Pamong X',
                'detail' => 'Fokus menjaga kelancaran hafalan lama.',
            ],
            [
                'tanggal_update' => '2026-03-24',
                'kategori_update' => 'catatan_pembinaan',
                'judul_capaian' => 'Perlu penguatan disiplin setor',
                'jumlah_tambahan' => 0,
                'status_update' => 'progres',
                'pamong_nama' => 'Pamong X',
                'detail' => 'Pendampingan malam tetap dijadwalkan.',
            ],
        ]);

        BoardingKonselingMt::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_user_id' => $pamong->id,
            'tanggal_konseling' => '2026-03-25',
            'kategori' => 'Motivasi',
            'prioritas' => 'sedang',
            'status_tindak_lanjut' => 'dipantau',
            'ringkasan_masalah' => 'Perlu penguatan fokus belajar.',
            'tindak_lanjut' => 'Pendampingan belajar 2 kali seminggu.',
            'konselor' => 'Pamong X',
        ]);

        BoardingKonselingMt::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_user_id' => $pamongLain->id,
            'tanggal_konseling' => '2026-03-24',
            'kategori' => 'Catatan lain',
            'prioritas' => 'rendah',
            'status_tindak_lanjut' => 'terbuka',
            'ringkasan_masalah' => 'Catatan pamong lain.',
            'tindak_lanjut' => 'Tidak boleh ikut rapot pamong X.',
            'konselor' => 'Pamong Y',
        ]);

        $keuangan = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_user_id' => $pamong->id,
            'pamong_nama' => 'Pamong X',
            'kategori_asrama' => 'putra',
        ]);

        $keuangan->transaksis()->createMany([
            [
                'tanggal_transaksi' => '2026-03-20',
                'jenis_transaksi' => 'titipan_uang_saku',
                'nominal' => 300000,
            ],
            [
                'tanggal_transaksi' => '2026-03-21',
                'jenis_transaksi' => 'pemberian_uang_saku',
                'nominal' => 100000,
            ],
            [
                'tanggal_transaksi' => '2026-03-22',
                'jenis_transaksi' => 'setoran_kas',
                'nominal' => 50000,
                'periode_bulan' => 3,
                'periode_tahun' => 2026,
            ],
        ]);

        $rapot = BoardingRapot::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_user_id' => $pamong->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => 'review',
            'tanggal_rapot' => '2026-03-25',
            'predikat_boarding' => 'jayyid',
        ]);

        $rapot->syncFromSources();
        $rapot->refresh();

        $this->assertNotNull($rapot->generated_at);
        $this->assertStringContainsString('RB/', (string) $rapot->nomor_dokumen);
        $this->assertSame('Abiel Shahreza', $rapot->rekap_payload['siswa']['nama']);
        $this->assertSame(150000, $rapot->rekap_payload['keuangan']['saldo_tersisa']);
        $this->assertSame(300000, $rapot->rekap_payload['keuangan']['total_titipan']);
        $this->assertSame(300000, $rapot->rekap_payload['keuangan']['titipan_masuk']);
        $this->assertSame(100000, $rapot->rekap_payload['keuangan']['total_pemberian']);
        $this->assertSame(100000, $rapot->rekap_payload['keuangan']['pemberian_uang_saku']);
        $this->assertSame(50000, $rapot->rekap_payload['keuangan']['total_kas']);
        $this->assertSame(50000, $rapot->rekap_payload['keuangan']['setoran_kas']);
        $this->assertSame($pamong->name, $rapot->rekap_payload['signatures']['wali_pamong_nama']);
        $this->assertCount(3, $rapot->rekap_payload['pencapaian']['detail_kelompok']);
        $this->assertCount(1, $rapot->rekap_payload['konseling']);
        $this->assertSame('Motivasi', $rapot->rekap_payload['konseling'][0]['kategori']);

        $rapot->update([
            'catatan_pamong' => 'Catatan final dari pamong untuk dicetak.',
            'rekomendasi_tindak_lanjut' => 'Rekomendasi final yang sudah disetujui.',
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Boarding',
            'username' => 'admin-boarding',
            'password' => 'secret123',
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin);

        $this->get(route('admin.boarding-rapots.preview', $rapot))
            ->assertOk()
            ->assertSee('RAPOT BOARDING')
            ->assertSee('Abiel Shahreza')
            ->assertSee('Pamong X');

        $rapot->refresh();

        $this->assertSame('Catatan final dari pamong untuk dicetak.', $rapot->catatan_pamong);
        $this->assertSame('Rekomendasi final yang sudah disetujui.', $rapot->rekomendasi_tindak_lanjut);

        $this->get(route('admin.boarding-rapots.export', $rapot))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    protected function runUserMigrations(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        $migration = require database_path('migrations/0001_01_01_000000_create_users_table.php');
        $migration->up();

        $scopeMigration = require database_path('migrations/2026_03_25_230000_add_boarding_scope_to_users_table.php');
        $scopeMigration->up();

        $rombelScopeMigration = require database_path('migrations/2026_03_26_160000_add_boarding_rombel_and_navigation_scope_to_users_table.php');
        $rombelScopeMigration->up();

        $teacherScopeMigration = require database_path('migrations/2026_03_26_170000_add_teacher_scope_and_navigation_items_to_users_table.php');
        $teacherScopeMigration->up();
    }

    protected function runPermissionMigration(): void
    {
        if (Schema::hasTable('roles')) {
            return;
        }

        $migration = require database_path('migrations/2026_01_12_111708_create_permission_tables.php');
        $migration->up();
    }

    protected function createDataSiswaTable(): void
    {
        if (Schema::hasTable('data_siswa')) {
            return;
        }

        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('rombel_saat_ini')->nullable();
            $table->string('jk', 2)->nullable();
            $table->string('status')->nullable();
            $table->string('kategori_non_aktif')->nullable();
            $table->text('alasan_non_aktif')->nullable();
            $table->timestamps();
        });
    }

    protected function runBoardingMigrations(): void
    {
        if (! Schema::hasTable('boarding_rapots')) {
            $migration = require database_path('migrations/2026_03_25_203000_create_boarding_management_tables.php');
            $migration->up();
        }

        $expandMigration = require database_path('migrations/2026_03_25_231000_expand_boarding_progress_and_rapot_tables.php');
        $expandMigration->up();

        $detailMigration = require database_path('migrations/2026_03_26_090000_add_boarding_detail_and_pamong_owner_fields.php');
        $detailMigration->up();

        $arsipHistoryMigration = require database_path('migrations/2026_03_26_182500_create_boarding_arsip_mt_histories_table.php');
        $arsipHistoryMigration->up();

        $financeCategoryMigration = require database_path('migrations/2026_03_30_130000_add_boarding_keuangan_categories.php');
        $financeCategoryMigration->up();

        $financeArusActorMigration = require database_path('migrations/2026_04_02_110000_add_arus_and_actor_fields_to_boarding_keuangan_transaksis_table.php');
        $financeArusActorMigration->up();

        $hafalanMigration = require database_path('migrations/2026_04_02_120000_create_boarding_hafalan_tables.php');
        $hafalanMigration->up();

        $maknaDanBacaanMigration = require database_path('migrations/2026_04_03_220000_create_boarding_makna_and_bacaan_tables.php');
        $maknaDanBacaanMigration->up();
    }
}
