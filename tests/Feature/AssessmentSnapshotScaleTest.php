<?php

namespace Tests\Feature;

use App\Actions\Assessment\CreateAssessmentPeriodSnapshotAction;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\ScoreSource;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentComponent;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\TeachingAssignment;
use App\Models\DataSiswa;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Concerns\BootstrapsStudentAndTeacherTables;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AssessmentSnapshotScaleTest extends TestCase
{
    use BootstrapsStudentAndTeacherTables;
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        config(['assessment.enabled' => true]);
        $this->bootstrapStudentAndTeacherTables();
        $this->bootstrapUserAndPermissionTables();
        $migration = require database_path('migrations/2026_07_31_080000_create_assessment_foundation_tables.php');
        $migration->up();
        $this->artisan('assessment:install-defaults')->assertSuccessful();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_preflight_open_snapshots_162_students_across_seven_rombels_without_loss(): void
    {
        $year = AcademicYear::query()->create([
            'code' => '2027-2028',
            'name' => '2027/2028',
            'is_active' => true,
        ]);
        $semester = Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => 'ganjil',
            'name' => 'Ganjil',
            'is_active' => true,
        ]);
        $curriculumUser = User::query()->create([
            'name' => 'Kurikulum Skala',
            'username' => 'kurikulum-assessment-scale',
            'password' => 'secret123',
        ]);
        $curriculumUser->assignRole('kurikulum');
        $subjects = collect([
            Subject::query()->create([
                'code' => 'MAT-SCALE',
                'name' => 'Matematika Skala',
                'is_active' => true,
            ]),
            Subject::query()->create([
                'code' => 'BIN-SCALE',
                'name' => 'Bahasa Indonesia Skala',
                'is_active' => true,
            ]),
        ]);
        $studentsPerRombel = [24, 23, 23, 23, 23, 23, 23];
        $rombelIds = [];
        $studentSequence = 1;
        $timestamp = now();

        foreach ($studentsPerRombel as $index => $studentCount) {
            $rombel = Rombel::query()->create([
                'nama' => 'XI '.($index + 1),
                'angkatan' => 'XI',
                'is_active' => true,
            ]);
            $rombelIds[] = $rombel->getKey();
            $teacher = GuruTendik::query()->create([
                'nama' => 'Guru Skala '.($index + 1),
                'jenis_ptk' => 'Guru',
                'status' => 'Aktif',
            ]);
            User::query()->create([
                'name' => $teacher->nama,
                'username' => 'guru-assessment-scale-'.($index + 1),
                'password' => 'secret123',
                'guru_tendik_id' => $teacher->getKey(),
            ])->assignRole('guru_mapel');

            $studentRows = [];

            for ($studentIndex = 0; $studentIndex < $studentCount; $studentIndex++) {
                $studentRows[] = [
                    'nama' => sprintf('Siswa Skala %03d', $studentSequence),
                    'nisn' => sprintf('%010d', 9000000000 + $studentSequence),
                    'rombel_saat_ini' => $rombel->nama,
                    'jk' => $studentSequence % 2 === 0 ? 'P' : 'L',
                    'status' => 'aktif',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                $studentSequence++;
            }

            DataSiswa::query()->insert($studentRows);

            foreach ($subjects as $subject) {
                TeachingAssignment::query()->create([
                    'assessment_semester_id' => $semester->getKey(),
                    'assessment_subject_id' => $subject->getKey(),
                    'teacher_id' => $teacher->getKey(),
                    'rombel_id' => $rombel->getKey(),
                    'teacher_name_snapshot' => $teacher->nama,
                    'subject_name_snapshot' => $subject->name,
                    'rombel_name_snapshot' => $rombel->nama,
                    'is_active' => true,
                ]);
            }

            HomeroomAssignment::query()->create([
                'assessment_semester_id' => $semester->getKey(),
                'teacher_id' => $teacher->getKey(),
                'rombel_id' => $rombel->getKey(),
                'teacher_name_snapshot' => $teacher->nama,
                'rombel_name_snapshot' => $rombel->nama,
                'is_active' => true,
            ]);
        }

        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-SCALE-162',
            'name' => 'ASTS Uji Skala 162 Siswa',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::DRAFT,
            'entry_start_at' => now()->subHour(),
            'entry_end_at' => now()->addHour(),
            'report_date' => now()->toDateString(),
            'settings' => ['rombel_ids' => $rombelIds],
            'created_by' => $curriculumUser->getKey(),
        ]);

        foreach ($subjects as $subject) {
            $scheme = AssessmentScheme::query()->create([
                'assessment_period_id' => $period->getKey(),
                'assessment_subject_id' => $subject->getKey(),
                'assessment_period_rombel_id' => null,
                'name' => 'Skema '.$subject->name,
                'rounding_precision' => 0,
                'minimum_score' => 0,
                'maximum_score' => 100,
                'is_active' => true,
            ]);
            AssessmentComponent::query()->create([
                'assessment_scheme_id' => $scheme->getKey(),
                'code' => 'UTAMA',
                'name' => 'Nilai Utama',
                'domain' => 'Pemahaman',
                'weight' => 100,
                'maximum_score' => 100,
                'is_required' => true,
                'sort_order' => 1,
                'score_source' => ScoreSource::MANUAL,
            ]);
        }

        $opened = app(CreateAssessmentPeriodSnapshotAction::class)
            ->execute($curriculumUser, $period);

        $this->assertSame(AssessmentPeriodStatus::OPEN, $opened->status);
        $this->assertSame(7, $opened->periodRombels()->count());
        $this->assertSame(162, $opened->students()->count());
        $this->assertSame(14, $opened->assignments()->count());
        $this->assertSame(7, $opened->homerooms()->count());

        foreach ($studentsPerRombel as $index => $expectedCount) {
            $this->assertSame(
                $expectedCount,
                $opened->students()
                    ->where('rombel_name_snapshot', 'XI '.($index + 1))
                    ->count(),
            );
        }

        $reopened = app(CreateAssessmentPeriodSnapshotAction::class)
            ->execute($curriculumUser, $opened);

        $this->assertSame(7, $reopened->periodRombels()->count());
        $this->assertSame(162, $reopened->students()->count());
        $this->assertSame(14, $reopened->assignments()->count());
        $this->assertSame(7, $reopened->homerooms()->count());
    }
}
