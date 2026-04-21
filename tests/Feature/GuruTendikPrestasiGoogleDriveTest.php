<?php

namespace Tests\Feature;

use App\Filament\Resources\GuruTendikResource\Pages\EditGuruTendik;
use App\Filament\Resources\GuruTendikResource\RelationManagers\BerkasGurusRelationManager;
use App\Filament\Resources\PrestasiResource\Pages\CreatePrestasi;
use App\Filament\Resources\PrestasiResource\Pages\EditPrestasi;
use App\Jobs\SyncBerkasGuruToGoogleDrive;
use App\Jobs\SyncPrestasiToGoogleDrive;
use App\Models\BerkasGuru;
use App\Models\GuruTendik;
use App\Models\GuruTendikTugasTambahan;
use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\Prestasi;
use App\Models\User;
use App\Support\GoogleDrive\GoogleDriveService;
use App\Support\SiteSettings\SiteSettingKeys;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

class GuruTendikPrestasiGoogleDriveTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->bootstrapAdminFeatureTables();
        $this->runTugasTambahanSkMigration();
        $this->runBerkasGoogleDriveMigration();
        $this->runPrestasiGoogleDriveMigration();
        $this->ensurePengaturanTable();
    }

    public function test_saving_teacher_assignment_sk_queues_linked_berkas_guru_for_google_drive_sync(): void
    {
        Queue::fake();
        $this->configureGoogleDriveSettings();

        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Rizal',
            'nip' => '1987015',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $path = UploadedFile::fake()
            ->create('sk-walas.pdf', 120, 'application/pdf')
            ->store('guru-tendik/sk', 'public');

        $history = GuruTendikTugasTambahan::query()->create([
            'guru_tendik_id' => $guru->id,
            'tugas_tambahan' => 'Wali Kelas',
            'no_sk' => 'SK-001/2026',
            'tmt' => now()->toDateString(),
            'sk_file_path' => $path,
        ]);

        $history->refresh();
        $berkas = $history->berkasGuru()->firstOrFail();

        $this->assertNotNull($history->berkas_guru_id);
        $this->assertSame($guru->id, $berkas->guru_id);
        $this->assertSame($path, $berkas->file_path);
        $this->assertSame(BerkasGuru::GDRIVE_STATUS_QUEUED, $berkas->gdrive_upload_status);
        Queue::assertPushed(SyncBerkasGuruToGoogleDrive::class);
    }

    public function test_guru_tendik_edit_page_shows_google_drive_sk_status_summary(): void
    {
        $this->configureGoogleDriveSettings();
        $admin = $this->makeAdminUser('admin-guru-drive-ui');

        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Syamil',
            'nip' => '1987018',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $path = UploadedFile::fake()
            ->create('sk-kesiswaan.pdf', 120, 'application/pdf')
            ->store('guru-tendik/sk', 'public');

        $history = GuruTendikTugasTambahan::query()->create([
            'guru_tendik_id' => $guru->id,
            'tugas_tambahan' => 'Kesiswaan',
            'no_sk' => 'SK-002/2026',
            'tmt' => now()->toDateString(),
            'sk_file_path' => $path,
        ]);

        $berkas = $history->fresh()->berkasGuru()->firstOrFail();
        $berkas->forceFill([
            'gdrive_upload_status' => BerkasGuru::GDRIVE_STATUS_QUEUED,
            'gdrive_upload_progress' => 0,
            'gdrive_upload_message' => 'Menunggu antrean upload Google Drive.',
        ])->saveQuietly();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(EditGuruTendik::class, ['record' => $guru->getRouteKey()])
            ->assertSee('Sinkron Google Drive SK Tugas Tambahan')
            ->assertSee('Ringkasan Status')
            ->assertSee('Status Google Drive')
            ->assertSee('Menunggu antrean');
    }


    public function test_guru_tendik_edit_page_shows_teacher_file_history_with_google_drive_actions(): void
    {
        $this->configureGoogleDriveSettings();
        $admin = $this->makeAdminUser('admin-guru-berkas-history');

        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Hafiz',
            'nip' => '1987021',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $jenisBerkas = JenisBerkas::query()->create([
            'nama_berkas' => 'Sertifikat Pelatihan',
            'urutan' => 1,
        ]);

        $path = UploadedFile::fake()
            ->create('sertifikat-hafiz.pdf', 120, 'application/pdf')
            ->store('berkas_guru', 'public');

        BerkasGuru::query()->create([
            'guru_id' => $guru->id,
            'jenis_berkas_id' => $jenisBerkas->id,
            'file_path' => $path,
            'keterangan' => 'Arsip pelatihan guru.',
            'uploaded_at' => now(),
            'gdrive_upload_status' => BerkasGuru::GDRIVE_STATUS_SYNCED,
            'gdrive_upload_progress' => 100,
            'gdrive_upload_message' => 'Semua file berhasil tersimpan di Google Drive.',
            'gdrive_last_sync_mode' => BerkasGuru::GDRIVE_SYNC_MODE_CREATED,
            'gdrive_file_url' => 'https://drive.google.com/file/d/berkas-hafiz/view',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(EditGuruTendik::class, ['record' => $guru->getRouteKey()])
            ->assertSee('Histori Berkas Guru');

        Livewire::actingAs($admin)
            ->test(BerkasGurusRelationManager::class, [
                'ownerRecord' => $guru,
                'pageClass' => EditGuruTendik::class,
            ])
            ->assertSee('Sertifikat Pelatihan')
            ->assertSee('Tersinkron')
            ->assertSee('Upload Sekarang')
            ->assertSee('Edit Berkas');
    }

    public function test_prestasi_with_files_is_queued_for_google_drive_sync_when_setting_is_enabled(): void
    {
        Queue::fake();
        $this->configureGoogleDriveSettings();

        $admin = $this->makeAdminUser('admin-prestasi-drive');

        $siswa = \App\Models\DataSiswa::query()->create([
            'nama' => 'Salma Prestasi',
            'rombel_saat_ini' => 'XI IPA 2',
            'status' => 'aktif',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(CreatePrestasi::class)
            ->set('data.siswa_id', $siswa->id)
            ->set('data.nama_lomba', 'Olimpiade Fisika')
            ->set('data.tanggal_prestasi', now()->toDateString())
            ->set('data.dokumentasi', [UploadedFile::fake()->image('dokumentasi.jpg')])
            ->set('data.sertifikat_files', [UploadedFile::fake()->create('sertifikat.pdf', 120, 'application/pdf')])
            ->call('create')
            ->assertHasNoErrors();

        $record = Prestasi::query()->latest('id')->firstOrFail();

        $this->assertSame(Prestasi::GDRIVE_STATUS_QUEUED, $record->gdrive_upload_status);
        $this->assertSame(0, (int) $record->gdrive_upload_progress);
        $this->assertSame('Menunggu antrean upload Google Drive.', $record->gdrive_upload_message);
        Queue::assertPushed(SyncPrestasiToGoogleDrive::class);
    }

    public function test_prestasi_edit_page_shows_google_drive_status_and_manual_action(): void
    {
        $this->configureGoogleDriveSettings();
        $admin = $this->makeAdminUser('admin-prestasi-drive-ui');

        $siswa = \App\Models\DataSiswa::query()->create([
            'nama' => 'Raisa Prestasi',
            'rombel_saat_ini' => 'X IPA 1',
            'status' => 'aktif',
        ]);

        $sertifikat = UploadedFile::fake()
            ->create('sertifikat-raisa.pdf', 120, 'application/pdf')
            ->store('prestasi/sertifikat', 'public');

        $dokumentasi = UploadedFile::fake()
            ->image('dokumentasi-raisa.jpg')
            ->store('prestasi/dokumentasi', 'public');

        $record = Prestasi::query()->create([
            'siswa_id' => $siswa->id,
            'nama_lomba' => 'Lomba Matematika',
            'tanggal_prestasi' => now()->toDateString(),
            'sertifikat_files' => [$sertifikat],
            'dokumentasi' => [$dokumentasi],
            'gdrive_upload_status' => Prestasi::GDRIVE_STATUS_QUEUED,
            'gdrive_upload_progress' => 0,
            'gdrive_upload_message' => 'Menunggu antrean upload Google Drive.',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(EditPrestasi::class, ['record' => $record->getRouteKey()])
            ->assertSee('Sinkron Google Drive')
            ->assertSee('Status Upload')
            ->assertSee('Menunggu antrean')
            ->assertSee('Upload Sekarang');
    }

    public function test_manual_upload_now_recovers_prestasi_files_when_remote_assets_are_missing(): void
    {
        $this->configureGoogleDriveSettings();

        $siswa = \App\Models\DataSiswa::query()->create([
            'nama' => 'Alya Prestasi',
            'rombel_saat_ini' => 'XII IPA 1',
            'status' => 'aktif',
        ]);

        $sertifikat = UploadedFile::fake()
            ->create('sertifikat-alya.pdf', 120, 'application/pdf')
            ->store('prestasi/sertifikat', 'public');

        $dokumentasi = UploadedFile::fake()
            ->image('dokumentasi-alya.jpg')
            ->store('prestasi/dokumentasi', 'public');

        $record = Prestasi::query()->create([
            'siswa_id' => $siswa->id,
            'nama_lomba' => 'Lomba Karya Ilmiah',
            'tanggal_prestasi' => now()->toDateString(),
            'juara' => 'Juara 2',
            'sertifikat_files' => [$sertifikat],
            'dokumentasi' => [$dokumentasi],
            'gdrive_file_id' => 'asset-lama',
        ]);

        Http::fakeSequence()
            ->push(['access_token' => 'token-prestasi'], 200)
            ->push(['files' => []], 200)
            ->push(['id' => 'folder-prestasi', 'webViewLink' => 'https://drive.google.com/drive/folders/folder-prestasi'], 200)
            ->push(['files' => []], 200)
            ->push(['id' => 'folder-siswa', 'webViewLink' => 'https://drive.google.com/drive/folders/folder-siswa'], 200)
            ->push(['files' => []], 200)
            ->push(['id' => 'folder-record', 'webViewLink' => 'https://drive.google.com/drive/folders/folder-record'], 200)
            ->push(['files' => []], 200)
            ->push(['id' => 'asset-sertifikat', 'webViewLink' => 'https://drive.google.com/file/d/asset-sertifikat/view'], 200)
            ->push(['files' => []], 200)
            ->push(['id' => 'asset-dokumentasi', 'webViewLink' => 'https://drive.google.com/file/d/asset-dokumentasi/view'], 200);

        $status = app(GoogleDriveService::class)->uploadPrestasiNow($record);

        $record->refresh();

        $this->assertSame(Prestasi::GDRIVE_STATUS_SYNCED, $status);
        $this->assertSame('folder-record', $record->gdrive_folder_id);
        $this->assertSame(Prestasi::GDRIVE_STATUS_SYNCED, $record->gdrive_upload_status);
        $this->assertSame(Prestasi::GDRIVE_SYNC_MODE_CREATED, $record->gdrive_last_sync_mode);
        $this->assertCount(2, $record->gdrive_assets_payload ?? []);
    }

    protected function configureGoogleDriveSettings(): void
    {
        $privateKey = <<<'PEM'
-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEA2ebtD9FChyzR0nIHYS1+/hPVZyAbZ9YJnSlD5sq05rlN2P7z
U4FQCVYcz47/IAwTSE3SQt2SqiAvDAheM89cJte0x2zd5O6jeoePYS+fkozhHwnZ
U8ELC2wZ9ZkB9VczjxZR/NBO0zDaqgQmG5p3IkeHstI+IIbnlSNkvqcELsPfL9Oh
SgMYX9iGthJyunp/lU6NZcSxjEZxsGEnf10FUdilAAGRGDQe1Ax1Py/ycDBl4BIP
UsxQKys4amNyQPz7Eac2NtZG56TlhsO6TQx/9BcEr8TT4OsBsBtgotKyOyo+dpzn
14orR4e9c6reU5aGd769R6iSZ4jezqgWe4IqmwIDAQABAoIBAEFqtYLJJPrl9rwC
JbsD6JsooymJlxCuTkaTa+Iuuu6FdRyPNce9C6Ux6AZb/LXHSkarrlMKqAxRCy7G
mFlfiF/U5F32jgs7pXKUnfPkUziw+KjT0R3213T/aC+2VsMsAbuUTNrkQrXeddcS
1cn1roxpAxEpUyN6vK2maYlfJL9Q2lgZmuyzVf4IlqrT/8ogT3Fp/306tg+KRzBL
+7/ZShaEQ9ar44ugrGRn9tfEFk/fwJTy1frEw+EhHRabMEyTAAZ7e9djHJfERZrW
xm6Tk59xiGfYX8OiD2cRU3OjXISKw34N34yUBCBc+JpLdCLZGPeDZOVVI7dxGvdK
GpuanaECgYEA/VIi3Z5VsPkCI2flyyrpAHDaIQIK4EiuyOR4Kjot2xJbB17eATjT
FMYtlH68JrVB3/TzIlDpaHDZBecc+uUzTmVqtb/XPvPRPsv+peV0DGo2ioXXQm/N
wjdHHp+mNvNzibsRB1g4TjlT8V/C4g8uCFZWRj8jRFvjpjc1fDk1mrECgYEA3DTk
z0yCIcJ7gcXRpP71PCMYibOMZAeeSSRYux9OtKCNKOeZcVqiL/FET9V6ztcnF0Xo
v0OpmCoM5UR90XCW1L3i3/UaiZmYHn+WwWZv+FvrH110xmlL8g1iXKxdJ8LPrKr6
Y2Lyjv60VmzGCHEmxjtfy0XbLgD3H2weYYHEFQsCgYAfwOb77rgBGgWJmKF2aSeR
1ZOSJaZlXNcD+ZeSe356AoAEmYCsmInlBb566bP+CiR6xUKg35GSdOrPUZwRWx+m
SRIqPCToEDn/bCS8eNmmIL47ePF1s3wQR0uT7CEyrCukbR2CVS2hqI/8JqvQGGUF
yITCA3IRRI9xq2P58VXl0QKBgQC9o+/JdyI64LpssGgzqD6aY78mF7K4EreGVf70
Z6nodLwclhfXPy6eCzHBbyAsMa5ApLwku6i6mrwwViPk0wmSfVV9eiA4kEYpPcgf
FpEnWkHK6TlABj6ZXl1vYiF3tJYVJcos/XHXJBM6usJxUsEJxuhgrvBrVfl83ifr
4U10sQKBgQCniilDcK43PzoU+mKTO9pAW9C1GzzWZz2H/eDbUPWODbKjuQIuBEb3
14mDI/EdLZ94jWNoT40nWaI7m5wT+Ngh2w6uMe4aE33aBK6Mc48JWZt18nHrw+i/
FXlIIymhBd1ROmnGXyRk05A2TaZK/OBkaxLvOGmTLkECDgumxOFjeA==
-----END RSA PRIVATE KEY-----
PEM;

        foreach ([
            SiteSettingKeys::GOOGLE_DRIVE_ENABLED => '1',
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_GURU => '1',
            SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_PRESTASI => '1',
            SiteSettingKeys::GOOGLE_DRIVE_ROOT_FOLDER_ID => 'folder-utama-test',
            SiteSettingKeys::GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON => json_encode([
                'client_email' => 'arsip-bot@example.test',
                'private_key' => $privateKey,
            ]),
        ] as $key => $value) {
            Pengaturan::query()->updateOrCreate(
                ['nama_pengaturan' => $key],
                ['nilai_pengaturan' => $value],
            );
        }
    }

    protected function makeAdminUser(string $username): User
    {
        $user = User::query()->create([
            'name' => ucwords(str_replace('-', ' ', $username)),
            'username' => $username,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        return $user;
    }

    protected function runTugasTambahanSkMigration(): void
    {
        $migration = require database_path('migrations/2026_03_27_163000_add_sk_columns_to_guru_tendik_tugas_tambahans_table.php');
        $migration->up();
    }

    protected function runBerkasGoogleDriveMigration(): void
    {
        $migration = require database_path('migrations/2026_04_06_090000_add_google_drive_fields_to_berkas_tables.php');
        $migration->up();
    }

    protected function runPrestasiGoogleDriveMigration(): void
    {
        $migration = require database_path('migrations/2026_04_06_100000_add_google_drive_fields_to_prestasis_table.php');
        $migration->up();
    }

    protected function ensurePengaturanTable(): void
    {
        if (Schema::hasTable('pengaturan')) {
            return;
        }

        Schema::create('pengaturan', function (Blueprint $table): void {
            $table->id();
            $table->string('nama_pengaturan')->unique();
            $table->text('nilai_pengaturan')->nullable();
        });
    }
}





