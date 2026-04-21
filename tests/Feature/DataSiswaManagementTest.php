<?php

namespace Tests\Feature;

use App\Exports\DataSiswaExport;
use App\Exports\DataSiswaImportTemplateExport;
use App\Filament\Resources\DataSiswaResource;
use App\Filament\Resources\DataSiswaResource\Pages\ManageDataSiswas;
use App\Filament\Resources\DataSiswaResource\Pages\ViewDataSiswa;
use App\Filament\Widgets\DataSiswaGenderByRombelChart;
use App\Filament\Widgets\DataSiswaNonAktifReasonChart;
use App\Filament\Widgets\DataSiswaStatsOverview;
use App\Filament\Widgets\DataSiswaStatusChart;
use App\Models\DataSiswa;
use App\Models\User;
use App\Support\DataSiswa\DataSiswaSupport;
use App\Support\DataSiswa\DataSiswaProfileWorkbookImporter;
use App\Support\DataSiswa\DataSiswaWorkbookImporter;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class DataSiswaManagementTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        $this->createDataSiswaTable();
    }

    public function test_data_siswa_table_keeps_only_the_requested_identity_columns_visible_by_default(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Test',
            'username' => 'admin-data-siswa',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        DataSiswa::query()->create([
            'nama' => 'Siswa Contoh',
            'nipd' => '2025001',
            'nisn' => '9988776655',
            'tanggal_lahir' => '2010-01-15',
            'tempat_lahir' => 'Bandung',
            'status' => 'aktif',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::actingAs($admin)
            ->test(ManageDataSiswas::class);

        $columns = collect($component->instance()->getTable()->getColumns());

        foreach (['Nama', 'NISN', 'Tanggal Lahir'] as $label) {
            $column = $columns->first(fn ($column): bool => method_exists($column, 'getLabel') && $column->getLabel() === $label);

            $this->assertNotNull($column, "Kolom {$label} tidak ditemukan.");
            $this->assertNull($column->getVisibleFrom(), "Kolom {$label} tidak boleh dibatasi breakpoint mobile.");
        }

        foreach (['NIPD', 'Tempat Lahir', 'Rombel', 'Angkatan', 'JK', 'Status', 'Kategori Non Aktif', 'Alasan Non Aktif', 'Tanggal Non Aktif', 'Billing'] as $label) {
            $column = $columns->first(fn ($column): bool => method_exists($column, 'getLabel') && $column->getLabel() === $label);

            $this->assertNotNull($column, "Kolom {$label} tidak ditemukan.");
            $this->assertSame('md', $column->getVisibleFrom(), "Kolom {$label} seharusnya hanya tampil dari breakpoint md ke atas.");
        }
    }

    public function test_data_siswa_row_links_to_view_page_with_sectioned_detail_layout(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Detail',
            'username' => 'admin-data-siswa-detail',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $record = DataSiswa::query()->create([
            'nama' => 'Siswa Detail',
            'nipd' => '2025111',
            'nisn' => '8877665544',
            'tempat_lahir' => 'Yogyakarta',
            'tanggal_lahir' => '2010-04-20',
            'status' => 'aktif',
            'rombel_saat_ini' => 'X.A / 2025-2026',
            'billing_code' => 'BILL-2025111',
            'wa_ortu' => '081200000000',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $listPage = Livewire::actingAs($admin)
            ->test(ManageDataSiswas::class);

        $recordUrl = $listPage->instance()->getTable()->getRecordUrl($record);

        $this->assertSame(DataSiswaResource::getUrl('view', ['record' => $record]), $recordUrl);

        Livewire::actingAs($admin)
            ->test(ViewDataSiswa::class, ['record' => $record->getRouteKey()])
            ->assertOk()
            ->assertSee('Ringkasan Siswa')
            ->assertSee('Identitas')
            ->assertSee('Kelahiran')
            ->assertSee('Ringkasan Relasi')
            ->assertSee('Kolom Database Lainnya');
    }

    public function test_data_tes_siswa_import_action_can_open_without_memory_error(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Data Tes',
            'username' => 'admin-data-tes-import',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        DataSiswa::query()->create([
            'nama' => 'Siswa Data Tes',
            'nipd' => '2025123',
            'nisn' => '0099887766',
            'status' => 'aktif',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)
            ->test(ManageDataSiswas::class)
            ->call('mountAction', 'importDataTesSiswa')
            ->assertOk();
    }

    public function test_data_tes_siswa_importer_requires_confirmation_for_similar_names(): void
    {
        $student = DataSiswa::query()->create([
            'nama' => 'ABDI FATIH BURHAN GHANI',
            'nipd' => '242510047',
            'nisn' => '0083273870',
            'status' => 'aktif',
        ]);

        $path = $this->createDataSiswaWorkbook([
            ['No', 'Nama', 'Kepribadian', 'Gaya Belajar', 'Profiling', 'MBTI'],
            [1, 'ABDI FATIH BURHAN GANI', 'Plegmatis', 'Kinestetik', 'Physical Quotient (PQ)', 'ESFP'],
        ]);

        try {
            $analysis = app(DataSiswaProfileWorkbookImporter::class)->analyze($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $analysis['summary']['review']);
        $this->assertSame('review', $analysis['rows'][0]['match_status']);
        $this->assertStringContainsString((string) $student->id, $analysis['rows'][0]['candidate_options_json']);

        $rows = $analysis['rows'];
        $rows[0]['selected_student_id'] = $student->id;
        $rows[0]['confirm_import'] = true;

        $result = app(DataSiswaProfileWorkbookImporter::class)->apply($rows);

        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseHas('data_siswa', [
            'id' => $student->id,
            'kepribadian' => 'PLEGMATIS',
            'gaya_belajar' => 'KINESTETIK',
            'profiling' => 'PHYSICAL QUOTIENT (PQ)',
            'mbti' => 'ESFP',
        ]);
    }

    public function test_data_siswa_importer_creates_and_updates_rows_by_nipd(): void
    {
        $path = $this->createDataSiswaWorkbook([
            ['nama', 'rombel_saat_ini', 'nipd', 'jk', 'status', 'tanggal_lahir'],
            ['Abiel Khiar', 'X.I / 2025-2026', '2025001', 'L', 'aktif', '2010-01-15'],
            ['Abiel Khiar Update', 'XI.I / 2026-2027', '2025001', 'L', 'aktif', '2010-01-15'],
        ]);

        try {
            $result = app(DataSiswaWorkbookImporter::class)->import($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertDatabaseCount('data_siswa', 1);
        $this->assertDatabaseHas('data_siswa', [
            'nama' => 'Abiel Khiar Update',
            'rombel_saat_ini' => 'XI.I / 2026-2027',
            'nipd' => '2025001',
        ]);
    }

    public function test_data_siswa_importer_supports_workbook_style_multiline_headings(): void
    {
        $path = $this->createDataSiswaWorkbook([
            ['No', 'Nama', 'NIPD', 'JK', 'Rombel Saat Ini', 'HP', 'Data Ayah'],
            ['', '', '', '', '', '', 'Nama'],
            [1, 'Fulan Aktif', '2025999', 'L', 'XI A - 2025/2026', '081234567890', 'Ayah Fulan'],
        ]);

        try {
            $result = app(DataSiswaWorkbookImporter::class)->import($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseHas('data_siswa', [
            'nama' => 'Fulan Aktif',
            'nipd' => '2025999',
            'jk' => 'L',
            'rombel_saat_ini' => 'XI A - 2025/2026',
            'wa_ortu' => '081234567890',
        ]);
    }

    public function test_data_siswa_importer_normalizes_boolean_flags_for_enum_schema_columns(): void
    {
        $path = $this->createDataSiswaWorkbook([
            ['nama', 'nipd', 'penerima_kps', 'penerima_kip', 'layak_pip', 'status'],
            ['Fulan Flag', '2025777', 'TIDAK', 'YA', 'YA', 'aktif'],
        ]);

        $importer = new class extends DataSiswaWorkbookImporter
        {
            protected function booleanStorageMode(string $column): string
            {
                return 'enum_ya_tidak';
            }
        };

        try {
            $result = $importer->import($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertDatabaseHas('data_siswa', [
            'nama' => 'Fulan Flag',
            'nipd' => '2025777',
            'penerima_kps' => 'Tidak',
            'penerima_kip' => 'Ya',
            'layak_pip' => 'Ya',
        ]);
    }

    public function test_data_siswa_importer_handles_tanggal_non_aktif_and_clears_non_active_fields_when_status_returns_aktif(): void
    {
        DataSiswa::query()->create([
            'nama' => 'Mutasi Lama',
            'nipd' => '2025888',
            'status' => 'pindah',
            'kategori_non_aktif' => 'mutasi',
            'alasan_non_aktif' => 'Pindah domisili.',
            'tanggal_non_aktif' => '2025-07-01',
        ]);

        $tanggalMutasiSerial = 45853;
        $tanggalMutasi = ExcelDate::excelToDateTimeObject($tanggalMutasiSerial)->format('Y-m-d');

        $path = $this->createDataSiswaWorkbook([
            ['nama', 'nipd', 'status', 'kategori_non_aktif', 'alasan_non_aktif', 'tanggal_non_aktif'],
            ['Mutasi Lama', '2025888', 'pindah', 'mutasi', 'Mutasi antarkota.', $tanggalMutasiSerial],
            ['Mutasi Lama', '2025888', 'aktif', '', '', ''],
        ]);

        try {
            $result = app(DataSiswaWorkbookImporter::class)->import($path);
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['updated']);
        $this->assertDatabaseHas('data_siswa', [
            'nama' => 'Mutasi Lama',
            'nipd' => '2025888',
            'status' => 'aktif',
            'kategori_non_aktif' => null,
            'alasan_non_aktif' => null,
            'tanggal_non_aktif' => null,
        ]);

        DataSiswa::query()->where('nipd', '2025888')->update(['status' => 'pindah']);

        $pathWithDate = $this->createDataSiswaWorkbook([
            ['nama', 'nipd', 'status', 'kategori_non_aktif', 'alasan_non_aktif', 'tanggal_non_aktif'],
            ['Mutasi Lama', '2025888', 'pindah', 'mutasi', 'Mutasi antarkota.', $tanggalMutasiSerial],
        ]);

        try {
            app(DataSiswaWorkbookImporter::class)->import($pathWithDate);
        } finally {
            @unlink($pathWithDate);
        }

        $mutasiStudent = DataSiswa::query()->where('nipd', '2025888')->first();

        $this->assertNotNull($mutasiStudent);
        $this->assertSame('pindah', $mutasiStudent->status);
        $this->assertSame($tanggalMutasi, $mutasiStudent->tanggal_non_aktif?->format('Y-m-d'));
    }

    public function test_template_export_and_data_export_follow_available_columns(): void
    {
        DataSiswa::query()->create([
            'nama' => 'Rian Akbar',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'nipd' => '2025002',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        $template = new DataSiswaImportTemplateExport;
        $sheets = $template->sheets();
        $templateRows = $sheets[0]->array();
        $guideRows = $sheets[1]->array();
        $exportRows = (new DataSiswaExport)->array();
        $exampleByColumn = array_combine($templateRows[0], $templateRows[1]) ?: [];

        $this->assertContains('nama', $templateRows[0]);
        $this->assertContains('rombel_saat_ini', $templateRows[0]);
        $this->assertContains('status', $templateRows[0]);
        $this->assertContains('kategori_non_aktif', $templateRows[0]);
        $this->assertContains('alasan_non_aktif', $templateRows[0]);
        $this->assertContains('tanggal_non_aktif', $templateRows[0]);

        foreach ([
            'alamat' => 'Jl. Contoh No. 1',
            'nama_ayah' => 'Bapak Contoh',
            'nama_ibu' => 'Ibu Contoh',
            'tinggi_badan' => '150',
            'berat_badan' => '42',
            'kategori_non_aktif' => 'mutasi',
            'tanggal_non_aktif' => '2025-07-15',
        ] as $column => $expectedValue) {
            if (array_key_exists($column, $exampleByColumn)) {
                $this->assertSame($expectedValue, $exampleByColumn[$column]);
            }
        }

        $guideText = collect($guideRows)
            ->flatten()
            ->filter(fn ($value): bool => filled($value))
            ->implode("\n");

        $this->assertStringContainsString('Kolom kosong pada file import tidak akan menimpa isi lama saat update.', $guideText);
        $this->assertStringContainsString('Format tanggal_lahir yang aman: YYYY-MM-DD.', $guideText);
        $this->assertStringContainsString('tanggal_non_aktif (YYYY-MM-DD)', $guideText);
        $this->assertStringContainsString('Isi minimal salah satu identitas unik (nipd atau nisn).', $guideText);

        $this->assertSame('2025-2026', DataSiswaSupport::extractAngkatan('X.I / 2025-2026'));
        $this->assertSame('nama', $exportRows[0][1]);
        $this->assertSame('Rian Akbar', $exportRows[1][1]);
    }

    public function test_import_template_route_returns_downloadable_excel_response(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Test',
            'username' => 'admin-template',
            'password' => bcrypt('password'),
        ]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.data-siswa.import-template'));

        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('template-import-data-siswa.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_widgets_summarize_student_status_gender_and_non_active_reasons(): void
    {
        DataSiswa::query()->create([
            'nama' => 'Abiel',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'nipd' => '2025001',
            'jk' => 'L',
            'status' => 'aktif',
        ]);

        DataSiswa::query()->create([
            'nama' => 'Siti',
            'rombel_saat_ini' => 'X.I / 2025-2026',
            'nipd' => '2025002',
            'jk' => 'P',
            'status' => 'aktif',
        ]);

        DataSiswa::query()->create([
            'nama' => 'Alumni Satu',
            'rombel_saat_ini' => 'ALUMNI 2021/2022',
            'nipd' => '2021001',
            'jk' => 'L',
            'status' => 'alumni',
        ]);

        DataSiswa::query()->create([
            'nama' => 'Mutasi Satu',
            'rombel_saat_ini' => 'XI.I / 2025-2026',
            'nipd' => '2025003',
            'jk' => 'P',
            'status' => 'pindah',
            'kategori_non_aktif' => 'mutasi',
            'alasan_non_aktif' => 'Mengikuti perpindahan tempat tinggal orang tua.',
        ]);

        DataSiswa::query()->create([
            'nama' => 'Keluar Satu',
            'rombel_saat_ini' => 'XI.II / 2025-2026',
            'nipd' => '2025004',
            'jk' => 'L',
            'status' => 'keluar',
            'kategori_non_aktif' => 'lainnya',
            'alasan_non_aktif' => 'Melanjutkan sekolah di jalur berbeda.',
        ]);

        $statsWidget = new class extends DataSiswaStatsOverview
        {
            public function exposeStats(): array
            {
                return $this->getStats();
            }
        };

        $genderChart = new class extends DataSiswaGenderByRombelChart
        {
            public function exposeData(): array
            {
                return $this->getData();
            }
        };

        $statusChart = new class extends DataSiswaStatusChart
        {
            public function exposeData(): array
            {
                return $this->getData();
            }
        };

        $nonActiveReasonChart = new class extends DataSiswaNonAktifReasonChart
        {
            public function exposeData(): array
            {
                return $this->getData();
            }
        };

        $stats = collect($statsWidget->exposeStats())
            ->mapWithKeys(fn (Stat $stat): array => [(string) $stat->getLabel() => (string) $stat->getValue()])
            ->all();
        $genderData = $genderChart->exposeData();
        $statusData = $statusChart->exposeData();
        $nonActiveData = $nonActiveReasonChart->exposeData();
        $statusDetails = $statusData['datasets'][0]['segmentDetails'];
        $nonActiveDetails = $nonActiveData['datasets'][0]['segmentDetails'];

        $this->assertSame('5', $stats['Total Siswa']);
        $this->assertSame('2', $stats['Siswa Aktif']);
        $this->assertSame('3', $stats['Siswa Non Aktif']);
        $this->assertSame('1', $stats['Alumni']);
        $this->assertSame('1', $stats['Rombel Terdaftar']);
        $this->assertSame(['X.I / 2025-2026'], $genderData['labels']);
        $this->assertSame([1], $genderData['datasets'][0]['data']);
        $this->assertSame([1], $genderData['datasets'][1]['data']);
        $this->assertSame([2, 1, 1, 1], $statusData['datasets'][0]['data']);
        $this->assertSame([1, 1, 0, 0, 1], $nonActiveData['datasets'][0]['data']);
        $this->assertSame('Aktif', $statusDetails[0]['label']);
        $this->assertStringContainsString('chart_status=aktif', $statusDetails[0]['url']);
        $this->assertSame(['status' => ['value' => 'aktif']], $statusDetails[0]['filters']);
        $this->assertSame('Mutasi', $nonActiveDetails[1]['label']);
        $this->assertStringContainsString('chart_status=pindah', $nonActiveDetails[1]['url']);
        $this->assertSame(['chart_status' => 'pindah'], $nonActiveDetails[1]['chartQuery']);
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
            $table->string('profiling')->nullable();
            $table->string('mbti')->nullable();
            $table->string('penerima_kps')->nullable();
            $table->string('penerima_kip')->nullable();
            $table->string('layak_pip')->nullable();
            $table->string('kategori_non_aktif')->nullable();
            $table->text('alasan_non_aktif')->nullable();
            $table->date('tanggal_non_aktif')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    protected function createDataSiswaWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $row) {
            $columnIndex = 1;

            foreach ($row as $value) {
                $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex + 1, $value);
                $columnIndex++;
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'data-siswa-');

        if ($path === false) {
            $this->fail('Gagal membuat file workbook data siswa untuk test.');
        }

        $finalPath = $path.'.xlsx';
        (new Xlsx($spreadsheet))->save($finalPath);

        if (is_file($path)) {
            @unlink($path);
        }

        return $finalPath;
    }
}
