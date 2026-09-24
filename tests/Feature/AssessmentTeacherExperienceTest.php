<?php

namespace Tests\Feature;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Enums\Assessment\ScoreSource;
use App\Filament\Pages\Assessment\AsasHomeroomRecap;
use App\Filament\Pages\Assessment\AsasHub;
use App\Filament\Pages\Assessment\AsatHub;
use App\Filament\Pages\Assessment\AssessmentDashboard;
use App\Filament\Pages\Assessment\AssessmentSetupWizard;
use App\Filament\Pages\Assessment\AssessmentTeachingMatrix;
use App\Filament\Pages\Assessment\OnlineExamPage;
use App\Filament\Pages\Assessment\QuestionBankBuilderPage;
use App\Filament\Pages\Assessment\AsasSubmissionStatus;
use App\Filament\Pages\Assessment\AstsHomeroomRecap;
use App\Filament\Pages\Assessment\AstsHub;
use App\Filament\Pages\Assessment\AstsInputScores;
use App\Filament\Pages\Assessment\AstsSubmissionStatus;
use App\Filament\Resources\AssessmentSchemeResource;
use App\Http\Controllers\Admin\AssessmentAstsExportController;
use App\Models\Assessment\AssessmentComponent;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\AssessmentScore;
use App\Models\Assessment\HomeroomReport;
use App\Models\Assessment\Subject;
use App\Models\Assessment\StudentSubjectResult;
use App\Models\User;
use App\Support\Assessment\AssessmentActionFailureNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AssessmentTeacherExperienceTest extends TestCase
{
    use BootstrapsUserAndPermissionTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapUserAndPermissionTables();
        Schema::create('guru_tendik', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nama');
            $table->string('status')->default('aktif');
            $table->string('foto_profil')->nullable();
            $table->timestamps();
        });
        DB::table('guru_tendik')->insert([
            'id' => 348,
            'nama' => 'Putra Kamulyan',
            'status' => 'aktif',
        ]);

        $migration = require database_path(
            'migrations/2026_07_31_080000_create_assessment_foundation_tables.php',
        );
        $migration->up();
        $reportStructureMigration = require database_path(
            'migrations/2026_07_31_120000_extend_assessment_report_structure.php',
        );
        $reportStructureMigration->up();
        (require database_path('migrations/2026_08_06_150000_add_assessment_subject_categories.php'))->up();
        (require database_path('migrations/2026_08_03_080000_add_stream_delivery_to_assessment_reports.php'))->up();

        Artisan::call('assessment:install-defaults');
        config(['assessment.enabled' => true]);
    }

    public function test_teacher_can_open_status_complete_remaining_scores_and_manage_own_homeroom(): void
    {
        $teacher = $this->teacher(348);
        $period = AssessmentPeriod::factory()
            ->asts()
            ->create(['status' => AssessmentPeriodStatus::OPEN]);
        $rombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'X 1',
        ]);
        $secondRombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'X 2',
        ]);
        $subject = Subject::factory()->create(['name' => 'Bahasa Indonesia']);
        $scheme = AssessmentScheme::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_subject_id' => null,
            'assessment_period_rombel_id' => null,
        ]);
        $component = AssessmentComponent::factory()->create([
            'assessment_scheme_id' => $scheme->getKey(),
            'name' => 'Nilai ASTS',
        ]);
        $assignment = AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'teacher_id' => 348,
            'teacher_name_snapshot' => 'Putra Kamulyan',
            'subject_name_snapshot' => 'Bahasa Indonesia',
            'rombel_name_snapshot' => 'X 1',
            'status' => AssignmentStatus::DRAFT,
        ]);
        $submittedAssignment = AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $secondRombel->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'teacher_id' => 348,
            'teacher_name_snapshot' => 'Putra Kamulyan',
            'subject_name_snapshot' => 'Bahasa Indonesia',
            'rombel_name_snapshot' => 'X 2',
            'status' => AssignmentStatus::SUBMITTED,
        ]);
        $students = AssessmentPeriodStudent::factory()->count(2)->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'rombel_name_snapshot' => 'X 1',
        ]);
        $homeroom = AssessmentPeriodHomeroom::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'teacher_id' => 348,
            'teacher_name_snapshot' => 'Putra Kamulyan',
            'rombel_name_snapshot' => 'X 1',
        ]);
        AssessmentScore::factory()->create([
            'assessment_period_assignment_id' => $assignment->getKey(),
            'assessment_period_student_id' => $students->first()->getKey(),
            'assessment_component_id' => $component->getKey(),
            'score' => '88.0000',
        ]);
        AssessmentScore::factory()->create([
            'assessment_period_assignment_id' => $assignment->getKey(),
            'assessment_period_student_id' => $students->last()->getKey(),
            'assessment_component_id' => $component->getKey(),
            'score' => '88.2560',
        ]);

        Livewire::actingAs($teacher)
            ->test(AstsInputScores::class)
            ->set('periodId', $period->getKey())
            ->set('assignmentId', $assignment->getKey())
            ->call('loadAssignment')
            ->assertSet("scoreRows.{$students->first()->getKey()}.scores.{$component->getKey()}", '88')
            ->assertSet("scoreRows.{$students->last()->getKey()}.scores.{$component->getKey()}", '88.26')
            ->assertSee('normalizeDraftValue');

        $this->actingAs($teacher)
            ->get(AstsSubmissionStatus::getUrl(['period' => $period->getKey()]))
            ->assertOk()
            ->assertSee('Status Penugasan')
            ->assertSeeHtml('assessment-status-data-card');

        Livewire::actingAs($teacher)
            ->test(AstsHub::class)
            ->set('periodId', $period->getKey())
            ->assertSee('1 dikirim')
            ->assertSee('1 belum dikirim')
            ->assertSee('Wali Kelas')
            ->assertSee('X 1')
            ->assertSee('Tugas Guru')
            ->assertSee('Tugas Wali Kelas')
            ->assertDontSee('Pindah fokus ujian');

        $studentId = (int) $students->first()->getKey();
        Livewire::actingAs($teacher)
            ->test(AstsInputScores::class)
            ->set('periodId', $period->getKey())
            ->set('assignmentId', $assignment->getKey())
            ->call('loadAssignment')
            ->set('selectedStudentIds', [$studentId])
            ->set('bulkComponentId', $component->getKey())
            ->set('bulkScore', '88')
            ->call('applyBulkValues')
            ->assertSet("scoreRows.{$studentId}.scores.{$component->getKey()}", 88.0)
            ->assertSee('Data belum masuk server sampai tombol');

        Livewire::actingAs($teacher)
            ->test(AstsInputScores::class)
            ->set('periodId', $period->getKey())
            ->set('assignmentId', $assignment->getKey())
            ->call('loadAssignment')
            ->set("scoreRows.{$studentId}.scores.{$component->getKey()}", 70)
            ->set("scoreRows.{$studentId}.description", 'Deskripsi lama.')
            ->set('selectedStudentIds', [$studentId])
            ->set('bulkComponentId', $component->getKey())
            ->set('bulkScore', '91')
            ->set('bulkDescription', 'Deskripsi hasil bulk terbaru.')
            ->assertSet('bulkFillEmptyOnly', false)
            ->call('applyBulkValues')
            ->assertSet("scoreRows.{$studentId}.scores.{$component->getKey()}", 91.0)
            ->assertSet("scoreRows.{$studentId}.description", 'Deskripsi lama.');

        $this->assertTrue(AstsHomeroomRecap::canAccess());
        $this->assertTrue(Gate::forUser($teacher)->allows('view', $homeroom));
        $this->assertTrue(Gate::forUser($teacher)->allows('create', [HomeroomReport::class, $homeroom]));

        Livewire::actingAs($teacher)
            ->test(AstsHomeroomRecap::class)
            ->set('periodId', $period->getKey())
            ->set('homeroomId', $homeroom->getKey())
            ->call('loadReports')
            ->assertSee('Isi Massal Rekap Wali Kelas')
            ->assertSee('X 1')
            ->assertSee('Sakit')
            ->assertSee('Ekstrakurikuler')
            ->assertSee('Predikat')
            ->assertDontSee('Predikat Spiritual')
            ->assertDontSee('Deskripsi Spiritual')
            ->assertDontSee('Predikat Sosial')
            ->assertDontSee('Deskripsi Sosial')
            ->assertDontSee('Kokurikuler')
            ->assertDontSee('Prestasi')
            ->assertDontSee('Catatan Wali')
            ->set('selectedStudentIds', [$studentId])
            ->set('bulkField', 'extracurricular_items')
            ->set('bulkStructuredItem.name', 'Pramuka')
            ->set('bulkStructuredItem.description', 'A')
            ->call('applyBulkValue')
            ->assertSet("reportRows.{$studentId}.extracurricular_items.0.name", 'Pramuka')
            ->assertSet("reportRows.{$studentId}.extracurricular_items.0.description", 'A');

        $this->assertSame(AssignmentStatus::DRAFT, $assignment->refresh()->status);
        $this->assertSame(AssignmentStatus::SUBMITTED, $submittedAssignment->refresh()->status);
    }

    public function test_asts_input_automatically_provides_three_daily_columns_and_pure_score(): void
    {
        $teacher = $this->teacher(348);
        $period = AssessmentPeriod::factory()->asts()->create([
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $rombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'X ASTS',
        ]);
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $scheme = AssessmentScheme::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_subject_id' => null,
            'assessment_period_rombel_id' => null,
            'settings' => ['kkm' => 75],
        ]);
        $assignment = AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'teacher_id' => 348,
            'teacher_name_snapshot' => 'Putra Kamulyan',
            'subject_name_snapshot' => 'Matematika',
            'rombel_name_snapshot' => 'X ASTS',
            'status' => AssignmentStatus::DRAFT,
        ]);
        AssessmentPeriodStudent::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'rombel_name_snapshot' => 'X ASTS',
        ]);

        Livewire::actingAs($teacher)
            ->test(AstsInputScores::class)
            ->set('periodId', $period->getKey())
            ->set('assignmentId', $assignment->getKey())
            ->call('loadAssignment')
            ->assertSee('Ujian Harian 1')
            ->assertSee('Ujian Harian 2')
            ->assertSee('Ujian Harian 3')
            ->assertSee('Nilai Murni ASTS')
            ->assertDontSee('Deskripsi Capaian')
            ->assertDontSee('Deskripsi Massal')
            ->assertDontSee('16.6667%')
            ->assertSee('16,67% · 0–100')
            ->assertSee('50% · 0–100');

        $this->assertSame([
            ['code' => 'UH1', 'name' => 'Ujian Harian 1', 'is_required' => false],
            ['code' => 'UH2', 'name' => 'Ujian Harian 2', 'is_required' => false],
            ['code' => 'UH3', 'name' => 'Ujian Harian 3', 'is_required' => false],
            ['code' => 'ASTS_MURNI', 'name' => 'Nilai Murni ASTS', 'is_required' => true],
        ], $scheme->fresh()->components()->orderBy('sort_order')->get(['code', 'name', 'is_required'])
            ->map(fn (AssessmentComponent $component): array => $component->only(['code', 'name', 'is_required']))
            ->all());
    }

    public function test_teacher_homeroom_input_only_lists_own_subjects_and_review_is_read_only(): void
    {
        $teacher = $this->teacher(348);
        $period = AssessmentPeriod::factory()->asts()->create([
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $homeroomRombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'XII 2',
        ]);
        $otherRombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'XII 3',
        ]);
        $english = Subject::factory()->create(['name' => 'Bahasa Inggris']);
        $math = Subject::factory()->create(['name' => 'Matematika']);
        $scheme = AssessmentScheme::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_subject_id' => null,
            'assessment_period_rombel_id' => null,
        ]);
        AssessmentComponent::factory()->create([
            'assessment_scheme_id' => $scheme->getKey(),
            'name' => 'Nilai ASTS',
        ]);

        $ownAssignments = collect([$homeroomRombel, $otherRombel])
            ->map(fn (AssessmentPeriodRombel $rombel): AssessmentPeriodAssignment => AssessmentPeriodAssignment::factory()->create([
                'assessment_period_id' => $period->getKey(),
                'assessment_period_rombel_id' => $rombel->getKey(),
                'assessment_subject_id' => $english->getKey(),
                'teacher_id' => 348,
                'teacher_name_snapshot' => 'Putra Kamulyan',
                'subject_name_snapshot' => 'Bahasa Inggris',
                'rombel_name_snapshot' => $rombel->rombel_name_snapshot,
                'status' => AssignmentStatus::DRAFT,
            ]));
        $foreignAssignment = AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $homeroomRombel->getKey(),
            'assessment_subject_id' => $math->getKey(),
            'teacher_id' => 999,
            'teacher_name_snapshot' => 'Guru Matematika',
            'subject_name_snapshot' => 'Matematika',
            'rombel_name_snapshot' => 'XII 2',
            'status' => AssignmentStatus::DRAFT,
        ]);
        AssessmentPeriodStudent::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $homeroomRombel->getKey(),
            'rombel_name_snapshot' => 'XII 2',
        ]);
        AssessmentPeriodHomeroom::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $homeroomRombel->getKey(),
            'teacher_id' => 348,
            'teacher_name_snapshot' => 'Putra Kamulyan',
            'rombel_name_snapshot' => 'XII 2',
        ]);

        $input = Livewire::actingAs($teacher)
            ->test(AstsInputScores::class)
            ->set('periodId', $period->getKey());
        $this->assertEqualsCanonicalizing(
            $ownAssignments->pluck('id')->all(),
            array_map('intval', array_keys($input->instance()->getAssignmentOptions())),
        );
        $this->assertSame([
            'total' => 2,
            'sent' => 0,
            'remaining' => 2,
        ], $input->instance()->getAssignmentProgress());

        $input
            ->set('mode', 'tidak-dikenal')
            ->assertSet('mode', 'input');
        $this->assertEqualsCanonicalizing(
            $ownAssignments->pluck('id')->all(),
            array_map('intval', array_keys($input->instance()->getAssignmentOptions())),
        );

        $input
            ->set('assignmentId', $foreignAssignment->getKey())
            ->call('loadAssignment')
            ->assertSet('assignmentId', null)
            ->assertSet('assignmentMeta', null);

        $status = Livewire::actingAs($teacher)
            ->test(AstsSubmissionStatus::class)
            ->set('periodId', $period->getKey());
        $statusRows = collect($status->instance()->getAssignmentRows());
        $this->assertCount(3, $statusRows);
        $this->assertStringContainsString(
            'mode=review',
            (string) $statusRows->firstWhere('id', $foreignAssignment->getKey())['review_url'],
        );

        Livewire::actingAs($teacher)
            ->test(AstsInputScores::class)
            ->set('periodId', $period->getKey())
            ->set('mode', 'review')
            ->set('assignmentId', $foreignAssignment->getKey())
            ->call('loadAssignment')
            ->assertSet('assignmentMeta.subject', 'Matematika')
            ->assertSet('assignmentMeta.editable', false)
            ->assertSee('Mode Tinjau Wali Kelas');

        $hub = Livewire::actingAs($teacher)
            ->test(AstsHub::class)
            ->set('periodId', $period->getKey())
            ->instance()
            ->getHubData();
        $this->assertSame(2, $hub['input_assignment_count']);
        $this->assertSame(3, $hub['assignment_count']);
        $this->assertSame('2', $hub['cards'][0]['value']);
    }

    public function test_homeroom_teacher_without_subject_gets_recap_empty_state(): void
    {
        $teacher = $this->teacher(777);
        $period = AssessmentPeriod::factory()->asas()->create([
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $rombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'XI 1',
        ]);
        AssessmentPeriodHomeroom::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'teacher_id' => 777,
            'teacher_name_snapshot' => 'Wali Tanpa Mapel',
            'rombel_name_snapshot' => 'XI 1',
        ]);

        Livewire::actingAs($teacher)
            ->test(\App\Filament\Pages\Assessment\AsasInputScores::class)
            ->set('periodId', $period->getKey())
            ->assertSet('assignmentId', null)
            ->assertSee('Belum ada mapel yang diampu')
            ->assertSee('Buka Rekap Wali');
    }

    public function test_admin_can_verify_and_return_filtered_assignments_atomically_with_visible_revision_note(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Kurikulum',
            'username' => 'admin-kurikulum-test',
            'password' => 'test-password',
        ]);
        Role::findOrCreate('admin', 'web');
        $admin->assignRole('admin');
        $period = AssessmentPeriod::factory()->asts()->create([
            'status' => AssessmentPeriodStatus::VERIFICATION,
        ]);
        $rombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'XI 1',
        ]);
        $secondRombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'XI 2',
        ]);
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $scheme = AssessmentScheme::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_subject_id' => null,
            'assessment_period_rombel_id' => null,
        ]);
        AssessmentComponent::factory()->create([
            'assessment_scheme_id' => $scheme->getKey(),
            'name' => 'Nilai ASTS',
        ]);
        $assignments = collect([
            [$rombel->getKey(), 'XI 1'],
            [$secondRombel->getKey(), 'XI 2'],
        ])->map(function (array $class) use ($period, $subject) {
            return AssessmentPeriodAssignment::factory()->create([
                'assessment_period_id' => $period->getKey(),
                'assessment_period_rombel_id' => $class[0],
                'assessment_subject_id' => $subject->getKey(),
                'teacher_id' => 348,
                'teacher_name_snapshot' => 'Putra Kamulyan',
                'subject_name_snapshot' => 'Matematika',
                'rombel_name_snapshot' => $class[1],
                'status' => AssignmentStatus::SUBMITTED,
            ]);
        });

        Livewire::actingAs($admin)
            ->test(AstsSubmissionStatus::class)
            ->set('periodId', $period->getKey())
            ->set('selectedAssignmentIds', $assignments->pluck('id')->all())
            ->call('verifySelectedAssignments')
            ->assertSet('selectedAssignmentIds', []);

        $this->assertSame(
            2,
            AssessmentPeriodAssignment::query()->where('status', AssignmentStatus::VERIFIED->value)->count(),
        );

        $reason = 'Mohon periksa kembali nilai dan deskripsi capaian siswa.';
        Livewire::actingAs($admin)
            ->test(AstsSubmissionStatus::class)
            ->set('periodId', $period->getKey())
            ->set('selectedAssignmentIds', $assignments->pluck('id')->all())
            ->call('prepareReturn')
            ->set('returnReason', $reason)
            ->call('confirmReturnAssignments')
            ->assertSet('selectedAssignmentIds', []);

        $returned = $assignments->first()->fresh();
        $this->assertSame(AssignmentStatus::RETURNED, $returned->status);
        $this->assertSame($reason, $returned->returned_reason);
        $this->assertSame($admin->getKey(), $returned->returned_by);

        Livewire::actingAs($admin)
            ->test(AstsInputScores::class)
            ->set('periodId', $period->getKey())
            ->set('assignmentId', $returned->getKey())
            ->call('loadAssignment')
            ->assertSet('assignmentId', $returned->getKey())
            ->assertSet('assignmentMeta.returned_reason', $reason)
            ->assertSet('assignmentMeta.returned_by', 'Admin Kurikulum')
            ->assertSee('Perlu Revisi');
    }

    public function test_all_homeroom_recap_columns_support_safe_bulk_fill_and_period_scoped_save(): void
    {
        $teacher = $this->teacher(348);

        $astsPeriod = AssessmentPeriod::factory()
            ->asas()
            ->create(['status' => AssessmentPeriodStatus::OPEN]);
        $astsRombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $astsPeriod->getKey(),
            'rombel_name_snapshot' => 'X 1',
        ]);
        $astsStudents = AssessmentPeriodStudent::factory()->count(2)->create([
            'assessment_period_id' => $astsPeriod->getKey(),
            'assessment_period_rombel_id' => $astsRombel->getKey(),
            'rombel_name_snapshot' => 'X 1',
        ]);
        $astsHomeroom = AssessmentPeriodHomeroom::factory()->create([
            'assessment_period_id' => $astsPeriod->getKey(),
            'assessment_period_rombel_id' => $astsRombel->getKey(),
            'teacher_id' => 348,
            'teacher_name_snapshot' => 'Putra Kamulyan',
            'rombel_name_snapshot' => 'X 1',
        ]);

        $asasPeriod = AssessmentPeriod::factory()
            ->asas()
            ->create(['status' => AssessmentPeriodStatus::OPEN]);
        $asasRombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $asasPeriod->getKey(),
            'rombel_name_snapshot' => 'XI 1',
        ]);
        $asasStudent = AssessmentPeriodStudent::factory()->create([
            'assessment_period_id' => $asasPeriod->getKey(),
            'assessment_period_rombel_id' => $asasRombel->getKey(),
            'rombel_name_snapshot' => 'XI 1',
        ]);
        $asasHomeroom = AssessmentPeriodHomeroom::factory()->create([
            'assessment_period_id' => $asasPeriod->getKey(),
            'assessment_period_rombel_id' => $asasRombel->getKey(),
            'teacher_id' => 348,
            'teacher_name_snapshot' => 'Putra Kamulyan',
            'rombel_name_snapshot' => 'XI 1',
        ]);
        $asasReport = HomeroomReport::factory()->create([
            'assessment_period_id' => $asasPeriod->getKey(),
            'assessment_period_student_id' => $asasStudent->getKey(),
            'homeroom_note' => 'Catatan ASAS tetap.',
        ]);

        $expectedHeaders = [
            'Sakit',
            'Izin',
            'Alpa',
            'Predikat Spiritual',
            'Deskripsi Spiritual',
            'Predikat Sosial',
            'Deskripsi Sosial',
            'Ekstrakurikuler',
            'Kokurikuler',
            'Prestasi',
            'Catatan Wali',
        ];
        $expectedFields = [
            'sick_days',
            'permission_days',
            'absent_days',
            'spiritual_predicate',
            'spiritual_description',
            'social_predicate',
            'social_description',
            'extracurricular_items',
            'kokurikuler',
            'achievement_items',
            'homeroom_note',
        ];

        $astsComponent = Livewire::actingAs($teacher)
            ->test(AsasHomeroomRecap::class)
            ->set('periodId', $astsPeriod->getKey())
            ->set('homeroomId', $astsHomeroom->getKey())
            ->call('loadReports')
            ->assertSet('bulkFillEmptyOnly', true);

        $this->assertSame(
            $expectedHeaders,
            array_column($astsComponent->instance()->getRecapFieldDefinitions(), 'header'),
        );
        $this->assertSame(
            $expectedFields,
            array_slice(array_keys($astsComponent->instance()->getBulkFieldOptions()), 0, count($expectedFields)),
        );

        $asasComponent = Livewire::actingAs($teacher)
            ->test(AsasHomeroomRecap::class)
            ->set('periodId', $asasPeriod->getKey())
            ->set('homeroomId', $asasHomeroom->getKey())
            ->call('loadReports');
        $asasBulkFields = array_keys($asasComponent->instance()->getBulkFieldOptions());

        $this->assertSame($expectedFields, array_slice($asasBulkFields, 0, count($expectedFields)));
        $this->assertSame('promotion_status', $asasBulkFields[count($expectedFields)] ?? null);

        $selectedStudentId = (int) $astsStudents->first()->getKey();
        $unselectedStudentId = (int) $astsStudents->last()->getKey();
        $bulkValues = [
            'sick_days' => ['3', 3],
            'permission_days' => ['2', 2],
            'absent_days' => ['1', 1],
            'spiritual_predicate' => ['Baik', 'Baik'],
            'spiritual_description' => ['Membiasakan ibadah dengan tertib.', 'Membiasakan ibadah dengan tertib.'],
            'social_predicate' => ['Sangat Baik', 'Sangat Baik'],
            'social_description' => ['Santun dan peduli terhadap teman.', 'Santun dan peduli terhadap teman.'],
            'homeroom_note' => ['Pertahankan semangat belajar.', 'Pertahankan semangat belajar.'],
        ];

        $astsComponent->set('selectedStudentIds', [$selectedStudentId]);
        foreach ($bulkValues as $field => [$input, $expected]) {
            $astsComponent
                ->set('bulkField', $field)
                ->assertSet('bulkValue', '')
                ->set('bulkValue', $input)
                ->call('applyBulkValue')
                ->assertSet("reportRows.{$selectedStudentId}.{$field}", $expected);
        }

        $astsComponent
            ->assertSet("reportRows.{$unselectedStudentId}.sick_days", 0)
            ->assertSet("reportRows.{$unselectedStudentId}.homeroom_note", null)
            ->set('bulkField', 'permission_days')
            ->set('bulkValue', '9')
            ->call('applyBulkValue')
            ->assertSet("reportRows.{$selectedStudentId}.permission_days", 2)
            ->set('bulkField', 'homeroom_note')
            ->set('bulkValue', 'Catatan yang tidak boleh menimpa.')
            ->call('applyBulkValue')
            ->assertSet("reportRows.{$selectedStudentId}.homeroom_note", 'Pertahankan semangat belajar.')
            ->set('bulkFillEmptyOnly', false)
            ->set('bulkField', 'permission_days')
            ->set('bulkValue', '9')
            ->call('applyBulkValue')
            ->assertSet("reportRows.{$selectedStudentId}.permission_days", 9)
            ->set('bulkField', 'homeroom_note')
            ->set('bulkValue', 'Catatan hasil timpa.')
            ->call('applyBulkValue')
            ->assertSet("reportRows.{$selectedStudentId}.homeroom_note", 'Catatan hasil timpa.');

        $astsComponent
            ->set('bulkField', 'extracurricular_items')
            ->assertSet('bulkStructuredItem.name', '')
            ->set('bulkStructuredItem.name', 'Pramuka')
            ->set('bulkStructuredItem.description', 'Sangat Baik')
            ->call('applyBulkValue')
            ->assertSet("reportRows.{$selectedStudentId}.extracurricular_items", [[
                'name' => 'Pramuka',
                'description' => 'Sangat Baik',
            ]])
            ->set('bulkStructuredItem.name', 'Pramuka')
            ->set('bulkStructuredItem.description', 'Sangat Baik')
            ->call('applyBulkValue')
            ->assertSet("reportRows.{$selectedStudentId}.extracurricular_items", [[
                'name' => 'Pramuka',
                'description' => 'Sangat Baik',
            ]])
            ->set('bulkStructuredMode', 'replace')
            ->set('bulkStructuredItem.name', 'Basket')
            ->set('bulkStructuredItem.description', 'Baik')
            ->call('applyBulkValue')
            ->assertSet("reportRows.{$selectedStudentId}.extracurricular_items", [[
                'name' => 'Basket',
                'description' => 'Baik',
            ]])
            ->set('bulkField', 'achievement_items')
            ->set('bulkStructuredItem.name', 'Juara 1 Olimpiade Sains')
            ->set('bulkStructuredItem.description', 'Tingkat Kota')
            ->call('applyBulkValue')
            ->assertSet("reportRows.{$selectedStudentId}.achievement_items", [[
                'name' => 'Juara 1 Olimpiade Sains',
                'description' => 'Tingkat Kota',
            ]])
            ->assertSet("reportRows.{$unselectedStudentId}.extracurricular_items", [])
            ->assertSet("reportRows.{$unselectedStudentId}.achievement_items", []);

        $this->assertDatabaseMissing('assessment_homeroom_reports', [
            'assessment_period_id' => $astsPeriod->getKey(),
            'assessment_period_student_id' => $selectedStudentId,
        ]);

        $astsComponent->call('saveReports');

        $savedReport = HomeroomReport::query()
            ->where('assessment_period_id', $astsPeriod->getKey())
            ->where('assessment_period_student_id', $selectedStudentId)
            ->firstOrFail();
        $this->assertSame(3, $savedReport->sick_days);
        $this->assertSame(9, $savedReport->permission_days);
        $this->assertSame(1, $savedReport->absent_days);
        $this->assertSame('Baik', $savedReport->spiritual_predicate);
        $this->assertSame('Membiasakan ibadah dengan tertib.', $savedReport->spiritual_description);
        $this->assertSame('Sangat Baik', $savedReport->social_predicate);
        $this->assertSame('Santun dan peduli terhadap teman.', $savedReport->social_description);
        $this->assertSame(
            [['name' => 'Basket', 'description' => 'Baik']],
            $savedReport->extracurricular_data,
        );
        $this->assertSame(
            [['name' => 'Juara 1 Olimpiade Sains', 'description' => 'Tingkat Kota']],
            $savedReport->achievement_data,
        );
        $this->assertSame('Catatan hasil timpa.', $savedReport->homeroom_note);

        $unselectedReport = HomeroomReport::query()
            ->where('assessment_period_id', $astsPeriod->getKey())
            ->where('assessment_period_student_id', $unselectedStudentId)
            ->firstOrFail();
        $this->assertSame(0, $unselectedReport->sick_days);
        $this->assertNull($unselectedReport->homeroom_note);
        $this->assertSame('Catatan ASAS tetap.', $asasReport->refresh()->homeroom_note);
    }

    public function test_curriculum_can_complete_legacy_structured_homeroom_items_while_other_teacher_is_denied(): void
    {
        Role::findOrCreate('kurikulum', 'web');
        $curriculum = User::query()->create([
            'name' => 'Kurikulum Assessment',
            'username' => 'curriculum-homeroom-items',
            'password' => 'test-password',
        ]);
        $curriculum->assignRole('kurikulum');
        $otherTeacher = $this->teacher(999);

        $period = AssessmentPeriod::factory()->asts()->create([
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $rombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'X 3',
        ]);
        $student = AssessmentPeriodStudent::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'rombel_name_snapshot' => 'X 3',
        ]);
        $homeroom = AssessmentPeriodHomeroom::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'teacher_id' => 348,
            'teacher_name_snapshot' => 'Putra Kamulyan',
            'rombel_name_snapshot' => 'X 3',
        ]);
        $report = HomeroomReport::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_student_id' => $student->getKey(),
            'extracurricular_data' => [['description' => 'Keterangan lama tetap ada']],
            'achievement_data' => [['name' => 'Juara Kelas', 'description' => 'Semester Ganjil']],
        ]);

        $this->assertTrue(Gate::forUser($curriculum)->allows('update', $report));
        $this->assertFalse(Gate::forUser($otherTeacher)->allows('update', $report));

        Livewire::actingAs($curriculum)
            ->test(AstsHomeroomRecap::class)
            ->set('periodId', $period->getKey())
            ->set('homeroomId', $homeroom->getKey())
            ->call('loadReports')
            ->assertSet('homeroomMeta.editable', true)
            ->assertSet("reportRows.{$student->getKey()}.extracurricular_items.0.name", '')
            ->assertSet(
                "reportRows.{$student->getKey()}.extracurricular_items.0.description",
                'Keterangan lama tetap ada',
            )
            ->call('addStructuredItem', $student->getKey(), 'achievement_items')
            ->assertSet("reportRows.{$student->getKey()}.achievement_items.1.name", '')
            ->call('removeStructuredItem', $student->getKey(), 'achievement_items', 1)
            ->assertSet("reportRows.{$student->getKey()}.achievement_items", [[
                'name' => 'Juara Kelas',
                'description' => 'Semester Ganjil',
            ]])
            ->call('saveReports')
            ->assertHasErrors(["rows.{$student->getKey()}.extracurricular_items.0.name"])
            ->set("reportRows.{$student->getKey()}.extracurricular_items.0.name", 'Pramuka')
            ->set("reportRows.{$student->getKey()}.extracurricular_items.0.description", 'A')
            ->call('saveReports');

        $this->assertSame(
            [['name' => 'Pramuka', 'description' => 'A']],
            $report->fresh()->extracurricular_data,
        );
        $this->assertSame(
            [['name' => 'Juara Kelas', 'description' => 'Semester Ganjil']],
            $report->fresh()->achievement_data,
        );
    }

    public function test_bulk_verification_blocker_links_to_its_filtered_submission_status(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::query()->create([
            'name' => 'Admin Penilaian',
            'username' => 'admin-filtered-blocker',
            'password' => 'test-password',
        ]);
        $admin->assignRole('admin');
        $period = AssessmentPeriod::factory()->asts()->create([
            'status' => AssessmentPeriodStatus::VERIFICATION,
        ]);
        $rombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'X 1',
        ]);
        $otherRombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'X 2',
        ]);
        $physics = Subject::factory()->create(['name' => 'Fisika']);
        $math = Subject::factory()->create(['name' => 'Matematika']);
        $blocker = AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_subject_id' => $physics->getKey(),
            'rombel_name_snapshot' => 'X 1',
            'subject_name_snapshot' => 'Fisika',
            'status' => AssignmentStatus::DRAFT,
        ]);
        AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_subject_id' => $math->getKey(),
            'rombel_name_snapshot' => 'X 1',
            'subject_name_snapshot' => 'Matematika',
            'status' => AssignmentStatus::DRAFT,
        ]);
        AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $otherRombel->getKey(),
            'assessment_subject_id' => $physics->getKey(),
            'rombel_name_snapshot' => 'X 2',
            'subject_name_snapshot' => 'Fisika',
            'status' => AssignmentStatus::DRAFT,
        ]);

        $this->actingAs($admin);
        $notification = AssessmentActionFailureNotification::make(
            ValidationException::withMessages([
                'assignments' => 'X 1 · Fisika belum berstatus Dikirim.',
            ]),
            'Verifikasi Penugasan Terpilih',
            $period,
            $blocker,
        )->toArray();

        $this->assertSame('Lihat X 1 · Fisika yang belum dikirim', $notification['actions'][0]['label']);
        $this->assertSame(
            AstsSubmissionStatus::getUrl([
                'period' => $period->getKey(),
                'rombel' => $rombel->getKey(),
                'subject' => $physics->getKey(),
                'status' => AssignmentStatus::DRAFT->value,
            ]),
            $notification['actions'][0]['url'],
        );

        $status = Livewire::actingAs($admin)
            ->test(AstsSubmissionStatus::class)
            ->set('periodId', $period->getKey())
            ->set('activeRombelId', $rombel->getKey())
            ->set('subjectId', $physics->getKey())
            ->set('statusFilter', AssignmentStatus::DRAFT->value);

        $rows = $status->instance()->getAssignmentRows();
        $this->assertCount(1, $rows);
        $this->assertSame($blocker->getKey(), $rows[0]['id']);
    }

    public function test_assessment_failure_notification_is_persistent_and_links_to_period_aware_repair_page(): void
    {
        $teacher = $this->teacher(348);
        $period = AssessmentPeriod::factory()->asts()->create([
            'status' => AssessmentPeriodStatus::OPEN,
        ]);

        $this->actingAs($teacher);
        $notification = AssessmentActionFailureNotification::make(
            ValidationException::withMessages([
                'assignments' => '1 penugasan belum dikirim. Input belum dapat ditutup.',
            ]),
            'Tutup Input',
            $period,
        )->toArray();

        $this->assertSame('persistent', $notification['duration']);
        $this->assertSame('danger', $notification['status']);
        $this->assertStringContainsString('Aksi ditolak: Tutup Input', (string) $notification['title']);
        $this->assertStringContainsString('Kendala', (string) $notification['body']);
        $this->assertStringContainsString('Solusi', (string) $notification['body']);
        $this->assertSame('Buka Status Pengumpulan', $notification['actions'][0]['label']);
        $this->assertSame(
            AstsSubmissionStatus::getUrl(['period' => $period->getKey()]),
            $notification['actions'][0]['url'],
        );

        $asasPeriod = AssessmentPeriod::factory()->asas()->create([
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $asasNotification = AssessmentActionFailureNotification::make(
            ValidationException::withMessages(['assignments' => 'Penugasan ASAS belum lengkap.']),
            'Tutup Input',
            $asasPeriod,
        )->toArray();

        $this->assertSame(
            AsasSubmissionStatus::getUrl(['period' => $asasPeriod->getKey()]),
            $asasNotification['actions'][0]['url'],
        );
    }

    public function test_scheme_failure_notification_points_to_components_and_weights_before_submission_status(): void
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::query()->create([
            'name' => 'Admin Skema',
            'username' => 'admin-skema-notice',
            'password' => 'test-password',
        ]);
        $admin->assignRole('admin');
        $period = AssessmentPeriod::factory()->asts()->create([
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $this->actingAs($admin);

        $notification = AssessmentActionFailureNotification::make(
            ValidationException::withMessages([
                'scheme' => 'Tidak ada skema penilaian aktif yang cocok dengan mapel dan kelas penugasan.',
            ]),
            'Sinkronisasi Mapel',
            $period,
        )->toArray();

        $this->assertSame('persistent', $notification['duration']);
        $this->assertSame('Buka Komponen dan Bobot', $notification['actions'][0]['label']);
        $this->assertStringContainsString(AssessmentSchemeResource::getUrl(), $notification['actions'][0]['url']);
        $this->assertStringContainsString('total bobot 100%', (string) $notification['body']);
    }

    public function test_blocking_assignment_notification_action_url_includes_assignment_and_no_markdown_outside_panel_context(): void
    {
        auth()->logout();

        $period = AssessmentPeriod::factory()->asts()->create([
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $assignment = AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(),
        ]);

        $notification = AssessmentActionFailureNotification::make(
            new \RuntimeException('Penilaian belum lengkap.'),
            'Simpan Draf Nilai',
            $period,
            $assignment,
        )->toArray();

        $this->assertSame('Buka Input Nilai', $notification['actions'][0]['label']);
        $this->assertSame(
            AstsInputScores::getUrl([
                'period' => $period->getKey(),
                'assignment' => $assignment->getKey(),
            ]),
            $notification['actions'][0]['url'],
        );
        $this->assertStringNotContainsString('**', (string) $notification['body']);
        $this->assertStringContainsString('Kendala: Penilaian belum lengkap.', (string) $notification['body']);
        $this->assertStringContainsString('Solusi: Buka penugasan nilai terkait, koreksi nilainya, simpan perubahan, lalu ulangi aksi.', (string) $notification['body']);

        $asasPeriod = AssessmentPeriod::factory()->asas()->create([
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $asasAssignment = AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $asasPeriod->getKey(),
        ]);

        $asasNotification = AssessmentActionFailureNotification::make(
            ValidationException::withMessages(['score' => 'Nilai melebihi batas maksimum.']),
            'Simpan Nilai',
            $asasPeriod,
            $asasAssignment,
        )->toArray();

        $this->assertSame('Buka Input Nilai', $asasNotification['actions'][0]['label']);
        $this->assertSame(
            \App\Filament\Pages\Assessment\AsasInputScores::getUrl([
                'period' => $asasPeriod->getKey(),
                'assignment' => $asasAssignment->getKey(),
            ]),
            $asasNotification['actions'][0]['url'],
        );
        $this->assertStringNotContainsString('**', (string) $asasNotification['body']);
    }

    public function test_sidebar_only_lists_operational_assessment_types_relevant_to_teacher_or_homeroom(): void
    {
        $teacher = $this->teacher(348);
        $teacher->forceFill(['module_access_levels' => ['penilaian' => 'view']])->save();
        $asts = AssessmentPeriod::factory()->asts()->create([
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $asas = AssessmentPeriod::factory()->asas()->create([
            'status' => AssessmentPeriodStatus::OPEN,
        ]);
        $asatDraft = AssessmentPeriod::factory()->create([
            'type' => \App\Enums\Assessment\AssessmentType::ASAT,
            'status' => AssessmentPeriodStatus::DRAFT,
        ]);
        $asatVerification = AssessmentPeriod::factory()->create([
            'type' => \App\Enums\Assessment\AssessmentType::ASAT,
            'status' => AssessmentPeriodStatus::VERIFICATION,
        ]);
        $rombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $asts->getKey(),
            'rombel_name_snapshot' => 'XI 1',
        ]);
        $subject = Subject::factory()->create(['name' => 'Fisika']);
        AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $asts->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'teacher_id' => 348,
            'teacher_name_snapshot' => 'Putra Kamulyan',
            'subject_name_snapshot' => 'Fisika',
            'rombel_name_snapshot' => 'XI 1',
        ]);
        AssessmentPeriodHomeroom::factory()->create([
            'assessment_period_id' => $asatVerification->getKey(),
            'teacher_id' => 348,
            'teacher_name_snapshot' => 'Putra Kamulyan',
            'rombel_name_snapshot' => 'XI 2',
        ]);
        AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $asatDraft->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'teacher_id' => 348,
            'teacher_name_snapshot' => 'Putra Kamulyan',
            'subject_name_snapshot' => 'Fisika',
            'rombel_name_snapshot' => 'XI 1',
        ]);

        $this->actingAs($teacher);

        $this->assertTrue(AstsHub::shouldRegisterNavigation());
        $this->assertFalse(AsasHub::shouldRegisterNavigation());
        $this->assertTrue(AsatHub::shouldRegisterNavigation());
        $this->assertFalse(AssessmentDashboard::shouldRegisterNavigation());
        $this->assertFalse(AssessmentSetupWizard::shouldRegisterNavigation());
        $this->assertFalse(AssessmentTeachingMatrix::shouldRegisterNavigation());
        $this->assertFalse(QuestionBankBuilderPage::shouldRegisterNavigation());
        $this->assertFalse(OnlineExamPage::shouldRegisterNavigation());

        $asatVerification->update(['status' => AssessmentPeriodStatus::DRAFT]);
        $this->assertFalse(AsatHub::shouldRegisterNavigation());

        // Hubs remain accessible when their type is authorized but not in the sidebar.
        $this->assertTrue(AsasHub::canAccess());

        DB::table('guru_tendik')->insert([
            'id' => 349,
            'nama' => 'Wali Kelas',
            'status' => 'aktif',
        ]);
        $homeroomOnly = User::query()->create([
            'name' => 'Wali Kelas',
            'username' => 'homeroom-assessment',
            'password' => 'test-password',
            'guru_tendik_id' => 349,
            'module_access_levels' => ['penilaian' => 'view'],
        ]);
        $homeroomOnly->assignRole('guru');
        AssessmentPeriodHomeroom::factory()->create([
            'assessment_period_id' => $asts->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'teacher_id' => 349,
        ]);

        $this->actingAs($homeroomOnly);
        $this->assertTrue(AstsHub::shouldRegisterNavigation());
        $this->assertFalse(AsasHub::shouldRegisterNavigation());
        $this->assertFalse(AsatHub::shouldRegisterNavigation());

        Livewire::actingAs($homeroomOnly)
            ->test(AstsHub::class)
            ->set('periodId', $asts->getKey())
            ->assertSee('Rekap Wali Kelas')
            ->assertDontSee('Tugas Guru');
    }

    public function test_asts_submission_blocks_missing_daily_or_pure_score(): void
    {
        $teacher = $this->teacher(348);
        $period = AssessmentPeriod::factory()->asts()->create(['status' => AssessmentPeriodStatus::OPEN]);
        $rombel = AssessmentPeriodRombel::factory()->create(['assessment_period_id' => $period->getKey()]);
        $student = AssessmentPeriodStudent::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
        ]);
        $subject = Subject::factory()->create();
        $scheme = AssessmentScheme::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'settings' => ['asts' => ['daily_weight' => 50, 'pure_weight' => 50]],
        ]);
        $components = collect([
            ['UH1', 'UH 1', 16.6667, false], ['UH2', 'UH 2', 16.6667, false],
            ['UH3', 'UH 3', 16.6666, false], ['ASTS_MURNI', 'Nilai Murni ASTS', 50, true],
        ])->map(fn (array $component, int $order): AssessmentComponent => AssessmentComponent::factory()->create([
            'assessment_scheme_id' => $scheme->getKey(), 'code' => $component[0], 'name' => $component[1],
            'weight' => $component[2], 'is_required' => $component[3], 'sort_order' => $order,
        ]));
        $assignment = AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(), 'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_subject_id' => $subject->getKey(), 'teacher_id' => 348, 'status' => AssignmentStatus::DRAFT,
        ]);

        // A pure score alone is a valid draft but cannot be submitted without a UH.
        AssessmentScore::factory()->create([
            'assessment_period_assignment_id' => $assignment->getKey(),
            'assessment_period_student_id' => $student->getKey(),
            'assessment_component_id' => $components->last()->getKey(), 'score' => 90,
        ]);
        try {
            app(\App\Actions\Assessment\SubmitAssessmentAssignmentAction::class)->execute($teacher, $assignment);
            $this->fail('ASTS submission should require at least one UH score.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scores', $exception->errors());
        }

        AssessmentScore::query()->where('assessment_period_assignment_id', $assignment->getKey())->delete();
        AssessmentScore::factory()->create([
            'assessment_period_assignment_id' => $assignment->getKey(),
            'assessment_period_student_id' => $student->getKey(),
            'assessment_component_id' => $components->first()->getKey(), 'score' => 90,
        ]);
        try {
            app(\App\Actions\Assessment\SubmitAssessmentAssignmentAction::class)->execute($teacher, $assignment);
            $this->fail('ASTS submission should require Nilai Murni ASTS.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('scores', $exception->errors());
        }
    }

    public function test_asts_homeroom_recap_shows_final_score_ranking_and_only_own_class(): void
    {
        $teacher = $this->teacher(348);
        $period = AssessmentPeriod::factory()->asts()->create(['status' => AssessmentPeriodStatus::OPEN]);
        $ownRombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'X Ranking',
        ]);
        $otherRombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'X Lain',
        ]);
        $students = AssessmentPeriodStudent::factory()->count(3)->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $ownRombel->getKey(),
            'rombel_name_snapshot' => 'X Ranking',
        ]);
        $otherStudent = AssessmentPeriodStudent::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $otherRombel->getKey(),
            'rombel_name_snapshot' => 'X Lain',
            'student_name_snapshot' => 'Siswa Kelas Lain',
        ]);
        $homeroom = AssessmentPeriodHomeroom::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $ownRombel->getKey(),
            'teacher_id' => 348,
            'rombel_name_snapshot' => 'X Ranking',
        ]);
        AssessmentPeriodHomeroom::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $otherRombel->getKey(),
            'teacher_id' => 999,
            'rombel_name_snapshot' => 'X Lain',
        ]);
        $subjectOne = Subject::factory()->create(['name' => 'Matematika']);
        $subjectTwo = Subject::factory()->create(['name' => 'Bahasa Indonesia']);
        $assignment = function (Subject $subject) use ($period, $ownRombel): AssessmentPeriodAssignment {
            return AssessmentPeriodAssignment::factory()->create([
                'assessment_period_id' => $period->getKey(),
                'assessment_period_rombel_id' => $ownRombel->getKey(),
                'assessment_subject_id' => $subject->getKey(),
                'subject_name_snapshot' => $subject->name,
            ]);
        };
        $math = $assignment($subjectOne);
        $indonesian = $assignment($subjectTwo);
        foreach ([[90, 80], [90, 80], [70, null]] as $index => [$mathScore, $indonesianScore]) {
            StudentSubjectResult::factory()->create([
                'assessment_period_id' => $period->getKey(),
                'assessment_period_student_id' => $students[$index]->getKey(),
                'assessment_period_assignment_id' => $math->getKey(),
                'final_score' => $mathScore,
            ]);
            if ($indonesianScore !== null) {
                StudentSubjectResult::factory()->create([
                    'assessment_period_id' => $period->getKey(),
                    'assessment_period_student_id' => $students[$index]->getKey(),
                    'assessment_period_assignment_id' => $indonesian->getKey(),
                    'final_score' => $indonesianScore,
                ]);
            }
        }
        StudentSubjectResult::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_student_id' => $otherStudent->getKey(),
            'assessment_period_assignment_id' => $math->getKey(),
            'final_score' => 100,
        ]);

        Livewire::actingAs($teacher)
            ->test(AstsHomeroomRecap::class)
            ->set('periodId', $period->getKey())
            ->set('homeroomId', $homeroom->getKey())
            ->call('loadReports')
            ->assertSet("astsRanking.rows.{$students[0]->getKey()}.total", 170.0)
            ->assertSet("astsRanking.rows.{$students[0]->getKey()}.average", 85.0)
            ->assertSet("astsRanking.rows.{$students[0]->getKey()}.rank", 1)
            ->assertSet("astsRanking.rows.{$students[1]->getKey()}.rank", 1)
            ->assertSet("astsRanking.rows.{$students[2]->getKey()}.rank", 3)
            ->assertSet("astsRanking.rows.{$students[2]->getKey()}.completed", 1)
            ->assertSee('Ringkasan Nilai Akhir dan Peringkat ASTS')
            ->assertSee('belum lengkap')
            ->assertDontSee('Siswa Kelas Lain');
    }

    public function test_admin_and_curriculum_can_edit_foreign_assignment_with_visible_updater_badge(): void
    {
        $owner = $this->teacher(348);
        $nonOwner = $this->teacher(999);
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('kurikulum', 'web');
        $admin = User::query()->create([
            'name' => 'Admin Nilai',
            'username' => 'admin-score-intervention',
            'password' => 'test-password',
        ]);
        $admin->assignRole('admin');
        $curriculum = User::query()->create([
            'name' => 'Kurikulum Nilai',
            'username' => 'kurikulum-score-intervention',
            'password' => 'test-password',
        ]);
        $curriculum->assignRole('kurikulum');
        $period = AssessmentPeriod::factory()->asts()->create([
            'status' => AssessmentPeriodStatus::OPEN,
            'entry_end_at' => now()->subMinute(),
        ]);
        $rombel = AssessmentPeriodRombel::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'rombel_name_snapshot' => 'XI Intervensi',
        ]);
        $student = AssessmentPeriodStudent::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'rombel_name_snapshot' => 'XI Intervensi',
        ]);
        $subject = Subject::factory()->create(['name' => 'Kimia']);
        $scheme = AssessmentScheme::factory()->create([
            'assessment_period_id' => $period->getKey(),
        ]);
        $component = AssessmentComponent::factory()->create([
            'assessment_scheme_id' => $scheme->getKey(),
            'name' => 'Nilai ASTS',
        ]);
        $assignment = AssessmentPeriodAssignment::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_subject_id' => $subject->getKey(),
            'teacher_id' => 348,
            'teacher_name_snapshot' => 'Putra Kamulyan',
            'subject_name_snapshot' => 'Kimia',
            'rombel_name_snapshot' => 'XI Intervensi',
            'status' => AssignmentStatus::DRAFT,
        ]);

        $this->assertFalse(Gate::forUser($nonOwner)->allows('updateScores', $assignment));
        Livewire::actingAs($nonOwner)
            ->test(AstsInputScores::class)
            ->set('periodId', $period->getKey())
            ->set('assignmentId', $assignment->getKey())
            ->call('loadAssignment')
            ->assertSet('assignmentId', null);

        foreach ([[$admin, 'Diperbarui oleh Admin', 90], [$curriculum, 'Diperbarui oleh Kurikulum', 91]] as [$actor, $badge, $score]) {
            $this->assertTrue(Gate::forUser($actor)->allows('updateScores', $assignment));
            Livewire::actingAs($actor)
                ->test(AstsInputScores::class)
                ->set('periodId', $period->getKey())
                ->set('assignmentId', $assignment->getKey())
                ->call('loadAssignment')
                ->assertSet('assignmentMeta.editable', true)
                ->set("scoreRows.{$student->getKey()}.scores.{$component->getKey()}", $score)
                ->call('saveDraft');

            $this->assertDatabaseHas('assessment_scores', [
                'assessment_period_assignment_id' => $assignment->getKey(),
                'assessment_period_student_id' => $student->getKey(),
                'assessment_component_id' => $component->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
            Livewire::actingAs($actor)
                ->test(AstsInputScores::class)
                ->set('periodId', $period->getKey())
                ->set('assignmentId', $assignment->getKey())
                ->call('loadAssignment')
                ->assertSee($badge);
        }

        $this->assertTrue(Gate::forUser($owner)->allows('updateScores', $assignment));
        Livewire::actingAs($owner)
            ->test(AstsInputScores::class)
            ->set('periodId', $period->getKey())
            ->set('assignmentId', $assignment->getKey())
            ->call('loadAssignment')
            ->set("scoreRows.{$student->getKey()}.scores.{$component->getKey()}", 80)
            ->call('saveDraft');

        $this->assertDatabaseHas('assessment_scores', [
            'assessment_period_assignment_id' => $assignment->getKey(),
            'assessment_period_student_id' => $student->getKey(),
            'assessment_component_id' => $component->getKey(),
            'score' => 91,
            'updated_by' => $curriculum->getKey(),
        ]);

        $pureComponent = AssessmentComponent::query()
            ->where('assessment_scheme_id', $scheme->getKey())
            ->where('code', 'ASTS_MURNI')
            ->firstOrFail();
        AssessmentScore::query()->updateOrCreate([
            'assessment_period_assignment_id' => $assignment->getKey(),
            'assessment_period_student_id' => $student->getKey(),
            'assessment_component_id' => $pureComponent->getKey(),
        ], [
            'score' => 90,
            'source' => ScoreSource::MANUAL,
            'updated_by' => $curriculum->getKey(),
        ]);

        try {
            app(\App\Actions\Assessment\SubmitAssessmentAssignmentAction::class)->execute($owner, $assignment);
            $this->fail('Teacher owner must not submit after the entry deadline.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('period', $exception->errors());
        }

        app(\App\Actions\Assessment\SubmitAssessmentAssignmentAction::class)->execute($curriculum, $assignment);
        $this->assertSame(AssignmentStatus::SUBMITTED, $assignment->fresh()->status);
        $this->assertDatabaseHas('assessment_audit_logs', [
            'event' => 'assignment.submitted',
            'subject_id' => $assignment->getKey(),
            'actor_id' => $curriculum->getKey(),
            'reason' => 'Submission after score-entry deadline was authorized by override policy.',
        ]);

        $this->actingAs($admin);
        $notification = AssessmentActionFailureNotification::make(
            ValidationException::withMessages([
                'period' => 'Batas waktu pengisian nilai telah berakhir.',
            ]),
            'Simpan Draf Nilai',
            $period,
            $assignment,
        )->toArray();

        $this->assertStringNotContainsString('**', (string) $notification['body']);
        $this->assertSame('Buka Input Nilai', $notification['actions'][0]['label']);
        $this->assertSame(
            AstsInputScores::getUrl([
                'period' => $period->getKey(),
                'assignment' => $assignment->getKey(),
            ]),
            $notification['actions'][0]['url'],
        );
    }

    public function test_asts_excel_exports_are_scoped_and_return_xlsx_workbooks(): void
    {
        $teacher = $this->teacher(348);
        $period = AssessmentPeriod::factory()->asts()->create(['status' => AssessmentPeriodStatus::OPEN]);
        $rombel = AssessmentPeriodRombel::factory()->create(['assessment_period_id' => $period->getKey(), 'rombel_name_snapshot' => 'XI Excel']);
        $student = AssessmentPeriodStudent::factory()->create(['assessment_period_id' => $period->getKey(), 'assessment_period_rombel_id' => $rombel->getKey(), 'rombel_name_snapshot' => 'XI Excel']);
        $subject = Subject::factory()->create(['name' => 'Ekspor Nilai']);
        $scheme = AssessmentScheme::factory()->create(['assessment_period_id' => $period->getKey()]);
        $uh1 = AssessmentComponent::factory()->create(['assessment_scheme_id' => $scheme->getKey(), 'code' => 'UH1']);
        $pure = AssessmentComponent::factory()->create(['assessment_scheme_id' => $scheme->getKey(), 'code' => 'ASTS_MURNI']);
        $assignment = AssessmentPeriodAssignment::factory()->create(['assessment_period_id' => $period->getKey(), 'assessment_period_rombel_id' => $rombel->getKey(), 'assessment_subject_id' => $subject->getKey(), 'teacher_id' => 348, 'rombel_name_snapshot' => 'XI Excel', 'subject_name_snapshot' => 'Ekspor Nilai']);
        AssessmentScore::factory()->create(['assessment_period_assignment_id' => $assignment->getKey(), 'assessment_period_student_id' => $student->getKey(), 'assessment_component_id' => $uh1->getKey(), 'score' => 80, 'updated_by' => $teacher->getKey()]);
        AssessmentScore::factory()->create(['assessment_period_assignment_id' => $assignment->getKey(), 'assessment_period_student_id' => $student->getKey(), 'assessment_component_id' => $pure->getKey(), 'score' => 90, 'updated_by' => $teacher->getKey()]);
        StudentSubjectResult::factory()->create(['assessment_period_id' => $period->getKey(), 'assessment_period_assignment_id' => $assignment->getKey(), 'assessment_period_student_id' => $student->getKey(), 'final_score' => 85, 'predicate' => 'B']);
        $homeroom = AssessmentPeriodHomeroom::factory()->create(['assessment_period_id' => $period->getKey(), 'assessment_period_rombel_id' => $rombel->getKey(), 'teacher_id' => 348, 'rombel_name_snapshot' => 'XI Excel']);

        $this->actingAs($teacher);
        $status = $this->get(route('admin.assessment.asts.status.export', $period));
        $status->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertSame(AssessmentAstsExportController::temporaryDirectory(), config('excel.temporary_files.local_path'));
        $this->assertStringStartsWith('PK', $status->streamedContent());
        $homeroomExport = $this->get(route('admin.assessment.asts.homeroom.export', [$period, $homeroom]));
        $homeroomExport->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $homeroomExport->streamedContent());

        $otherRombel = AssessmentPeriodRombel::factory()->create(['assessment_period_id' => $period->getKey(), 'rombel_name_snapshot' => 'XI Lain']);
        $otherHomeroom = AssessmentPeriodHomeroom::factory()->create(['assessment_period_id' => $period->getKey(), 'assessment_period_rombel_id' => $otherRombel->getKey(), 'teacher_id' => 999]);
        $this->get(route('admin.assessment.asts.homeroom.export', [$period, $otherHomeroom]))->assertRedirect();
    }

    private function teacher(int $teacherId): User
    {
        Role::findOrCreate('guru', 'web');

        $user = User::query()->create([
            'name' => 'Putra Kamulyan',
            'username' => 'teacher-assessment-'.$teacherId,
            'email' => null,
            'password' => 'test-password',
            'guru_tendik_id' => $teacherId,
        ]);
        $user->assignRole('guru');

        return $user;
    }
}
