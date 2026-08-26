<?php

namespace Tests\Feature;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\Semester;
use App\Models\Assessment\StudentSubjectResult;
use App\Models\Assessment\Subject;
use App\Support\Assessment\Reporting\AssessmentReportCompleteness;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AssessmentReportCompletenessTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        // Mengikuti pola AssessmentFoundationTest: hanya migrasi yang
        // dibutuhkan, bukan RefreshDatabase (seluruh migrasi aplikasi tidak
        // dapat dijalankan di SQLite memori).
        $this->bootstrapUserAndPermissionTables();

        (require database_path('migrations/2026_07_31_080000_create_assessment_foundation_tables.php'))->up();
        (require database_path('migrations/2026_07_31_120000_extend_assessment_report_structure.php'))->up();
        (require database_path('migrations/2026_08_06_150000_add_assessment_subject_categories.php'))->up();
        (require database_path('migrations/2026_08_03_080000_add_stream_delivery_to_assessment_reports.php'))->up();
    }

    public function test_jenis_asat_hanya_semester_genap_dan_memakai_template_asas(): void
    {
        $this->assertSame(['genap'], AssessmentType::ASAT->semesterYangDiizinkan());
        $this->assertSame(['ganjil', 'genap'], AssessmentType::ASAS->semesterYangDiizinkan());
        $this->assertSame(['ganjil', 'genap'], AssessmentType::ASTS->semesterYangDiizinkan());

        // ASAT mencari template 'asat' lebih dulu, baru jatuh ke 'asas'.
        $this->assertSame(['asat', 'asas'], AssessmentType::ASAT->templateTypeCandidates());
        $this->assertSame(['asas'], AssessmentType::ASAS->templateTypeCandidates());

        $this->assertSame('Asesmen Sumatif Akhir Tahun', AssessmentType::ASAT->namaPanjang());
        $this->assertArrayHasKey('asat', AssessmentType::options());
    }

    public function test_mapel_tanpa_nilai_membuat_rapor_sementara_dan_terdaftar(): void
    {
        [$period, $rombel, $siswa, $assignments] = $this->siapkan();

        // Hanya 2 dari 3 mapel diisi nilainya.
        $this->isiNilai($period, $siswa, $assignments[0], 88.0);
        $this->isiNilai($period, $siswa, $assignments[1], 75.5);

        $hasil = app(AssessmentReportCompleteness::class)->untukSiswa($siswa);

        $this->assertTrue($hasil['sementara']);
        $this->assertSame(3, $hasil['total_mapel']);
        $this->assertSame(1, $hasil['jumlah_belum_diisi']);
        $this->assertSame('IPA', $hasil['mapel_belum_diisi'][0]['mapel']);
        $this->assertStringContainsString('SEMENTARA', $hasil['ringkasan']);
        $this->assertStringContainsString('1 dari 3 mapel', $hasil['ringkasan']);
    }

    public function test_nilai_lengkap_belum_verifikasi_bukan_sementara(): void
    {
        [$period, $rombel, $siswa, $assignments] = $this->siapkan();

        // Semua mapel terisi, tetapi belum ada yang diverifikasi.
        foreach ($assignments as $i => $a) {
            $this->isiNilai($period, $siswa, $a, 80.0 + $i);
        }

        $hasil = app(AssessmentReportCompleteness::class)->untukSiswa($siswa);

        // Inti pemisahan: isi rapor sudah utuh, jadi TIDAK sementara.
        $this->assertFalse($hasil['sementara']);
        $this->assertSame(0, $hasil['jumlah_belum_diisi']);
        $this->assertSame(3, $hasil['jumlah_belum_verifikasi']);
        $this->assertStringContainsString('menunggu verifikasi', $hasil['ringkasan']);
    }

    public function test_semua_terverifikasi_menghasilkan_rapor_final(): void
    {
        [$period, $rombel, $siswa, $assignments] = $this->siapkan();

        foreach ($assignments as $i => $a) {
            $this->isiNilai($period, $siswa, $a, 90.0 + $i);
            $a->update(['status' => AssignmentStatus::VERIFIED->value]);
        }

        $hasil = app(AssessmentReportCompleteness::class)->untukSiswa($siswa);

        $this->assertFalse($hasil['sementara']);
        $this->assertSame(0, $hasil['jumlah_belum_diisi']);
        $this->assertSame(0, $hasil['jumlah_belum_verifikasi']);
        $this->assertStringContainsString('FINAL', $hasil['ringkasan']);
    }

    public function test_pemeriksaan_rombel_menghitung_siswa_yang_nilainya_kosong(): void
    {
        [$period, $rombel, $siswa, $assignments] = $this->siapkan();

        // Siswa kedua di rombel yang sama, sengaja tanpa nilai apa pun.
        $siswa2 = $this->buatSiswa($period, $rombel, 902, 'Siswa Dua');

        // Siswa pertama lengkap 3 mapel, siswa kedua nol.
        foreach ($assignments as $i => $a) {
            $this->isiNilai($period, $siswa, $a, 85.0 + $i);
        }

        $hasil = app(AssessmentReportCompleteness::class)
            ->untukRombel((int) $period->getKey(), (int) $rombel->getKey());

        $this->assertSame(2, $hasil['total_siswa']);
        $this->assertTrue($hasil['sementara']);
        // Ketiga mapel belum lengkap karena siswa kedua kosong semua.
        $this->assertSame(3, $hasil['jumlah_belum_diisi']);
        $this->assertSame(1, $hasil['mapel_belum_diisi'][0]['siswa_kosong']);
        $this->assertSame(2, $hasil['mapel_belum_diisi'][0]['siswa_total']);
    }

    public function test_label_nilai_kosong_ditulis_belum_diisi(): void
    {
        $svc = app(AssessmentReportCompleteness::class);

        $this->assertSame('(belum diisi)', $svc->labelNilai(null));
        $this->assertSame('(belum diisi)', $svc->labelNilai(''));
        $this->assertSame('88', $svc->labelNilai(88));
        $this->assertSame('0', $svc->labelNilai(0), 'nilai 0 adalah nilai sah, bukan kosong');
    }

    /**
     * @return array{0: AssessmentPeriod, 1: AssessmentPeriodRombel, 2: AssessmentPeriodStudent, 3: array<int, AssessmentPeriodAssignment>}
     */
    private function siapkan(): array
    {
        $year = AcademicYear::query()->create([
            'code' => '2026-2027',
            'name' => '2026/2027',
            'is_active' => true,
        ]);
        $semester = Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => 'ganjil',
            'name' => 'Ganjil',
            'is_active' => true,
        ]);

        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-KELENGKAPAN',
            'name' => 'ASTS Uji',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::OPEN,
        ]);

        $rombel = AssessmentPeriodRombel::query()->create([
            'assessment_period_id' => $period->getKey(),
            'source_rombel_id' => 101,
            'rombel_name_snapshot' => 'X 1',
            'grade_level' => 'X',
            'is_active' => true,
        ]);

        $siswa = $this->buatSiswa($period, $rombel, 901, 'Siswa Satu');

        $assignments = [];
        foreach (['B. Indonesia', 'Matematika', 'IPA'] as $i => $mapel) {
            // assessment_subject_id punya foreign key, jadi mapelnya harus
            // benar-benar ada — bukan id karangan.
            $subject = Subject::query()->create([
                'code' => 'UJI-'.($i + 1),
                'name' => $mapel,
                'is_active' => true,
            ]);

            $assignments[] = AssessmentPeriodAssignment::query()->create([
                'assessment_period_id' => $period->getKey(),
                'assessment_period_rombel_id' => $rombel->getKey(),
                'teacher_id' => 300 + $i,
                'assessment_subject_id' => $subject->getKey(),
                'teacher_name_snapshot' => 'Guru '.$mapel,
                'subject_name_snapshot' => $mapel,
                'rombel_name_snapshot' => 'X 1',
                'status' => AssignmentStatus::SUBMITTED,
            ]);
        }

        return [$period, $rombel, $siswa, $assignments];
    }

    private function buatSiswa(
        AssessmentPeriod $period,
        AssessmentPeriodRombel $rombel,
        int $studentId,
        string $nama,
    ): AssessmentPeriodStudent {
        return AssessmentPeriodStudent::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'student_id' => $studentId,
            'student_name_snapshot' => $nama,
            'rombel_name_snapshot' => (string) $rombel->rombel_name_snapshot,
            'is_active' => true,
        ]);
    }

    private function isiNilai(
        AssessmentPeriod $period,
        AssessmentPeriodStudent $siswa,
        AssessmentPeriodAssignment $assignment,
        float $nilai,
    ): void {
        StudentSubjectResult::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_student_id' => $siswa->getKey(),
            'assessment_period_assignment_id' => $assignment->getKey(),
            'final_score' => $nilai,
            'calculated_at' => now(),
        ]);
    }
}
