<?php

namespace Tests\Feature;

use App\Filament\Resources\DokumenKomiteResource\Pages\CreateDokumenKomite;
use App\Filament\Resources\DokumenKomiteResource\Pages\EditDokumenKomite;
use App\Filament\Resources\DokumenKomiteResource\Pages\ListDokumenKomites;
use App\Models\KomiteDocument;
use App\Models\Pengaturan;
use App\Models\User;
use App\Support\SiteSettings\SiteSettingKeys;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class DokumenKomiteResourceTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->bootstrapUserAndPermissionTables();
        $this->ensureKomiteDocumentsTable();
        $this->ensurePengaturanTable();
    }

    public function test_admin_can_create_and_update_committee_document_archive_records(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Dokumen Komite',
            'username' => 'admin-dokumen-komite',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(CreateDokumenKomite::class)
            ->set('data.arsip_tahun', 2026)
            ->set('data.jenis_dokumen', KomiteDocument::TYPE_MEETING_NOTES)
            ->set('data.judul', 'Notulen Rapat Komite Semester Genap')
            ->set('data.nomor_dokumen', 'NK-01/2026')
            ->set('data.tanggal_dokumen', '2026-02-15')
            ->set('data.catatan', 'Membahas evaluasi program kerja dan rencana kegiatan wali murid.')
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('komite_documents', [
            'arsip_tahun' => 2026,
            'jenis_dokumen' => KomiteDocument::TYPE_MEETING_NOTES,
            'judul' => 'Notulen Rapat Komite Semester Genap',
        ]);

        $record = KomiteDocument::query()->firstOrFail();

        Livewire::actingAs($admin)
            ->test(EditDokumenKomite::class, ['record' => $record->getKey()])
            ->set('data.jenis_dokumen', KomiteDocument::TYPE_MEETING_SUMMARY)
            ->set('data.catatan', 'Ringkasan keputusan rapat komite dan tindak lanjut semester genap.')
            ->call('save')
            ->assertHasNoErrors();

        $record->refresh();

        $this->assertSame(KomiteDocument::TYPE_MEETING_SUMMARY, $record->jenis_dokumen);
        $this->assertSame('Ringkasan keputusan rapat komite dan tindak lanjut semester genap.', $record->catatan);
    }

    public function test_committee_document_with_file_is_queued_for_google_drive_sync_when_setting_is_enabled(): void
    {
        Queue::fake();

        $this->configureGoogleDriveSettings();

        $admin = User::query()->create([
            'name' => 'Admin Sinkron Drive',
            'username' => 'admin-sinkron-drive',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(CreateDokumenKomite::class)
            ->set('data.arsip_tahun', 2026)
            ->set('data.jenis_dokumen', KomiteDocument::TYPE_DECREE)
            ->set('data.judul', 'SK Komite Tahun 2026')
            ->set('data.file_path', UploadedFile::fake()->create('sk-komite-2026.pdf', 200, 'application/pdf'))
            ->call('create')
            ->assertHasNoErrors();

        $record = KomiteDocument::query()->latest('id')->firstOrFail();

        $this->assertSame(KomiteDocument::GDRIVE_STATUS_QUEUED, $record->gdrive_upload_status);
        $this->assertSame(0, (int) $record->gdrive_upload_progress);
        $this->assertSame('Menunggu antrean upload Google Drive.', $record->gdrive_upload_message);
        Queue::assertPushed(\App\Jobs\SyncKomiteDocumentToGoogleDrive::class);
    }

    public function test_manual_upload_now_recovers_document_when_remote_file_is_missing(): void
    {
        $this->configureGoogleDriveSettings();

        $path = UploadedFile::fake()
            ->create('sk-komite-2026.pdf', 200, 'application/pdf')
            ->store('komite/dokumen', 'public');

        $record = KomiteDocument::query()->create([
            'arsip_tahun' => 2026,
            'jenis_dokumen' => KomiteDocument::TYPE_DECREE,
            'judul' => 'SK Komite Tahun 2026',
            'file_path' => $path,
            'gdrive_folder_id' => 'doc-lama',
            'gdrive_file_id' => 'file-lama',
        ]);

        Http::fakeSequence()
            ->push(['access_token' => 'token-komite'], 200)
            ->push(['files' => []], 200)
            ->push(['id' => 'year-2026', 'webViewLink' => 'https://drive.google.com/drive/folders/year-2026'], 200)
            ->push(['files' => []], 200)
            ->push(['id' => 'type-sk', 'webViewLink' => 'https://drive.google.com/drive/folders/type-sk'], 200)
            ->push(['files' => []], 200)
            ->push(['id' => 'doc-2026', 'webViewLink' => 'https://drive.google.com/drive/folders/doc-2026'], 200)
            ->push(['error' => ['message' => 'File not found: file-lama.']], 404)
            ->push(['files' => []], 200)
            ->push(['id' => 'file-baru', 'webViewLink' => 'https://drive.google.com/file/d/file-baru/view'], 200);

        $status = app(\App\Support\GoogleDrive\GoogleDriveService::class)->uploadKomiteDocumentNow($record);

        $record->refresh();

        $this->assertSame(KomiteDocument::GDRIVE_STATUS_SYNCED, $status);
        $this->assertSame(KomiteDocument::GDRIVE_STATUS_SYNCED, $record->gdrive_upload_status);
        $this->assertSame('doc-2026', $record->gdrive_folder_id);
        $this->assertSame('file-baru', $record->gdrive_file_id);
        $this->assertSame(KomiteDocument::GDRIVE_SYNC_MODE_RESTORED, $record->gdrive_last_sync_mode);
        $this->assertSame(100, (int) $record->gdrive_upload_progress);
    }

    public function test_manual_upload_now_replaces_existing_remote_file_without_creating_new_id(): void
    {
        $this->configureGoogleDriveSettings();

        $path = UploadedFile::fake()
            ->create('sk-komite-2026.pdf', 200, 'application/pdf')
            ->store('komite/dokumen', 'public');

        $record = KomiteDocument::query()->create([
            'arsip_tahun' => 2026,
            'jenis_dokumen' => KomiteDocument::TYPE_DECREE,
            'judul' => 'SK Komite Tahun 2026',
            'file_path' => $path,
            'gdrive_file_id' => 'file-existing',
        ]);

        Http::fakeSequence()
            ->push(['access_token' => 'token-komite'], 200)
            ->push(['files' => []], 200)
            ->push(['id' => 'year-2026', 'webViewLink' => 'https://drive.google.com/drive/folders/year-2026'], 200)
            ->push(['files' => []], 200)
            ->push(['id' => 'type-sk', 'webViewLink' => 'https://drive.google.com/drive/folders/type-sk'], 200)
            ->push(['files' => []], 200)
            ->push(['id' => 'doc-2026', 'webViewLink' => 'https://drive.google.com/drive/folders/doc-2026'], 200)
            ->push(['id' => 'file-existing', 'parents' => ['doc-2026']], 200)
            ->push(['id' => 'file-existing', 'webViewLink' => 'https://drive.google.com/file/d/file-existing/view'], 200);

        $status = app(\App\Support\GoogleDrive\GoogleDriveService::class)->uploadKomiteDocumentNow($record);

        $record->refresh();

        $this->assertSame(KomiteDocument::GDRIVE_STATUS_SYNCED, $status);
        $this->assertSame('file-existing', $record->gdrive_file_id);
        $this->assertSame(KomiteDocument::GDRIVE_SYNC_MODE_REPLACED, $record->gdrive_last_sync_mode);
        Http::assertSent(fn ($request): bool => $request->method() === 'PATCH'
            && str_contains((string) $request->url(), '/upload/drive/v3/files/file-existing'));
    }

    public function test_admin_can_filter_committee_documents_by_last_sync_mode(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Filter Sinkron',
            'username' => 'admin-filter-sinkron',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $created = KomiteDocument::query()->create([
            'arsip_tahun' => 2026,
            'jenis_dokumen' => KomiteDocument::TYPE_DECREE,
            'judul' => 'SK Sinkron Baru',
            'gdrive_last_sync_mode' => KomiteDocument::GDRIVE_SYNC_MODE_CREATED,
        ]);

        $replaced = KomiteDocument::query()->create([
            'arsip_tahun' => 2026,
            'jenis_dokumen' => KomiteDocument::TYPE_MEETING_NOTES,
            'judul' => 'Notulen Sinkron Diganti',
            'gdrive_last_sync_mode' => KomiteDocument::GDRIVE_SYNC_MODE_REPLACED,
        ]);

        $restored = KomiteDocument::query()->create([
            'arsip_tahun' => 2026,
            'jenis_dokumen' => KomiteDocument::TYPE_MEETING_SUMMARY,
            'judul' => 'Catatan Sinkron Dipulihkan',
            'gdrive_last_sync_mode' => KomiteDocument::GDRIVE_SYNC_MODE_RESTORED,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ListDokumenKomites::class)
            ->call('loadTable')
            ->filterTable('gdrive_last_sync_mode', KomiteDocument::GDRIVE_SYNC_MODE_RESTORED)
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
            ['nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_ROOT_FOLDER_ID],
            ['nilai_pengaturan' => 'folder-komite-test'],
        );
        Pengaturan::query()->updateOrCreate(
            ['nama_pengaturan' => SiteSettingKeys::GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON],
            ['nilai_pengaturan' => json_encode([
                'client_email' => 'komite-bot@example.test',
                'private_key' => $privateKey,
            ])],
        );
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
