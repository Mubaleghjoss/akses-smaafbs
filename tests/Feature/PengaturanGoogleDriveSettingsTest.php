<?php

namespace Tests\Feature;

use App\Filament\Resources\PengaturanResource\Pages\ListPengaturans;
use App\Models\KomiteDocument;
use App\Models\Prestasi;
use App\Models\User;
use App\Support\GoogleDrive\GoogleDriveService;
use App\Support\SiteSettings\SiteSettingKeys;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class PengaturanGoogleDriveSettingsTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->recreatePengaturanTable();
    }

    public function test_admin_can_manage_google_drive_settings_from_pengaturan_page(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Google Drive',
            'username' => 'admin-google-drive',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListPengaturans::class)
            ->assertSee('Integrasi Google Drive')
            ->set('data.google_drive_enabled', true)
            ->set('data.google_drive_auto_sync_komite_documents', true)
            ->set('data.google_drive_auto_sync_berkas_siswa', true)
            ->set('data.google_drive_auto_sync_berkas_guru', true)
            ->set('data.google_drive_auto_sync_prestasi', true)
            ->set('data.google_drive_root_folder_id', 'folder-komite-utama')
            ->set('data.google_drive_shared_drive_id', 'shared-drive-komite')
            ->set('data.google_drive_service_account_json', json_encode([
                'client_email' => 'komite-bot@example.test',
                'private_key' => "-----BEGIN PRIVATE KEY-----\nTEST\n-----END PRIVATE KEY-----\n",
            ], JSON_PRETTY_PRINT))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_ENABLED,
            'nilai_pengaturan' => '1',
        ]);

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_SISWA,
            'nilai_pengaturan' => '1',
        ]);

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_GURU,
            'nilai_pengaturan' => '1',
        ]);

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_PRESTASI,
            'nilai_pengaturan' => '1',
        ]);

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_ROOT_FOLDER_ID,
            'nilai_pengaturan' => 'folder-komite-utama',
        ]);

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_SHARED_DRIVE_ID,
            'nilai_pengaturan' => 'shared-drive-komite',
        ]);
    }

    public function test_pengaturan_page_extracts_google_drive_ids_from_full_urls(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Google Drive URL',
            'username' => 'admin-google-drive-url',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListPengaturans::class)
            ->set('data.google_drive_enabled', true)
            ->set('data.google_drive_root_folder_id', 'https://drive.google.com/drive/folders/1enhWddFR0yKtCgy66dn_GETBzTH4GGJQ')
            ->set('data.google_drive_shared_drive_id', 'https://drive.google.com/drive/folders/0AJGJefc4HsbSUk9PVA')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_ROOT_FOLDER_ID,
            'nilai_pengaturan' => '1enhWddFR0yKtCgy66dn_GETBzTH4GGJQ',
        ]);

        $this->assertDatabaseHas('pengaturan', [
            'nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_SHARED_DRIVE_ID,
            'nilai_pengaturan' => '0AJGJefc4HsbSUk9PVA',
        ]);
    }

    public function test_pengaturan_page_shows_google_drive_document_monitoring_lists(): void
    {
        $this->ensureKomiteDocumentsTable();

        KomiteDocument::query()->create([
            'arsip_tahun' => 2026,
            'jenis_dokumen' => KomiteDocument::TYPE_DECREE,
            'judul' => 'SK Menunggu Antrean',
            'gdrive_upload_status' => KomiteDocument::GDRIVE_STATUS_QUEUED,
            'gdrive_upload_progress' => 0,
            'gdrive_upload_message' => 'Menunggu antrean upload Google Drive.',
            'gdrive_last_sync_mode' => KomiteDocument::GDRIVE_SYNC_MODE_CREATED,
        ]);

        KomiteDocument::query()->create([
            'arsip_tahun' => 2026,
            'jenis_dokumen' => KomiteDocument::TYPE_MEETING_NOTES,
            'judul' => 'Notulen Gagal Sinkron',
            'gdrive_upload_status' => KomiteDocument::GDRIVE_STATUS_FAILED,
            'gdrive_upload_progress' => 35,
            'gdrive_upload_message' => 'Upload Google Drive gagal: folder tidak ditemukan.',
            'gdrive_last_sync_mode' => KomiteDocument::GDRIVE_SYNC_MODE_REPLACED,
        ]);

        KomiteDocument::query()->create([
            'arsip_tahun' => 2025,
            'jenis_dokumen' => KomiteDocument::TYPE_MEETING_SUMMARY,
            'judul' => 'Catatan Rapat Tersinkron',
            'gdrive_upload_status' => KomiteDocument::GDRIVE_STATUS_SYNCED,
            'gdrive_upload_progress' => 100,
            'gdrive_upload_message' => 'Semua file berhasil tersimpan di Google Drive.',
            'gdrive_last_sync_mode' => KomiteDocument::GDRIVE_SYNC_MODE_RESTORED,
            'gdrive_file_url' => 'https://drive.google.com/file/d/mock/view',
            'gdrive_uploaded_at' => now(),
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Monitor Drive',
            'username' => 'admin-monitor-drive',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListPengaturans::class)
            ->assertSee('Monitoring sinkron file')
            ->assertSee('Cakupan modul')
            ->assertSee('Mode sinkron terakhir')
            ->assertSee('Antrean & proses aktif')
            ->assertSee('Belum terkirim / perlu tindakan')
            ->assertSee('Sudah tersinkron terakhir')
            ->assertSee('SK Menunggu Antrean')
            ->assertSee('Notulen Gagal Sinkron')
            ->assertSee('Catatan Rapat Tersinkron')
            ->assertSee('Dalam Antrean')
            ->assertSee('Gagal')
            ->assertSee('Tersinkron')
            ->assertSee('Baru')
            ->assertSee('Diganti')
            ->assertSee('Dipulihkan')
            ->assertSee('Upload Sekarang');
    }

    public function test_pengaturan_page_shows_berkas_siswa_and_berkas_guru_sync_history(): void
    {
        $this->ensureJenisBerkasAndOwnerTables();
        $this->ensureBerkasSiswaTable();
        $this->ensureBerkasGuruTable();
        $this->ensureGuruTendikTugasTambahanTable();

        DB::table('jenis_berkas')->insert([
            ['id' => 1, 'nama_berkas' => 'Kartu Keluarga'],
            ['id' => 2, 'nama_berkas' => 'SK Tugas'],
        ]);

        DB::table('data_siswa')->insert([
            'id' => 10,
            'nama' => 'Siswa Sinkron',
            'rombel_saat_ini' => 'XI IPA 1',
        ]);

        DB::table('guru_tendik')->insert([
            'id' => 20,
            'nama' => 'Guru Sinkron',
        ]);

        DB::table('berkas_siswa')->insert([
            'id' => 100,
            'siswa_id' => 10,
            'jenis_berkas_id' => 1,
            'status' => 'lengkap',
            'file_path' => 'berkas_siswa/kk-siswa.pdf',
            'file_name' => 'kk-siswa.pdf',
            'uploaded_at' => now(),
            'updated_at' => now(),
            'gdrive_upload_status' => 'queued',
            'gdrive_upload_progress' => 10,
            'gdrive_upload_message' => 'Menunggu antrean upload Google Drive.',
            'gdrive_last_sync_mode' => 'created',
        ]);

        DB::table('berkas_guru')->insert([
            'id' => 200,
            'guru_id' => 20,
            'jenis_berkas_id' => 2,
            'file_path' => 'berkas_guru/sk-guru.pdf',
            'uploaded_at' => now(),
            'gdrive_upload_status' => 'synced',
            'gdrive_upload_progress' => 100,
            'gdrive_upload_message' => 'Semua file berhasil tersimpan di Google Drive.',
            'gdrive_last_sync_mode' => 'restored',
            'gdrive_file_url' => 'https://drive.google.com/file/d/berkas-guru/view',
            'gdrive_uploaded_at' => now(),
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Monitor Berkas',
            'username' => 'admin-monitor-berkas',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListPengaturans::class)
            ->assertSee('Buka Berkas Siswa')
            ->assertSee('Buka Berkas Guru')
            ->assertSee('Berkas Siswa')
            ->assertSee('Berkas Guru')
            ->assertSee('kk-siswa.pdf')
            ->assertSee('sk-guru.pdf')
            ->assertSee('Siswa Sinkron')
            ->assertSee('Guru Sinkron')
            ->assertSee('Kartu Keluarga')
            ->assertSee('SK Tugas');
    }

    public function test_pengaturan_page_shows_prestasi_sync_history(): void
    {
        $this->ensureJenisBerkasAndOwnerTables();
        $this->ensurePrestasiTable();

        DB::table('data_siswa')->insert([
            'id' => 11,
            'nama' => 'Siswa Prestasi',
            'rombel_saat_ini' => 'X IPA 3',
        ]);

        DB::table('prestasis')->insert([
            'id' => 300,
            'siswa_id' => 11,
            'nama_lomba' => 'Olimpiade Biologi',
            'tanggal_prestasi' => now()->toDateString(),
            'juara' => 'Juara 1',
            'dokumentasi' => json_encode(['prestasi/dokumentasi/biologi-1.jpg']),
            'sertifikat_files' => json_encode(['prestasi/sertifikat/biologi-1.pdf']),
            'gdrive_upload_status' => Prestasi::GDRIVE_STATUS_SYNCED,
            'gdrive_upload_progress' => 100,
            'gdrive_upload_message' => 'Semua file berhasil tersimpan di Google Drive.',
            'gdrive_last_sync_mode' => Prestasi::GDRIVE_SYNC_MODE_CREATED,
            'gdrive_file_url' => 'https://drive.google.com/file/d/prestasi/view',
            'gdrive_assets_payload' => json_encode([
                [
                    'kind' => 'certificate',
                    'name' => 'olimpiade-biologi-sertifikat-01.pdf',
                    'id' => 'sertifikat-prestasi-1',
                    'url' => 'https://drive.google.com/file/d/sertifikat-prestasi-1/view',
                ],
                [
                    'kind' => 'documentation',
                    'name' => 'olimpiade-biologi-dokumentasi-01.jpg',
                    'id' => 'dokumentasi-prestasi-1',
                    'url' => 'https://drive.google.com/file/d/dokumentasi-prestasi-1/view',
                ],
            ]),
            'gdrive_uploaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Monitor Prestasi',
            'username' => 'admin-monitor-prestasi',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListPengaturans::class)
            ->assertSee('Buka Prestasi')
            ->assertSee('Tambah Prestasi')
            ->assertSee('Prestasi')
            ->assertSee('Asset prestasi')
            ->assertSee('Sertifikat tersinkron')
            ->assertSee('Dokumentasi tersinkron')
            ->assertSee('Olimpiade Biologi')
            ->assertSee('Siswa Prestasi')
            ->assertSee('Juara 1')
            ->assertSee('Sertifikat 1/1')
            ->assertSee('Dokumentasi 1/1');
    }

    public function test_pengaturan_page_can_trigger_manual_google_drive_upload_now(): void
    {
        $this->ensureKomiteDocumentsTable();

        $record = KomiteDocument::query()->create([
            'arsip_tahun' => 2026,
            'jenis_dokumen' => KomiteDocument::TYPE_DECREE,
            'judul' => 'SK Upload Manual',
            'file_path' => 'komite/dokumen/sk-upload-manual.pdf',
            'gdrive_upload_status' => KomiteDocument::GDRIVE_STATUS_FAILED,
            'gdrive_upload_progress' => 15,
            'gdrive_upload_message' => 'Upload sebelumnya gagal.',
        ]);

        $admin = User::query()->create([
            'name' => 'Admin Upload Manual',
            'username' => 'admin-upload-manual',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $mock = \Mockery::mock(GoogleDriveService::class);
        $mock->shouldReceive('uploadKomiteDocumentNow')
            ->once()
            ->withArgs(fn (KomiteDocument $passed): bool => $passed->is($record))
            ->andReturn(KomiteDocument::GDRIVE_STATUS_SYNCED);

        $this->app->instance(GoogleDriveService::class, $mock);

        Livewire::actingAs($admin)
            ->test(ListPengaturans::class)
            ->call('uploadGoogleDriveNow', $record->getKey())
            ->assertHasNoErrors();
    }

    protected function recreatePengaturanTable(): void
    {
        Schema::dropIfExists('pengaturan');

        Schema::create('pengaturan', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_pengaturan')->unique();
            $table->text('nilai_pengaturan')->nullable();
        });
    }

    protected function ensureKomiteDocumentsTable(): void
    {
        if (Schema::hasTable('komite_documents')) {
            return;
        }

        Schema::create('komite_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('arsip_tahun');
            $table->string('jenis_dokumen', 40);
            $table->string('judul', 180);
            $table->string('nomor_dokumen', 120)->nullable();
            $table->date('tanggal_dokumen')->nullable();
            $table->string('file_path')->nullable();
            $table->json('dokumentasi')->nullable();
            $table->text('catatan')->nullable();
            $table->string('gdrive_upload_status', 40)->nullable();
            $table->unsignedTinyInteger('gdrive_upload_progress')->nullable();
            $table->text('gdrive_upload_message')->nullable();
            $table->string('gdrive_folder_id', 120)->nullable();
            $table->string('gdrive_folder_url', 2048)->nullable();
            $table->string('gdrive_file_id', 120)->nullable();
            $table->string('gdrive_file_url', 2048)->nullable();
            $table->string('gdrive_last_sync_mode', 40)->nullable();
            $table->json('gdrive_documentation_payload')->nullable();
            $table->timestamp('gdrive_uploaded_at')->nullable();
            $table->timestamps();
        });
    }

    protected function ensureJenisBerkasAndOwnerTables(): void
    {
        if (! Schema::hasTable('jenis_berkas')) {
            Schema::create('jenis_berkas', function (Blueprint $table): void {
                $table->id();
                $table->string('nama_berkas');
                $table->unsignedInteger('urutan')->nullable();
            });
        }

        if (! Schema::hasTable('data_siswa')) {
            Schema::create('data_siswa', function (Blueprint $table): void {
                $table->id();
                $table->string('nama')->nullable();
                $table->string('rombel_saat_ini')->nullable();
            });
        }

        if (! Schema::hasTable('guru_tendik')) {
            Schema::create('guru_tendik', function (Blueprint $table): void {
                $table->id();
                $table->string('nama')->nullable();
            });
        }
    }

    protected function ensureBerkasSiswaTable(): void
    {
        if (Schema::hasTable('berkas_siswa')) {
            return;
        }

        Schema::create('berkas_siswa', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('siswa_id')->nullable();
            $table->unsignedBigInteger('jenis_berkas_id')->nullable();
            $table->string('status')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('has_deleted')->default(false);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('gdrive_upload_status', 40)->nullable();
            $table->unsignedTinyInteger('gdrive_upload_progress')->nullable();
            $table->text('gdrive_upload_message')->nullable();
            $table->string('gdrive_folder_id', 120)->nullable();
            $table->string('gdrive_folder_url', 2048)->nullable();
            $table->string('gdrive_file_id', 120)->nullable();
            $table->string('gdrive_file_url', 2048)->nullable();
            $table->string('gdrive_last_sync_mode', 40)->nullable();
            $table->timestamp('gdrive_uploaded_at')->nullable();
        });
    }

    protected function ensureBerkasGuruTable(): void
    {
        if (Schema::hasTable('berkas_guru')) {
            return;
        }

        Schema::create('berkas_guru', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('guru_id')->nullable();
            $table->unsignedBigInteger('jenis_berkas_id')->nullable();
            $table->string('file_path')->nullable();
            $table->string('keterangan')->nullable();
            $table->boolean('has_deleted')->default(false);
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('gdrive_upload_status', 40)->nullable();
            $table->unsignedTinyInteger('gdrive_upload_progress')->nullable();
            $table->text('gdrive_upload_message')->nullable();
            $table->string('gdrive_folder_id', 120)->nullable();
            $table->string('gdrive_folder_url', 2048)->nullable();
            $table->string('gdrive_file_id', 120)->nullable();
            $table->string('gdrive_file_url', 2048)->nullable();
            $table->string('gdrive_last_sync_mode', 40)->nullable();
            $table->timestamp('gdrive_uploaded_at')->nullable();
        });
    }

    protected function ensureGuruTendikTugasTambahanTable(): void
    {
        if (Schema::hasTable('guru_tendik_tugas_tambahans')) {
            return;
        }

        Schema::create('guru_tendik_tugas_tambahans', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('berkas_guru_id')->nullable();
        });
    }

    protected function ensurePrestasiTable(): void
    {
        if (Schema::hasTable('prestasis')) {
            return;
        }

        Schema::create('prestasis', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('siswa_id')->nullable();
            $table->string('nama_lomba')->nullable();
            $table->date('tanggal_prestasi')->nullable();
            $table->string('penyelenggara')->nullable();
            $table->string('juara')->nullable();
            $table->string('hadiah')->nullable();
            $table->text('keterangan')->nullable();
            $table->json('dokumentasi')->nullable();
            $table->json('sertifikat_files')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('gdrive_upload_status', 40)->nullable();
            $table->unsignedTinyInteger('gdrive_upload_progress')->nullable();
            $table->text('gdrive_upload_message')->nullable();
            $table->string('gdrive_folder_id', 120)->nullable();
            $table->string('gdrive_folder_url', 2048)->nullable();
            $table->string('gdrive_file_id', 120)->nullable();
            $table->string('gdrive_file_url', 2048)->nullable();
            $table->string('gdrive_last_sync_mode', 40)->nullable();
            $table->json('gdrive_assets_payload')->nullable();
            $table->timestamp('gdrive_uploaded_at')->nullable();
            $table->timestamps();
        });
    }
}
