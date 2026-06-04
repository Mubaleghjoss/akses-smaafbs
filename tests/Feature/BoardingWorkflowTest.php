<?php

namespace Tests\Feature;

use App\Filament\Resources\BoardingKeuanganSiswaResource;
use App\Filament\Resources\BoardingPencapaianResource;
use App\Filament\Resources\BoardingRapotResource;
use App\Filament\Resources\DataSiswaResource;
use App\Filament\Resources\UserResource;
use App\Models\BoardingBacaanAssessment;
use App\Models\BoardingHafalanAssessment;
use App\Models\BoardingHafalanPoint;
use App\Models\BoardingKeuanganSiswa;
use App\Models\BoardingKonselingMt;
use App\Models\BoardingMaknaProgress;
use App\Models\BoardingMateriProgress;
use App\Models\BoardingMtProgress;
use App\Models\BoardingPencapaian;
use App\Models\BoardingRapot;
use App\Models\DataSiswa;
use App\Models\Pengaturan;
use App\Models\User;
use App\Support\Boarding\BoardingRapotBulkPrintSupport;
use App\Support\Boarding\BoardingRapotSheetRows;
use Database\Seeders\InitialAdminSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BoardingWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->runUserMigrations();
        $this->runPermissionMigration();
        $this->createPengaturanTable();
        $this->createDataSiswaTable();
        $this->runBoardingMigrations();
        BoardingKeuanganSiswa::flushRuntimeSchemaCache();
        BoardingRapot::flushDocumentSettingSnapshot();
        (new InitialAdminSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_pamong_scope_uses_student_gender_and_class_for_pencapaian_and_rapot(): void
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

        BoardingRapot::query()->create([
            'siswa_id' => $putraScope->id,
            'pamong_user_id' => $pamong->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => 'draft',
            'tanggal_rapot' => '2026-06-03',
        ]);
        BoardingRapot::query()->create([
            'siswa_id' => $putraScopeKelasDua->id,
            'pamong_user_id' => $pamong->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => 'draft',
            'tanggal_rapot' => '2026-06-03',
        ]);
        BoardingRapot::query()->create([
            'siswa_id' => $putraOwnerLain->id,
            'pamong_user_id' => $pamongLain->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => 'draft',
            'tanggal_rapot' => '2026-06-03',
        ]);
        BoardingRapot::query()->create([
            'siswa_id' => $putriScope->id,
            'pamong_user_id' => $pamong->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => 'draft',
            'tanggal_rapot' => '2026-06-03',
        ]);
        BoardingRapot::query()->create([
            'siswa_id' => $bedaAngkatan->id,
            'pamong_user_id' => $pamong->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => 'draft',
            'tanggal_rapot' => '2026-06-03',
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
        sort($visiblePencapaian);
        $visibleRapot = BoardingRapotResource::getEloquentQuery()->with('siswa')->get()->pluck('siswa.nama')->all();
        sort($visibleRapot);
        $visibleKeuangan = BoardingKeuanganSiswaResource::getEloquentQuery()->with('siswa')->get()->pluck('siswa.nama')->all();

        $this->assertSame(['Putra Milik Pamong Lain', 'Putra Scope', 'Putra Scope Kelas Dua'], $visibleStudents);
        $this->assertSame(['Putra Milik Pamong Lain', 'Putra Scope', 'Putra Scope Kelas Dua'], $visiblePencapaian);
        $this->assertSame(['Putra Milik Pamong Lain', 'Putra Scope', 'Putra Scope Kelas Dua'], $visibleRapot);
        $this->assertSame(['Putra Scope', 'Putra Scope Kelas Dua'], $visibleKeuangan);
        $this->assertContains('Boarding', $pamong->resolvedNavigationGroups());
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

    public function test_boarding_rapot_with_pamong_uses_global_pencapaian_data(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Murid Global Pencapaian',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $pamong = User::query()->create([
            'name' => 'Pamong Rapot Global',
            'username' => 'pamong-rapot-global',
            'password' => 'secret123',
            'boarding_rombel_scope' => ['X.I / 2025-2026'],
        ]);
        $pamong->assignRole('pamong_putra');

        $pencapaian = BoardingPencapaian::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_user_id' => null,
            'status_pencapaian' => 'tercapai_sebagian',
            'materi_rapot_scope' => BoardingPencapaian::MATERI_RAPOT_SCOPE_BOARDING,
        ]);

        BoardingMateriProgress::ensureDefaultsForPencapaian($pencapaian);
        BoardingMateriProgress::query()
            ->where('boarding_pencapaian_id', $pencapaian->getKey())
            ->where('target_key', 'kedisiplinan')
            ->update([
                'grade' => 'baik',
                'notes' => 'Sudah konsisten mengikuti kegiatan.',
                'updated_at' => now(),
            ]);

        BoardingBacaanAssessment::query()->create([
            'boarding_pencapaian_id' => $pencapaian->getKey(),
            'assessed_at' => '2026-06-02',
            'kelas_bacaan' => 'a',
            'pp_grade' => 'B',
            'kl_grade' => 'B',
            'tj_grade' => 'B',
            'mj_grade' => 'B',
            'reviewer_user_id' => $pamong->id,
        ]);

        $rapot = BoardingRapot::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_user_id' => $pamong->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'tanggal_rapot' => '2026-06-03',
            'status_rapot' => 'draft',
            'kelas_boarding_override' => 'pegon_bacaan',
        ]);

        $rapot->syncFromSources();
        $rapot->refresh();

        $this->assertSame('Tercapai Sebagian', $rapot->rekap_payload['pencapaian']['status']);
        $this->assertSame('Materi Boarding', $rapot->rekap_payload['pencapaian']['materi_rapot_label']);
        $this->assertSame(1, $rapot->rekap_payload['pencapaian']['materi_boarding']['filled_manual_count']);
        $this->assertStringContainsString('1 simakan', $rapot->rekap_payload['pencapaian']['materi_boarding']['bacaan_quran']['summary_label']);
        $this->assertSame('Baik - Sudah konsisten mengikuti kegiatan.', $rapot->rekap_payload['pencapaian']['materi_boarding']['manual_groups'][0]['rows'][0]['grade'].' - '.$rapot->rekap_payload['pencapaian']['materi_boarding']['manual_groups'][0]['rows'][0]['notes']);
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

        $lambatanPoint = BoardingHafalanPoint::query()
            ->where('is_active', true)
            ->where('materi_key', 'lambatan')
            ->whereIn('jenis', BoardingHafalanPoint::hafalanJenis())
            ->orderBy('urutan')
            ->firstOrFail();

        BoardingHafalanAssessment::query()->create([
            'boarding_pencapaian_id' => $pencapaian->getKey(),
            'boarding_hafalan_point_id' => $lambatanPoint->getKey(),
            'assessed_at' => '2026-03-24',
            'score' => 82,
            'reviewer_user_id' => $pamong->id,
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
            'administrasi_rapot_items' => [
                [
                    'question' => 'Status Administrasi',
                    'answer' => 'Sudah diverifikasi manual.',
                ],
            ],
        ]);

        Storage::fake('public');
        Storage::disk('public')->put(
            'boarding-rapot/logo/rapot-test.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"><rect width="24" height="24" fill="#0f766e"/></svg>'
        );

        BoardingRapot::saveDocumentSettings([
            BoardingRapot::SETTING_LOGO_PATH => 'boarding-rapot/logo/rapot-test.svg',
            BoardingRapot::SETTING_KOP_SITE_NAME => 'Kop Rapot Boarding Test',
            BoardingRapot::SETTING_KOP_SUBTITLE => 'Laporan Boarding Santri',
            BoardingRapot::SETTING_KOP_CONTACT => '0812-0000-0000 | boarding.test@example.test',
            BoardingRapot::SETTING_PROLOG => 'Prolog khusus rapot boarding untuk orang tua santri.',
            BoardingRapot::SETTING_WALI_LABEL => 'Pembimbing Asrama',
            BoardingRapot::SETTING_KEPALA_LABEL => 'Penanggung Jawab Boarding',
            BoardingRapot::SETTING_MUDIR_LABEL => 'Pengesah Asrama',
            BoardingRapot::SETTING_KEPALA_NAME => 'Kepala Boarding Test',
            BoardingRapot::SETTING_MUDIR_NAME => 'Mudir Asrama Test',
            BoardingRapot::SETTING_KOTA => 'Bandung',
        ]);

        $rapot->syncFromSources();
        $rapot->refresh();

        $this->assertNotNull($rapot->generated_at);
        $this->assertStringContainsString('RB/', (string) $rapot->nomor_dokumen);
        $this->assertSame('Abiel Shahreza', $rapot->rekap_payload['siswa']['nama']);
        $this->assertSame('Belum Diisi', $rapot->rekap_payload['rapot']['kelas_boarding']);
        $this->assertSame('Kelas Lambatan', $rapot->rekap_payload['rapot']['kelas_boarding_auto']);
        $this->assertNull($rapot->rekap_payload['rapot']['kelas_boarding_override_key']);
        $this->assertSame('Status Administrasi', $rapot->rekap_payload['rapot']['administrasi_items'][0]['question']);
        $this->assertSame('Sudah diverifikasi manual.', $rapot->rekap_payload['rapot']['administrasi_items'][0]['answer']);
        $this->assertSame('Prolog khusus rapot boarding untuk orang tua santri.', $rapot->rekap_payload['document']['prolog']);
        $this->assertSame('Pembimbing Asrama', $rapot->rekap_payload['signatures']['wali_pamong_label']);
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

        $rapot->update(['kelas_boarding_override' => 'cepatan']);
        $rapot->syncFromSources();
        $rapot->refresh();

        $this->assertSame('Kelas Cepatan', $rapot->rekap_payload['rapot']['kelas_boarding']);
        $this->assertSame('Kelas Lambatan', $rapot->rekap_payload['rapot']['kelas_boarding_auto']);
        $this->assertSame('cepatan', $rapot->rekap_payload['rapot']['kelas_boarding_override_key']);
        $this->assertSame('Kelas Cepatan', $rapot->rekap_payload['rapot']['kelas_boarding_override']);
        $this->assertSame('Abiel Shahreza', $rapot->rekap_payload['siswa']['nama']);
        $this->assertCount(3, $rapot->rekap_payload['pencapaian']['detail_kelompok']);
        $this->assertSame('Materi Boarding', $rapot->rekap_payload['pencapaian']['materi_rapot_label']);

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
            ->assertSee('Materi Boarding')
            ->assertSee('Pencapaian Target Materi Boarding')
            ->assertDontSee('Target Materi Rapot Aktif')
            ->assertDontSee('Halaman 1 - Materi Boarding')
            ->assertSee('KOP RAPOT BOARDING TEST')
            ->assertSee('LAPORAN BOARDING SANTRI')
            ->assertSee('Prolog khusus rapot boarding untuk orang tua santri.')
            ->assertSee('Kelas Boarding')
            ->assertSee('Kelas Cepatan')
            ->assertSee('src="data:', false)
            ->assertSee('Status Administrasi')
            ->assertSee('Sudah diverifikasi manual.')
            ->assertSee('Pembimbing Asrama')
            ->assertSee('Kepala Boarding Test')
            ->assertDontSee('Halaman 2 - Rapot MT')
            ->assertDontSee('Detail Target Boarding')
            ->assertDontSee('Riwayat Konseling Terbaru')
            ->assertDontSee('Predikat')
            ->assertSee('Pengetesan Makna')
            ->assertSee('Abiel Shahreza')
            ->assertSee('Pamong X');

        $this->get(route('admin.boarding-rapots.rekap', $rapot))
            ->assertOk()
            ->assertSee('REKAP DATA RAPOT BOARDING')
            ->assertSee('Detail Target Boarding')
            ->assertSee('Riwayat Konseling Terbaru')
            ->assertSee('Motivasi');

        $rapot->refresh();

        $this->assertSame('Catatan final dari pamong untuk dicetak.', $rapot->catatan_pamong);
        $this->assertSame('Rekomendasi final yang sudah disetujui.', $rapot->rekomendasi_tindak_lanjut);

        $this->get(route('admin.boarding-rapots.export', $rapot))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_boarding_rapot_manual_signature_fields_override_document_defaults(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Tanda Tangan',
            'rombel_saat_ini' => 'XI.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $pamong = User::query()->create([
            'name' => 'Rizki',
            'username' => 'pamong-signature-settings',
            'password' => 'secret123',
            'boarding_rombel_scope' => ['XI.I / 2025-2026'],
        ]);

        $rapot = BoardingRapot::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_user_id' => $pamong->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => 'review',
            'tanggal_rapot' => '2026-05-30',
            'wali_pamong_nama' => 'Rizki',
            'kepala_boarding_nama' => ':',
            'mudir_asrama_nama' => ':',
            'tempat_cetak' => 'Bogor',
        ]);

        BoardingRapot::saveDocumentSettings([
            BoardingRapot::SETTING_KOTA => 'Tangerang',
            BoardingRapot::SETTING_WALI_LABEL => 'Kepala Sekolah',
            BoardingRapot::SETTING_WALI_NAME => 'H. Toharyono, S.Si.',
            BoardingRapot::SETTING_KEPALA_LABEL => 'Kepala Boarding',
            BoardingRapot::SETTING_KEPALA_NAME => 'Yusuf Choirii',
            BoardingRapot::SETTING_MUDIR_LABEL => 'Pamong',
            BoardingRapot::SETTING_MUDIR_NAME => 'Ustadz Pamong Tiga',
        ]);

        $rapot->syncFromSources();
        $rapot->refresh();

        $this->assertSame('Bogor', $rapot->rekap_payload['school']['kota']);
        $this->assertSame('Kepala Sekolah', $rapot->rekap_payload['signatures']['wali_pamong_label']);
        $this->assertSame('H. Toharyono, S.Si.', $rapot->rekap_payload['signatures']['wali_pamong_nama']);
        $this->assertSame('Kepala Boarding', $rapot->rekap_payload['signatures']['kepala_boarding_label']);
        $this->assertSame('Yusuf Choirii', $rapot->rekap_payload['signatures']['kepala_boarding_nama']);
        $this->assertSame('Pamong', $rapot->rekap_payload['signatures']['mudir_asrama_label']);
        $this->assertSame('Ustadz Pamong Tiga', $rapot->rekap_payload['signatures']['mudir_asrama_nama']);
    }

    public function test_boarding_rapots_are_created_and_refreshed_from_filled_pencapaian_targets(): void
    {
        $filledStudent = DataSiswa::query()->create([
            'nama' => 'Santri Terisi',
            'rombel_saat_ini' => 'XI.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $blankStudent = DataSiswa::query()->create([
            'nama' => 'Santri Kosong',
            'rombel_saat_ini' => 'XI.I / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $pamong = User::query()->create([
            'name' => 'Pamong Auto Rapot',
            'username' => 'pamong-auto-rapot',
            'password' => 'secret123',
            'boarding_rombel_scope' => ['XI.I / 2025-2026'],
        ]);
        $pamong->assignRole('pamong_putra');

        $admin = User::query()->create([
            'name' => 'Admin Auto Rapot',
            'username' => 'admin-auto-rapot',
            'password' => 'secret123',
        ]);
        $admin->assignRole('admin');

        $filledPencapaian = BoardingPencapaian::query()->create([
            'siswa_id' => $filledStudent->id,
            'pamong_user_id' => $pamong->id,
            'status_pencapaian' => 'proses',
        ]);

        BoardingPencapaian::query()->create([
            'siswa_id' => $blankStudent->id,
            'pamong_user_id' => $pamong->id,
            'status_pencapaian' => 'proses',
        ]);

        $detail = $filledPencapaian->details()->create([
            'kategori_detail' => 'hafalan_surat',
            'nama_target' => 'Al-Fill',
            'target_nilai' => 2,
            'capaian_nilai' => 1,
            'satuan' => 'surat',
            'status_detail' => 'proses',
        ]);

        $point = BoardingHafalanPoint::query()
            ->where('is_active', true)
            ->where('materi_key', 'pegon_bacaan')
            ->whereIn('jenis', BoardingHafalanPoint::hafalanJenis())
            ->orderBy('urutan')
            ->firstOrFail();

        BoardingHafalanAssessment::query()->create([
            'boarding_pencapaian_id' => $filledPencapaian->getKey(),
            'boarding_hafalan_point_id' => $point->getKey(),
            'assessed_at' => '2026-05-29',
            'score' => 86,
            'reviewer_user_id' => $admin->id,
        ]);

        BoardingMaknaProgress::ensureDefaultsForPencapaian($filledPencapaian);
        BoardingMaknaProgress::query()
            ->where('boarding_pencapaian_id', $filledPencapaian->getKey())
            ->where('target_key', 'quran_juz_1')
            ->firstOrFail()
            ->update(['status' => 'khatam']);

        BoardingMtProgress::ensureDefaultsForPencapaian($filledPencapaian);
        BoardingMtProgress::query()
            ->where('boarding_pencapaian_id', $filledPencapaian->getKey())
            ->where('target_key', 'muslim_jilid_1')
            ->firstOrFail()
            ->update([
                'progress_value' => 12,
                'target_total' => 20,
                'notes' => 'Sudah khatam sebagian.',
            ]);
        BoardingMtProgress::query()
            ->where('boarding_pencapaian_id', $filledPencapaian->getKey())
            ->where('target_key', 'kesemangatan')
            ->firstOrFail()
            ->update([
                'grade' => 'baik',
                'notes' => 'Semangat setor meningkat.',
            ]);

        BoardingMateriProgress::ensureDefaultsForPencapaian($filledPencapaian);
        BoardingMateriProgress::query()
            ->where('boarding_pencapaian_id', $filledPencapaian->getKey())
            ->where('target_key', 'pengetesan_makna')
            ->firstOrFail()
            ->update([
                'grade' => 'baik',
                'notes' => 'Pengetesan makna baik.',
            ]);
        BoardingMateriProgress::query()
            ->where('boarding_pencapaian_id', $filledPencapaian->getKey())
            ->where('target_key', 'kedisiplinan')
            ->firstOrFail()
            ->update([
                'grade' => 'cukup',
                'notes' => 'Perlu datang tepat waktu.',
            ]);

        BoardingBacaanAssessment::query()->create([
            'boarding_pencapaian_id' => $filledPencapaian->getKey(),
            'assessed_at' => '2026-05-30',
            'pp_grade' => 'A',
            'kl_grade' => 'B',
            'tj_grade' => 'A',
            'mj_grade' => 'B',
            'reviewer_user_id' => $admin->id,
            'notes' => 'Bacaan lancar.',
        ]);

        $result = BoardingRapot::syncFromFilledPencapaians(
            user: $admin,
            periodeTahun: '2025/2026',
            semester: 'genap',
            tanggalRapot: '2026-05-30',
            overwriteNarratives: true,
        );

        $this->assertSame(['created' => 1, 'updated' => 0, 'total' => 1], $result);
        $this->assertDatabaseHas('boarding_rapots', [
            'siswa_id' => $filledStudent->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'pamong_user_id' => $pamong->id,
        ]);
        $this->assertDatabaseMissing('boarding_rapots', [
            'siswa_id' => $blankStudent->id,
        ]);

        $rapot = BoardingRapot::query()->where('siswa_id', $filledStudent->id)->firstOrFail();

        $this->assertSame(1, $rapot->rekap_payload['pencapaian']['realisasi']['surat']);
        $this->assertSame('boarding', $rapot->rekap_payload['pencapaian']['materi_rapot_scope']);
        $this->assertNotEmpty($rapot->rekap_payload['pencapaian']['hafalan_detail']);
        $this->assertSame(1, $rapot->rekap_payload['pencapaian']['makna']['filled_count']);
        $this->assertSame(2, $rapot->rekap_payload['pencapaian']['materi_boarding']['filled_manual_count']);
        $this->assertFalse($rapot->rekap_payload['pencapaian']['mt']['is_active']);
        $this->assertStringContainsString('Materi Boarding: 2 pengetesan/catatan terisi', (string) $rapot->ringkasan_pencapaian);
        $this->assertStringNotContainsString('Materi MT:', (string) $rapot->ringkasan_pencapaian);
        $this->assertSame(1, $rapot->rekap_payload['pencapaian']['bacaan']['total_sessions']);
        $this->assertStringContainsString('Catatan dan Saran Boarding: Kedisiplinan: Cukup - Perlu datang tepat waktu.', (string) $rapot->catatan_pamong);
        $this->assertStringNotContainsString('Catatan dan Saran MT:', (string) $rapot->catatan_pamong);

        $materiBoardingSheetRows = BoardingRapotSheetRows::materiBoardingRows($rapot->rekap_payload);

        $this->assertSame('Belum Tuntas - sudah hafal 1 materi dari 24 materi', $materiBoardingSheetRows[5][2]);
        $this->assertSame('Cukup - Perlu datang tepat waktu.', $materiBoardingSheetRows[9][2]);

        $filledPencapaian->update(['materi_rapot_scope' => 'mt']);
        $rapot->refresh();

        $this->assertSame('mt', $rapot->rekap_payload['pencapaian']['materi_rapot_scope']);
        $this->assertFalse($rapot->rekap_payload['pencapaian']['materi_boarding']['is_active']);
        $this->assertSame(2, $rapot->rekap_payload['pencapaian']['mt']['filled_count']);
        $this->assertStringContainsString('Materi MT: 2 dari 11 target MT terisi', (string) $rapot->ringkasan_pencapaian);
        $this->assertStringNotContainsString('Materi Boarding:', (string) $rapot->ringkasan_pencapaian);
        $this->assertStringContainsString('Catatan dan Saran MT: Kesemangatan: Baik - Semangat setor meningkat.', (string) $rapot->catatan_pamong);
        $this->assertStringNotContainsString('Catatan dan Saran Boarding:', (string) $rapot->catatan_pamong);

        $mtSheetRows = BoardingRapotSheetRows::mtRows($rapot->rekap_payload);

        $this->assertSame('Baik - Semangat setor meningkat.', collect($mtSheetRows)->firstWhere(0, 'Kesemangatan')[1] ?? null);

        $detail->update([
            'capaian_nilai' => 2,
            'status_detail' => 'tuntas',
            'tuntas_at' => '2026-05-30',
        ]);

        $rapot->refresh();

        $this->assertSame(2, $rapot->rekap_payload['pencapaian']['realisasi']['surat']);
        $this->assertStringContainsString('Realisasi surat/doa/hadits: 2 / 0 / 0', (string) $rapot->ringkasan_pencapaian);

        BoardingMtProgress::query()
            ->where('boarding_pencapaian_id', $filledPencapaian->getKey())
            ->where('target_key', 'tugas_praktek')
            ->firstOrFail()
            ->update(['grade' => 'cukup']);

        $rapot->refresh();

        $this->assertSame(3, $rapot->rekap_payload['pencapaian']['mt']['filled_count']);
        $this->assertStringContainsString('Materi MT: 3 dari 11 target MT terisi', (string) $rapot->ringkasan_pencapaian);
    }

    public function test_boarding_rapot_index_page_renders_without_mass_sync_on_open(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Rapot Index',
            'username' => 'admin-rapot-index',
            'password' => 'secret123',
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.boarding-rapots.index'))
            ->assertOk()
            ->assertSee('Rapot Boarding');
    }

    public function test_boarding_rapot_create_and_edit_pages_render_as_full_pages(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Rapot Full Page',
            'username' => 'admin-rapot-full-page',
            'password' => 'secret123',
        ]);
        $admin->assignRole('admin');

        $siswa = DataSiswa::query()->create([
            'nama' => 'Siswa Rapot Full Page',
            'rombel_saat_ini' => 'X.1 / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $rapot = BoardingRapot::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_user_id' => $admin->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => 'draft',
            'tanggal_rapot' => '2026-05-31',
        ]);

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.boarding-rapots.create'))
            ->assertOk()
            ->assertSee('Data Rapot Boarding');

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.boarding-rapots.edit', $rapot))
            ->assertOk()
            ->assertSee('Data Rapot Boarding')
            ->assertSee('Kembali ke daftar rapot');
    }

    public function test_boarding_rapot_signature_uses_latest_pamong_profile_without_reusing_it_as_kepala_sekolah(): void
    {
        $pamong = User::query()->create([
            'name' => 'Pamong Lama',
            'username' => 'pamong-rapot-profile',
            'password' => 'secret123',
        ]);
        $pamong->assignRole('pamong_putra');

        $siswa = DataSiswa::query()->create([
            'nama' => 'Siswa Rapot Profil',
            'rombel_saat_ini' => 'X.2 / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $rapot = BoardingRapot::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_user_id' => $pamong->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => 'draft',
            'tanggal_rapot' => '2026-05-31',
            'wali_pamong_nama' => 'Pamong Lama',
            'mudir_asrama_nama' => 'Pamong Lama',
        ]);

        BoardingRapot::saveDocumentSettings([
            BoardingRapot::SETTING_WALI_LABEL => 'Kepala Sekolah',
            BoardingRapot::SETTING_WALI_NAME => '',
            BoardingRapot::SETTING_KEPALA_LABEL => 'Kepala Boarding',
            BoardingRapot::SETTING_MUDIR_LABEL => 'Pamong',
            BoardingRapot::SETTING_MUDIR_NAME => '',
        ]);

        $rapot->syncFromSources();
        $rapot->refresh();

        $this->assertSame('Kepala Sekolah', $rapot->rekap_payload['signatures']['wali_pamong_label']);
        $this->assertSame('-', $rapot->rekap_payload['signatures']['wali_pamong_nama']);
        $this->assertSame('Pamong Lama', $rapot->rekap_payload['signatures']['mudir_asrama_nama']);

        $pamong->update(['name' => 'Pamong Baru']);

        $rapot->syncFromSources();
        $rapot->refresh();

        $this->assertSame('-', $rapot->rekap_payload['signatures']['wali_pamong_nama']);
        $this->assertSame('Pamong Baru', $rapot->rekap_payload['signatures']['mudir_asrama_nama']);
    }

    public function test_boarding_rapot_print_all_outputs_ready_rapots_when_scope_is_incomplete(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Rapot Bulk Print',
            'username' => 'admin-rapot-bulk-print',
            'password' => 'secret123',
        ]);
        $admin->assignRole('admin');

        $siswaReady = DataSiswa::query()->create([
            'nama' => 'Siswa Siap Cetak',
            'rombel_saat_ini' => 'X.2 / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $siswaDraft = DataSiswa::query()->create([
            'nama' => 'Siswa Masih Draft',
            'rombel_saat_ini' => 'X.2 / 2025-2026',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        BoardingRapot::query()->create([
            'siswa_id' => $siswaReady->id,
            'pamong_user_id' => $admin->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => BoardingRapot::STATUS_READY_PRINT,
            'tanggal_rapot' => '2026-05-31',
            'kelas_boarding_override' => 'cepatan',
        ]);

        $draftRapot = BoardingRapot::query()->create([
            'siswa_id' => $siswaDraft->id,
            'pamong_user_id' => $admin->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => BoardingRapot::STATUS_DRAFT,
            'tanggal_rapot' => '2026-05-31',
            'kelas_boarding_override' => 'cepatan',
        ]);

        $params = [
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'rombel' => 'X.2 / 2025-2026',
            'jenis_kelamin' => 'all',
        ];

        $this->actingAs($admin)
            ->get(route('admin.boarding-rapots.print-all', $params))
            ->assertOk()
            ->assertSee('Print Semua Rapot Boarding')
            ->assertSee('Siswa Siap Cetak')
            ->assertDontSee('Siswa Masih Draft')
            ->assertSee('Siap Cetak');

        $draftRapot->update(['status_rapot' => BoardingRapot::STATUS_READY_PRINT]);

        $this->actingAs($admin)
            ->get(route('admin.boarding-rapots.print-all', $params))
            ->assertOk()
            ->assertSee('Print Semua Rapot Boarding')
            ->assertSee('Siswa Siap Cetak')
            ->assertSee('Siswa Masih Draft')
            ->assertSee('Siap Cetak');
    }

    public function test_boarding_rapot_bulk_print_summary_uses_pamong_scope_and_counts_incomplete_students(): void
    {
        $pamong = User::query()->create([
            'name' => 'Pamong Bulk Print',
            'username' => 'pamong-bulk-print',
            'password' => 'secret123',
            'boarding_rombel_scope' => ['X.3 / 2025-2026', 'XI.3 / 2025-2026'],
        ]);
        $pamong->assignRole('pamong_putra');

        $siswaReady = DataSiswa::query()->create([
            'nama' => 'Putra Siap',
            'rombel_saat_ini' => 'X.3 / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $siswaDraft = DataSiswa::query()->create([
            'nama' => 'Putra Draft',
            'rombel_saat_ini' => 'X.3 / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        DataSiswa::query()->create([
            'nama' => 'Putra Belum Ada Rapot',
            'rombel_saat_ini' => 'X.3 / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        DataSiswa::query()->create([
            'nama' => 'Putri Di Luar Scope',
            'rombel_saat_ini' => 'X.3 / 2025-2026',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $siswaKelasLain = DataSiswa::query()->create([
            'nama' => 'Putra Kelas Lain',
            'rombel_saat_ini' => 'XI.3 / 2025-2026',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        BoardingRapot::query()->create([
            'siswa_id' => $siswaReady->id,
            'pamong_user_id' => $pamong->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => BoardingRapot::STATUS_READY_PRINT,
            'tanggal_rapot' => '2026-05-31',
        ]);

        BoardingRapot::query()->create([
            'siswa_id' => $siswaDraft->id,
            'pamong_user_id' => $pamong->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => BoardingRapot::STATUS_DRAFT,
            'tanggal_rapot' => '2026-05-31',
        ]);

        BoardingRapot::query()->create([
            'siswa_id' => $siswaKelasLain->id,
            'pamong_user_id' => $pamong->id,
            'periode_tahun' => '2025/2026',
            'semester' => 'genap',
            'status_rapot' => BoardingRapot::STATUS_READY_PRINT,
            'tanggal_rapot' => '2026-05-31',
        ]);

        $summary = BoardingRapotBulkPrintSupport::summary(
            user: $pamong,
            periodeTahun: '2025/2026',
            semester: 'genap',
        );

        $this->assertSame(3, $summary['total_students']);
        $this->assertSame(2, $summary['total_rapots']);
        $this->assertSame(1, $summary['ready_rapots']);
        $this->assertSame(1, $summary['not_ready_rapots']);
        $this->assertSame(1, $summary['missing_rapots']);
        $this->assertFalse($summary['is_complete']);
        $this->assertStringContainsString('scope pamong', $summary['scope_label']);
        $this->assertStringContainsString('kelas X.3 / 2025-2026', $summary['scope_label']);
        $this->assertStringContainsString('putra', $summary['scope_label']);
        $this->assertStringContainsString(
            'Baru 1 dari 3 murid',
            BoardingRapotBulkPrintSupport::incompleteConfirmationText($summary),
        );

        $this->actingAs($pamong)
            ->get(route('admin.boarding-rapots.print-all', [
                'periode_tahun' => '2025/2026',
                'semester' => 'genap',
            ]))
            ->assertOk()
            ->assertSee('Putra Siap')
            ->assertDontSee('Putra Kelas Lain');
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

    protected function createPengaturanTable(): void
    {
        if (! Schema::hasTable('pengaturan')) {
            Schema::create('pengaturan', function (Blueprint $table): void {
                $table->id();
                $table->string('nama_pengaturan')->unique();
                $table->longText('nilai_pengaturan')->nullable();
            });
        }

        Pengaturan::flushRuntimeSchemaCache();
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
            $table->string('kepribadian')->nullable();
            $table->string('gaya_belajar')->nullable();
            $table->string('profiling')->nullable();
            $table->string('mbti')->nullable();
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

        $materiMigration = require database_path('migrations/2026_05_30_210000_expand_boarding_materi_master.php');
        $materiMigration->up();

        $separateMateriMigration = require database_path('migrations/2026_05_30_213000_separate_boarding_materi_tambahan_groups.php');
        $separateMateriMigration->up();

        $consolidateMateriMigration = require database_path('migrations/2026_05_30_214000_consolidate_boarding_materi_tambahan_class.php');
        $consolidateMateriMigration->up();

        $splitMateriByClassMigration = require database_path('migrations/2026_05_30_215000_split_boarding_materi_tambahan_by_class.php');
        $splitMateriByClassMigration->up();

        $scopeMateriMigration = require database_path('migrations/2026_05_30_216000_add_scope_and_mt_materi_boarding_points.php');
        $scopeMateriMigration->up();

        $mtProgressMigration = require database_path('migrations/2026_05_30_217000_create_boarding_mt_progresses_table.php');
        $mtProgressMigration->up();

        $maknaDanBacaanMigration = require database_path('migrations/2026_04_03_220000_create_boarding_makna_and_bacaan_tables.php');
        $maknaDanBacaanMigration->up();

        $kelasBacaanMigration = require database_path('migrations/2026_06_03_080000_add_kelas_bacaan_to_boarding_bacaan_assessments.php');
        $kelasBacaanMigration->up();

        $materiBoardingMigration = require database_path('migrations/2026_05_30_218000_expand_boarding_makna_and_materi_boarding.php');
        $materiBoardingMigration->up();

        $renameMaknaQuranMigration = require database_path('migrations/2026_05_30_219000_rename_boarding_makna_quran_targets.php');
        $renameMaknaQuranMigration->up();

        $materiRapotScopeMigration = require database_path('migrations/2026_05_31_080000_add_materi_rapot_scope_to_boarding_pencapaians.php');
        $materiRapotScopeMigration->up();

        $administrasiRapotMigration = require database_path('migrations/2026_05_31_100000_add_administrasi_items_to_boarding_rapots.php');
        $administrasiRapotMigration->up();

        $kelasBoardingOverrideMigration = require database_path('migrations/2026_05_31_101000_add_kelas_boarding_override_to_boarding_rapots.php');
        $kelasBoardingOverrideMigration->up();
    }
}
