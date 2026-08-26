<?php

namespace Tests\Feature;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentComponent;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\TeachingAssignment;
use App\Models\Rombel;
use App\Support\Assessment\AssessmentSetupStatus;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AssessmentSetupStatusTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        // Tabel rombels berada di luar modul penilaian, tetapi setiap langkah
        // penugasan/wali kelas membandingkan terhadap rombel AKTIF.
        (require database_path('migrations/2026_04_27_090000_create_rombels_table.php'))->up();
        (require database_path('migrations/2026_07_31_080000_create_assessment_foundation_tables.php'))->up();
        // report_group_* dibuat migrasi ini — dibutuhkan langkah "Mapel & kategori".
        (require database_path('migrations/2026_07_31_120000_extend_assessment_report_structure.php'))->up();
        (require database_path('migrations/2026_08_06_150000_add_assessment_subject_categories.php'))->up();
    }

    public function test_tanpa_data_semua_langkah_belum_dan_terkunci_berurutan(): void
    {
        $hasil = app(AssessmentSetupStatus::class)->untukSemester(null);

        $this->assertFalse($hasil['siap']);
        $this->assertSame(0, $hasil['langkah_selesai']);
        $this->assertSame(6, $hasil['total_langkah']);

        // Langkah 1 TIDAK terkunci (harus bisa dikerjakan), sisanya terkunci.
        $this->assertFalse($hasil['langkah'][0]['terkunci']);
        foreach (array_slice($hasil['langkah'], 1) as $l) {
            $this->assertTrue($l['terkunci'], "Langkah {$l['nomor']} seharusnya terkunci.");
        }
    }

    public function test_langkah_menyebut_apa_yang_kurang_bukan_hanya_belum_lengkap(): void
    {
        $semester = $this->semester();
        $this->rombel('X 1');
        $this->rombel('X 2');

        // Dua mapel, salah satunya TANPA kelompok rapor.
        $bindo = $this->subject('B. Indonesia', 'Wajib');
        $seni = Subject::query()->create([
            'code' => 'SENI',
            'name' => 'Seni Budaya',
            'is_active' => true,
        ]);

        $hasil = app(AssessmentSetupStatus::class)->untukSemester($semester);
        $mapel = $hasil['langkah'][1];

        $this->assertSame(AssessmentSetupStatus::KURANG, $mapel['status']);
        // Nama mapel yang bermasalah HARUS disebut, bukan hanya jumlahnya.
        $this->assertStringContainsString('Seni Budaya', (string) $mapel['catatan']);
        $this->assertStringNotContainsString('B. Indonesia', (string) $mapel['catatan']);
    }

    public function test_penugasan_mengajar_menyebut_kelas_yang_belum_lengkap(): void
    {
        $semester = $this->semester();
        $x1 = $this->rombel('X 1');
        $x2 = $this->rombel('X 2');
        $bindo = $this->subject('B. Indonesia', 'Wajib');
        $mtk = $this->subject('Matematika', 'Wajib');

        // X 1 lengkap (2 mapel), X 2 hanya 1 dari 2.
        $this->teaching($semester, $x1, $bindo, 301);
        $this->teaching($semester, $x1, $mtk, 302);
        $this->teaching($semester, $x2, $bindo, 301);

        $hasil = app(AssessmentSetupStatus::class)->untukSemester($semester);
        $langkah = $hasil['langkah'][2];

        $this->assertSame(AssessmentSetupStatus::KURANG, $langkah['status']);
        $this->assertStringContainsString('X 2', (string) $langkah['catatan']);
        $this->assertStringContainsString('1/2', (string) $langkah['catatan']);
        $this->assertStringNotContainsString('X 1', (string) $langkah['catatan']);
    }

    public function test_wali_kelas_menyebut_rombel_yang_belum_punya_wali(): void
    {
        $semester = $this->semester();
        $x1 = $this->rombel('X 1');
        $x2 = $this->rombel('X 2');

        HomeroomAssignment::query()->create([
            'assessment_semester_id' => $semester->getKey(),
            'teacher_id' => 311,
            'rombel_id' => $x1->getKey(),
            'teacher_name_snapshot' => 'Wali X1',
            'rombel_name_snapshot' => 'X 1',
            'is_active' => true,
        ]);

        $hasil = app(AssessmentSetupStatus::class)->untukSemester($semester);
        $langkah = $hasil['langkah'][3];

        $this->assertSame(AssessmentSetupStatus::KURANG, $langkah['status']);
        $this->assertStringContainsString('X 2', (string) $langkah['catatan']);
        $this->assertStringContainsString('1 dari 2', (string) $langkah['ringkasan']);
    }

    public function test_setelan_lengkap_menjadi_siap_dan_tidak_terkunci(): void
    {
        $semester = $this->semester();
        $x1 = $this->rombel('X 1');
        $bindo = $this->subject('B. Indonesia', 'Wajib');

        $this->teaching($semester, $x1, $bindo, 301);

        HomeroomAssignment::query()->create([
            'assessment_semester_id' => $semester->getKey(),
            'teacher_id' => 311,
            'rombel_id' => $x1->getKey(),
            'teacher_name_snapshot' => 'Wali X1',
            'rombel_name_snapshot' => 'X 1',
            'is_active' => true,
        ]);

        $periode = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $semester->assessment_academic_year_id,
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-SIAP',
            'name' => 'ASTS Siap',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::OPEN,
        ]);

        $skema = AssessmentScheme::query()->create([
            'assessment_period_id' => $periode->getKey(),
            'assessment_subject_id' => $bindo->getKey(),
            'name' => 'Skema Wajib',
            'is_active' => true,
        ]);

        AssessmentComponent::query()->create([
            'assessment_scheme_id' => $skema->getKey(),
            'code' => 'UH',
            'name' => 'Ulangan Harian',
            'weight' => 100,
        ]);

        $hasil = app(AssessmentSetupStatus::class)->untukSemester($semester);

        $this->assertTrue($hasil['siap'], 'Setelan lengkap seharusnya siap. Rincian: '
            .collect($hasil['langkah'])->map(fn ($l) => "{$l['nomor']}={$l['status']}")->implode(' '));
        $this->assertSame(6, $hasil['langkah_selesai']);

        foreach ($hasil['langkah'] as $l) {
            $this->assertFalse($l['terkunci'], "Langkah {$l['nomor']} tidak boleh terkunci saat semua selesai.");
        }
    }

    public function test_skema_tanpa_komponen_dianggap_belum_lengkap(): void
    {
        $semester = $this->semester();
        $x1 = $this->rombel('X 1');
        $bindo = $this->subject('B. Indonesia', 'Wajib');
        $this->teaching($semester, $x1, $bindo, 301);

        $periode = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $semester->assessment_academic_year_id,
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-NOKOMP',
            'name' => 'ASTS Tanpa Komponen',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::OPEN,
        ]);

        AssessmentScheme::query()->create([
            'assessment_period_id' => $periode->getKey(),
            'assessment_subject_id' => $bindo->getKey(),
            'name' => 'Skema Kosong',
            'is_active' => true,
        ]);

        $langkah = app(AssessmentSetupStatus::class)->untukSemester($semester)['langkah'][4];

        $this->assertSame(AssessmentSetupStatus::KURANG, $langkah['status']);
        $this->assertStringContainsString('Skema Kosong', (string) $langkah['catatan']);
    }

    private function semester(): Semester
    {
        $year = AcademicYear::query()->firstOrCreate(
            ['code' => '2026-2027'],
            ['name' => '2026/2027', 'is_active' => true],
        );

        return Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => '2026-2027-GANJIL',
            'name' => 'Semester Ganjil',
            'starts_on' => '2026-07-01',
            'is_active' => true,
        ]);
    }

    private function rombel(string $nama): Rombel
    {
        return Rombel::query()->create(['nama' => $nama, 'is_active' => true]);
    }

    private function subject(string $nama, string $kelompok): Subject
    {
        return Subject::query()->create([
            'code' => strtoupper(substr(md5($nama), 0, 6)),
            'name' => $nama,
            'report_group_code' => strtoupper(substr($kelompok, 0, 4)),
            'report_group_name' => $kelompok,
            'is_active' => true,
        ]);
    }

    private function teaching(Semester $semester, Rombel $rombel, Subject $subject, int $teacherId): TeachingAssignment
    {
        return TeachingAssignment::query()->create([
            'assessment_semester_id' => $semester->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'teacher_id' => $teacherId,
            'rombel_id' => $rombel->getKey(),
            'teacher_name_snapshot' => 'Guru '.$teacherId,
            'subject_name_snapshot' => $subject->name,
            'rombel_name_snapshot' => $rombel->nama,
            'is_active' => true,
        ]);
    }
}
