<?php

namespace Tests\Feature;

use App\Filament\Pages\Assessment\AssessmentDashboard;
use App\Filament\Pages\Bk\RekapSigapPage;
use App\Filament\Resources\BkKasusResource;
use App\Filament\Resources\BkKasusResource\Pages\CreateBkKasus;
use App\Filament\Resources\BkKasusResource\Pages\EditBkKasus;
use App\Filament\Resources\CatatanBkResource;
use App\Filament\Resources\PerpustakaanBukuResource;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Filament\Resources\UserResource;
use App\Models\BkKasus;
use App\Models\DataSiswa;
use App\Models\Rombel;
use App\Models\User;
use App\Support\Admin\AdminModuleAccess;
use App\Support\Bk\BkKasusSiswaSync;
use App\Support\Bk\BkSigapRecap;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class BkKasusSigapTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->createDataSiswaTable();
        $this->createRombelTable();
        $this->runBkKasusMigration();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_akun_biasa_bisa_disetel_mengisi_laporan_sigap_tanpa_menjadi_admin(): void
    {
        $user = User::query()->create([
            'name' => 'Guru Pengisi SIGAP',
            'username' => 'guru-pengisi-sigap',
            'password' => bcrypt('password'),
            'module_access_levels' => [
                'bk_kasus' => AdminModuleAccess::MANAGE,
            ],
        ]);
        $user->assignRole('guru');

        $this->actingAs($user);

        $definition = AdminModuleAccess::definition('bk_kasus');

        $this->assertSame('Laporan SIGAP', $definition['label']);
        $this->assertStringContainsString('mengisi', strtolower($definition['description']));
        $this->assertSame('Isi / Kelola Laporan', AdminModuleAccess::levelOptions('bk_kasus')[AdminModuleAccess::MANAGE]);
        $this->assertTrue(BkKasusResource::canViewAny());
        $this->assertTrue(BkKasusResource::canCreate());

        Livewire::actingAs($user)
            ->test(CreateBkKasus::class)
            ->assertOk();
    }

    public function test_akun_lihat_saja_tidak_bisa_mengisi_laporan_sigap(): void
    {
        $user = User::query()->create([
            'name' => 'Guru Pemantau SIGAP',
            'username' => 'guru-pemantau-sigap',
            'password' => bcrypt('password'),
            'module_access_levels' => [
                'bk_kasus' => AdminModuleAccess::VIEW,
            ],
        ]);
        $user->assignRole('guru');

        $this->actingAs($user);

        $this->assertTrue(BkKasusResource::canViewAny());
        $this->assertFalse(BkKasusResource::canCreate());
    }

    public function test_walas_bisa_dibatasi_hanya_ke_submenu_laporan_sigap(): void
    {
        $user = User::query()->create([
            'name' => 'Walas Lapor SIGAP Saja',
            'username' => 'walas-lapor-sigap-saja',
            'password' => bcrypt('password'),
            'module_access_levels' => [
                'bk_kasus' => AdminModuleAccess::MANAGE,
                'catatan_bk' => AdminModuleAccess::MANAGE,
            ],
            'allowed_navigation_items' => [
                Dashboard::class,
                BkKasusResource::class,
            ],
        ]);
        $user->assignRole('guru');

        $this->actingAs($user);

        $this->assertTrue(BkKasusResource::canViewAny());
        $this->assertTrue(BkKasusResource::canCreate());
        $this->assertFalse(RekapSigapPage::canAccess());
        $this->assertFalse(CatatanBkResource::canViewAny());

        $options = AdminModuleAccess::navigationItemOptions();
        $this->assertArrayNotHasKey(Dashboard::class, $options);
        $this->assertGreaterThanOrEqual(52, count($options));
        $this->assertSame('Manajemen Sekolah -> Penilaian -> Pengaturan Penilaian', $options[AssessmentDashboard::class]);
        $this->assertSame('Manajemen Sekolah -> BK -> Laporan SIGAP', $options[BkKasusResource::class]);
        $this->assertSame('Manajemen Sekolah -> BK -> Rekap SIGAP', $options[RekapSigapPage::class]);
        $this->assertSame('Manajemen Sekolah -> BK -> Catatan BK', $options[CatatanBkResource::class]);
    }

    public function test_pilihan_submenu_granular_disimpan_tanpa_membuka_seluruh_modul(): void
    {
        $user = User::query()->create([
            'name' => 'Walas Simpan SIGAP Saja',
            'username' => 'walas-simpan-sigap-saja',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        UserResource::syncScopedModuleConfiguration($user, [
            'roles' => $user->roles->pluck('id')->all(),
            'module_access_levels' => [
                'bk_kasus' => AdminModuleAccess::MANAGE,
                'catatan_bk' => AdminModuleAccess::MANAGE,
            ],
            'allowed_navigation_items' => [
                Dashboard::class,
                BkKasusResource::class,
            ],
        ]);

        $user->refresh();

        $this->assertTrue($user->hasExplicitNavigationSelection());
        $this->assertSame([
            Dashboard::class,
            BkKasusResource::class,
        ], $user->resolvedNavigationItems());
        $this->assertTrue($user->canAccessNavigationItem(BkKasusResource::class));
        $this->assertFalse($user->canAccessNavigationItem(RekapSigapPage::class));
    }

    public function test_submenu_yang_dipilih_per_menu_disimpan_sebagai_satu_daftar_eksplisit(): void
    {
        $user = User::query()->create([
            'name' => 'Walas Pilih Menu SIGAP',
            'username' => 'walas-pilih-menu-sigap',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        UserResource::syncScopedModuleConfiguration($user, [
            'roles' => $user->roles->pluck('id')->all(),
            'module_access_levels' => [
                'bk_kasus' => AdminModuleAccess::MANAGE,
                'catatan_bk' => AdminModuleAccess::MANAGE,
            ],
            'allowed_navigation_items' => [
                'manajemen_sekolah_bk' => [
                    BkKasusResource::class,
                    RekapSigapPage::class,
                ],
                'menu_utama' => [],
            ],
            'navigation_selection_explicit' => true,
        ]);

        $user->refresh();

        $this->assertTrue($user->hasExplicitNavigationSelection());
        $this->assertSame([
            BkKasusResource::class,
            RekapSigapPage::class,
        ], $user->resolvedNavigationItems());
        $this->assertTrue($user->canAccessNavigationItem(BkKasusResource::class));
        $this->assertTrue($user->canAccessNavigationItem(RekapSigapPage::class));
        $this->assertFalse($user->canAccessNavigationItem(CatatanBkResource::class));
    }

    public function test_submenu_perpustakaan_yang_tidak_dipilih_ditolak(): void
    {
        $user = User::query()->create([
            'name' => 'Petugas Literasi Terbatas',
            'username' => 'literasi-terbatas',
            'password' => bcrypt('password'),
            'module_access_levels' => ['perpustakaan_literasi' => AdminModuleAccess::MANAGE],
            'allowed_navigation_items' => [
                PerpustakaanLiterasiMaterialResource::class,
                User::EXPLICIT_NAVIGATION_MARKER,
            ],
        ]);
        $user->assignRole('guru');

        $this->actingAs($user);

        $this->assertTrue($user->canAccessNavigationItem(PerpustakaanLiterasiMaterialResource::class));
        $this->assertFalse(PerpustakaanBukuResource::canViewAny());
    }

    public function test_pilihan_granular_tetap_eksplisit_tanpa_dashboard(): void
    {
        $user = User::query()->create([
            'name' => 'Walas Tanpa Dashboard',
            'username' => 'walas-tanpa-dashboard',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('guru');

        UserResource::syncScopedModuleConfiguration($user, [
            'roles' => $user->roles->pluck('id')->all(),
            'module_access_levels' => [
                'bk_kasus' => AdminModuleAccess::MANAGE,
                'catatan_bk' => AdminModuleAccess::MANAGE,
            ],
            'allowed_navigation_items' => [BkKasusResource::class],
            'navigation_selection_explicit' => true,
        ]);

        $user->refresh();

        $this->assertTrue($user->hasExplicitNavigationSelection());
        $this->assertSame([BkKasusResource::class], $user->resolvedNavigationItems());
        $this->assertFalse($user->canAccessNavigationItem(RekapSigapPage::class));
        $this->assertFalse($user->canAccessNavigationItem(CatatanBkResource::class));
    }

    public function test_payload_sigap_tidak_bisa_menyisipkan_siswa_nonaktif(): void
    {
        $user = User::query()->create([
            'name' => 'Guru Pengisi SIGAP Aman',
            'username' => 'guru-pengisi-sigap-aman',
            'password' => bcrypt('password'),
            'module_access_levels' => [
                'bk_kasus' => AdminModuleAccess::MANAGE,
            ],
        ]);
        $user->assignRole('guru');

        $inactive = DataSiswa::query()->create([
            'nama' => 'Siswa Nonaktif Disisipkan',
            'nipd' => '2026999',
            'nisn' => '9999999999',
            'rombel_saat_ini' => null,
            'status' => 'keluar',
        ]);

        $this->actingAs($user);
        $kasus = BkKasus::query()->create([
            'tanggal_kasus' => now()->toDateString(),
            'judul_kasus' => 'Payload manipulasi',
            'keterangan_kasus' => 'ID siswa dikirim langsung.',
            'kategori' => 'Lainnya',
            'tindak_lanjut' => 'Ditolak',
            'status_tindak_lanjut' => 'belum',
            'created_by' => $user->id,
        ]);

        try {
            BkKasusSiswaSync::sync($kasus, [$inactive->id]);
            $this->fail('Payload siswa nonaktif seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('siswa_ids', $exception->errors());
        }

        $this->assertSame(0, $kasus->siswa()->count());
    }

    public function test_satu_kasus_bisa_menampung_banyak_siswa_dengan_satu_tindak_lanjut(): void
    {
        $admin = $this->makeAdmin();
        [$siswaA, $siswaB] = $this->seedStudents();

        Livewire::actingAs($admin)
            ->test(CreateBkKasus::class)
            ->fillForm([
                'tanggal_kasus' => '2026-08-10',
                'judul_kasus' => 'Terlambat masuk kelas jam pertama',
                'keterangan_kasus' => 'Tiga siswa terlambat lebih dari 15 menit pada jam pelajaran pertama.',
                'kategori' => 'kedisiplinan',
                'tingkat' => 'ringan',
                'pelapor' => 'Guru Piket',
                'siswa_ids' => [$siswaA->id, $siswaB->id],
                'tindak_lanjut' => 'Pembinaan oleh guru BK, membuat surat pernyataan, dan pemberitahuan ke orang tua.',
                'status_tindak_lanjut' => BkKasus::STATUS_PROSES,
                'tanggal_tindak_lanjut' => '2026-08-11',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $kasus = BkKasus::query()->firstOrFail();

        $this->assertSame('Terlambat masuk kelas jam pertama', $kasus->judul_kasus);
        $this->assertSame(
            'Pembinaan oleh guru BK, membuat surat pernyataan, dan pemberitahuan ke orang tua.',
            $kasus->tindak_lanjut
        );
        $this->assertSame(2, $kasus->siswa()->count());

        // Tindak lanjut tunggal berlaku untuk seluruh siswa (tersimpan di tabel kasus).
        $this->assertDatabaseMissing('bk_kasus_siswa', ['bk_kasus_id' => $kasus->id, 'siswa_id' => 99999]);
        $this->assertDatabaseHas('bk_kasus_siswa', [
            'bk_kasus_id' => $kasus->id,
            'siswa_id' => $siswaA->id,
            'rombel_snapshot' => 'X 1',
        ]);
        $this->assertDatabaseHas('bk_kasus_siswa', [
            'bk_kasus_id' => $kasus->id,
            'siswa_id' => $siswaB->id,
            'rombel_snapshot' => 'XI 2',
        ]);
    }

    public function test_snapshot_rombel_dipertahankan_saat_siswa_pindah_kelas(): void
    {
        $admin = $this->makeAdmin();
        [$siswaA, $siswaB] = $this->seedStudents();

        $kasus = BkKasus::query()->create([
            'tanggal_kasus' => '2026-08-05',
            'judul_kasus' => 'Membawa perangkat tanpa izin',
            'keterangan_kasus' => 'Ditemukan membawa gawai saat pembelajaran.',
            'status_tindak_lanjut' => BkKasus::STATUS_BELUM,
        ]);
        $kasus->siswa()->attach($siswaA->id, ['rombel_snapshot' => 'X 1']);

        $siswaA->update(['rombel_saat_ini' => 'XI 2']);

        Livewire::actingAs($admin)
            ->test(EditBkKasus::class, ['record' => $kasus->getKey()])
            ->fillForm([
                'siswa_ids' => [$siswaA->id, $siswaB->id],
                'tindak_lanjut' => 'Gawai dititipkan ke BK sampai akhir semester.',
                'status_tindak_lanjut' => BkKasus::STATUS_SELESAI,
                'tanggal_tindak_lanjut' => '2026-08-06',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bk_kasus_siswa', [
            'bk_kasus_id' => $kasus->id,
            'siswa_id' => $siswaA->id,
            'rombel_snapshot' => 'X 1',
        ]);
        $this->assertSame(2, $kasus->fresh()->siswa()->count());
    }

    public function test_rekap_menampilkan_kelas_terdampak_dan_kelas_bersih_sesuai_rentang_tanggal(): void
    {
        [$siswaA, $siswaB] = $this->seedStudents();

        $kasusDalamRentang = BkKasus::query()->create([
            'tanggal_kasus' => '2026-08-10',
            'judul_kasus' => 'Terlambat masuk kelas',
            'keterangan_kasus' => 'Terlambat lebih dari 15 menit.',
            'kategori' => 'kedisiplinan',
            'tingkat' => 'ringan',
            'tindak_lanjut' => 'Pembinaan dan pemberitahuan orang tua.',
            'status_tindak_lanjut' => BkKasus::STATUS_BELUM,
        ]);
        $kasusDalamRentang->siswa()->attach($siswaA->id, ['rombel_snapshot' => 'X 1']);

        $kasusLuarRentang = BkKasus::query()->create([
            'tanggal_kasus' => '2026-07-01',
            'judul_kasus' => 'Kasus bulan lalu',
            'keterangan_kasus' => 'Di luar rentang rekap.',
            'status_tindak_lanjut' => BkKasus::STATUS_SELESAI,
        ]);
        $kasusLuarRentang->siswa()->attach($siswaB->id, ['rombel_snapshot' => 'XI 2']);

        $recap = BkSigapRecap::build('2026-08-01', '2026-08-31');

        $this->assertSame(1, $recap['ringkasan']['total_kasus']);
        $this->assertSame(1, $recap['ringkasan']['total_siswa']);
        $this->assertSame(1, $recap['ringkasan']['kelas_terdampak']);
        $this->assertSame(3, $recap['ringkasan']['kelas_aktif']);

        $this->assertSame(['X 1'], array_column($recap['kelas_terdampak'], 'kelas'));
        $this->assertSame('Siswa A', $recap['kelas_terdampak'][0]['siswa'][0]['nama']);

        // Kelas tanpa kasus pada rentang terpilih.
        $this->assertEqualsCanonicalizing(['XI 2', 'XII 3'], $recap['kelas_bersih']);
    }

    public function test_halaman_rekap_sigap_bisa_dibuka_admin(): void
    {
        $admin = $this->makeAdmin();
        [$siswaA] = $this->seedStudents();

        $kasus = BkKasus::query()->create([
            'tanggal_kasus' => now()->toDateString(),
            'judul_kasus' => 'Kasus hari ini',
            'keterangan_kasus' => 'Uji tampilan rekap.',
            'tindak_lanjut' => 'Pendampingan wali kelas.',
            'status_tindak_lanjut' => BkKasus::STATUS_PROSES,
        ]);
        $kasus->siswa()->attach($siswaA->id, ['rombel_snapshot' => 'X 1']);

        Livewire::actingAs($admin)
            ->test(RekapSigapPage::class)
            ->assertOk()
            ->assertSee('Kelas yang Terkena Catatan SIGAP')
            ->assertSee('Kelas Tanpa Catatan SIGAP')
            ->assertSee('Siswa A')
            ->assertSee('XII 3')
            ->assertSee('sigap-page', false)
            ->assertSee('sigap-summary-grid', false)
            ->assertSee('data-label="Nama Siswa"', false)
            ->assertDontSee('class="space-y-6"', false)
            ->assertDontSee('md:grid-cols-4', false);

        $blade = file_get_contents(resource_path('views/filament/pages/bk/rekap-sigap.blade.php'));
        $css = file_get_contents(public_path('css/filament-admin-responsive.css'));

        $this->assertIsString($blade);
        $this->assertIsString($css);
        preg_match_all('/\bsigap-[a-z0-9_-]+/', $blade, $matches);
        $classes = array_values(array_unique($matches[0]));

        $this->assertNotEmpty($classes);
        foreach ($classes as $class) {
            $this->assertStringContainsString('.'.$class, $css, "Selector .{$class} belum tersedia di CSS panel.");
        }
    }

    protected function makeAdmin(): User
    {
        $admin = User::query()->create([
            'name' => 'Admin SIGAP',
            'username' => 'admin-sigap',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    /**
     * @return array<int, DataSiswa>
     */
    protected function seedStudents(): array
    {
        foreach (['X 1', 'XI 2', 'XII 3'] as $nama) {
            Rombel::query()->create(['nama' => $nama, 'is_active' => true]);
        }

        Rombel::query()->create(['nama' => 'ALUMNI 2021/2022', 'is_active' => false]);

        $siswaA = DataSiswa::query()->create([
            'nama' => 'Siswa A',
            'nipd' => '2026001',
            'nisn' => '1000000001',
            'rombel_saat_ini' => 'X 1',
            'status' => 'aktif',
        ]);

        $siswaB = DataSiswa::query()->create([
            'nama' => 'Siswa B',
            'nipd' => '2026002',
            'nisn' => '1000000002',
            'rombel_saat_ini' => 'XI 2',
            'status' => 'aktif',
        ]);

        return [$siswaA, $siswaB];
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
            $table->string('billing_code')->nullable();
            $table->string('wa_ortu')->nullable();
            $table->string('nipd')->nullable();
            $table->string('jk', 2)->nullable();
            $table->string('nisn')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->string('status')->nullable();
            $table->string('kepribadian')->nullable();
            $table->string('gaya_belajar')->nullable();
            $table->text('profiling')->nullable();
            $table->string('mbti')->nullable();
            $table->timestamps();
        });
    }

    protected function createRombelTable(): void
    {
        if (Schema::hasTable('rombels')) {
            return;
        }

        Schema::create('rombels', function (Blueprint $table): void {
            $table->id();
            $table->string('nama')->unique();
            $table->string('angkatan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    protected function runBkKasusMigration(): void
    {
        if (Schema::hasTable('bk_kasus')) {
            return;
        }

        $migration = require database_path('migrations/2026_08_28_100000_create_bk_kasus_tables.php');
        $migration->up();
    }
}
