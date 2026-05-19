<?php

namespace Tests\Feature;

use App\Filament\Resources\BerkasGuruResource\Pages\CreateBerkasGuru;
use App\Filament\Resources\BerkasSiswaResource\Pages\CreateBerkasSiswa;
use App\Filament\Resources\BerkasSiswaResource\Pages\ListBerkasSiswas;
use App\Filament\Resources\BerkasGuruResource;
use App\Jobs\SyncBerkasSiswaToGoogleDrive;
use App\Models\BerkasGuru;
use App\Models\BerkasSiswa;
use App\Models\DataSiswa;
use App\Models\GuruTendik;
use App\Models\GuruTendikTugasTambahan;
use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\ProfilSekolah;
use App\Models\User;
use App\Support\GoogleDrive\GoogleDriveService;
use App\Support\SiteSettings\SiteSettingKeys;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

class BerkasGoogleDriveSyncTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->bootstrapAdminFeatureTables();
        $this->ensureBerkasSiswaTable();
        $this->ensureBerkasGoogleDriveColumns('berkas_siswa');
        $this->ensureBerkasGoogleDriveColumns('berkas_guru');
        $this->ensurePengaturanTable();
        $this->ensureProfilSekolahTable();
    }

    public function test_student_file_with_upload_is_queued_for_google_drive_sync_when_setting_is_enabled(): void
    {
        Queue::fake();
        $this->configureGoogleDriveSettings();

        $admin = User::query()->create([
            'name' => 'Admin Berkas Siswa',
            'username' => 'admin-berkas-siswa',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $siswa = DataSiswa::query()->create([
            'nama' => 'Ahmad Siswa',
            'rombel_saat_ini' => 'X IPA 1',
            'status' => 'aktif',
        ]);

        $jenisBerkas = JenisBerkas::query()->create([
            'nama_berkas' => 'Kartu Keluarga',
            'wajib' => 'ya',
            'urutan' => 1,
            'status' => 'aktif',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(CreateBerkasSiswa::class)
            ->set('data.siswa_id', $siswa->id)
            ->set('data.jenis_berkas_id', $jenisBerkas->id)
            ->set('data.status', 'lengkap')
            ->set('data.file_path', UploadedFile::fake()->create('kartu-keluarga.pdf', 120, 'application/pdf'))
            ->call('create')
            ->assertHasNoErrors();

        $record = BerkasSiswa::query()->latest('id')->firstOrFail();

        $this->assertSame(BerkasSiswa::GDRIVE_STATUS_QUEUED, $record->gdrive_upload_status);
        $this->assertSame(0, (int) $record->gdrive_upload_progress);
        $this->assertSame('Menunggu antrean upload Google Drive.', $record->gdrive_upload_message);
        $this->assertSame('Kartu Keluarga - Ahmad Siswa - X IPA 1.pdf', basename((string) $record->file_path));
        $this->assertSame('Kartu Keluarga - Ahmad Siswa - X IPA 1.pdf', $record->file_name);
        $this->assertSame('Kartu Keluarga - Ahmad Siswa - X IPA 1.pdf', $record->displayFileName());
        Storage::disk('public')->assertExists($record->file_path);
        Queue::assertPushed(SyncBerkasSiswaToGoogleDrive::class);
    }

    public function test_teacher_file_normalization_uses_document_type_and_teacher_name(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Fitri Nurfadhilah,S.Pd.',
            'nip' => '1987015',
            'jenis_ptk' => 'Guru Mapel',
            'status' => 'aktif',
        ]);

        $jenisBerkas = JenisBerkas::query()->create([
            'nama_berkas' => 'Ijazah',
            'wajib' => 'ya',
            'urutan' => 1,
            'status' => 'aktif',
        ]);

        $path = UploadedFile::fake()
            ->create('random.pdf', 120, 'application/pdf')
            ->store('berkas_guru', 'public');

        $record = BerkasGuru::query()->create([
            'guru_id' => $guru->id,
            'jenis_berkas_id' => $jenisBerkas->id,
            'file_path' => $path,
            'uploaded_at' => now(),
            'has_deleted' => 0,
        ]);

        $this->assertTrue(BerkasGuruResource::normalizeRecord($record));

        $record->refresh();

        $this->assertSame('Ijazah - Fitri Nurfadhilah S Pd.pdf', basename((string) $record->file_path));
        $this->assertSame('Ijazah - Fitri Nurfadhilah S Pd.pdf', $record->displayFileName());
        Storage::disk('public')->assertExists($record->file_path);
    }

    public function test_tugas_tambahan_file_normalization_uses_task_label_and_teacher_name(): void
    {
        $guru = GuruTendik::query()->create([
            'nama' => 'Fitri Nurfadhilah,S.Pd.',
            'nip' => '1987016',
            'jenis_ptk' => 'Guru Mapel',
            'status' => 'aktif',
        ]);

        $path = UploadedFile::fake()
            ->create('sk-random.pdf', 120, 'application/pdf')
            ->store('berkas_guru', 'public');

        $history = GuruTendikTugasTambahan::query()->create([
            'guru_tendik_id' => $guru->id,
            'tugas_tambahan' => 'WAKA KURIKULUM',
            'no_sk' => 'SK-001/AFBS/2026',
            'tmt' => now()->toDateString(),
            'sk_file_path' => $path,
        ]);

        $record = $history->fresh()->berkasGuru()->with(['guru', 'jenisBerkas', 'tugasTambahanHistory'])->firstOrFail();

        $this->assertSame('Tugas Tambahan - WAKA KURIKULUM - Fitri Nurfadhilah S Pd.pdf', basename((string) $record->file_path));
        $this->assertSame($record->file_path, $history->fresh()->sk_file_path);
        $this->assertSame('Tugas Tambahan - WAKA KURIKULUM - Fitri Nurfadhilah S Pd.pdf', $record->displayFileName());
        Storage::disk('public')->assertExists($record->file_path);
    }

    public function test_manual_upload_now_recovers_teacher_file_when_remote_file_is_missing(): void
    {
        $this->configureGoogleDriveSettings();

        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Haris',
            'nip' => '1987009',
            'jenis_ptk' => 'Guru Mapel',
            'status' => 'aktif',
        ]);

        $jenisBerkas = JenisBerkas::query()->create([
            'nama_berkas' => 'Ijazah',
            'wajib' => 'ya',
            'urutan' => 1,
            'status' => 'aktif',
        ]);

        $path = UploadedFile::fake()
            ->create('ijazah-haris.pdf', 120, 'application/pdf')
            ->store('berkas_guru', 'public');

        $record = BerkasGuru::query()->create([
            'guru_id' => $guru->id,
            'jenis_berkas_id' => $jenisBerkas->id,
            'file_path' => $path,
            'keterangan' => 'Dokumen arsip guru',
            'uploaded_at' => now(),
            'has_deleted' => 0,
            'gdrive_folder_id' => 'folder-lama',
            'gdrive_file_id' => 'file-lama',
        ]);

        $uploadResponses = [
            'folder-berkas-guru',
            'folder-guru',
            'folder-jenis',
            'folder-record',
            'file-baru',
        ];
        $uploadIndex = 0;

        Http::fake(function (Request $request) use (&$uploadIndex, $uploadResponses) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'token-guru'], 200);
            }

            if (str_contains($request->url(), '/drive/v3/files/file-lama')) {
                return Http::response(['error' => ['message' => 'File not found: file-lama.']], 404);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/drive/v3/files')) {
                return Http::response(['files' => []], 200);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), 'upload/drive/v3/files')) {
                $id = $uploadResponses[$uploadIndex] ?? 'upload-'.$uploadIndex;
                $uploadIndex++;
                $link = str_starts_with($id, 'file-')
                    ? 'https://drive.google.com/file/d/'.$id.'/view'
                    : 'https://drive.google.com/drive/folders/'.$id;

                return Http::response([
                    'id' => $id,
                    'webViewLink' => $link,
                ], 200);
            }

            return Http::response([], 200);
        });

        $status = app(GoogleDriveService::class)->uploadBerkasGuruNow($record);

        $record->refresh();

        $this->assertSame(BerkasGuru::GDRIVE_STATUS_SYNCED, $status);
        $this->assertSame(BerkasGuru::GDRIVE_STATUS_SYNCED, $record->gdrive_upload_status);
        $this->assertSame('folder-record', $record->gdrive_folder_id);
        $this->assertSame('file-baru', $record->gdrive_file_id);
        $this->assertSame(BerkasGuru::GDRIVE_SYNC_MODE_RESTORED, $record->gdrive_last_sync_mode);
        $this->assertSame(100, (int) $record->gdrive_upload_progress);
    }

    public function test_teacher_file_google_drive_folder_uses_custom_folder_name_from_jenis_berkas(): void
    {
        $this->configureGoogleDriveSettings();

        $guru = GuruTendik::query()->create([
            'nama' => 'Ustadz Salman',
            'nip' => '1987012',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $jenisBerkas = JenisBerkas::query()->create([
            'nama_berkas' => 'Surat Kepegawaian',
            'google_drive_folder_name' => 'Arsip Kepegawaian Guru',
            'wajib' => 'tidak',
            'urutan' => 2,
            'status' => 'aktif',
        ]);

        $path = UploadedFile::fake()
            ->create('surat-kepegawaian.pdf', 120, 'application/pdf')
            ->store('berkas_guru', 'public');

        $record = BerkasGuru::query()->create([
            'guru_id' => $guru->id,
            'jenis_berkas_id' => $jenisBerkas->id,
            'file_path' => $path,
            'uploaded_at' => now(),
            'has_deleted' => 0,
        ]);

        $payloads = [];
        $uploadResponses = [
            'folder-berkas-guru',
            'folder-guru',
            'folder-jenis-custom',
            'folder-record',
            'file-custom',
        ];
        $uploadIndex = 0;

        Http::fake(function (Request $request) use (&$payloads, &$uploadIndex, $uploadResponses) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'token-guru'], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/drive/v3/files')) {
                return Http::response(['files' => []], 200);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), 'upload/drive/v3/files')) {
                $payloads[] = $request->body();
                $id = $uploadResponses[$uploadIndex] ?? 'upload-'.$uploadIndex;
                $uploadIndex++;
                $link = str_starts_with($id, 'file-')
                    ? 'https://drive.google.com/file/d/'.$id.'/view'
                    : 'https://drive.google.com/drive/folders/'.$id;

                return Http::response([
                    'id' => $id,
                    'webViewLink' => $link,
                ], 200);
            }

            return Http::response([], 200);
        });

        $status = app(GoogleDriveService::class)->uploadBerkasGuruNow($record);

        $record->refresh();

        $this->assertSame(BerkasGuru::GDRIVE_STATUS_SYNCED, $status);
        $this->assertSame('folder-record', $record->gdrive_folder_id);
        $this->assertTrue(collect($payloads)->contains(fn (string $body): bool => str_contains($body, 'Arsip Kepegawaian Guru')));
    }
    public function test_school_identity_accreditation_file_can_sync_to_google_drive(): void
    {
        $this->configureGoogleDriveSettings();

        $path = UploadedFile::fake()
            ->create('akreditasi-sekolah.pdf', 120, 'application/pdf')
            ->store('identitas-sekolah/akreditasi', 'public');

        $record = ProfilSekolah::query()->create([
            'title' => 'Identitas Sekolah',
            'nama_sekolah' => 'SMA AFBS',
            'file_akreditasi_path' => $path,
        ]);

        $payloads = [];
        $uploadResponses = [
            'folder-identitas',
            'folder-record-identitas',
            'file-identitas',
        ];
        $uploadIndex = 0;

        Http::fake(function (Request $request) use (&$payloads, &$uploadIndex, $uploadResponses) {
            if (str_contains($request->url(), 'oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'token-identitas'], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/drive/v3/files')) {
                return Http::response(['files' => []], 200);
            }

            if ($request->method() === 'POST' && str_contains($request->url(), 'upload/drive/v3/files')) {
                $payloads[] = $request->body();
                $id = $uploadResponses[$uploadIndex] ?? 'upload-'.$uploadIndex;
                $uploadIndex++;
                $link = str_starts_with($id, 'file-')
                    ? 'https://drive.google.com/file/d/'.$id.'/view'
                    : 'https://drive.google.com/drive/folders/'.$id;

                return Http::response([
                    'id' => $id,
                    'webViewLink' => $link,
                ], 200);
            }

            return Http::response([], 200);
        });

        $status = app(GoogleDriveService::class)->uploadProfilSekolahNow($record);

        $record->refresh();

        $this->assertSame(ProfilSekolah::GDRIVE_STATUS_SYNCED, $status);
        $this->assertSame('folder-record-identitas', $record->gdrive_folder_id);
        $this->assertSame('file-identitas', $record->gdrive_file_id);
        $this->assertSame(ProfilSekolah::GDRIVE_SYNC_MODE_CREATED, $record->gdrive_last_sync_mode);
        $this->assertTrue(collect($payloads)->contains(fn (string $body): bool => str_contains($body, 'Identitas Sekolah')));
    }


    public function test_admin_user_with_guru_role_still_sees_all_guru_tendik_records_for_berkas_form(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Guru Hybrid',
            'username' => 'admin-guru-hybrid',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');
        $admin->assignRole('guru');

        $selfGuru = GuruTendik::query()->create([
            'nama' => 'Guru Admin',
            'nip' => '1987101',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $otherGuru = GuruTendik::query()->create([
            'nama' => 'Guru Senior',
            'nip' => '1987102',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);

        $admin->forceFill([
            'guru_tendik_id' => $selfGuru->id,
        ])->save();

        $visibleIds = GuruTendik::query()
            ->visibleToUser($admin)
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$selfGuru->id, $otherGuru->id], $visibleIds);
    }

    public function test_admin_can_filter_student_files_by_last_sync_mode(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Filter Berkas',
            'username' => 'admin-filter-berkas',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $siswa = DataSiswa::query()->create([
            'nama' => 'Siswa Filter',
            'rombel_saat_ini' => 'XI IPS 1',
            'status' => 'aktif',
        ]);

        $jenisBerkas = JenisBerkas::query()->create([
            'nama_berkas' => 'Akta',
            'wajib' => 'ya',
            'urutan' => 1,
            'status' => 'aktif',
        ]);

        $created = BerkasSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'jenis_berkas_id' => $jenisBerkas->id,
            'status' => 'lengkap',
            'file_path' => 'berkas_siswa/akta-baru.pdf',
            'gdrive_last_sync_mode' => BerkasSiswa::GDRIVE_SYNC_MODE_CREATED,
            'uploaded_at' => now(),
        ]);

        $replaced = BerkasSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'jenis_berkas_id' => $jenisBerkas->id,
            'status' => 'lengkap',
            'file_path' => 'berkas_siswa/akta-diganti.pdf',
            'gdrive_last_sync_mode' => BerkasSiswa::GDRIVE_SYNC_MODE_REPLACED,
            'uploaded_at' => now(),
        ]);

        $restored = BerkasSiswa::query()->create([
            'siswa_id' => $siswa->id,
            'jenis_berkas_id' => $jenisBerkas->id,
            'status' => 'lengkap',
            'file_path' => 'berkas_siswa/akta-dipulihkan.pdf',
            'gdrive_last_sync_mode' => BerkasSiswa::GDRIVE_SYNC_MODE_RESTORED,
            'uploaded_at' => now(),
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListBerkasSiswas::class)
            ->call('loadTable')
            ->filterTable('gdrive_last_sync_mode', BerkasSiswa::GDRIVE_SYNC_MODE_RESTORED)
            ->assertCanSeeTableRecords([$restored])
            ->assertCanNotSeeTableRecords([$created, $replaced]);
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

        Pengaturan::query()->updateOrCreate(
            ['nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_ENABLED],
            ['nilai_pengaturan' => '1'],
        );
        Pengaturan::query()->updateOrCreate(
            ['nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_KOMITE_DOCUMENTS],
            ['nilai_pengaturan' => '1'],
        );
        Pengaturan::query()->updateOrCreate(
            ['nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_SISWA],
            ['nilai_pengaturan' => '1'],
        );
        Pengaturan::query()->updateOrCreate(
            ['nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_BERKAS_GURU],
            ['nilai_pengaturan' => '1'],
        );
        Pengaturan::query()->updateOrCreate(
            ['nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_ROOT_FOLDER_ID],
            ['nilai_pengaturan' => 'folder-utama-test'],
        );
        Pengaturan::query()->updateOrCreate(
            ['nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON],
            ['nilai_pengaturan' => json_encode([
                'client_email' => 'arsip-bot@example.test',
                'private_key' => $privateKey,
            ])],
        );
    }

    protected function ensureBerkasSiswaTable(): void
    {
        if (Schema::hasTable('berkas_siswa')) {
            return;
        }

        Schema::create('berkas_siswa', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('siswa_id');
            $table->unsignedBigInteger('jenis_berkas_id');
            $table->string('status', 40)->default('belum_mengumpulkan');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('has_deleted')->default(false);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    protected function ensureBerkasGoogleDriveColumns(string $tableName): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
            if (! Schema::hasColumn($tableName, 'gdrive_upload_status')) {
                $table->string('gdrive_upload_status', 40)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_upload_progress')) {
                $table->unsignedTinyInteger('gdrive_upload_progress')->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_upload_message')) {
                $table->text('gdrive_upload_message')->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_folder_id')) {
                $table->string('gdrive_folder_id', 120)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_folder_url')) {
                $table->string('gdrive_folder_url', 2048)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_file_id')) {
                $table->string('gdrive_file_id', 120)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_file_url')) {
                $table->string('gdrive_file_url', 2048)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_last_sync_mode')) {
                $table->string('gdrive_last_sync_mode', 40)->nullable();
            }

            if (! Schema::hasColumn($tableName, 'gdrive_uploaded_at')) {
                $table->timestamp('gdrive_uploaded_at')->nullable();
            }
        });
    }


    protected function ensureProfilSekolahTable(): void
    {
        if (Schema::hasTable('profil_sekolahs')) {
            return;
        }

        Schema::create('profil_sekolahs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('singleton_key')->default(1)->unique();
            $table->string('title', 160)->default('Identitas Sekolah');
            $table->string('nama_sekolah', 180)->nullable();
            $table->string('provinsi', 120)->nullable();
            $table->string('desa_kelurahan', 120)->nullable();
            $table->string('kecamatan', 120)->nullable();
            $table->text('alamat')->nullable();
            $table->string('kode_pos', 20)->nullable();
            $table->string('kontak_telepon', 60)->nullable();
            $table->string('kontak_email', 120)->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->string('status_sekolah', 120)->nullable();
            $table->string('kelompok_sekolah', 120)->nullable();
            $table->string('terakreditasi', 120)->nullable();
            $table->date('tanggal_identitas')->nullable();
            $table->string('tahun_berdiri', 20)->nullable();
            $table->date('tanggal_berdiri')->nullable();
            $table->string('kbm', 120)->nullable();
            $table->string('bangunan_sekolah', 160)->nullable();
            $table->string('luas_bangunan', 120)->nullable();
            $table->string('organisasi_penyelenggara', 180)->nullable();
            $table->json('identitas_tambahan')->nullable();
            $table->string('file_akreditasi_path')->nullable();
            $table->string('maps_url', 2048)->nullable();
            $table->string('youtube_url', 2048)->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->string('facebook_url', 2048)->nullable();
            $table->string('tiktok_url', 2048)->nullable();
            $table->json('fasilitas')->nullable();
            $table->json('jadwal_kbm')->nullable();
            $table->json('menu_makan')->nullable();
            $table->string('gdrive_upload_status', 40)->nullable();
            $table->unsignedTinyInteger('gdrive_upload_progress')->nullable();
            $table->text('gdrive_upload_message')->nullable();
            $table->string('gdrive_folder_id', 120)->nullable();
            $table->text('gdrive_folder_url')->nullable();
            $table->string('gdrive_file_id', 120)->nullable();
            $table->text('gdrive_file_url')->nullable();
            $table->string('gdrive_last_sync_mode', 40)->nullable();
            $table->timestamp('gdrive_uploaded_at')->nullable();
            $table->timestamps();
        });
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





