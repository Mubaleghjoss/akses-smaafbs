<?php

namespace Tests\Feature;

use App\Enums\Assessment\AssessmentType;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\Semester;
use App\Support\Assessment\SemesterKind;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class SemesterKindTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        (require database_path('migrations/2026_07_31_080000_create_assessment_foundation_tables.php'))->up();
    }

    public function test_mengenali_ganjil_genap_dari_kode_dan_nama(): void
    {
        $svc = app(SemesterKind::class);

        // Pola produksi: code '2026-2027-GANJIL', name 'Semester Ganjil'.
        $this->assertSame(
            SemesterKind::GANJIL,
            $svc->dari($this->semester('2026-2027-GANJIL', 'Semester Ganjil', '2026-07-01')),
        );
        $this->assertSame(
            SemesterKind::GENAP,
            $svc->dari($this->semester('2026-2027-GENAP', 'Semester Genap', '2027-01-02')),
        );
    }

    public function test_menebak_dari_bulan_mulai_bila_nama_tidak_baku(): void
    {
        $svc = app(SemesterKind::class);

        // Tanpa kata ganjil/genap: Juli-Desember = ganjil, Januari-Juni = genap.
        $this->assertSame(
            SemesterKind::GANJIL,
            $svc->dari($this->semester('SMT-1', 'Semester Satu', '2026-08-01')),
        );
        $this->assertSame(
            SemesterKind::GENAP,
            $svc->dari($this->semester('SMT-2', 'Semester Dua', '2027-02-01')),
        );
    }

    public function test_asat_hanya_boleh_di_semester_genap(): void
    {
        $svc = app(SemesterKind::class);
        $ganjil = $this->semester('2026-2027-GANJIL', 'Semester Ganjil', '2026-07-01');
        $genap = $this->semester('2026-2027-GENAP', 'Semester Genap', '2027-01-02');

        $this->assertFalse($svc->cocok(AssessmentType::ASAT, $ganjil));
        $this->assertTrue($svc->cocok(AssessmentType::ASAT, $genap));

        // ASTS & ASAS bebas di kedua semester.
        foreach ([AssessmentType::ASTS, AssessmentType::ASAS] as $jenis) {
            $this->assertTrue($svc->cocok($jenis, $ganjil));
            $this->assertTrue($svc->cocok($jenis, $genap));
        }
    }

    public function test_alasan_penolakan_menyebut_jenis_dan_semester(): void
    {
        $svc = app(SemesterKind::class);
        $ganjil = $this->semester('2026-2027-GANJIL', 'Semester Ganjil', '2026-07-01');

        $alasan = $svc->alasanTidakCocok(AssessmentType::ASAT, $ganjil);

        $this->assertNotNull($alasan);
        $this->assertStringContainsString('ASAT', $alasan);
        $this->assertStringContainsString('Semester Genap', $alasan);
        $this->assertStringContainsString('Semester Ganjil', $alasan);

        // Yang cocok tidak menghasilkan alasan.
        $this->assertNull($svc->alasanTidakCocok(AssessmentType::ASTS, $ganjil));
    }

    public function test_pilihan_jenis_disaring_mengikuti_semester(): void
    {
        $svc = app(SemesterKind::class);

        $ganjil = $svc->jenisUntukSemester($this->semester('X-GANJIL', 'Semester Ganjil', '2026-07-01'));
        $this->assertArrayHasKey('asts', $ganjil);
        $this->assertArrayHasKey('asas', $ganjil);
        $this->assertArrayNotHasKey('asat', $ganjil, 'ASAT tidak boleh muncul di semester ganjil');

        $genap = $svc->jenisUntukSemester($this->semester('X-GENAP', 'Semester Genap', '2027-01-02'));
        $this->assertArrayHasKey('asat', $genap);
        $this->assertCount(3, $genap);
    }

    public function test_semester_tidak_dikenali_tidak_menghalangi(): void
    {
        $svc = app(SemesterKind::class);

        // Tanpa nama baku DAN tanpa tanggal mulai -> tidak dapat dikenali.
        $tanpaPetunjuk = $this->semester('APA-SAJA', 'Periode Khusus', null);

        $this->assertNull($svc->dari($tanpaPetunjuk));
        // Tidak dikenali = diizinkan, agar penamaan tak baku tidak memblokir kerja.
        $this->assertTrue($svc->cocok(AssessmentType::ASAT, $tanpaPetunjuk));
        $this->assertNull($svc->alasanTidakCocok(AssessmentType::ASAT, $tanpaPetunjuk));

        // Semester null juga tidak menghalangi.
        $this->assertNull($svc->dari(null));
        $this->assertTrue($svc->cocok(AssessmentType::ASAT, null));
    }

    private function semester(string $code, string $name, ?string $startsOn): Semester
    {
        $year = AcademicYear::query()->firstOrCreate(
            ['code' => '2026-2027'],
            ['name' => '2026/2027', 'is_active' => true],
        );

        return Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => $code,
            'name' => $name,
            'starts_on' => $startsOn,
            'is_active' => false,
        ]);
    }
}
