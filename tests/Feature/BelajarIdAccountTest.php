<?php

namespace Tests\Feature;

use App\Filament\Resources\BelajarIdAccountResource;
use App\Filament\Resources\BelajarIdGuruResource;
use App\Models\BelajarIdAccount;
use App\Models\User;
use App\Support\BelajarId\BelajarIdWorkbookImporter;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class BelajarIdAccountTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->createBelajarIdTable();
    }

    protected function createBelajarIdTable(): void
    {
        if (Schema::hasTable('belajar_id_accounts')) {
            return;
        }

        $migration = require database_path('migrations/2026_08_21_090200_create_belajar_id_accounts_table.php');
        $migration->up();
    }

    protected function adminUser(): User
    {
        $admin = User::query()->create([
            'name' => 'Admin Test',
            'username' => 'admin-belajar-id',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    protected function makeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['NAMA', 'STATUS', 'EMAIL', 'PASSWORD'], null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'belajarid_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function test_import_splits_rows_into_siswa_and_guru_by_status(): void
    {
        $path = $this->makeWorkbook([
            ['Siswa Satu', 'X IPA 1', 'siswa1@belajar.id', 'pass-siswa-1'],
            ['Guru Satu', 'guru', 'guru1@belajar.id', 'pass-guru-1'],
            ['Tendik Satu', 'tendik', 'tendik1@belajar.id', 'pass-tendik-1'],
        ]);

        $result = app(BelajarIdWorkbookImporter::class)->import($path);
        @unlink($path);

        $this->assertSame(3, $result['created']);
        $this->assertSame(0, $result['updated']);

        $this->assertSame(1, $result['siswa']);
        $this->assertSame(2, $result['guru']);

        $this->assertSame('siswa', BelajarIdAccount::where('email', 'siswa1@belajar.id')->value('role'));
        $this->assertSame('guru', BelajarIdAccount::where('email', 'guru1@belajar.id')->value('role'));
        $this->assertSame('guru', BelajarIdAccount::where('email', 'tendik1@belajar.id')->value('role'));
        // STATUS asli dipertahankan.
        $this->assertSame('tendik', BelajarIdAccount::where('email', 'tendik1@belajar.id')->value('status'));
        $this->assertSame('X IPA 1', BelajarIdAccount::where('email', 'siswa1@belajar.id')->value('status'));
    }

    public function test_import_upserts_by_email_and_skips_invalid_rows(): void
    {
        BelajarIdAccount::query()->create([
            'role' => 'siswa', 'nama' => 'Lama', 'status' => 'X IPA 2',
            'email' => 'dup@belajar.id', 'password' => 'old',
        ]);

        $path = $this->makeWorkbook([
            ['Baru Nama', 'XI IPA 1', 'dup@belajar.id', 'new-pass'], // update
            ['Tanpa Email', 'X IPA 1', 'bukan-email', 'p'],          // skip: email invalid
            ['', 'X', 'kosong@belajar.id', 'p'],                     // skip: nama kosong
            ['Tanpa Password', 'X', 'nopass@belajar.id', ''],        // skip: password kosong
        ]);

        $result = app(BelajarIdWorkbookImporter::class)->import($path);
        @unlink($path);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(3, $result['skipped']);

        $row = BelajarIdAccount::where('email', 'dup@belajar.id')->first();
        $this->assertSame('Baru Nama', $row->nama);
        $this->assertSame('new-pass', $row->password);
        $this->assertSame('XI IPA 1', $row->status);
    }

    public function test_siswa_resource_scopes_only_siswa_rows(): void
    {
        BelajarIdAccount::query()->create(['role' => 'siswa', 'nama' => 'S', 'status' => 'X', 'email' => 's@belajar.id', 'password' => 'p']);
        BelajarIdAccount::query()->create(['role' => 'guru', 'nama' => 'G', 'status' => 'guru', 'email' => 'g@belajar.id', 'password' => 'p']);

        $this->actingAs($this->adminUser());

        $siswaEmails = BelajarIdAccountResource::getEloquentQuery()->pluck('email')->all();
        $this->assertSame(['s@belajar.id'], $siswaEmails);

        $guruEmails = BelajarIdGuruResource::getEloquentQuery()->pluck('email')->all();
        $this->assertSame(['g@belajar.id'], $guruEmails);
    }
}
