<?php

namespace Tests\Feature;

use App\Filament\Resources\WifiAccountResource;
use App\Filament\Resources\WifiGuruResource;
use App\Models\HotspotUser;
use App\Support\WifiAccount\WifiAccountSyncClient;
use App\Support\WifiAccount\WifiAccountWorkbookImporter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class WifiAccountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createHotspotUsersTable();
    }

    protected function createHotspotUsersTable(): void
    {
        if (Schema::hasTable('hotspot_users')) {
            return;
        }

        $base = require database_path('migrations/2026_08_18_000001_create_hotspot_users_table.php');
        $base->up();

        $identity = require database_path('migrations/2026_08_21_090100_add_identity_fields_to_hotspot_users_table.php');
        $identity->up();
    }

    protected function makeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['USERNAME', 'PASSWORD', 'PROFIL', 'KELAS', 'ROLE'], null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'wifi_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_import_marks_role_and_source_and_upserts_by_username(): void
    {
        HotspotUser::query()->create([
            'username' => 'siswa001', 'password' => 'old', 'profile' => 'default', 'role' => 'siswa', 'input_mode' => 'manual',
        ]);

        $path = $this->makeWorkbook([
            ['siswa001', 'baru-pass', 'default', 'X IPA 1', 'siswa'], // update
            ['guru.budi', 'rahasia', 'default', 'guru', 'guru'],       // create guru
            ['', 'p', 'default', 'X', 'siswa'],                        // skip: no username
            ['tanpa.pass', '', 'default', 'X', 'siswa'],               // skip: no password
        ]);

        $result = app(WifiAccountWorkbookImporter::class)->import($path);
        @unlink($path);

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(2, $result['skipped']);

        $siswa = HotspotUser::where('username', 'siswa001')->first();
        $this->assertSame('baru-pass', $siswa->password);
        $this->assertSame('otomatis', $siswa->input_mode);
        $this->assertSame('siswa', $siswa->role);

        $guru = HotspotUser::where('username', 'guru.budi')->first();
        $this->assertSame('guru', $guru->role);
        $this->assertSame('guru', $guru->kelas);
    }

    public function test_resources_scope_by_role(): void
    {
        HotspotUser::query()->create(['username' => 's1', 'password' => 'p', 'role' => 'siswa']);
        HotspotUser::query()->create(['username' => 'g1', 'password' => 'p', 'role' => 'guru']);

        $this->assertSame(['s1'], WifiAccountResource::getEloquentQuery()->pluck('username')->all());
        $this->assertSame(['g1'], WifiGuruResource::getEloquentQuery()->pluck('username')->all());
    }

    public function test_sync_client_fails_closed_when_disabled(): void
    {
        config()->set('wifi_sync.enabled', false);

        $this->expectException(\RuntimeException::class);
        app(WifiAccountSyncClient::class)->fetchAccounts();
    }

    public function test_sync_client_rejects_non_https_production_url(): void
    {
        config()->set('wifi_sync.enabled', true);
        config()->set('wifi_sync.base_url', 'http://mikrotik.smaafbs.sch.id');
        config()->set('wifi_sync.token', 'token-panjang-sekali-1234567890');

        $this->expectException(\RuntimeException::class);
        app(WifiAccountSyncClient::class)->fetchAccounts();
    }

    public function test_sync_fetch_diff_and_apply_via_fake(): void
    {
        config()->set('wifi_sync.enabled', true);
        config()->set('wifi_sync.base_url', 'https://mikrotik.smaafbs.sch.id');
        config()->set('wifi_sync.token', 'token-panjang-sekali-1234567890');

        HotspotUser::query()->create(['username' => 'lama', 'password' => 'sama', 'profile' => 'default', 'role' => 'siswa']);

        Http::fake([
            'https://mikrotik.smaafbs.sch.id/api-hotspot.php' => Http::response([
                'ok' => true,
                'accounts' => [
                    ['username' => 'lama', 'profile' => 'default', 'disabled' => false, 'password' => 'sama'], // sama
                    ['username' => 'baru', 'profile' => 'guru-1m', 'disabled' => false, 'password' => 'p1'],   // baru
                ],
            ], 200),
        ]);

        $client = app(WifiAccountSyncClient::class);
        $accounts = $client->fetchAccounts();
        $this->assertCount(2, $accounts);

        $preview = $client->diffPreview($accounts);
        $this->assertSame(1, $preview['baru']);
        $this->assertSame(1, $preview['sama']);
        $this->assertSame(0, $preview['berubah']);

        $applied = $client->apply($accounts);
        $this->assertSame(1, $applied['created']);
        $this->assertSame(1, $applied['updated']);

        $this->assertDatabaseHas('hotspot_users', ['username' => 'baru', 'input_mode' => 'otomatis']);
    }

    public function test_sync_client_rejects_bad_token_401(): void
    {
        config()->set('wifi_sync.enabled', true);
        config()->set('wifi_sync.base_url', 'https://mikrotik.smaafbs.sch.id');
        config()->set('wifi_sync.token', 'salah');

        Http::fake([
            'https://mikrotik.smaafbs.sch.id/api-hotspot.php' => Http::response(['ok' => false], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        app(WifiAccountSyncClient::class)->fetchAccounts();
    }
}
