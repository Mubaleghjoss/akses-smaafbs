<?php

namespace Tests\Feature;

use App\Filament\Pages\Perpustakaan\AnalisisLiterasiPage;
use App\Filament\Pages\Perpustakaan\KelolaDispensasiPage;
use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiDispensation;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\User;
use App\Support\Admin\AdminModuleAccess;
use App\Support\Perpustakaan\LiteracyAnalysisShareText;
use App\Support\Perpustakaan\LiteracyDispensationWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Concerns\BootstrapsAdminFeatureTables;
use Tests\TestCase;

/**
 * Menguji dua halaman terpisah untuk analisis literasi: halaman analisis dan
 * halaman kelola dispensasi, termasuk aksi massal lintas materi.
 */
class LiteracyAnalysisPagesTest extends TestCase
{
    use BootstrapsAdminFeatureTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapAdminFeatureTables();
        $this->runLiteracyTables();
    }

    public function test_halaman_analisis_menampilkan_basis_responden_tanpa_dispensasi(): void
    {
        $material = $this->createMaterial('Numerasi Pekan 1');

        $mengisi = $this->createStudent('Ahmad', 'X 1');
        $tesMt = $this->createStudent('Budi', 'X 1');
        $this->createStudent('Cici', 'X 1');

        $this->createResponse($material, $mengisi);
        $this->createDispensation($material, $tesMt, PerpustakaanLiterasiDispensation::REASON_MT_TEST);

        $admin = $this->createAdmin('admin-analisis-literasi');

        $component = Livewire::actingAs($admin)
            ->test(AnalisisLiterasiPage::class)
            ->assertSuccessful();

        $base = $component->instance()->base;

        $this->assertSame(3, $base['active_total']);
        $this->assertSame(1, $base['excluded_total']);
        $this->assertSame(2, $base['respondent_base']);
        $this->assertSame(1, $base['completed_total']);
        $this->assertSame(1, $base['missing_total']);
        $this->assertSame(50.0, $base['participation_percentage']);
    }

    public function test_filter_kelas_mempersempit_basis_responden(): void
    {
        $material = $this->createMaterial('Numerasi Pekan 2');

        $this->createResponse($material, $this->createStudent('Ahmad', 'X 1'));
        $this->createStudent('Budi', 'X 2');

        $admin = $this->createAdmin('admin-filter-kelas-literasi');

        $component = Livewire::actingAs($admin)
            ->test(AnalisisLiterasiPage::class)
            ->set('kelas', 'X 1');

        $base = $component->instance()->base;

        $this->assertSame(1, $base['active_total']);
        $this->assertSame(1, $base['completed_total']);
        $this->assertSame(100.0, $base['participation_percentage']);
        $this->assertSame(['X 1'], array_column($base['classes'], 'class'));
    }

    public function test_persentase_partisipasi_tidak_pernah_melebihi_seratus_persen(): void
    {
        // Kasus yang dulu menghasilkan 1200%: banyak materi, satu kelas.
        $students = [
            $this->createStudent('Ahmad', 'XI 2'),
            $this->createStudent('Budi', 'XI 2'),
        ];

        foreach (range(1, 6) as $index) {
            $material = $this->createMaterial('Materi '.$index);

            foreach ($students as $student) {
                $this->createResponse($material, $student);
            }
        }

        $admin = $this->createAdmin('admin-persentase-literasi');

        $component = Livewire::actingAs($admin)->test(AnalisisLiterasiPage::class);
        $base = $component->instance()->base;

        $this->assertSame(12, $base['active_total']);
        $this->assertSame(12, $base['completed_total']);
        $this->assertSame(100.0, $base['participation_percentage']);
        $this->assertLessThanOrEqual(100.0, $base['classes'][0]['participation_percentage']);
    }

    public function test_rentang_tanggal_terbalik_dinormalkan(): void
    {
        $material = $this->createMaterial('Numerasi Pekan 3');
        $this->createResponse($material, $this->createStudent('Ahmad', 'X 1'));

        $admin = $this->createAdmin('admin-rentang-literasi');

        $component = Livewire::actingAs($admin)
            ->test(AnalisisLiterasiPage::class)
            ->set('dari', now()->addDays(3)->toDateString())
            ->set('sampai', now()->subDays(3)->toDateString());

        $this->assertSame(1, $component->instance()->base['completed_total']);
    }

    public function test_pengguna_tanpa_izin_tidak_dapat_mengakses_halaman(): void
    {
        $tanpaAkses = User::query()->create([
            'name' => 'Tanpa Akses Analisis Literasi',
            'username' => 'tanpa-akses-analisis-literasi',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($tanpaAkses);

        $this->assertFalse(AnalisisLiterasiPage::canAccess());
        $this->assertFalse(KelolaDispensasiPage::canAccess());
    }

    public function test_kedua_halaman_terdaftar_pada_modul_perpustakaan_literasi(): void
    {
        $classes = AdminModuleAccess::itemClassesForLevels([
            'perpustakaan_literasi' => AdminModuleAccess::MANAGE,
        ]);

        $this->assertContains(AnalisisLiterasiPage::class, $classes);
        $this->assertContains(KelolaDispensasiPage::class, $classes);
    }

    public function test_aksi_massal_menetapkan_dispensasi_lintas_materi(): void
    {
        $materials = [
            $this->createMaterial('Materi A'),
            $this->createMaterial('Materi B'),
            $this->createMaterial('Materi C'),
        ];

        $siswaSatu = $this->createStudent('Ahmad', 'XII 1');
        $siswaDua = $this->createStudent('Budi', 'XII 1');

        $admin = $this->createAdmin('admin-massal-dispensasi');

        $result = LiteracyDispensationWriter::assignBulk(
            [(int) $siswaSatu->getKey(), (int) $siswaDua->getKey()],
            array_map(fn (PerpustakaanLiterasiMaterial $material): int => (int) $material->getKey(), $materials),
            PerpustakaanLiterasiDispensation::REASON_MT_TEST,
            null,
            $admin,
        );

        // 2 siswa x 3 materi = 6 baris, satu panggilan.
        $this->assertSame(6, $result['applied']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame(6, PerpustakaanLiterasiDispensation::query()->count());
    }

    public function test_aksi_massal_melewati_siswa_yang_sudah_mengisi(): void
    {
        $material = $this->createMaterial('Materi Sudah Diisi');
        $sudahMengisi = $this->createStudent('Ahmad', 'XII 2');
        $belumMengisi = $this->createStudent('Budi', 'XII 2');

        $this->createResponse($material, $sudahMengisi);

        $admin = $this->createAdmin('admin-lewati-dispensasi');

        $result = LiteracyDispensationWriter::assignBulk(
            [(int) $sudahMengisi->getKey(), (int) $belumMengisi->getKey()],
            [(int) $material->getKey()],
            PerpustakaanLiterasiDispensation::REASON_SICK,
            null,
            $admin,
        );

        $this->assertSame(1, $result['applied']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString('Ahmad', $result['skipped'][0]);
    }

    public function test_izin_wajib_menyertakan_keterangan(): void
    {
        $material = $this->createMaterial('Materi Izin');
        $student = $this->createStudent('Ahmad', 'X 2');
        $admin = $this->createAdmin('admin-izin-keterangan');

        $this->expectException(ValidationException::class);

        LiteracyDispensationWriter::assign(
            $material,
            $student,
            PerpustakaanLiterasiDispensation::REASON_PERMISSION,
            null,
            $admin,
        );
    }

    public function test_alasan_dispensasi_dapat_diubah_tanpa_hapus_buat_ulang(): void
    {
        $material = $this->createMaterial('Materi Ubah Alasan');
        $student = $this->createStudent('Ahmad', 'X 2');
        $admin = $this->createAdmin('admin-ubah-alasan');

        $dispensation = LiteracyDispensationWriter::assign(
            $material,
            $student,
            PerpustakaanLiterasiDispensation::REASON_SICK,
            null,
            $admin,
        );

        LiteracyDispensationWriter::update(
            $dispensation,
            PerpustakaanLiterasiDispensation::REASON_PERMISSION,
            'Izin mengikuti lomba kabupaten.',
            $admin,
        );

        $dispensation->refresh();

        $this->assertSame(PerpustakaanLiterasiDispensation::REASON_PERMISSION, $dispensation->reason);
        $this->assertSame('Izin mengikuti lomba kabupaten.', $dispensation->note);
        $this->assertSame(1, PerpustakaanLiterasiDispensation::query()->count());
    }

    public function test_halaman_kelola_dispensasi_menampilkan_baris_yang_ada(): void
    {
        $material = $this->createMaterial('Materi Tabel Dispensasi');
        $student = $this->createStudent('Ahmad Dispensasi', 'XI 1');
        $dispensation = $this->createDispensation($material, $student, PerpustakaanLiterasiDispensation::REASON_MT_TEST);

        $admin = $this->createAdmin('admin-tabel-dispensasi');

        Livewire::actingAs($admin)
            ->test(KelolaDispensasiPage::class)
            ->assertSuccessful()
            ->loadTable()
            ->assertCanSeeTableRecords([$dispensation]);
    }

    public function test_halaman_merender_kelas_css_khusus_bukan_utility_tailwind(): void
    {
        // Panel admin memuat CSS pracompile, bukan Tailwind via Vite, sehingga
        // halaman harus memakai kelas .lit-* agar tetap bergaya.
        $material = $this->createMaterial('Materi Render CSS');
        $this->createResponse($material, $this->createStudent('Ahmad Render', 'X 1'));

        $admin = $this->createAdmin('admin-render-css-literasi');

        Livewire::actingAs($admin)
            ->test(AnalisisLiterasiPage::class)
            ->assertSuccessful()
            ->assertSee('lit-cards', false)
            ->assertSee('lit-table', false)
            ->assertSee('lit-field__control', false)
            ->assertSee('lit-copybar', false)
            ->assertDontSee('xl:grid-cols-5', false);
    }

    public function test_teks_salin_per_bagian_tersedia_untuk_semua_label(): void
    {
        $material = $this->createMaterial('Materi Bagian Salin');
        $mengisi = $this->createStudent('Ahmad Salin', 'X 1');
        $belum = $this->createStudent('Budi Salin', 'X 1');
        $dispensasi = $this->createStudent('Cici Salin', 'X 2');

        $this->createResponse($material, $mengisi);
        $this->createDispensation($material, $dispensasi, PerpustakaanLiterasiDispensation::REASON_SICK);

        $admin = $this->createAdmin('admin-bagian-salin');

        $sections = Livewire::actingAs($admin)
            ->test(AnalisisLiterasiPage::class)
            ->instance()
            ->shareSections();

        $this->assertSame(
            array_keys(LiteracyAnalysisShareText::sectionLabels()),
            array_keys($sections),
        );

        foreach ($sections as $key => $text) {
            $this->assertNotSame('', trim($text), "Bagian {$key} kosong.");
            $this->assertStringContainsString('*ANALISIS LITERASI NUMERASI*', $text);
        }

        // Angka pada teks harus sama dengan angka yang tampil di halaman.
        $this->assertStringContainsString('Basis responden: 2', $sections['ringkasan']);
        $this->assertStringContainsString('Dikeluarkan (izin/sakit/tes MT): 1', $sections['ringkasan']);
        $this->assertStringContainsString($belum->nama, $sections['belum']);
        $this->assertStringContainsString($dispensasi->nama, $sections['dispensasi']);
        $this->assertStringNotContainsString($mengisi->nama, $sections['belum']);
    }

    public function test_teks_salin_mengikuti_filter_kelas_yang_aktif(): void
    {
        $material = $this->createMaterial('Materi Filter Salin');
        $this->createResponse($material, $this->createStudent('Ahmad Kelas', 'X 1'));
        $luarKelas = $this->createStudent('Budi Kelas', 'X 2');

        $admin = $this->createAdmin('admin-filter-salin');

        $sections = Livewire::actingAs($admin)
            ->test(AnalisisLiterasiPage::class)
            ->set('kelas', 'X 1')
            ->instance()
            ->shareSections();

        $this->assertStringContainsString('*Kelas:* X 1', $sections['ringkasan']);
        $this->assertStringContainsString('X 1', $sections['partisipasi']);
        $this->assertStringNotContainsString($luarKelas->nama, $sections['belum']);
    }

    public function test_teks_salin_membersihkan_karakter_penanda_whatsapp(): void
    {
        // Nama materi bertanda bintang tidak boleh merusak format tebal WhatsApp.
        $material = $this->createMaterial('Materi *Bintang* Uji');
        $this->createStudent('Ahmad Bintang', 'XI 3');
        $this->createResponse($material, $this->createStudent('Budi Bintang', 'XI 3'));

        $admin = $this->createAdmin('admin-sanitasi-salin');

        $sections = Livewire::actingAs($admin)
            ->test(AnalisisLiterasiPage::class)
            ->instance()
            ->shareSections();

        $this->assertStringContainsString('Materi Bintang Uji', $sections['belum']);
        $this->assertStringNotContainsString('*Bintang*', $sections['belum']);
    }

    public function test_teks_salin_tetap_terbentuk_saat_tidak_ada_data(): void
    {
        $admin = $this->createAdmin('admin-kosong-salin');

        $sections = Livewire::actingAs($admin)
            ->test(AnalisisLiterasiPage::class)
            ->instance()
            ->shareSections();

        $this->assertStringContainsString('Tidak ada data pada rentang dan filter ini.', $sections['partisipasi']);
        $this->assertStringContainsString('Belum ada dispensasi pada rentang dan filter ini.', $sections['dispensasi']);
        $this->assertStringContainsString('Tidak ada indikasi kemiripan.', $sections['plagiasi']);
    }

    public function test_ranking_kelas_jawaban_terbanyak_dibatasi_tujuh_kelas(): void
    {
        $material = $this->createMaterial('Materi Tujuh Kelas');

        // 9 kelas mengisi; tabel hanya boleh menampilkan 7 teratas.
        foreach (range(1, 9) as $index) {
            $class = 'X '.$index;

            foreach (range(1, $index) as $urutan) {
                $this->createResponse($material, $this->createStudent('Siswa '.$class.' '.$urutan, $class));
            }
        }

        $admin = $this->createAdmin('admin-tujuh-kelas');

        $ranking = Livewire::actingAs($admin)
            ->test(AnalisisLiterasiPage::class)
            ->instance()
            ->analytics['class_response_ranking'];

        $this->assertCount(7, $ranking);
        // Urutan dari jawaban terbanyak: X 9 (9 jawaban) sampai X 3.
        $this->assertSame('X 9', $ranking[0]['class']);
        $this->assertSame(9, $ranking[0]['total']);
        $this->assertSame('X 3', $ranking[6]['class']);
    }

    public function test_timeline_pengisian_menampilkan_awal_akhir_dan_hari_tersibuk(): void
    {
        $material = $this->createMaterial('Materi Timeline');

        $awal = now()->startOfMonth()->addDays(1)->setTime(7, 30);
        $sibuk = now()->startOfMonth()->addDays(3)->setTime(9, 0);

        $satu = $this->createResponse($material, $this->createStudent('Ahmad Timeline', 'XI 1'));
        $satu->forceFill(['submitted_at' => $awal])->save();

        foreach (['Budi Timeline', 'Cici Timeline'] as $nama) {
            $response = $this->createResponse($material, $this->createStudent($nama, 'XI 1'));
            $response->forceFill(['submitted_at' => $sibuk])->save();
        }

        $admin = $this->createAdmin('admin-timeline-walas');

        $component = Livewire::actingAs($admin)
            ->test(AnalisisLiterasiPage::class)
            ->assertSuccessful()
            ->assertSee('Timeline Pengisian Per Kelas');

        $timeline = $component->instance()->analytics['class_submission_timeline'];

        $this->assertCount(1, $timeline);
        $this->assertSame('XI 1', $timeline[0]['class']);
        $this->assertSame(3, $timeline[0]['total']);
        $this->assertSame($awal->toDateTimeString(), $timeline[0]['first_at']->toDateTimeString());
        $this->assertSame($sibuk->toDateTimeString(), $timeline[0]['last_at']->toDateTimeString());
        $this->assertSame(2, $timeline[0]['active_days']);
        $this->assertSame($sibuk->toDateString(), $timeline[0]['busiest_day']);
        $this->assertSame(2, $timeline[0]['busiest_day_total']);

        $sections = $component->instance()->shareSections();
        $this->assertStringContainsString('*TIMELINE PENGISIAN PER KELAS*', $sections['timeline']);
        $this->assertStringContainsString('Hari tersibuk', $sections['timeline']);
    }

    public function test_teks_share_mengikuti_rentang_tanggal_aktif(): void
    {
        $material = $this->createMaterial('Materi Share Rentang');
        $student = $this->createStudent('Ahmad Share', 'X 1');
        $response = $this->createResponse($material, $student);

        // Jawaban digeser ke bulan lalu supaya rentang default tidak memuatnya.
        $response->forceFill(['submitted_at' => now()->subMonthNoOverflow()])->save();

        $admin = $this->createAdmin('admin-share-rentang');

        $component = Livewire::actingAs($admin)->test(AnalisisLiterasiPage::class);

        $this->assertStringContainsString('*REKAP BULANAN LITERASI NUMERASI*', $component->instance()->shareText());

        $lalu = now()->subMonthNoOverflow();
        $component
            ->set('dari', $lalu->copy()->startOfMonth()->toDateString())
            ->set('sampai', $lalu->copy()->endOfMonth()->toDateString());

        $this->assertStringContainsString(
            $lalu->copy()->startOfMonth()->format('d/m/Y'),
            $component->instance()->shareText(),
        );
    }

    protected function createAdmin(string $username): User
    {
        $user = User::query()->create([
            'name' => 'Admin '.$username,
            'username' => $username,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('admin');

        return $user;
    }

    protected function createMaterial(string $title, array $attributes = []): PerpustakaanLiterasiMaterial
    {
        return PerpustakaanLiterasiMaterial::query()->create(array_merge([
            'title' => $title,
            'reading_content' => 'Bacaan untuk pengujian halaman analisis.',
            'program_category' => PerpustakaanLiterasiMaterial::CATEGORY_NUMERACY_EXCELLENCE,
            'is_active' => true,
        ], $attributes));
    }

    protected function createStudent(string $name, string $class): DataSiswa
    {
        return DataSiswa::query()->create([
            'nama' => $name,
            'rombel_saat_ini' => $class,
            'status' => 'aktif',
        ]);
    }

    protected function createResponse(
        PerpustakaanLiterasiMaterial $material,
        DataSiswa $student,
    ): PerpustakaanLiterasiResponse {
        return PerpustakaanLiterasiResponse::query()->create([
            'material_id' => $material->getKey(),
            'data_siswa_id' => $student->getKey(),
            'student_name_snapshot' => $student->nama,
            'student_class_snapshot' => $student->rombel_saat_ini,
            'submitted_at' => now(),
        ]);
    }

    protected function createDispensation(
        PerpustakaanLiterasiMaterial $material,
        DataSiswa $student,
        string $reason,
    ): PerpustakaanLiterasiDispensation {
        return PerpustakaanLiterasiDispensation::query()->create([
            'material_id' => $material->getKey(),
            'data_siswa_id' => $student->getKey(),
            'reason' => $reason,
            'student_name_snapshot' => $student->nama,
            'student_class_snapshot' => $student->rombel_saat_ini,
            'confirmed_at' => now(),
            'note' => $reason === PerpustakaanLiterasiDispensation::REASON_PERMISSION
                ? 'Izin acara keluarga di luar kota.'
                : null,
        ]);
    }

    protected function runLiteracyTables(): void
    {
        foreach ([
            '2026_05_11_120000_create_perpustakaan_literasi_program_tables.php',
            '2026_05_12_090000_add_grading_to_perpustakaan_literasi_answers_table.php',
            '2026_06_04_090000_add_answer_key_and_plagiarism_toggle_to_perpustakaan_literasi_questions_table.php',
            '2026_07_01_090000_add_soft_deletes_to_perpustakaan_literasi_tables.php',
            '2026_07_07_090000_add_literasi_numerasi_program_fields.php',
            '2026_07_30_090000_create_perpustakaan_literasi_dispensations_table.php',
        ] as $file) {
            $migration = require database_path('migrations/'.$file);
            $migration->up();
        }

        if (! Schema::hasColumn('perpustakaan_literasi_materials', 'opens_at')) {
            Schema::table('perpustakaan_literasi_materials', function (Blueprint $table): void {
                $table->date('opens_at')->nullable();
            });
        }
    }
}
