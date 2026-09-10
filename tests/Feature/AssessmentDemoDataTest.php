<?php

namespace Tests\Feature;

use App\Actions\Assessment\SaveAssessmentScoresAction;
use App\Console\Commands\SeedAssessmentDemoData;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\AssessmentScore;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\HomeroomReport;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\Semester;
use App\Models\Assessment\StudentSubjectResult;
use App\Models\Assessment\Subject;
use App\Models\Assessment\SubjectCategory;
use App\Models\Assessment\TeachingAssignment;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use App\Support\Assessment\AssessmentCalculator;
use App\Support\Assessment\AssessmentSchemeResolver;
use App\Support\Assessment\AssessmentStatusScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Concerns\BootstrapsAssessmentTables;
use Tests\Feature\Concerns\BootstrapsStudentAndTeacherTables;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AssessmentDemoDataTest extends TestCase
{
    use BootstrapsAssessmentTables;
    use BootstrapsStudentAndTeacherTables;
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assessment.enabled' => true]);
        $this->bootstrapUserAndPermissionTables();
        $this->bootstrapStudentAndTeacherTables();
        $this->bootstrapAssessmentTables();
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_pratinjau_tidak_menulis_baris_apa_pun(): void
    {
        $before = $this->demoCounts();
        $this->assertSame(0, Artisan::call('assessment:demo-data', ['--siswa' => 2, '--penuh' => true]), Artisan::output());
        $this->assertSame($before, $this->demoCounts());
    }

    public function test_matriks_resmi_utuh_tepat_satu_guru_dan_tanpa_penugasan_liar(): void
    {
        $this->jalankanDemo();
        $period = $this->period();
        $this->assertCount(19, SeedAssessmentDemoData::SUBJECTS);
        $this->assertCount(7, SeedAssessmentDemoData::CLASSES);
        $this->assertCount(12, SeedAssessmentDemoData::TEACHER_PLAN);

        $expected = [];
        foreach (SeedAssessmentDemoData::TEACHER_PLAN as $teacher => $subjects) {
            foreach ($subjects as $code => $classes) {
                foreach ($classes as $class) {
                    $expected[$code.'|'.$class] = $teacher;
                }
            }
        }
        $actual = $period->assignments()->with('subject')->get()->groupBy(fn ($assignment) => $assignment->subject->code.'|'.$assignment->rombel_name_snapshot);
        $this->assertCount(count($expected), $actual);
        foreach ($expected as $key => $teacher) {
            $this->assertArrayHasKey($key, $actual);
            $this->assertCount(1, $actual[$key]);
            $this->assertSame($teacher, $actual[$key]->first()->teacher_name_snapshot);
        }
        $this->assertSame([], array_values(array_diff($actual->keys()->all(), array_keys($expected))));
        $this->assertFalse($actual->has('SBD|X 1'));
        $this->assertFalse($actual->has('SBD|X 2'));
    }

    public function test_mode_penuh_menerbitkan_tiga_periode_dan_semua_penugasan_selesai(): void
    {
        Storage::fake('local');
        $this->jalankanPenuh();
        $periods = AssessmentPeriod::query()->orderBy('id')->get();
        $this->assertCount(3, $periods);
        $this->assertSame(['asts', 'asas', 'asat'], $periods->pluck('type')->map(fn ($type) => $type->value)->all());
        $this->assertSame(['Demo Ganjil', 'Demo Ganjil', 'Demo Genap'], $periods->map(fn ($period) => $period->semester->name)->all());
        $this->assertTrue($periods->every(fn ($period) => $period->status === AssessmentPeriodStatus::PUBLISHED));
        $this->assertSame(0, AssessmentPeriodAssignment::query()->whereIn('status', ['draft', 'submitted'])->count());
        $this->assertSame(AssessmentPeriodAssignment::query()->count(), AssessmentPeriodAssignment::query()->whereIn('status', ['verified', 'locked'])->count());
    }

    public function test_cakupan_nilai_dan_hasil_mapel_lengkap_serta_konsisten_dengan_kalkulator(): void
    {
        Storage::fake('local');
        $this->jalankanPenuh();
        foreach (AssessmentPeriod::all() as $period) {
            $expectedScores = 0;
            $expectedResults = 0;
            foreach ($period->assignments as $assignment) {
                $students = $period->students()->where('assessment_period_rombel_id', $assignment->assessment_period_rombel_id)->where('is_active', true)->count();
                $components = app(AssessmentSchemeResolver::class)->forAssignment($assignment)->components;
                $expectedScores += $students * $components->count();
                $expectedResults += $students;
            }
            $this->assertSame($expectedScores, AssessmentScore::query()->whereHas('assignment', fn ($q) => $q->where('assessment_period_id', $period->id))->whereNotNull('score')->count());
            $this->assertSame($expectedResults, StudentSubjectResult::query()->where('assessment_period_id', $period->id)->count());
            foreach (StudentSubjectResult::query()->where('assessment_period_id', $period->id)->get() as $stored) {
                $scheme = app(AssessmentSchemeResolver::class)->forAssignment($stored->assignment);
                $scores = AssessmentScore::query()->where('assessment_period_assignment_id', $stored->assessment_period_assignment_id)->where('assessment_period_student_id', $stored->assessment_period_student_id)->get();
                $calculated = app(AssessmentCalculator::class)->calculate($scheme->components, $scores, $scheme);
                $this->assertSame($calculated->finalScore, (float) $stored->final_score);
                $this->assertSame(AssessmentCalculator::FORMULA_VERSION, $stored->formula_version);
            }
        }
    }

    public function test_rapor_resmi_semua_siswa_dan_kelas_adalah_pdf_valid(): void
    {
        Storage::fake('local');
        $this->jalankanPenuh();
        foreach (AssessmentPeriod::all() as $period) {
            $snapshots = ReportSnapshot::query()->where('assessment_period_id', $period->id)->get();
            $this->assertCount($period->students()->where('is_active', true)->count(), $snapshots);
            foreach ($snapshots as $snapshot) {
                Storage::disk('local')->assertExists($snapshot->pdf_path);
                $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($snapshot->pdf_path));
            }
            $artifacts = ClassReportArtifact::query()->where('assessment_period_id', $period->id)->get();
            $this->assertCount(7, $artifacts);
            foreach ($artifacts as $artifact) {
                Storage::disk('local')->assertExists($artifact->pdf_path);
                $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($artifact->pdf_path));
            }
        }
    }

    public function test_demo_asas_and_asat_include_manual_homeroom_data_without_asat_promotion_status(): void
    {
        Storage::fake('local');
        $this->jalankanPenuh();

        $asas = AssessmentPeriod::query()->where('type', 'asas')->firstOrFail();
        $asat = AssessmentPeriod::query()->where('type', 'asat')->firstOrFail();
        $asasReport = HomeroomReport::query()->where('assessment_period_id', $asas->id)->firstOrFail();
        $asatReport = HomeroomReport::query()->where('assessment_period_id', $asat->id)->firstOrFail();

        $this->assertSame('Naik Kelas', $asasReport->promotion_status);
        $this->assertNull($asatReport->promotion_status);
        $this->assertNotEmpty(data_get($asasReport->achievement_data, 'kokurikuler'));
        $this->assertNotEmpty(data_get($asatReport->achievement_data, 'kokurikuler'));
        $this->assertNotEmpty(data_get($asasReport->achievement_data, 'items'));
        $this->assertNotEmpty(data_get($asatReport->achievement_data, 'items'));
    }

    public function test_hak_akses_mengikuti_matriks_resmi_dan_peran_gabungan(): void
    {
        $this->jalankanDemo();
        $period = $this->period();
        $scope = app(AssessmentStatusScope::class);
        $ahmad = User::where('username', 'demo-ahmad-tri-anggoro')->firstOrFail();
        $kholifin = User::where('username', 'demo-kholifin-suharno-hilman')->firstOrFail();
        $fitri = User::where('username', 'demo-fitri-nurfadilah')->firstOrFail();
        $kepala = User::where('username', 'demo-kepala-sekolah')->firstOrFail();
        $assignments = $period->assignments()->with('subject')->get();
        $ahmadOwn = $assignments->where('teacher_id', $ahmad->guru_tendik_id);
        $this->assertSame(['FIS', 'PKWU'], $ahmadOwn->pluck('subject.code')->unique()->sort()->values()->all());
        $this->assertSame(['X 1', 'X 2', 'XI 1', 'XII 1'], $ahmadOwn->where('subject.code', 'FIS')->pluck('rombel_name_snapshot')->sort()->values()->all());
        $this->assertSame(['TIK'], $assignments->where('teacher_id', $kholifin->guru_tendik_id)->pluck('subject.code')->unique()->values()->all());
        $foreign = $assignments->first(fn ($assignment) => $assignment->teacher_id !== $ahmad->guru_tendik_id);
        try {
            app(SaveAssessmentScoresAction::class)->execute($ahmad, $foreign, [], (int) $foreign->lock_version);
            $this->fail('Ahmad seharusnya tidak boleh mengisi penugasan guru lain.');
        } catch (AuthorizationException|HttpException|ValidationException $exception) {
            $status = $exception instanceof AuthorizationException ? 403 : ($exception instanceof ValidationException ? 422 : $exception->getStatusCode());
            $this->assertContains($status, [403, 422]);
        }
        $wali = $ahmad;
        $ownRombel = $period->periodRombels()->where('rombel_name_snapshot', 'X 1')->firstOrFail();
        $otherRombel = $period->periodRombels()->where('rombel_name_snapshot', 'X 2')->firstOrFail();
        $this->assertTrue($scope->bolehCetakRapor($wali, $period->id, $ownRombel->id));
        $this->assertFalse($scope->bolehCetakRapor($wali, $period->id, $otherRombel->id));
        $this->assertSame([$ownRombel->id], $scope->rombelBolehCetak($wali, $period->id));
        $this->assertSame('all', $scope->mode($fitri, $period->id));
        $this->assertTrue($kepala->can('penilaian.view'));
        $this->assertTrue($scope->bolehCetakRapor($kepala, $period->id, $otherRombel->id));
        $this->assertFalse($kepala->can('penilaian.verify'));
    }

    public function test_generator_idempoten_dalam_mode_penuh(): void
    {
        Storage::fake('local');
        $this->jalankanPenuh();
        $first = $this->demoCounts();
        $this->jalankanPenuh();
        $this->assertSame($first, $this->demoCounts());
    }

    public function test_ganti_lama_menghapus_generasi_lama_sebelum_membangun_matriks_resmi(): void
    {
        Rombel::query()->create(['nama' => 'DEMO XI-1', 'angkatan' => 'DEMO-2526', 'is_active' => true, 'catatan' => 'Rombel data demo Penilaian.']);
        $exit = Artisan::call('assessment:demo-data', ['--terapkan' => true, '--ganti-lama' => true, '--siswa' => 1]);
        $this->assertSame(0, $exit, Artisan::output());
        $this->assertDatabaseMissing('rombels', ['nama' => 'DEMO XI-1']);
        $this->assertSame(SeedAssessmentDemoData::CLASSES, Rombel::query()->orderBy('id')->pluck('nama')->all());
    }

    public function test_master_sekolah_dipinjam_dan_tidak_tersentuh_saat_dibersihkan(): void
    {
        $wajib = SubjectCategory::query()->create(['code' => 'DEMO-WAJIB', 'name' => 'Wajib Sekolah', 'type' => 'wajib', 'sort_order' => 71, 'description' => 'Kategori sekolah', 'is_active' => false]);
        SubjectCategory::query()->create(['code' => 'DEMO-PILIHAN', 'name' => 'Pilihan Sekolah', 'type' => 'pilihan', 'sort_order' => 72, 'description' => 'Kategori sekolah', 'is_active' => false]);
        foreach (SeedAssessmentDemoData::SUBJECTS as $index => $definition) {
            Subject::query()->create(['code' => $index, 'name' => 'Master '.$index, 'description' => 'Master sekolah', 'report_group_code' => 'SEKOLAH', 'report_group_name' => 'Kelompok Sekolah', 'report_group_sort_order' => 99, 'is_active' => false, 'sort_order' => 99]);
        }
        foreach (SeedAssessmentDemoData::CLASSES as $name) {
            Rombel::query()->create(['nama' => $name, 'angkatan' => '2026', 'is_active' => false, 'catatan' => 'Rombel sekolah']);
        }
        $year = AcademicYear::query()->create(['code' => '2026-2027', 'name' => 'Tahun Sekolah', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        $semester = Semester::query()->create(['assessment_academic_year_id' => $year->id, 'code' => '2026-2027-GANJIL', 'name' => 'Ganjil Sekolah', 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31', 'is_active' => true]);
        $teacher = GuruTendik::query()->create(['nip' => 'SEKOLAH-001', 'nama' => 'Guru Sekolah', 'jenis_ptk' => 'Guru', 'jk' => 'L', 'status' => 'aktif']);
        $assignment = TeachingAssignment::query()->create(['assessment_semester_id' => $semester->id, 'assessment_subject_id' => Subject::query()->where('code', 'BIG')->value('id'), 'assessment_subject_category_id' => $wajib->id, 'teacher_id' => $teacher->id, 'rombel_id' => Rombel::query()->where('nama', 'X 1')->value('id'), 'teacher_name_snapshot' => 'Guru Sekolah', 'subject_name_snapshot' => 'Master BIG', 'rombel_name_snapshot' => 'X 1', 'is_active' => false]);
        $subjectSnapshot = Subject::query()->orderBy('id')->get()->mapWithKeys(fn (Subject $subject) => [$subject->code => $subject->getAttributes()])->all();
        $rombelSnapshot = Rombel::query()->orderBy('id')->get()->mapWithKeys(fn (Rombel $rombel) => [$rombel->nama => $rombel->getAttributes()])->all();
        $assignmentSnapshot = $assignment->fresh()->getAttributes();

        $this->jalankanDemo();

        $this->assertSame($subjectSnapshot, Subject::query()->whereIn('code', array_keys(SeedAssessmentDemoData::SUBJECTS))->orderBy('id')->get()->mapWithKeys(fn (Subject $subject) => [$subject->code => $subject->getAttributes()])->all());
        $this->assertSame($rombelSnapshot, Rombel::query()->whereIn('nama', SeedAssessmentDemoData::CLASSES)->orderBy('id')->get()->mapWithKeys(fn (Rombel $rombel) => [$rombel->nama => $rombel->getAttributes()])->all());
        $this->assertSame($assignmentSnapshot, $assignment->fresh()->getAttributes());

        $this->assertSame(0, Artisan::call('assessment:demo-data', ['--bersihkan' => true, '--terapkan' => true]), Artisan::output());

        $this->assertCount(19, Subject::query()->whereIn('code', array_keys(SeedAssessmentDemoData::SUBJECTS))->get());
        $this->assertCount(7, Rombel::query()->whereIn('nama', SeedAssessmentDemoData::CLASSES)->get());
        $this->assertSame(0, AssessmentPeriod::query()->where('code', 'like', 'DEMO-%')->count());
        $this->assertSame(0, User::query()->where('username', 'like', 'demo-%')->count());
        $this->assertSame(0, AssessmentPeriodStudent::query()->count());
        $this->assertSame($assignmentSnapshot, $assignment->fresh()->getAttributes());
    }

    public function test_generator_ditolak_di_environment_production(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);
        $exit = Artisan::call('assessment:demo-data', ['--terapkan' => true, '--penuh' => true, '--siswa' => 1]);
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('DITOLAK', Artisan::output());
        $this->assertSame(0, AssessmentPeriod::query()->count());
    }

    private function jalankanDemo(): void
    {
        $this->assertSame(0, Artisan::call('assessment:demo-data', ['--terapkan' => true, '--siswa' => 1, '--status' => 'locked']), Artisan::output());
    }

    private function jalankanPenuh(): void
    {
        $this->assertSame(0, Artisan::call('assessment:demo-data', ['--terapkan' => true, '--penuh' => true, '--siswa' => 1]), Artisan::output());
    }

    private function period(): AssessmentPeriod
    {
        return AssessmentPeriod::query()->where('code', 'DEMO-ASTS-2526-GANJIL')->firstOrFail();
    }

    private function demoCounts(): array
    {
        return ['periods' => AssessmentPeriod::query()->where('code', 'like', 'DEMO-%')->count(), 'students' => AssessmentPeriodStudent::query()->count(), 'assignments' => AssessmentPeriodAssignment::query()->count(), 'scores' => AssessmentScore::query()->count(), 'results' => StudentSubjectResult::query()->count(), 'snapshots' => ReportSnapshot::query()->count(), 'artifacts' => ClassReportArtifact::query()->count(), 'users' => User::query()->where('username', 'like', 'demo-%')->count()];
    }
}
