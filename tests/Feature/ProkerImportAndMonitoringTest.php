<?php

namespace Tests\Feature;

use App\Exports\ProkerPeriodExport;
use App\Filament\Pages\DashboardProker;
use App\Models\Proker;
use App\Support\Proker\ProkerWorkbookImporter;
use DateTimeImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProkerImportAndMonitoringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createUsersTable();
        $this->createProkerTables();
    }

    public function test_matrix_workbook_import_creates_proker_with_nullable_optional_fields(): void
    {
        $path = $this->createMatrixWorkbook();

        try {
            $result = app(ProkerWorkbookImporter::class)->import($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);

        /** @var Proker $proker */
        $proker = Proker::query()->firstOrFail();

        $this->assertSame('KURIKULUM', $proker->point_dari);
        $this->assertSame(1, $proker->nomor_urut);
        $this->assertSame('PEMBUATAN KSP DAN ADM GURU', $proker->nama);
        $this->assertSame('2026-2027', $proker->periode_label);
        $this->assertSame(['Jun-26' => '20'], $proker->jadwal_bulanan);
        $this->assertSame('Jun-26: 20', $proker->jadwal_ringkas);
        $this->assertSame('2026-06-20', $proker->target_mulai?->toDateString());
        $this->assertSame('2026-06-20', $proker->target_selesai?->toDateString());
        $this->assertSame('draft', $proker->status);
        $this->assertSame(0, $proker->progress_persen);
        $this->assertNull($proker->rab_global);
        $this->assertNull($proker->keterangan);
    }

    public function test_matrix_workbook_import_marks_past_rows_as_selesai(): void
    {
        $path = $this->createMatrixWorkbook([
            'sheet_title' => '2025',
            'periode_label' => 'PROKER 2025-2026',
            'nama' => 'ARSIP PROGRAM KURIKULUM',
            'month_date' => '2025-06-01',
        ]);

        try {
            app(ProkerWorkbookImporter::class)->import($path);
        } finally {
            @unlink($path);
        }

        /** @var Proker $proker */
        $proker = Proker::query()->firstOrFail();

        $this->assertSame('selesai', $proker->status);
        $this->assertSame(100, $proker->progress_persen);
        $this->assertSame('2025-06-20', $proker->target_mulai?->toDateString());
        $this->assertSame('2025-06-20', $proker->target_selesai?->toDateString());
    }

    public function test_import_can_target_specific_sheet_name_from_workbook(): void
    {
        $path = $this->createWorkbookWithSheets([
            [
                'sheet_title' => '2025',
                'periode_label' => 'PROKER 2025-2026',
                'nama' => 'PROGRAM LAMA',
                'point_dari' => 'KURIKULUM',
                'month_date' => '2025-06-01',
            ],
            [
                'sheet_title' => '2026',
                'periode_label' => 'PROKER 2026-2027',
                'nama' => 'PROGRAM 2026',
                'point_dari' => 'HUMAS',
                'month_date' => '2026-08-01',
                'schedule' => '6-7',
            ],
        ]);

        try {
            $result = app(ProkerWorkbookImporter::class)->import($path, null, 'sheet:2026');
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['created']);
        $this->assertSame([
            [
                'sheet' => '2026',
                'rows' => 1,
            ],
        ], $result['sheets']);
        $this->assertDatabaseCount('prokers', 1);
        $this->assertDatabaseHas('prokers', [
            'nama' => 'PROGRAM 2026',
            'periode_tahun' => 2026,
            'point_dari' => 'HUMAS',
        ]);
    }

    public function test_indicator_summary_filters_by_period_and_proker_name(): void
    {
        \DB::table('proker_bidangs')->insert([
            [
                'id' => 1,
                'nama' => 'HUMAS',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'nama' => 'KURIKULUM',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $rapatKomite = Proker::query()->create([
            'bidang_id' => 1,
            'nama' => 'Rapat Komite',
            'periode_tahun' => 2026,
            'periode_label' => '2026-2027',
            'status' => 'berjalan',
            'prioritas' => 'sedang',
            'progress_persen' => 55,
        ]);

        $rapatGuru = Proker::query()->create([
            'bidang_id' => 2,
            'nama' => 'Rapat Guru',
            'periode_tahun' => 2025,
            'periode_label' => '2025-2026',
            'status' => 'selesai',
            'prioritas' => 'sedang',
            'progress_persen' => 100,
        ]);

        \DB::table('proker_indikators')->insert([
            [
                'proker_id' => $rapatKomite->id,
                'urutan' => 1,
                'indikator' => 'Undangan terkirim',
                'bobot' => 1,
                'is_checked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'proker_id' => $rapatKomite->id,
                'urutan' => 2,
                'indikator' => 'Notulen lengkap',
                'bobot' => 1,
                'is_checked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'proker_id' => $rapatGuru->id,
                'urutan' => 1,
                'indikator' => 'Materi rapat',
                'bobot' => 1,
                'is_checked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $dashboard = new DashboardProker;
        $dashboard->indicatorPeriodYear = '2026';
        $dashboard->indicatorProkerSearch = 'Komite';

        $rows = $dashboard->getIndicatorSummaryByBidang();
        $meta = $dashboard->getIndicatorSummaryMeta();

        $this->assertCount(1, $rows);
        $this->assertSame('HUMAS', $rows->first()['bidang']);
        $this->assertSame(1, $rows->first()['proker_count']);
        $this->assertSame(2, $rows->first()['total_indikator']);
        $this->assertSame(1, $rows->first()['indikator_selesai']);
        $this->assertSame(50, $rows->first()['persen_indikator']);
        $this->assertSame(55, $rows->first()['avg_progress']);
        $this->assertSame(1, $meta['matched_bidangs']);
        $this->assertSame(1, $meta['matched_prokers']);
        $this->assertStringContainsString('2026', $meta['active_period_label']);
        $this->assertStringContainsString('tableFilters%5Bperiode_tahun%5D%5Bvalue%5D=2026', $rows->first()['manage_url']);
        $this->assertStringContainsString('tableSearch=Komite', $rows->first()['manage_url']);
    }

    public function test_quick_checklist_filters_by_period_and_proker_name(): void
    {
        \DB::table('proker_bidangs')->insert([
            [
                'id' => 1,
                'nama' => 'HUMAS',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'nama' => 'SARPRAS',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $komite = Proker::query()->create([
            'bidang_id' => 1,
            'nama' => 'Rapat Komite',
            'periode_tahun' => 2026,
            'periode_label' => '2026-2027',
            'status' => 'berjalan',
            'prioritas' => 'sedang',
            'progress_persen' => 65,
        ]);

        Proker::query()->create([
            'bidang_id' => 2,
            'nama' => 'Pengadaan Kursi',
            'periode_tahun' => 2025,
            'periode_label' => '2025-2026',
            'status' => 'draft',
            'prioritas' => 'sedang',
            'progress_persen' => 0,
        ]);

        $dashboard = new DashboardProker;
        $dashboard->quickChecklistPeriodYear = '2026';
        $dashboard->quickChecklistProkerSearch = 'Komite';

        $rows = $dashboard->getQuickChecklistProkers();
        $meta = $dashboard->getQuickChecklistMeta();

        $this->assertCount(1, $rows);
        $this->assertSame($komite->id, $rows->first()->id);
        $this->assertSame('Rapat Komite', $rows->first()->nama);
        $this->assertSame(1, $meta['matched_prokers']);
        $this->assertStringContainsString('2026', $meta['active_period_label']);
    }

    public function test_record_monitoring_update_syncs_proker_and_indicators(): void
    {
        \DB::table('proker_bidangs')->insert([
            'id' => 1,
            'nama' => 'KURIKULUM',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $proker = Proker::query()->create([
            'bidang_id' => 1,
            'nama' => 'Program Uji Monitoring',
            'periode_tahun' => 2026,
            'status' => 'draft',
            'prioritas' => 'sedang',
            'progress_persen' => 0,
        ]);

        \DB::table('proker_indikators')->insert([
            [
                'proker_id' => $proker->id,
                'urutan' => 1,
                'indikator' => 'Tahap 1',
                'bobot' => 1,
                'is_checked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'proker_id' => $proker->id,
                'urutan' => 2,
                'indikator' => 'Tahap 2',
                'bobot' => 1,
                'is_checked' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $proker->recordMonitoringUpdate([
            'tanggal_update' => '2026-03-25',
            'status_snapshot' => 'selesai',
            'progress_persen' => 100,
            'ringkasan' => 'Semua tahap selesai.',
            'evaluasi' => 'Berjalan lancar.',
            'tindak_lanjut' => 'Dokumentasi diarsipkan.',
            'dokumentasi' => ['proker/updates/bukti-1.jpg'],
        ], true);

        $proker->refresh();

        $this->assertSame('selesai', $proker->status);
        $this->assertSame(100, $proker->progress_persen);
        $this->assertSame('Berjalan lancar.', $proker->evaluasi_akhir);
        $this->assertSame('Dokumentasi diarsipkan.', $proker->tindak_lanjut_umum);
        $this->assertNotNull($proker->last_monitored_at);
        $this->assertDatabaseCount('proker_updates', 1);
        $this->assertDatabaseHas('proker_indikators', [
            'proker_id' => $proker->id,
            'indikator' => 'Tahap 1',
            'is_checked' => 1,
        ]);
        $this->assertDatabaseHas('proker_indikators', [
            'proker_id' => $proker->id,
            'indikator' => 'Tahap 2',
            'is_checked' => 1,
        ]);
    }

    public function test_dashboard_monitoring_defaults_keep_latest_notes_and_documentation(): void
    {
        \DB::table('proker_bidangs')->insert([
            'id' => 1,
            'nama' => 'HUMAS',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $proker = Proker::query()->create([
            'bidang_id' => 1,
            'nama' => 'Rapat Komite',
            'periode_tahun' => 2026,
            'status' => 'berjalan',
            'prioritas' => 'sedang',
            'progress_persen' => 60,
        ]);

        $proker->updates()->create([
            'tanggal_update' => '2026-03-24',
            'status_snapshot' => 'berjalan',
            'progress_persen' => 75,
            'ringkasan' => 'Undangan sudah dibagikan.',
            'evaluasi' => 'Koordinasi orang tua berjalan baik.',
            'tindak_lanjut' => 'Tambahkan daftar hadir final.',
            'dokumentasi' => [
                'proker/updates/notulen.pdf',
                'proker/updates/foto-rapat.jpg',
            ],
        ]);

        $dashboard = new class extends DashboardProker
        {
            public function exposeMonitoringDefaultData(Proker $proker, bool $forCompletion): array
            {
                return $this->getMonitoringDefaultData($proker, $forCompletion);
            }
        };

        $defaults = $dashboard->exposeMonitoringDefaultData($proker, false);
        $completionDefaults = $dashboard->exposeMonitoringDefaultData($proker, true);

        $this->assertSame(now()->toDateString(), $defaults['tanggal_update']);
        $this->assertSame('berjalan', $defaults['status_snapshot']);
        $this->assertSame(75, $defaults['progress_persen']);
        $this->assertSame('Undangan sudah dibagikan.', $defaults['ringkasan']);
        $this->assertSame('Koordinasi orang tua berjalan baik.', $defaults['evaluasi']);
        $this->assertSame('Tambahkan daftar hadir final.', $defaults['tindak_lanjut']);
        $this->assertSame([
            'proker/updates/notulen.pdf',
            'proker/updates/foto-rapat.jpg',
        ], $defaults['dokumentasi']);

        $this->assertSame('selesai', $completionDefaults['status_snapshot']);
        $this->assertSame(100, $completionDefaults['progress_persen']);
        $this->assertSame([
            'proker/updates/notulen.pdf',
            'proker/updates/foto-rapat.jpg',
        ], $completionDefaults['dokumentasi']);
    }

    public function test_period_export_only_contains_selected_period_rows(): void
    {
        \DB::table('proker_bidangs')->insert([
            [
                'id' => 1,
                'nama' => 'HUMAS',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'nama' => 'SARPRAS',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Proker::query()->create([
            'bidang_id' => 1,
            'nama' => 'Rapat Komite',
            'periode_tahun' => 2026,
            'periode_label' => '2026-2027',
            'point_dari' => 'HUMAS',
            'nomor_urut' => 1,
            'penanggung_jawab' => 'Tim Humas',
            'jadwal_bulanan' => [
                'Jun-26' => '20',
                'Jul-26' => '6-7',
            ],
            'waktu_pelaksanaan' => 'Aula',
            'rab_global' => 'Rp 1.000.000',
            'keterangan' => 'Koordinasi komite',
            'status' => 'berjalan',
            'prioritas' => 'tinggi',
            'progress_persen' => 60,
        ]);

        Proker::query()->create([
            'bidang_id' => 2,
            'nama' => 'Pengadaan Kursi',
            'periode_tahun' => 2025,
            'periode_label' => '2025-2026',
            'point_dari' => 'SARPRAS',
            'status' => 'draft',
            'prioritas' => 'sedang',
            'progress_persen' => 0,
        ]);

        $rows = (new ProkerPeriodExport(2026))->array();

        $this->assertCount(2, $rows);
        $this->assertSame('periode_tahun', $rows[0][0]);
        $this->assertContains('jadwal_jun_2026', $rows[0]);
        $this->assertContains('jadwal_jul_2027', $rows[0]);
        $this->assertSame(2026, $rows[1][0]);
        $this->assertSame('Rapat Komite', $rows[1][4]);
        $this->assertSame('HUMAS', $rows[1][2]);
        $this->assertSame('20', $rows[1][7]);
        $this->assertSame('6-7', $rows[1][8]);
    }

    protected function createUsersTable(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function createProkerTables(): void
    {
        if (! Schema::hasTable('proker_bidangs')) {
            Schema::create('proker_bidangs', function (Blueprint $table): void {
                $table->id();
                $table->string('nama');
                $table->string('kode')->nullable();
                $table->string('penanggung_jawab')->nullable();
                $table->text('deskripsi')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prokers')) {
            Schema::create('prokers', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('bidang_id');
                $table->string('nama');
                $table->unsignedSmallInteger('periode_tahun');
                $table->string('periode_label', 100)->nullable();
                $table->string('point_dari', 150)->nullable();
                $table->unsignedInteger('nomor_urut')->nullable();
                $table->string('penanggung_jawab')->nullable();
                $table->date('target_mulai')->nullable();
                $table->date('target_selesai')->nullable();
                $table->json('jadwal_bulanan')->nullable();
                $table->text('jadwal_ringkas')->nullable();
                $table->text('waktu_pelaksanaan')->nullable();
                $table->string('rab_global')->nullable();
                $table->text('keterangan')->nullable();
                $table->string('status')->default('draft');
                $table->string('prioritas')->default('sedang');
                $table->unsignedTinyInteger('progress_persen')->default(0);
                $table->text('deskripsi')->nullable();
                $table->text('output_target')->nullable();
                $table->text('evaluasi_akhir')->nullable();
                $table->text('tindak_lanjut_umum')->nullable();
                $table->dateTime('last_monitored_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('proker_indikators')) {
            Schema::create('proker_indikators', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('proker_id');
                $table->unsignedInteger('urutan')->default(0);
                $table->string('indikator');
                $table->text('target')->nullable();
                $table->unsignedTinyInteger('bobot')->default(1);
                $table->boolean('is_checked')->default(false);
                $table->dateTime('checked_at')->nullable();
                $table->text('catatan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('proker_updates')) {
            Schema::create('proker_updates', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('proker_id');
                $table->date('tanggal_update');
                $table->string('status_snapshot')->default('draft');
                $table->unsignedTinyInteger('progress_persen')->nullable();
                $table->text('ringkasan')->nullable();
                $table->text('evaluasi')->nullable();
                $table->text('tindak_lanjut')->nullable();
                $table->json('dokumentasi')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function createMatrixWorkbook(array $sheet = []): string
    {
        return $this->createWorkbookWithSheets([$sheet]);
    }

    protected function createWorkbookWithSheets(array $sheets): string
    {
        $spreadsheet = new Spreadsheet;

        foreach (array_values($sheets) as $index => $definition) {
            $sheet = $index === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $this->fillMatrixSheet($sheet, $definition);
        }

        return $this->saveWorkbook($spreadsheet, 'proker-matrix-');
    }

    protected function fillMatrixSheet(Worksheet $sheet, array $definition): void
    {
        $definition = array_merge([
            'sheet_title' => '2026',
            'periode_label' => 'PROKER 2026-2027',
            'point_dari' => 'KURIKULUM',
            'nomor_urut' => '1',
            'nama' => 'PEMBUATAN KSP DAN ADM GURU',
            'schedule' => '20',
            'month_date' => '2026-06-01',
        ], $definition);

        $sheet->setTitle($definition['sheet_title']);
        $sheet->setCellValue('A1', $definition['periode_label']);
        $sheet->setCellValue('A4', 'POINT DARI');
        $sheet->setCellValue('B4', 'NO');
        $sheet->setCellValue('C4', 'NAMA KEGIATAN');
        $sheet->setCellValue('D4', 'BULAN');
        $sheet->setCellValue('R4', 'JAM PELAKSANAAN (MENYUSUL)');
        $sheet->setCellValue('S4', 'PJ');
        $sheet->setCellValue('T4', 'RAB GLOBAL');
        $sheet->setCellValue('D5', ExcelDate::dateTimeToExcel(new DateTimeImmutable($definition['month_date'])));
        $sheet->getStyle('D5')->getNumberFormat()->setFormatCode('mmm-yy');
        $sheet->setCellValue('A6', $definition['point_dari']);
        $sheet->setCellValue('B6', $definition['nomor_urut']);
        $sheet->setCellValue('C6', $definition['nama']);
        $sheet->setCellValue('D6', $definition['schedule']);
    }

    protected function saveWorkbook(Spreadsheet $spreadsheet, string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);

        if ($path === false) {
            $this->fail('Gagal membuat file sementara untuk workbook test.');
        }

        $finalPath = $path.'.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($finalPath);

        if (is_file($path)) {
            @unlink($path);
        }

        return $finalPath;
    }
}
