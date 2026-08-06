<?php

namespace Tests\Feature;

use App\Actions\Assessment\SyncOpenPeriodSubjectAssignmentsAction;
use App\Actions\Assessment\SyncOpenPeriodSubjectsAction;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\AssignmentStatus;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\Semester;
use App\Models\Assessment\Subject;
use App\Models\Assessment\SubjectCategory;
use App\Models\Assessment\TeachingAssignment;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use App\Support\Assessment\AssessmentSchemeResolver;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BootstrapsStudentAndTeacherTables;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AssessmentTeachingPlanTest extends TestCase
{
    use BootstrapsStudentAndTeacherTables;
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapStudentAndTeacherTables();
        $this->bootstrapUserAndPermissionTables();
        config(['assessment.enabled' => true]);
        (require database_path('migrations/2026_07_31_080000_create_assessment_foundation_tables.php'))->up();
        (require database_path('migrations/2026_07_31_120000_extend_assessment_report_structure.php'))->up();
        (require database_path('migrations/2026_08_06_150000_add_assessment_subject_categories.php'))->up();

        $year = AcademicYear::query()->create([
            'code' => '2026-2027',
            'name' => 'Tahun Pelajaran 2026/2027',
            'is_active' => true,
        ]);
        Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => '2026-2027-GANJIL',
            'name' => 'Semester Ganjil',
            'is_active' => true,
        ]);

        foreach (['X 1', 'X 2', 'XI 1', 'XI 2', 'XII 1', 'XII 2', 'XII 3'] as $className) {
            Rombel::query()->create(['nama' => $className, 'is_active' => true]);
        }

        $names = [
            'Ahmad Tri Anggoro, S.T.', 'Aisyah Sekar Tri Wardani, S.Pd.', 'Fitri Nurfadhilah, S.Pd.',
            'Khoiriyah, S.Pd.', 'Kholifin Hilman Suharno, S.Pd.', 'Komariyah, S.Si.',
            'M. Fandakir, S.Pd.', 'Menik Putri Lestari, S.T.', 'M. Zahki Maulana, S.H.',
            'Mulky Fauzan, S.T., M.M.', 'Nurul Afifah, S.Pd.', 'Nurul Izzah Nisfulaily, S.Pd.',
        ];
        foreach ($names as $index => $name) {
            $teacher = GuruTendik::query()->create(['nama' => $name, 'jenis_ptk' => 'Guru', 'status' => 'aktif']);
            User::query()->create([
                'name' => $name,
                'username' => 'guru-rencana-'.($index + 1),
                'password' => 'secret123',
                'guru_tendik_id' => $teacher->getKey(),
            ]);
        }
    }

    public function test_preview_is_read_only_and_apply_is_atomic_idempotent_with_assignment_categories(): void
    {
        $this->artisan('assessment:teaching-plan-2026')
            ->expectsOutputToContain('Mode preview')
            ->assertSuccessful();
        $this->assertSame(0, Subject::query()->count());
        $this->assertSame(0, TeachingAssignment::query()->count());

        $semester = Semester::query()->where('code', '2026-2027-GANJIL')->firstOrFail();
        $oldTeacher = GuruTendik::query()->create(['nama' => 'Putra Kamulyan', 'jenis_ptk' => 'Guru', 'status' => 'aktif']);
        $oldSubject = Subject::query()->create(['code' => 'BIN', 'name' => 'Bahasa Indonesia', 'is_active' => true]);
        $oldAssignment = TeachingAssignment::query()->create([
            'assessment_semester_id' => $semester->getKey(),
            'assessment_subject_id' => $oldSubject->getKey(),
            'assessment_subject_category_id' => SubjectCategory::query()->where('code', 'UMUM-A-LEGACY')->value('id'),
            'teacher_id' => $oldTeacher->getKey(),
            'rombel_id' => Rombel::query()->where('nama', 'X 1')->value('id'),
            'teacher_name_snapshot' => $oldTeacher->nama,
            'subject_name_snapshot' => $oldSubject->name,
            'rombel_name_snapshot' => 'X 1',
            'is_active' => true,
        ]);

        $this->artisan('assessment:teaching-plan-2026', ['--apply' => true])
            ->expectsOutputToContain('Selesai:')
            ->assertSuccessful();

        $this->assertSame(19, Subject::query()->count());
        $this->assertSame(99, TeachingAssignment::query()->where('is_active', true)->count());
        $this->assertFalse($oldAssignment->fresh()->is_active);
        $this->assertSame(3, SubjectCategory::query()->count());
        $this->assertSame(2, SubjectCategory::query()->where('is_active', true)->count());

        $bin = Subject::query()->where('code', 'BIN')->firstOrFail();
        $x1 = Rombel::query()->where('nama', 'X 1')->firstOrFail();
        $xi1 = Rombel::query()->where('nama', 'XI 1')->firstOrFail();
        $this->assertDatabaseHas('assessment_teaching_assignments', [
            'assessment_semester_id' => $semester->getKey(),
            'assessment_subject_id' => $bin->getKey(),
            'rombel_id' => $x1->getKey(),
            'assessment_subject_category_id' => SubjectCategory::query()->where('code', 'PILIHAN')->value('id'),
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('assessment_teaching_assignments', [
            'assessment_subject_id' => $bin->getKey(),
            'rombel_id' => $xi1->getKey(),
            'assessment_subject_category_id' => SubjectCategory::query()->where('code', 'WAJIB')->value('id'),
            'is_active' => true,
        ]);

        $firstCount = TeachingAssignment::query()->count();
        $this->artisan('assessment:teaching-plan-2026', ['--apply' => true])->assertSuccessful();
        $this->assertSame($firstCount, TeachingAssignment::query()->count());
        $this->assertSame(99, TeachingAssignment::query()->where('is_active', true)->count());
    }

    public function test_apply_stops_before_writing_when_one_linked_teacher_is_missing(): void
    {
        User::query()->where('username', 'guru-rencana-12')->delete();

        $this->artisan('assessment:teaching-plan-2026', ['--apply' => true])
            ->expectsOutputToContain('harus tepat satu akun tertaut')
            ->assertFailed();

        $this->assertSame(0, Subject::query()->count());
        $this->assertSame(0, TeachingAssignment::query()->count());
    }

    public function test_explicit_open_period_sync_moves_teacher_and_increments_lock_without_touching_locked_periods(): void
    {
        $this->artisan('assessment:teaching-plan-2026', ['--apply' => true])->assertSuccessful();
        Role::findOrCreate('admin', 'web');
        $actor = User::query()->create([
            'name' => 'Admin Sinkron Plotting',
            'username' => 'admin-sinkron-plotting',
            'password' => 'secret123',
        ]);
        $actor->assignRole('admin');
        $semester = Semester::query()->where('code', '2026-2027-GANJIL')->firstOrFail();
        $subject = Subject::query()->where('code', 'FIS')->firstOrFail();
        $rombel = Rombel::query()->where('nama', 'X 1')->firstOrFail();
        $desiredMaster = TeachingAssignment::query()->where([
            'assessment_semester_id' => $semester->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'rombel_id' => $rombel->getKey(),
            'is_active' => true,
        ])->firstOrFail();
        $desiredMaster->teacher->userAccount->assignRole('admin');
        $oldTeacher = GuruTendik::query()->create(['nama' => 'Guru Lama Fisika', 'jenis_ptk' => 'Guru', 'status' => 'aktif']);
        $year = $semester->academicYear;
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-SYNC-PLOTTING',
            'name' => 'ASTS Sinkron Plotting',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::OPEN,
            'settings' => ['rombel_ids' => [$rombel->getKey()]],
            'created_by' => $actor->getKey(),
        ]);
        $periodRombel = AssessmentPeriodRombel::query()->create([
            'assessment_period_id' => $period->getKey(),
            'source_rombel_id' => $rombel->getKey(),
            'rombel_name_snapshot' => $rombel->nama,
            'grade_level' => 'X',
            'is_active' => true,
        ]);
        $periodAssignment = AssessmentPeriodAssignment::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $periodRombel->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'teacher_id' => $oldTeacher->getKey(),
            'teacher_name_snapshot' => $oldTeacher->nama,
            'subject_name_snapshot' => $subject->name,
            'subject_group_code_snapshot' => 'UMUM-A-LEGACY',
            'subject_group_name_snapshot' => 'Kelompok A (Umum)',
            'subject_group_sort_order_snapshot' => 900,
            'subject_sort_order_snapshot' => $subject->sort_order,
            'rombel_name_snapshot' => $rombel->nama,
            'status' => AssignmentStatus::DRAFT,
            'lock_version' => 1,
        ]);
        $scheme = AssessmentScheme::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'name' => 'TES 1',
            'rounding_precision' => 2,
            'minimum_score' => 50,
            'maximum_score' => 100,
            'settings' => ['kkm' => 75],
            'is_active' => true,
        ]);
        $scheme->components()->create([
            'code' => 'TES1',
            'name' => 'TES 1',
            'weight' => 100,
            'maximum_score' => 100,
            'is_required' => true,
            'sort_order' => 1,
            'score_source' => 'manual',
            'settings' => ['is_active' => true],
        ]);

        $summary = app(SyncOpenPeriodSubjectAssignmentsAction::class)->execute($actor, $subject, $period);
        $this->assertSame(['created' => 0, 'updated' => 1, 'deleted' => 0], $summary);
        $this->assertSame($desiredMaster->teacher_id, $periodAssignment->fresh()->teacher_id);
        $this->assertSame(2, $periodAssignment->fresh()->lock_version);
        $this->assertSame('PILIHAN', $periodAssignment->fresh()->subject_group_code_snapshot);

        $period->forceFill(['status' => AssessmentPeriodStatus::LOCKED])->save();
        $this->expectException(ValidationException::class);
        app(SyncOpenPeriodSubjectAssignmentsAction::class)->execute($actor, $subject, $period->fresh());
    }

    public function test_bulk_sync_uses_one_default_scheme_and_turns_19_subjects_and_99_plots_into_period_assignments(): void
    {
        $this->artisan('assessment:teaching-plan-2026', ['--apply' => true])->assertSuccessful();
        $adminRole = Role::findOrCreate('admin', 'web');
        User::query()->whereNotNull('guru_tendik_id')->get()->each->assignRole($adminRole);
        $actor = User::query()->create([
            'name' => 'Admin Bulk Periode',
            'username' => 'admin-bulk-periode',
            'password' => 'secret123',
        ]);
        $actor->assignRole($adminRole);

        $semester = Semester::query()->where('code', '2026-2027-GANJIL')->firstOrFail();
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $semester->assessment_academic_year_id,
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-BULK-99',
            'name' => 'ASTS Bulk 99',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::OPEN,
            'settings' => ['rombel_ids' => Rombel::query()->pluck('id')->all()],
            'created_by' => $actor->getKey(),
        ]);
        $periodRombels = Rombel::query()->orderBy('id')->get()->mapWithKeys(function (Rombel $rombel) use ($period): array {
            $snapshot = AssessmentPeriodRombel::query()->create([
                'assessment_period_id' => $period->getKey(),
                'source_rombel_id' => $rombel->getKey(),
                'rombel_name_snapshot' => $rombel->nama,
                'grade_level' => str($rombel->nama)->before(' ')->toString(),
                'is_active' => true,
            ]);

            return [$rombel->getKey() => $snapshot];
        });

        $bin = Subject::query()->where('code', 'BIN')->firstOrFail();
        $binMasters = TeachingAssignment::query()
            ->with('category')
            ->where('assessment_semester_id', $semester->getKey())
            ->where('assessment_subject_id', $bin->getKey())
            ->where('is_active', true)
            ->get();
        $legacyTeacher = GuruTendik::query()->create([
            'nama' => 'Putra Kamulyan, S.Kom.',
            'jenis_ptk' => 'Guru',
            'status' => 'aktif',
        ]);
        $originalAssignmentIds = [];
        foreach ($binMasters->values() as $index => $master) {
            $periodRombel = $periodRombels->get($master->rombel_id);
            $assignment = AssessmentPeriodAssignment::query()->create([
                'assessment_period_id' => $period->getKey(),
                'source_teaching_assignment_id' => null,
                'assessment_period_rombel_id' => $periodRombel->getKey(),
                'assessment_subject_id' => $bin->getKey(),
                'teacher_id' => $legacyTeacher->getKey(),
                'teacher_name_snapshot' => $legacyTeacher->nama,
                'subject_name_snapshot' => $bin->name,
                'subject_group_code_snapshot' => 'A',
                'subject_group_name_snapshot' => 'Kelompok A (Umum)',
                'subject_group_sort_order_snapshot' => 10,
                'subject_sort_order_snapshot' => $bin->sort_order,
                'rombel_name_snapshot' => $periodRombel->rombel_name_snapshot,
                'status' => $index === 0 ? AssignmentStatus::RETURNED : AssignmentStatus::LOCKED,
                'lock_version' => 10,
            ]);
            $originalAssignmentIds[] = (int) $assignment->getKey();
        }

        $source = AssessmentScheme::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_subject_id' => $bin->getKey(),
            'name' => 'TES 1',
            'rounding_precision' => 2,
            'minimum_score' => 50,
            'maximum_score' => 100,
            'settings' => [
                'kkm' => 75,
                'predicates' => [['label' => 'A', 'minimum_score' => 90]],
                'fallback_predicate' => 'B',
                'description_template' => 'SEMANGAT',
            ],
            'is_active' => true,
        ]);
        $source->components()->create([
            'code' => 'TES1',
            'name' => 'TES 1',
            'domain' => 'TES1-',
            'weight' => 100,
            'maximum_score' => 100,
            'is_required' => true,
            'sort_order' => 1,
            'score_source' => 'manual',
            'settings' => ['is_active' => true],
        ]);

        $subjectIds = Subject::query()->where('is_active', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $sync = app(SyncOpenPeriodSubjectsAction::class);
        try {
            $sync->preview($period, $subjectIds, 999999);
            $this->fail('Skema sumber dari luar periode seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Skema sumber tidak aktif', collect($exception->errors())->flatten()->first());
        }
        $preview = $sync->preview($period, $subjectIds, $source->getKey());
        $this->assertSame(19, $preview['subject_count']);
        $this->assertSame(7, $preview['class_count']);
        $this->assertSame(99, $preview['plotting_count']);
        $this->assertSame(92, $preview['created']);
        $this->assertSame(7, $preview['unchanged']);
        $this->assertSame(7, $preview['protected']);
        $this->assertTrue($preview['default_scheme_created']);
        $this->assertSame(7, AssessmentPeriodAssignment::query()->where('assessment_period_id', $period->getKey())->count());
        $this->assertSame(1, AssessmentScheme::query()->where('assessment_period_id', $period->getKey())->count());

        $this->artisan('assessment:sync-open-period-subjects', [
            'period' => $period->getKey(),
            '--all' => true,
            '--source-scheme' => $source->getKey(),
        ])->expectsOutputToContain('Mode preview: database belum diubah')->assertSuccessful();
        $this->assertSame(7, AssessmentPeriodAssignment::query()->where('assessment_period_id', $period->getKey())->count());

        $blockedAccount = User::query()->whereNotNull('guru_tendik_id')->firstOrFail();
        $blockedAccount->removeRole($adminRole);
        try {
            $sync->execute($actor, $period, $subjectIds, $source->getKey());
            $this->fail('Sinkronisasi seharusnya ditolak ketika satu akun guru belum siap.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('belum siap Input dan Kirim Nilai', collect($exception->errors())->flatten()->first());
        }
        $this->assertSame(7, AssessmentPeriodAssignment::query()->where('assessment_period_id', $period->getKey())->count());
        $this->assertSame(1, AssessmentScheme::query()->where('assessment_period_id', $period->getKey())->count());
        $blockedAccount->assignRole($adminRole);

        $summary = $sync->execute($actor, $period, $subjectIds, $source->getKey());
        $this->assertSame(92, $summary['created']);
        $this->assertSame(0, $summary['updated']);
        $this->assertSame(7, $summary['unchanged']);
        $this->assertSame(7, $summary['protected']);
        $this->assertSame(99, AssessmentPeriodAssignment::query()->where('assessment_period_id', $period->getKey())->count());
        $this->assertEqualsCanonicalizing($originalAssignmentIds, AssessmentPeriodAssignment::query()->whereIn('id', $originalAssignmentIds)->pluck('id')->map(fn ($id): int => (int) $id)->all());
        $this->assertSame(7, AssessmentPeriodAssignment::query()->whereIn('id', $originalAssignmentIds)->where('teacher_id', $legacyTeacher->getKey())->count());

        $default = AssessmentScheme::query()
            ->where('assessment_period_id', $period->getKey())
            ->whereNull('assessment_subject_id')
            ->whereNull('source_rombel_id')
            ->where('is_active', true)
            ->firstOrFail();
        $this->assertSame($source->rounding_precision, $default->rounding_precision);
        $this->assertSame($source->settings, $default->settings);
        $this->assertSame(1, $default->components()->count());
        $this->assertSame('TES1', $default->components()->firstOrFail()->code);
        $resolver = app(AssessmentSchemeResolver::class);
        $binPeriodAssignment = AssessmentPeriodAssignment::query()
            ->where('assessment_period_id', $period->getKey())
            ->where('assessment_subject_id', $bin->getKey())
            ->firstOrFail();
        $fisPeriodAssignment = AssessmentPeriodAssignment::query()
            ->where('assessment_period_id', $period->getKey())
            ->where('assessment_subject_id', Subject::query()->where('code', 'FIS')->value('id'))
            ->firstOrFail();
        $this->assertTrue($resolver->forAssignment($binPeriodAssignment)->is($source));
        $this->assertTrue($resolver->forAssignment($fisPeriodAssignment)->is($default));

        $second = $sync->execute($actor, $period->fresh(), $subjectIds);
        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $second['updated']);
        $this->assertSame(99, $second['unchanged']);
        $this->assertSame(7, $second['protected']);
        $this->assertSame(2, AssessmentScheme::query()->where('assessment_period_id', $period->getKey())->count());
    }
}
