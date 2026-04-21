<?php

namespace Tests\Feature;

use App\Filament\Resources\BoardingKeuanganSiswaResource;
use App\Filament\Resources\BoardingRapotResource;
use App\Filament\Resources\SppBillResource;
use App\Models\BoardingArsipMt;
use App\Models\BoardingArsipMtHistory;
use App\Models\BoardingKeuanganKategori;
use App\Models\BoardingKeuanganSiswa;
use App\Models\BoardingKeuanganTransaksi;
use App\Models\BoardingKeuanganTransaksiHistory;
use App\Models\BoardingKonselingMt;
use App\Models\BoardingPencapaian;
use App\Models\BoardingRapot;
use App\Models\DataSiswa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LogicException;
use ReflectionClass;
use Tests\TestCase;

class BoardingManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->runUserMigration();
        $this->runPermissionMigration();
        $this->createDataSiswaTable();
        $this->runBoardingMigration();
    }

    public function test_boarding_records_stay_linked_to_data_siswa(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Ahmad Fauzan',
            'rombel_saat_ini' => 'XII IPA 1',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        BoardingRapot::query()->create([
            'siswa_id' => $siswa->id,
            'periode_tahun' => '2026/2027',
            'semester' => 'ganjil',
            'status_rapot' => 'draft',
        ]);

        BoardingPencapaian::query()->create([
            'siswa_id' => $siswa->id,
            'status_pencapaian' => 'proses',
            'jumlah_surat_dihafal' => 10,
        ]);

        BoardingArsipMt::query()->create([
            'siswa_id' => $siswa->id,
            'angkatan_label' => 'Angkatan 2026',
            'tahun_lulus' => 2026,
        ]);

        BoardingKonselingMt::query()->create([
            'siswa_id' => $siswa->id,
            'tanggal_konseling' => '2026-03-25',
            'prioritas' => 'sedang',
            'status_tindak_lanjut' => 'terbuka',
        ]);

        $keuangan = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_nama' => 'Pamong A',
        ]);

        $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-03-25',
            'jenis_transaksi' => 'titipan_uang_saku',
            'nominal' => 200000,
        ]);

        $siswa->update(['nama' => 'Ahmad Fauzan Update']);

        $this->assertCount(1, $siswa->fresh()->boardingRapots);
        $this->assertNotNull($siswa->fresh()->boardingPencapaian);
        $this->assertNotNull($siswa->fresh()->boardingArsipMt);
        $this->assertCount(1, $siswa->fresh()->boardingKonselingMts);
        $this->assertNotNull($siswa->fresh()->boardingKeuanganSiswa);
        $this->assertSame('Ahmad Fauzan Update', BoardingRapot::query()->with('siswa')->firstOrFail()->siswa->nama);
        $this->assertSame('Ahmad Fauzan Update', BoardingKeuanganSiswa::query()->with('siswa')->firstOrFail()->siswa->nama);
    }

    public function test_boarding_keuangan_query_aggregates_transaction_totals(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Siti Rahma',
            'rombel_saat_ini' => 'XI IPS 1',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $keuangan = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_nama' => 'Pamong Putri',
            'kategori_asrama' => 'putri',
        ]);

        $keuangan->transaksis()->createMany([
            [
                'tanggal_transaksi' => '2026-03-01',
                'jenis_transaksi' => 'titipan_uang_saku',
                'nominal' => 500000,
            ],
            [
                'tanggal_transaksi' => '2026-03-10',
                'jenis_transaksi' => 'pemberian_uang_saku',
                'nominal' => 125000,
            ],
            [
                'tanggal_transaksi' => '2026-03-12',
                'jenis_transaksi' => 'setoran_kas',
                'nominal' => 50000,
                'periode_bulan' => 3,
                'periode_tahun' => 2026,
            ],
        ]);

        $row = BoardingKeuanganSiswaResource::getEloquentQuery()->firstOrFail();

        $this->assertSame(500000, (int) $row->titipan_total);
        $this->assertSame(125000, (int) $row->pemberian_total);
        $this->assertSame(50000, (int) $row->kas_total);
        $this->assertSame(175000, $row->total_keluar);
        $this->assertSame(325000, $row->saldo_tersisa);
        $this->assertSame('Rp. 325.000', BoardingKeuanganSiswa::formatRupiah($row->saldo_tersisa));
    }

    public function test_boarding_arsip_status_changes_are_logged_to_history(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Arsip',
            'rombel_saat_ini' => 'XI IPA 1',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $arsip = BoardingArsipMt::query()->create([
            'siswa_id' => $siswa->id,
            'status_arsip' => 'berangkat_tes',
        ]);

        $arsip->update([
            'status_arsip' => 'lulus_tes_mt_lanjut_sekolah',
        ]);

        $this->assertSame('Lulus Tes MT dan Lanjut Sekolah', BoardingArsipMt::statusLabel($arsip->fresh()->status_arsip));
        $this->assertSame(2, BoardingArsipMtHistory::query()->count());
        $this->assertDatabaseHas('boarding_arsip_mt_histories', [
            'boarding_arsip_mt_id' => $arsip->id,
            'status_lama' => 'berangkat_tes',
            'status_baru' => 'lulus_tes_mt_lanjut_sekolah',
        ]);
    }

    public function test_navigation_moves_boarding_resources_into_manajemen_boarding_group(): void
    {
        $this->assertSame('Manajemen Boarding', BoardingRapotResource::getNavigationGroup());
        $this->assertFalse(SppBillResource::shouldRegisterNavigation());
    }

    public function test_finance_categories_support_builtins_custom_and_legacy_compatibility(): void
    {
        $this->assertEqualsCanonicalizing(
            [
                'kas_umum' => 'kas umum',
                'kas_kamar' => 'kas kamar',
                'qurban' => 'qurban',
                'isrun' => 'isrun',
            ],
            BoardingKeuanganKategori::query()
                ->where('is_system', true)
                ->pluck('nama', 'slug')
                ->all(),
        );

        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Kategori',
            'rombel_saat_ini' => 'XI IPA 2',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $keuangan = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_nama' => 'Pamong Kategori',
            'kategori_asrama' => 'putra',
        ]);

        $custom = BoardingKeuanganKategori::createCustom('tabungan event');

        $createdFromCategory = $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-03-15',
            'boarding_keuangan_kategori_id' => $custom->id,
            'nominal' => 70000,
        ]);

        $legacy = $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-03-16',
            'jenis_transaksi' => 'setoran_kas',
            'nominal' => 40000,
            'periode_bulan' => 3,
            'periode_tahun' => 2026,
        ]);

        $unknownLegacy = $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-03-17',
            'jenis_transaksi' => 'legacy_tak_dikenal',
            'nominal' => 12000,
        ]);

        $row = BoardingKeuanganSiswaResource::getEloquentQuery()->firstOrFail();

        $this->assertSame('kategori:tabungan_event', $createdFromCategory->fresh()->jenis_transaksi);
        $this->assertNotNull($legacy->fresh()->boarding_keuangan_kategori_id);
        $this->assertSame(40000, (int) $row->kas_total);
        $this->assertSame('legacy_tak_dikenal', $unknownLegacy->fresh()->kategori_label);
    }

    public function test_unused_custom_finance_category_can_be_deleted(): void
    {
        $custom = BoardingKeuanganKategori::createCustom('kategori hapus aman');

        $custom->delete();

        $this->assertDatabaseMissing('boarding_keuangan_kategoris', [
            'id' => $custom->id,
        ]);
    }

    public function test_custom_finance_category_with_transactions_cannot_be_deleted(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Hapus Kategori',
            'rombel_saat_ini' => 'XI IPA 3',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $keuangan = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_nama' => 'Pamong Hapus',
            'kategori_asrama' => 'putra',
        ]);

        $custom = BoardingKeuanganKategori::createCustom('kategori masih dipakai');

        $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-03-18',
            'boarding_keuangan_kategori_id' => $custom->id,
            'nominal' => 50000,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Kategori ini masih dipakai transaksi');

        $custom->delete();
    }

    public function test_finance_category_slug_scope_matches_plain_and_prefixed_custom_transactions(): void
    {
        $siswaA = DataSiswa::query()->create([
            'nama' => 'Santri Prefix',
            'rombel_saat_ini' => 'XI IPA 4',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $siswaB = DataSiswa::query()->create([
            'nama' => 'Santri Plain',
            'rombel_saat_ini' => 'XI IPA 4',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $siswaC = DataSiswa::query()->create([
            'nama' => 'Santri Lain',
            'rombel_saat_ini' => 'XI IPA 4',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $slug = 'qa_mobile_custom_20260331';
        $custom = BoardingKeuanganKategori::query()->create([
            'nama' => 'qa mobile custom',
            'slug' => $slug,
            'is_system' => false,
        ]);

        $keuanganA = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswaA->id,
            'pamong_nama' => 'Pamong QA',
            'kategori_asrama' => 'putra',
        ]);
        $keuanganB = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswaB->id,
            'pamong_nama' => 'Pamong QA',
            'kategori_asrama' => 'putra',
        ]);
        $keuanganC = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswaC->id,
            'pamong_nama' => 'Pamong QA',
            'kategori_asrama' => 'putra',
        ]);

        $keuanganA->transaksis()->create([
            'tanggal_transaksi' => '2026-03-31',
            'boarding_keuangan_kategori_id' => $custom->id,
            'jenis_transaksi' => 'kategori:'.$slug,
            'nominal' => 10000,
        ]);

        // Legacy custom rows may still store plain slug value.
        $keuanganB->transaksis()->create([
            'tanggal_transaksi' => '2026-03-31',
            'jenis_transaksi' => $slug,
            'nominal' => 15000,
        ]);

        $keuanganC->transaksis()->create([
            'tanggal_transaksi' => '2026-03-31',
            'jenis_transaksi' => 'kategori:slug_lain',
            'nominal' => 20000,
        ]);

        $matchedNames = BoardingKeuanganSiswa::query()
            ->whereHas('transaksis', fn ($query) => $query->forCategorySlug($slug))
            ->with('siswa')
            ->get()
            ->pluck('siswa.nama')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['Santri Plain', 'Santri Prefix'], $matchedNames);
    }

    public function test_builtin_finance_category_cannot_be_demoted_or_renamed(): void
    {
        $builtin = BoardingKeuanganKategori::query()->where('slug', 'kas_umum')->firstOrFail();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Kategori bawaan sistem tidak dapat diubah.');

        $builtin->update([
            'is_system' => false,
            'nama' => 'kas umum ubah',
        ]);
    }

    public function test_finance_query_stays_readable_when_category_schema_is_missing(): void
    {
        Schema::dropIfExists('boarding_keuangan_transaksis');

        Schema::create('boarding_keuangan_transaksis', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('boarding_keuangan_siswa_id')
                ->constrained('boarding_keuangan_siswas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->date('tanggal_transaksi');
            $table->string('jenis_transaksi', 40);
            $table->unsignedInteger('nominal');
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->unsignedSmallInteger('periode_tahun')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['jenis_transaksi', 'tanggal_transaksi'], 'boarding_keuangan_jenis_tanggal_index');
        });

        if (Schema::hasTable('boarding_keuangan_kategoris')) {
            Schema::drop('boarding_keuangan_kategoris');
        }

        $this->resetBoardingKeuanganRuntimeCaches();

        BoardingKeuanganKategori::ensureBuiltinsSeeded();

        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Legacy Kas',
            'rombel_saat_ini' => 'XI IPA 5',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $keuangan = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_nama' => 'Pamong Legacy',
            'kategori_asrama' => 'putra',
        ]);

        $keuangan->transaksis()->createMany([
            [
                'tanggal_transaksi' => '2026-03-20',
                'jenis_transaksi' => 'titipan_uang_saku',
                'nominal' => 90000,
            ],
            [
                'tanggal_transaksi' => '2026-03-21',
                'jenis_transaksi' => 'pemberian_uang_saku',
                'nominal' => 25000,
            ],
        ]);

        $row = BoardingKeuanganSiswaResource::getEloquentQuery()->firstOrFail();

        $this->assertSame(90000, (int) $row->titipan_total);
        $this->assertSame(25000, (int) $row->pemberian_total);
        $this->assertSame(0, (int) $row->kas_total);
        $this->assertSame(0, $row->total_kategori_custom);
    }

    public function test_finance_profile_write_stays_safe_when_pamong_owner_column_is_missing(): void
    {
        Schema::dropIfExists('boarding_keuangan_transaksis');
        Schema::dropIfExists('boarding_keuangan_siswas');

        Schema::create('boarding_keuangan_siswas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('siswa_id');
            $table->string('pamong_nama', 100)->nullable();
            $table->string('angkatan_label', 100)->nullable();
            $table->string('kategori_asrama', 30)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique('siswa_id', 'boarding_keuangan_siswa_unique');
            $table->index('siswa_id', 'boarding_keuangan_siswa_index');
            $table->index('pamong_nama');
        });

        $this->resetBoardingKeuanganRuntimeCaches();

        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Tanpa Owner',
            'rombel_saat_ini' => 'XI IPA 6',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $record = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_nama' => 'Pamong Legacy Owner',
            'kategori_asrama' => 'putri',
        ]);

        $this->assertNotNull($record->id);
        $this->assertFalse(BoardingKeuanganSiswa::pamongUserColumnAvailable());
    }

    public function test_finance_category_prefers_stored_default_nominal_then_falls_back_to_latest_history(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Default Nominal',
            'rombel_saat_ini' => 'XI IPA 7',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $keuangan = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_nama' => 'Pamong Nominal',
            'kategori_asrama' => 'putra',
        ]);

        $kategoriDenganDefault = BoardingKeuanganKategori::createCustom('iuran tetap bulanan', 250000);
        $kategoriHistori = BoardingKeuanganKategori::createCustom('iuran histori');

        $this->assertDatabaseHas('boarding_keuangan_kategoris', [
            'id' => $kategoriDenganDefault->id,
            'default_nominal' => 250000,
        ]);

        $keuangan->transaksis()->createMany([
            [
                'tanggal_transaksi' => '2026-03-01',
                'boarding_keuangan_kategori_id' => $kategoriDenganDefault->id,
                'nominal' => 50000,
            ],
            [
                'tanggal_transaksi' => '2026-03-15',
                'boarding_keuangan_kategori_id' => $kategoriDenganDefault->id,
                'nominal' => 125000,
            ],
            [
                'tanggal_transaksi' => '2026-03-16',
                'boarding_keuangan_kategori_id' => $kategoriHistori->id,
                'nominal' => 70000,
            ],
            [
                'tanggal_transaksi' => '2026-03-17',
                'boarding_keuangan_kategori_id' => $kategoriHistori->id,
                'nominal' => 90000,
            ],
        ]);

        $this->assertSame(
            250000,
            BoardingKeuanganTransaksi::preferredNominalForCategory($keuangan->id, $kategoriDenganDefault->id),
        );
        $this->assertSame(
            125000,
            BoardingKeuanganTransaksi::suggestedNominalForCategory($keuangan->id, $kategoriDenganDefault->id),
        );
        $this->assertSame(
            90000,
            BoardingKeuanganTransaksi::preferredNominalForCategory($keuangan->id, $kategoriHistori->id),
        );
        $this->assertNull(BoardingKeuanganTransaksi::suggestedNominalForCategory($keuangan->id, 999999));
    }

    public function test_finance_category_default_nominal_flow_stays_safe_when_default_column_is_missing(): void
    {
        Schema::dropIfExists('boarding_keuangan_kategoris');

        Schema::create('boarding_keuangan_kategoris', function (Blueprint $table): void {
            $table->id();
            $table->string('nama', 100);
            $table->string('slug', 100)->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $this->resetBoardingKeuanganRuntimeCaches();

        BoardingKeuanganKategori::ensureBuiltinsSeeded();

        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Legacy Default Nominal',
            'rombel_saat_ini' => 'XI IPA 9',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $keuangan = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_nama' => 'Pamong Legacy Default',
            'kategori_asrama' => 'putra',
        ]);

        $custom = BoardingKeuanganKategori::createCustom('kategori tanpa kolom default', 345000);

        $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-03-18',
            'boarding_keuangan_kategori_id' => $custom->id,
            'nominal' => 88000,
        ]);

        $this->assertSame(
            88000,
            BoardingKeuanganTransaksi::preferredNominalForCategory($keuangan->id, $custom->id),
        );
    }

    public function test_finance_transaction_flow_labels_detect_uang_masuk_and_keluar_consistently(): void
    {
        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Arus Transaksi',
            'rombel_saat_ini' => 'XI IPA 8',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        $keuangan = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_nama' => 'Pamong Arus',
            'kategori_asrama' => 'putri',
        ]);

        $masukKategoriId = BoardingKeuanganKategori::idBySlug('kas_umum');
        $keluarKategoriId = BoardingKeuanganKategori::idBySlug('qurban');

        $this->assertNotNull($masukKategoriId);
        $this->assertNotNull($keluarKategoriId);

        $masukDariKategori = $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-03-20',
            'boarding_keuangan_kategori_id' => $masukKategoriId,
            'nominal' => 100000,
        ]);

        $keluarDariKategori = $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-03-21',
            'boarding_keuangan_kategori_id' => $keluarKategoriId,
            'nominal' => 25000,
        ]);

        $masukLegacy = $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-03-22',
            'jenis_transaksi' => 'titipan_uang_saku',
            'nominal' => 120000,
        ]);

        $keluarLegacy = $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-03-23',
            'jenis_transaksi' => 'setoran_kas',
            'nominal' => 30000,
        ]);

        $masukDariJenisKategori = $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-03-24',
            'jenis_transaksi' => 'kategori:kas_umum',
            'nominal' => 90000,
        ]);

        $masukDariSlugDash = $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-03-25',
            'jenis_transaksi' => 'kas-umum',
            'nominal' => 70000,
        ]);

        $this->assertTrue($masukDariKategori->fresh()->isUangMasuk());
        $this->assertFalse($keluarDariKategori->fresh()->isUangMasuk());
        $this->assertTrue($masukLegacy->fresh()->isUangMasuk());
        $this->assertFalse($keluarLegacy->fresh()->isUangMasuk());
        $this->assertTrue($masukDariJenisKategori->fresh()->isUangMasuk());
        $this->assertTrue($masukDariSlugDash->fresh()->isUangMasuk());

        $this->assertSame('masuk', $masukDariKategori->fresh()->arus);
        $this->assertSame('keluar', $keluarDariKategori->fresh()->arus);
    }

    public function test_finance_transaction_sets_creator_updater_and_logs_actor_history(): void
    {
        $user = User::query()->create([
            'name' => 'Admin Keuangan',
            'username' => 'admin-keuangan',
            'password' => 'secret123',
        ]);

        $this->actingAs($user);

        $siswa = DataSiswa::query()->create([
            'nama' => 'Santri Riwayat Keuangan',
            'rombel_saat_ini' => 'XI IPA 10',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $keuangan = BoardingKeuanganSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'pamong_nama' => 'Pamong Riwayat',
            'kategori_asrama' => 'putra',
        ]);

        $masukKategoriId = BoardingKeuanganKategori::idBySlug('kas_umum');
        $keluarKategoriId = BoardingKeuanganKategori::idBySlug('qurban');

        $this->assertNotNull($masukKategoriId);
        $this->assertNotNull($keluarKategoriId);

        $transaksi = $keuangan->transaksis()->create([
            'tanggal_transaksi' => '2026-04-02',
            'boarding_keuangan_kategori_id' => $masukKategoriId,
            'nominal' => 150000,
        ]);

        $this->assertDatabaseHas('boarding_keuangan_transaksis', [
            'id' => $transaksi->id,
            'arus' => 'masuk',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $transaksi->update([
            'boarding_keuangan_kategori_id' => $keluarKategoriId,
            'nominal' => 50000,
        ]);

        $this->assertDatabaseHas('boarding_keuangan_transaksis', [
            'id' => $transaksi->id,
            'arus' => 'keluar',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->assertSame(2, BoardingKeuanganTransaksiHistory::query()->where('boarding_keuangan_transaksi_id', $transaksi->id)->count());

        $this->assertDatabaseHas('boarding_keuangan_transaksi_histories', [
            'boarding_keuangan_transaksi_id' => $transaksi->id,
            'aksi' => 'dibuat',
            'user_id' => $user->id,
            'user_name' => $user->name,
        ]);

        $this->assertDatabaseHas('boarding_keuangan_transaksi_histories', [
            'boarding_keuangan_transaksi_id' => $transaksi->id,
            'aksi' => 'diperbarui',
            'user_id' => $user->id,
            'user_name' => $user->name,
        ]);

        $this->assertSame('keluar', $transaksi->fresh()->arus);
        $this->assertFalse($transaksi->fresh()->isUangMasuk());
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
            $table->timestamps();
        });
    }

    protected function runUserMigration(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        $migration = require database_path('migrations/0001_01_01_000000_create_users_table.php');
        $migration->up();
    }

    protected function runPermissionMigration(): void
    {
        if (Schema::hasTable('roles')) {
            return;
        }

        $migration = require database_path('migrations/2026_01_12_111708_create_permission_tables.php');
        $migration->up();
    }

    protected function runBoardingMigration(): void
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

        $financeCategoryDefaultNominalMigration = require database_path('migrations/2026_04_01_090000_add_default_nominal_to_boarding_keuangan_kategoris_table.php');
        $financeCategoryDefaultNominalMigration->up();

        $financeArusActorMigration = require database_path('migrations/2026_04_02_110000_add_arus_and_actor_fields_to_boarding_keuangan_transaksis_table.php');
        $financeArusActorMigration->up();

        $hafalanMigration = require database_path('migrations/2026_04_02_120000_create_boarding_hafalan_tables.php');
        $hafalanMigration->up();

        $maknaDanBacaanMigration = require database_path('migrations/2026_04_03_220000_create_boarding_makna_and_bacaan_tables.php');
        $maknaDanBacaanMigration->up();
    }

    protected function resetBoardingKeuanganRuntimeCaches(): void
    {
        BoardingKeuanganSiswa::flushRuntimeSchemaCache();
        BoardingKeuanganKategori::flushRuntimeSchemaCache();
        BoardingKeuanganTransaksi::flushRuntimeSchemaCache();

        $reflection = new ReflectionClass(BoardingKeuanganSiswa::class);
        $property = $reflection->getProperty('pamongOwnershipColumn');
        $property->setValue(null, 'pamong_user_id');
    }
}
