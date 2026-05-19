<?php

namespace Tests\Feature;

use App\Models\SarprasBospInventory;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class SarprasBospStickerTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->ensureSarprasTables();
    }

    public function test_public_scan_page_shows_inventory_and_placement_note(): void
    {
        $record = SarprasBospInventory::query()->create([
            'nama_barang' => 'Proyektor Epson',
            'quality' => 1,
            'kode_barang' => 'BOSP-PRJ-001',
            'lokasi_barang' => 'Lab Komputer',
            'tempat_stiker' => 'Rak 2 Lab Komputer',
            'tahun_beli' => 2025,
            'catatan' => 'Letakkan di lemari alat Lab Komputer rak 2.',
        ]);

        $this->get(route('sarpras.bosp-inventories.show', $record))
            ->assertOk()
            ->assertSee('Proyektor Epson')
            ->assertSee('BOSP-PRJ-001')
            ->assertSee('Rak 2 Lab Komputer')
            ->assertSee('Catatan Peletakan Barang')
            ->assertSee('Letakkan di lemari alat Lab Komputer rak 2.');
    }

    public function test_admin_can_download_single_bosp_sticker_pdf(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Sarpras',
            'username' => 'admin-sarpras-sticker-'.str()->random(8),
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $record = SarprasBospInventory::query()->create([
            'nama_barang' => 'Meja Guru',
            'quality' => 1,
            'kode_barang' => 'BOSP-MJ-001',
            'lokasi_barang' => 'Ruang Kelas X',
            'tahun_beli' => 2025,
            'catatan' => 'Stiker ditempel di sisi kanan bawah meja.',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.sarpras-bosp-inventories.sticker', $record))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString('stiker-meja-guru-bosp-mj-001.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertPdfPageCount(1, $response->getContent());
        $this->assertPdfImageCountAtLeast(1, $response->getContent());
    }

    public function test_admin_can_download_single_bosp_sticker_png_named_after_item(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Sarpras PNG',
            'username' => 'admin-sarpras-sticker-png-'.str()->random(8),
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $record = SarprasBospInventory::query()->create([
            'nama_barang' => 'Proyektor Epson EB E500',
            'quality' => 1,
            'kode_barang' => 'BOSP-PRJ-002',
            'lokasi_barang' => 'Ruang Guru',
            'tahun_beli' => 2025,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.sarpras-bosp-inventories.sticker', [
                'sarprasBospInventory' => $record,
                'format' => 'png',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->assertStringContainsString('stiker-proyektor-epson-eb-e500-bosp-prj-002.png', (string) $response->headers->get('content-disposition'));
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($response->getContent(), 0, 8));
    }

    public function test_admin_can_download_bulk_bosp_sticker_pdf(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Bulk Sarpras',
            'username' => 'admin-sarpras-sticker-bulk-'.str()->random(8),
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $first = SarprasBospInventory::query()->create([
            'nama_barang' => 'Kursi Siswa',
            'quality' => 2,
            'kode_barang' => 'BOSP-KRS-001',
            'lokasi_barang' => 'Ruang Kelas XI',
            'tahun_beli' => 2025,
        ]);

        $second = SarprasBospInventory::query()->create([
            'nama_barang' => 'Lemari Arsip',
            'quality' => 1,
            'kode_barang' => 'BOSP-LMR-001',
            'lokasi_barang' => 'Ruang TU',
            'tahun_beli' => 2025,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sarpras-bosp-inventories.stickers', [
                'ids' => [$first->id, $second->id],
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_can_download_bulk_bosp_sticker_png_zip(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Bulk Sarpras PNG',
            'username' => 'admin-sarpras-sticker-bulk-png-'.str()->random(8),
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $first = SarprasBospInventory::query()->create([
            'nama_barang' => 'Laptop HP',
            'quality' => 1,
            'kode_barang' => 'BOSP-LTP-001',
            'lokasi_barang' => 'Ruang IPS',
            'tahun_beli' => 2026,
        ]);

        $second = SarprasBospInventory::query()->create([
            'nama_barang' => 'Proyektor Epson EB E500',
            'quality' => 1,
            'kode_barang' => 'BOSP-PRJ-003',
            'lokasi_barang' => 'Ruang Guru',
            'tahun_beli' => 2024,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.sarpras-bosp-inventories.stickers', [
                'selected' => "{$first->id},{$second->id}",
                'format' => 'png',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');

        $this->assertStringContainsString('stiker-laptop-hp-bosp-ltp-001-dan-1-barang.zip', (string) $response->headers->get('content-disposition'));
    }

    public function test_admin_bulk_bosp_sticker_png_still_accepts_indexed_ids_query(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Bulk Sarpras Indexed PNG',
            'username' => 'admin-sarpras-sticker-indexed-png-'.str()->random(8),
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $first = SarprasBospInventory::query()->create([
            'nama_barang' => 'Printer Canon',
            'quality' => 1,
            'kode_barang' => 'BOSP-PRN-001',
            'lokasi_barang' => 'Ruang TU',
            'tahun_beli' => 2026,
        ]);

        $second = SarprasBospInventory::query()->create([
            'nama_barang' => 'Scanner Epson',
            'quality' => 1,
            'kode_barang' => 'BOSP-SCN-001',
            'lokasi_barang' => 'Ruang TU',
            'tahun_beli' => 2026,
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/sarpras-bosp-inventories/stickers?ids%5B0%5D='.$first->id.'&ids%5B1%5D='.$second->id.'&format=png')
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');

        $this->assertStringContainsString('stiker-printer-canon-bosp-prn-001-dan-1-barang.zip', (string) $response->headers->get('content-disposition'));
    }

    protected function ensureSarprasTables(): void
    {
        if (Schema::hasTable('sarpras_bosp_inventories')) {
            if (! Schema::hasColumn('sarpras_bosp_inventories', 'tempat_stiker')) {
                $migration = require database_path('migrations/2026_04_29_090000_add_tempat_stiker_to_sarpras_bosp_inventories_table.php');
                $migration->up();
            }

            return;
        }

        $migration = require database_path('migrations/2026_04_14_130000_create_sarpras_tables.php');
        $migration->up();
    }

    protected function assertPdfPageCount(int $expected, string $pdf): void
    {
        $this->assertSame($expected, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
    }

    protected function assertPdfImageCountAtLeast(int $expected, string $pdf): void
    {
        $this->assertGreaterThanOrEqual($expected, preg_match_all('/\/Subtype\s*\/Image\b/', $pdf));
    }
}
