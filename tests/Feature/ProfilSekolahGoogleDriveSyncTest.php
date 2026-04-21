<?php

namespace Tests\Feature;

use App\Models\Pengaturan;
use App\Models\ProfilSekolah;
use App\Support\GoogleDrive\GoogleDriveService;
use App\Support\SiteSettings\SiteSettingKeys;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilSekolahGoogleDriveSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->ensureProfilSekolahTable();
        $this->ensurePengaturanTable();
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
            ['nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_AUTO_SYNC_IDENTITAS_SEKOLAH],
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


