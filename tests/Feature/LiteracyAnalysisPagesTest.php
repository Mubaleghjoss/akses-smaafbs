<?php

namespace Tests\Feature;

use App\Filament\Pages\Perpustakaan\AnalisisLiterasiPage;
use App\Filament\Pages\Perpustakaan\KelolaDispensasiPage;
use App\Filament\Pages\Perpustakaan\RincianHarianKelasPage;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages\ViewPerpustakaanLiterasiMaterial;
use App\Filament\Resources\PerpustakaanLiterasiMaterialResource\RelationManagers\ResponsesRelationManager;
use App\Models\DataSiswa;
use App\Models\PerpustakaanLiterasiAnswer;
use App\Models\PerpustakaanLiterasiDispensation;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
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

    public function test_dispensasi_terkonfirmasi_mengecualikan_slot_meski_response_juga_ada(): void
    {
        $material = $this->createMaterial('Materi Benturan Dispensasi');
        $student = $this->createStudent('Ahmad Benturan', 'X 3');
        $this->createResponse($material, $student);
        $this->createDispensation($material, $student, PerpustakaanLiterasiDispensation::REASON_SICK);

        $component = Livewire::actingAs($this->createAdmin('admin-benturan-dispensasi'))
            ->test(AnalisisLiterasiPage::class);

        $this->assertSame(1, $component->instance()->base['excluded_total']);
        $this->assertSame(0, $component->instance()->base['respondent_base']);
        $this->assertSame(0, $component->instance()->base['completed_total']);
        $this->assertSame('0/0', $component->instance()->base['ratio']);
    }

    public function test_materi_terpilih_tetap_mengikuti_rentang_tanggal(): void
    {
        $material = $this->createMaterial('Materi Terpilih Berjangka');
        $response = $this->createResponse($material, $this->createStudent('Ahmad Lama', 'X 4'));
        $response->forceFill(['submitted_at' => now()->subMonths(2)])->save();

        $component = Livewire::actingAs($this->createAdmin('admin-materi-berjangka'))
            ->test(AnalisisLiterasiPage::class)
            ->set('materi', (string) $material->getKey());

        $this->assertSame(0, $component->instance()->base['completed_total']);
        $this->assertSame(1, $component->instance()->base['missing_total']);
        $this->assertSame('0/1', $component->instance()->base['ratio']);
    }

    public function test_filter_kelas_dan_status_aktif_membatasi_ringkasan_grading(): void
    {
        $material = $this->createMaterial('Materi Grading Terfilter');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Jawaban terfilter?',
            'max_characters' => 500,
        ]);

        foreach ([['Aktif X1', 'X 1', 'aktif'], ['Aktif X2', 'X 2', 'aktif'], ['Nonaktif X1', 'X 1', 'keluar']] as [$name, $class, $status]) {
            $student = $this->createStudent($name, $class);
            $student->forceFill(['status' => $status])->save();
            $response = $this->createResponse($material, $student);
            PerpustakaanLiterasiAnswer::query()->create([
                'response_id' => $response->getKey(),
                'question_id' => $question->getKey(),
                'answer_text' => 'Jawaban '.$name,
                'character_count' => 15,
                'is_correct' => true,
                'score_earned' => 1,
                'score_possible' => 1,
            ]);
        }

        $component = Livewire::actingAs($this->createAdmin('admin-grading-terfilter'))
            ->test(AnalisisLiterasiPage::class)
            ->set('kelas', 'X 1');
        $summary = $component->instance()->analytics['grading_summary'];

        $this->assertSame(1, $summary['responses']);
        $this->assertSame(1, $summary['total_answers']);
        $this->assertSame(1, $summary['graded_answers']);
        $this->assertSame(1, $summary['correct_answers']);
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

    public function test_tombol_salin_diinisialisasi_ulang_saat_payload_filter_berubah(): void
    {
        $partial = file_get_contents(resource_path('views/filament/pages/perpustakaan/partials/salin-bagian.blade.php'));

        $this->assertIsString($partial);
        $this->assertStringContainsString('$payloadKey = hash(\'sha256\', $teks);', $partial);
        $this->assertStringContainsString('wire:key="lit-copybar-{{ $payloadKey }}"', $partial);

        $admin = $this->createAdmin('admin-key-salin-filter');
        $component = Livewire::actingAs($admin)
            ->test(AnalisisLiterasiPage::class)
            ->set('kategori', 'numeracy_excellence')
            ->set('dari', '2026-08-01')
            ->set('sampai', '2026-08-31');

        $ringkasan = $component->instance()->shareSections()['ringkasan'];

        $this->assertStringContainsString('*Periode:* 01 Agustus 2026 s.d. 31 Agustus 2026', $ringkasan);
        $this->assertStringContainsString('*Lingkup:* Numeracy Excellence', $ringkasan);
        $component->assertSee('lit-copybar-'.hash('sha256', $ringkasan), false);
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

        $this->assertStringContainsString('*ANALISIS LITERASI NUMERASI*', $component->instance()->shareText());

        $lalu = now()->subMonthNoOverflow();
        $component
            ->set('dari', $lalu->copy()->startOfMonth()->toDateString())
            ->set('sampai', $lalu->copy()->endOfMonth()->toDateString());

        $this->assertStringContainsString(
            $lalu->copy()->startOfMonth()->translatedFormat('d F Y'),
            $component->instance()->shareText(),
        );
    }

    public function test_timeline_menyertakan_rincian_harian_dan_daftar_belum_mengisi(): void
    {
        $material = $this->createMaterial('Materi Rincian Harian');

        $hariSatu = now()->startOfMonth()->addDays(1)->setTime(7, 15);
        $hariDua = now()->startOfMonth()->addDays(2)->setTime(8, 45);

        $ahmad = $this->createStudent('Ahmad Rincian', 'XII 1');
        $budi = $this->createStudent('Budi Rincian', 'XII 1');
        $belum = $this->createStudent('Cici Rincian', 'XII 1');
        $dispensasi = $this->createStudent('Dedi Rincian', 'XII 1');

        $this->createResponse($material, $ahmad)->forceFill(['submitted_at' => $hariSatu])->save();
        $this->createResponse($material, $budi)->forceFill(['submitted_at' => $hariDua])->save();
        $this->createDispensation($material, $dispensasi, PerpustakaanLiterasiDispensation::REASON_SICK);

        $admin = $this->createAdmin('admin-rincian-harian');

        $component = Livewire::actingAs($admin)->test(AnalisisLiterasiPage::class)->assertSuccessful();

        $row = $component->instance()->analytics['class_submission_timeline'][0];

        // Dispensasi keluar dari penyebut: 4 slot aktif - 1 dispensasi = 3 basis.
        $this->assertSame(4, $row['active_total']);
        $this->assertSame(1, $row['excluded_total']);
        $this->assertSame(3, $row['respondent_base']);
        $this->assertSame(2, $row['total']);
        $this->assertSame(2, $row['response_records']);
        $this->assertSame(1, $row['missing_total']);

        $this->assertCount(2, $row['days']);
        $this->assertSame($hariSatu->toDateString(), $row['days'][0]['date']);
        $this->assertSame(1, $row['days'][0]['total']);
        $this->assertSame('Ahmad Rincian', $row['days'][0]['students'][0]['name']);
        $this->assertSame($hariSatu->format('H:i'), $row['days'][0]['students'][0]['time']);

        $this->assertSame([$belum->nama], array_column($row['missing_students'], 'name'));
        $this->assertSame([$dispensasi->nama], array_column($row['excluded_students'], 'name'));

        $timelineTeks = $component->instance()->shareSections()['timeline'];
        $this->assertStringContainsString('Rincian per hari', $timelineTeks);
        $this->assertStringContainsString('Ahmad Rincian', $timelineTeks);
        $this->assertStringContainsString('Belum mengisi', $timelineTeks);
        $this->assertStringContainsString($belum->nama, $timelineTeks);
    }

    public function test_ranking_kelas_membawa_rincian_harian_untuk_menjelaskan_jumlah_jawaban(): void
    {
        $material = $this->createMaterial('Materi Ranking Rincian');

        $hari = now()->startOfMonth()->addDays(4)->setTime(10, 0);

        foreach (['Ahmad Rank', 'Budi Rank'] as $nama) {
            $this->createResponse($material, $this->createStudent($nama, 'XI 5'))
                ->forceFill(['submitted_at' => $hari])
                ->save();
        }

        $admin = $this->createAdmin('admin-ranking-rincian');

        $component = Livewire::actingAs($admin)->test(AnalisisLiterasiPage::class)->assertSuccessful();

        $row = $component->instance()->analytics['class_response_ranking'][0];

        $this->assertSame('XI 5', $row['class']);
        $this->assertSame(2, $row['total']);
        $this->assertSame(2, $row['unique_students']);
        $this->assertSame(1, $row['material_count']);
        $this->assertSame('2/2', $row['ratio']);
        $this->assertSame(100.0, $row['percentage']);
        $this->assertCount(1, $row['days']);
        $this->assertSame(2, $row['days'][0]['total']);

        // Container ranking partisipasi sudah dihapus dari halaman karena
        // tumpang tindih dengan peringkat jawaban benar. Datanya tetap tersedia
        // untuk timeline/rincian harian dan tidak perlu disalin dua kali.
        $this->assertArrayNotHasKey('ranking', $component->instance()->shareSections());
    }

    public function test_akurasi_kelas_menyertakan_materi_yang_belum_dinilai(): void
    {
        $material = $this->createMaterial('Materi Belum Dinilai');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan yang sudah dinilai?',
            'max_characters' => 500,
        ]);
        $pendingQuestion = $material->questions()->create([
            'sort_order' => 2,
            'prompt' => 'Pertanyaan yang belum dinilai?',
            'max_characters' => 500,
        ]);

        $siswa = $this->createStudent('Ahmad Nilai', 'XI 6');
        $response = $this->createResponse($material, $siswa);

        // Dinilai benar: menjaga baris kelas tetap muncul di ranking akurasi.
        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $response->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => 'Jawaban sudah dinilai benar oleh guru.',
            'character_count' => 37,
            'is_correct' => true,
            'score_earned' => 1,
            'score_possible' => 1,
        ]);

        // Belum dinilai: tanpa score_earned dan tanpa is_correct.
        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $response->getKey(),
            'question_id' => $pendingQuestion->getKey(),
            'answer_text' => 'Jawaban kedua yang masih menunggu penilaian.',
            'character_count' => 44,
            'score_possible' => 1,
        ]);

        // Kelas ini hanya memiliki jawaban pending. Barisnya tetap harus muncul,
        // walau belum ada denominator akurasi.
        $pendingOnlyResponse = $this->createResponse(
            $material,
            $this->createStudent('Budi Pending Saja', 'XI 8'),
        );
        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $pendingOnlyResponse->getKey(),
            'question_id' => $pendingQuestion->getKey(),
            'answer_text' => 'Jawaban kelas yang seluruhnya belum dinilai.',
            'character_count' => 43,
            'score_possible' => 1,
        ]);

        $admin = $this->createAdmin('admin-belum-dinilai');

        $component = Livewire::actingAs($admin)->test(AnalisisLiterasiPage::class)->assertSuccessful();

        $pending = $component->instance()->analytics['class_pending_grading'];

        $this->assertArrayHasKey('XI 6', $pending);
        $this->assertSame($material->getKey(), $pending['XI 6'][0]['material_id']);
        $this->assertSame(1, $pending['XI 6'][0]['pending_answers']);
        $this->assertSame(1, $pending['XI 6'][0]['pending_students']);
        $this->assertSame(1, $pending['XI 8'][0]['pending_answers']);
        $component->assertSee('XI 8')->assertSee('Nilai sekarang');

        $this->assertArrayNotHasKey('ranking', $component->instance()->shareSections());
    }

    public function test_partisipasi_kelas_melaporkan_jumlah_materi_dan_slot_wajib(): void
    {
        $satu = $this->createMaterial('Materi Wajib Satu');
        $dua = $this->createMaterial('Materi Wajib Dua');

        $ahmad = $this->createStudent('Ahmad Materi', 'X 3');
        $budi = $this->createStudent('Budi Materi', 'X 3');

        $this->createResponse($satu, $ahmad);
        $this->createResponse($dua, $ahmad);
        $this->createResponse($satu, $budi);

        $component = Livewire::actingAs($this->createAdmin('admin-materi-wajib'))
            ->test(AnalisisLiterasiPage::class)
            ->assertSuccessful();

        $base = $component->instance()->base;
        $row = collect($base['classes'])->firstWhere('class', 'X 3');

        // 2 siswa x 2 materi = 4 slot wajib; terisi 3.
        $this->assertSame(2, $row['material_count']);
        $this->assertSame(2, $row['unique_students']);
        $this->assertSame(4, $row['active_total']);
        $this->assertSame(3, $row['completed_total']);
        $this->assertSame('3/4', $row['ratio']);

        $this->assertSame(2, $base['material_count']);
        $this->assertSame(
            ['Materi Wajib Dua', 'Materi Wajib Satu'],
            array_column($base['materials'], 'title'),
        );

        $component->assertSee('Slot Wajib')->assertSee('Daftar materi pada rentang ini');
    }

    public function test_tautan_nilai_sekarang_membawa_filter_kelas_dan_status_belum_dinilai(): void
    {
        $material = $this->createMaterial('Materi Tautan Penilaian');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan menunggu penilaian?',
            'max_characters' => 500,
        ]);

        $response = $this->createResponse($material, $this->createStudent('Ahmad Tautan', 'XI 9'));
        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $response->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => 'Jawaban yang belum dinilai sama sekali.',
            'character_count' => 38,
            'score_possible' => 1,
        ]);

        $url = PerpustakaanLiterasiMaterialResource::gradingUrl((int) $material->getKey(), 'XI 9');

        $this->assertNotNull($url);
        $this->assertStringContainsString('relation=0', $url);
        $this->assertStringContainsString(
            ResponsesRelationManager::GRADING_FOCUS_CLASS.'=XI+9',
            $url,
        );
        $this->assertStringContainsString(
            ResponsesRelationManager::GRADING_FOCUS_STATUS.'=belum',
            $url,
        );

        // Halaman analisis harus memakai tautan terfilter itu, bukan URL polos.
        Livewire::actingAs($this->createAdmin('admin-tautan-nilai'))
            ->test(AnalisisLiterasiPage::class)
            ->assertSuccessful()
            ->assertSee(ResponsesRelationManager::GRADING_FOCUS_STATUS.'=belum', false);
    }

    public function test_relation_manager_menerapkan_filter_dari_parameter_url(): void
    {
        $material = $this->createMaterial('Materi Filter Otomatis');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan untuk filter otomatis?',
            'max_characters' => 500,
        ]);

        $belumDinilai = $this->createResponse($material, $this->createStudent('Ahmad Belum', 'XII 4'));
        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $belumDinilai->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => 'Jawaban yang belum dinilai.',
            'character_count' => 27,
            'score_possible' => 1,
        ]);

        $sudahDinilai = $this->createResponse($material, $this->createStudent('Budi Sudah', 'XII 4'));
        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $sudahDinilai->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => 'Jawaban yang sudah dinilai guru.',
            'character_count' => 32,
            'is_correct' => true,
            'score_earned' => 1,
            'score_possible' => 1,
        ]);

        $kelasLain = $this->createResponse($material, $this->createStudent('Cici Lain', 'XII 5'));
        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $kelasLain->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => 'Jawaban kelas lain yang belum dinilai.',
            'character_count' => 37,
            'score_possible' => 1,
        ]);

        // Parameter dibaca dari request; Livewire::withQueryParams menirukan URL.
        $component = Livewire::actingAs($this->createAdmin('admin-filter-otomatis'))
            ->withQueryParams([
                ResponsesRelationManager::GRADING_FOCUS_CLASS => 'XII 4',
                ResponsesRelationManager::GRADING_FOCUS_STATUS => 'belum',
            ])
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
            ])
            ->assertSuccessful();

        $filters = $component->instance()->tableFilters;

        $this->assertSame('XII 4', $filters['student_class_snapshot']['value']);
        // TernaryFilter menyimpan 0/1, bukan boolean PHP.
        $this->assertSame(0, $filters['grading_complete']['value']);
        $this->assertSame('active', $filters['response_status']['value']);

        $component
            ->assertCanSeeTableRecords([$belumDinilai])
            ->assertCanNotSeeTableRecords([$sudahDinilai, $kelasLain]);
    }

    public function test_fokus_penilaian_dititipkan_sebagai_properti_komponen_lazy(): void
    {
        // Relation manager Filament lazy: query string hanya ada pada request GET
        // halaman materi, sedangkan tabel dimuat oleh POST /livewire/update.
        // getDefaultProperties() harus menyalin fokus ke properti komponen agar
        // nilainya ikut ke snapshot placeholder dan bertahan sampai tabel dimuat.
        request()->query->set(ResponsesRelationManager::GRADING_FOCUS_CLASS, 'XII 4');
        request()->query->set(ResponsesRelationManager::GRADING_FOCUS_STATUS, 'belum');

        $properties = ResponsesRelationManager::getDefaultProperties();

        $this->assertTrue($properties['lazy'] ?? false);
        $this->assertSame('XII 4', $properties['fokusKelas']);
        $this->assertSame('belum', $properties['fokusPenilaian']);
    }

    public function test_relation_manager_menerapkan_filter_dari_properti_tanpa_query_string(): void
    {
        $material = $this->createMaterial('Materi Filter Lazy');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan untuk filter lazy?',
            'max_characters' => 500,
        ]);

        $belumDinilai = $this->createResponse($material, $this->createStudent('Dedi Belum', 'XII 6'));
        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $belumDinilai->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => 'Jawaban lazy yang belum dinilai.',
            'character_count' => 31,
            'score_possible' => 1,
        ]);

        $sudahDinilai = $this->createResponse($material, $this->createStudent('Eka Sudah', 'XII 6'));
        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $sudahDinilai->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => 'Jawaban lazy yang sudah dinilai.',
            'character_count' => 31,
            'is_correct' => true,
            'score_earned' => 1,
            'score_possible' => 1,
        ]);

        // Tanpa withQueryParams: meniru POST /livewire/update yang tidak membawa
        // query string, hanya properti hasil hidrasi snapshot placeholder.
        $component = Livewire::actingAs($this->createAdmin('admin-filter-lazy'))
            ->test(ResponsesRelationManager::class, [
                'ownerRecord' => $material,
                'pageClass' => ViewPerpustakaanLiterasiMaterial::class,
                'fokusKelas' => 'XII 6',
                'fokusPenilaian' => 'belum',
            ])
            ->assertSuccessful();

        $filters = $component->instance()->tableFilters;

        $this->assertSame('XII 6', $filters['student_class_snapshot']['value']);
        $this->assertSame(0, $filters['grading_complete']['value']);

        $component
            ->assertCanSeeTableRecords([$belumDinilai])
            ->assertCanNotSeeTableRecords([$sudahDinilai]);
    }

    public function test_semua_container_analisis_minimize_saat_pertama_dibuka(): void
    {
        $partials = [
            'filter-analisis',
            'ringkasan-responden',
            'partisipasi-kelas',
            'timeline-walas',
            'dispensasi-ringkas',
            'analisis-siswa',
            'peringkat-benar',
            'plagiasi-literasi',
        ];

        foreach ($partials as $partial) {
            $source = file_get_contents(resource_path("views/filament/pages/perpustakaan/partials/{$partial}.blade.php"));

            $this->assertStringContainsString(
                '<x-filament::section collapsible collapsed>',
                $source,
                "Container {$partial} harus dapat diminimize dan tertutup secara default.",
            );
        }
    }

    public function test_container_siswa_menggabungkan_terbaik_salah_dan_sering_tidak_mengisi_sesuai_filter(): void
    {
        $materiSatu = $this->createMaterial('Materi Gabungan Satu', ['opens_at' => now()]);
        $materiDua = $this->createMaterial('Materi Gabungan Dua', ['opens_at' => now()]);
        $soal = $materiSatu->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan gabungan?',
            'max_characters' => 500,
        ]);

        $terbaik = $this->createStudent('Ahmad Terbaik Filter', 'X 1');
        $seringKosong = $this->createStudent('Budi Sering Kosong Filter', 'X 1');
        $luarKelas = $this->createStudent('Cici Luar Filter', 'X 2');

        $response = $this->createResponse($materiSatu, $terbaik);
        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $response->getKey(),
            'question_id' => $soal->getKey(),
            'answer_text' => 'Jawaban yang salah.',
            'character_count' => 18,
            'is_correct' => false,
            'score_earned' => 0,
            'score_possible' => 1,
        ]);

        $component = Livewire::actingAs($this->createAdmin('admin-container-siswa-gabungan'))
            ->test(AnalisisLiterasiPage::class)
            ->set('kelas', 'X 1')
            ->assertSuccessful()
            ->assertDontSee('Ranking Kelas & Siswa')
            ->assertSee('Analisis Siswa')
            ->assertSee('Siswa Terbaik Per Kelas')
            ->assertSee('Siswa dengan Jawaban Salah Terbanyak')
            ->assertSee('Siswa yang Sering Tidak Mengisi')
            ->assertSee($terbaik->nama)
            ->assertSee($seringKosong->nama)
            ->assertDontSee($luarKelas->nama);

        $sections = $component->instance()->shareSections();
        $this->assertArrayHasKey('siswa', $sections);
        $this->assertArrayNotHasKey('ranking', $sections);
        $this->assertStringContainsString('*Kelas:* X 1', $sections['siswa']);
        $this->assertStringContainsString($terbaik->nama, $sections['siswa']);
        $this->assertStringContainsString($seringKosong->nama, $sections['siswa']);
        $this->assertStringContainsString('2 slot tidak diisi', $sections['siswa']);
        $this->assertStringNotContainsString($luarKelas->nama, $sections['siswa']);

        $modal = view('filament.pages.perpustakaan.partials.rekap-share', [
            'sections' => $sections,
            'sectionLabels' => LiteracyAnalysisShareText::sectionLabels(),
            'allText' => $component->instance()->shareText(),
            'periodeLabel' => $component->instance()->periodeLabel,
            'lingkupLabel' => $component->instance()->lingkupLabel,
        ])->render();
        $this->assertStringContainsString('Semua Bagian (Filter Aktif)', $modal);
        $this->assertStringNotContainsString('Rekap Bulanan Lengkap', $modal);
        $this->assertStringContainsString('Budi Sering Kosong Filter', $modal);
        $this->assertStringNotContainsString('Cici Luar Filter', $modal);
    }

    public function test_peringkat_jawaban_benar_mencakup_semua_kelas_dan_menandai_peringkat_sementara(): void
    {
        $material = $this->createMaterial('Materi Peringkat Benar');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Pertanyaan peringkat?',
            'max_characters' => 500,
        ]);
        $questionKedua = $material->questions()->create([
            'sort_order' => 2,
            'prompt' => 'Pertanyaan peringkat kedua?',
            'max_characters' => 500,
        ]);

        // Kelas A: 2 poin benar, penilaian lengkap.
        $unggul = $this->createResponse($material, $this->createStudent('Ahmad Unggul', 'X 7'));
        foreach ([$question, $questionKedua] as $soal) {
            PerpustakaanLiterasiAnswer::query()->create([
                'response_id' => $unggul->getKey(),
                'question_id' => $soal->getKey(),
                'answer_text' => 'Jawaban benar yang sudah dinilai.',
                'character_count' => 33,
                'is_correct' => true,
                'score_earned' => 1,
                'score_possible' => 1,
            ]);
        }

        // Kelas B: 1 poin benar + 1 jawaban tertunda -> potensi menyamai kelas A.
        $tertunda = $this->createResponse($material, $this->createStudent('Budi Tertunda', 'X 8'));
        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $tertunda->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => 'Jawaban benar kelas kedua.',
            'character_count' => 26,
            'is_correct' => true,
            'score_earned' => 1,
            'score_possible' => 1,
        ]);
        PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $tertunda->getKey(),
            'question_id' => $questionKedua->getKey(),
            'answer_text' => 'Jawaban kedua yang masih menunggu penilaian.',
            'character_count' => 44,
            'score_possible' => 1,
        ]);

        $component = Livewire::actingAs($this->createAdmin('admin-peringkat-benar'))
            ->test(AnalisisLiterasiPage::class)
            ->assertSuccessful();

        $peringkat = collect($component->instance()->analytics['class_correct_ranking_full'])->keyBy('class');

        $this->assertSame(1, $peringkat['X 7']['rank']);
        $this->assertSame(2, $peringkat['X 7']['correct_answers']);
        $this->assertSame(0, $peringkat['X 7']['pending_answers']);
        // Kelas teratas tetap ditandai sementara karena kelas lain masih bisa
        // menyamainya setelah penilaian tertunda selesai.
        $this->assertTrue($peringkat['X 7']['rank_provisional']);

        $this->assertSame(2, $peringkat['X 8']['rank']);
        $this->assertSame(1, $peringkat['X 8']['correct_answers']);
        $this->assertSame(1, $peringkat['X 8']['pending_answers']);
        $this->assertSame(2, $peringkat['X 8']['potential_correct']);
        $this->assertTrue($peringkat['X 8']['rank_provisional']);
        $this->assertSame(
            'Materi Peringkat Benar',
            $peringkat['X 8']['pending_materials'][0]['material_title'],
        );

        $component
            ->assertSee('Peringkat Kelas: Jawaban Benar Terbanyak')
            ->assertSee('Masih bisa berubah');

        $teks = $component->instance()->shareSections()['peringkat'];
        $this->assertStringContainsString('*PERINGKAT KELAS: JAWABAN BENAR TERBANYAK*', $teks);
        $this->assertStringContainsString('Belum final', $teks);
        $this->assertStringContainsString('potensi benar sampai 2', $teks);
    }

    public function test_halaman_rincian_harian_menampilkan_pengisi_dan_yang_belum_per_hari(): void
    {
        $material = $this->createMaterial('Materi Rincian Halaman');

        $hariSatu = now()->startOfMonth()->addDays(1)->setTime(7, 30);
        $hariDua = now()->startOfMonth()->addDays(2)->setTime(9, 10);

        $ahmad = $this->createStudent('Ahmad Harian', 'XI 4');
        $budi = $this->createStudent('Budi Harian', 'XI 4');
        $belum = $this->createStudent('Cici Harian', 'XI 4');

        $this->createResponse($material, $ahmad)->forceFill(['submitted_at' => $hariSatu])->save();
        $this->createResponse($material, $budi)->forceFill(['submitted_at' => $hariDua])->save();

        $component = Livewire::actingAs($this->createAdmin('admin-rincian-halaman'))
            ->test(RincianHarianKelasPage::class, ['kelas' => 'XI 4'])
            ->assertSuccessful();

        $rincian = $component->instance()->rincian;

        $this->assertSame('XI 4', $rincian['class']);
        $this->assertSame(1, $rincian['material_count']);
        $this->assertSame(3, $rincian['respondent_base']);
        $this->assertSame(2, $rincian['completed_total']);
        $this->assertCount(2, $rincian['days']);

        // Hari pertama: Ahmad sudah mengisi, Budi dan Cici belum.
        $this->assertSame(['Ahmad Harian'], array_column($rincian['days'][0]['students'], 'name'));
        $this->assertSame(2, $rincian['days'][0]['pending_total']);
        $this->assertSame(
            ['Budi Harian', 'Cici Harian'],
            array_column($rincian['days'][0]['pending_students'], 'name'),
        );

        // Hari kedua: Budi menyusul, hanya Cici yang masih belum.
        $this->assertSame(['Budi Harian'], array_column($rincian['days'][1]['students'], 'name'));
        $this->assertSame(1, $rincian['days'][1]['pending_total']);
        $this->assertSame($belum->nama, $rincian['days'][1]['pending_students'][0]['name']);

        $component->assertSee('Sudah mengisi')->assertSee('Belum mengisi');
    }

    public function test_timeline_menautkan_rincian_harian_ke_halaman_tersendiri(): void
    {
        $material = $this->createMaterial('Materi Tautan Rincian');
        $this->createResponse($material, $this->createStudent('Ahmad Tautan Rincian', 'XII 7'));

        Livewire::actingAs($this->createAdmin('admin-tautan-rincian'))
            ->test(AnalisisLiterasiPage::class)
            ->assertSuccessful()
            ->assertSee('Rincian harian')
            ->assertSee('rincian-harian-literasi', false);
    }

    public function test_ranking_kemiripan_tidak_memasukkan_hasil_yang_sudah_dinyatakan_aman(): void
    {
        $material = $this->createMaterial('Materi Kemiripan Ditinjau');
        $question = $material->questions()->create([
            'sort_order' => 1,
            'prompt' => 'Tuliskan tanggapanmu.',
            'max_characters' => 500,
        ]);

        $responses = collect([
            $this->createStudent('Ahmad Mirip', 'XI 7'),
            $this->createStudent('Budi Mirip', 'XI 7'),
            $this->createStudent('Cici Mirip', 'XI 7'),
        ])->map(fn (DataSiswa $student): PerpustakaanLiterasiResponse => $this->createResponse($material, $student));

        $answers = $responses->map(fn (PerpustakaanLiterasiResponse $response) => PerpustakaanLiterasiAnswer::query()->create([
            'response_id' => $response->getKey(),
            'question_id' => $question->getKey(),
            'answer_text' => 'Jawaban untuk pengujian kemiripan.',
            'character_count' => 33,
        ]));

        foreach ([
            [0, 1, PerpustakaanLiterasiSimilarityMatch::REVIEW_CLEARED],
            [0, 2, PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED],
        ] as [$later, $matched, $status]) {
            PerpustakaanLiterasiSimilarityMatch::query()->create([
                'material_id' => $material->getKey(),
                'question_id' => $question->getKey(),
                'later_response_id' => $responses[$later]->getKey(),
                'matched_response_id' => $responses[$matched]->getKey(),
                'later_answer_id' => $answers[$later]->getKey(),
                'matched_answer_id' => $answers[$matched]->getKey(),
                'student_class_snapshot' => 'XI 7',
                'similarity_score' => 90,
                'later_submitted_at' => now(),
                'matched_submitted_at' => now(),
                'review_status' => $status,
            ]);
        }

        $component = Livewire::actingAs($this->createAdmin('admin-kemiripan-ditinjau'))
            ->test(AnalisisLiterasiPage::class)
            ->set('materi', (string) $material->getKey());
        $analytics = $component->instance()->analytics;

        $this->assertSame(1, $analytics['plagiarism_class_ranking'][0]['total']);
        $this->assertSame(1, $analytics['plagiarism_student_ranking'][0]['total']);
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
            '2026_05_12_101500_add_review_status_to_perpustakaan_literasi_similarity_matches_table.php',
            '2026_05_12_090000_add_grading_to_perpustakaan_literasi_answers_table.php',
            '2026_06_04_090000_add_answer_key_and_plagiarism_toggle_to_perpustakaan_literasi_questions_table.php',
            '2026_07_01_090000_add_soft_deletes_to_perpustakaan_literasi_tables.php',
            '2026_07_07_090000_add_literasi_numerasi_program_fields.php',
            '2026_07_29_080000_add_objective_question_types_to_literacy.php',
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
