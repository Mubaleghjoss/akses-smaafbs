<?php

namespace Tests\Feature;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Filament\Pages\Assessment\AsasHomeroomRecap;
use App\Filament\Pages\Assessment\AstsHomeroomRecap;
use App\Filament\Pages\Assessment\AstsHub;
use App\Filament\Pages\Assessment\AstsInputScores;
use App\Filament\Pages\Assessment\AstsSubmissionStatus;
use App\Models\Assessment\AssessmentComponent;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\HomeroomReport;
use App\Models\Assessment\Subject;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
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
            ->assertSee('X 1');

        $studentId = (int) $students->first()->getKey();
        Livewire::actingAs($teacher)
            ->test(AstsInputScores::class)
            ->set('periodId', $period->getKey())
            ->set('assignmentId', $assignment->getKey())
            ->call('loadAssignment')
            ->set('selectedStudentIds', [$studentId])
            ->set('bulkComponentId', $component->getKey())
            ->set('bulkScore', '88')
            ->set('bulkDescription', 'Menunjukkan pemahaman yang baik.')
            ->call('applyBulkValues')
            ->assertSet("scoreRows.{$studentId}.scores.{$component->getKey()}", 88.0)
            ->assertSet("scoreRows.{$studentId}.description", 'Menunjukkan pemahaman yang baik.')
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
            ->assertSet("scoreRows.{$studentId}.description", 'Deskripsi hasil bulk terbaru.');

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
            ->set('selectedStudentIds', [$studentId])
            ->set('bulkField', 'homeroom_note')
            ->set('bulkValue', 'Terus tingkatkan kedisiplinan dan tanggung jawab.')
            ->call('applyBulkValue')
            ->assertSet(
                "reportRows.{$studentId}.homeroom_note",
                'Terus tingkatkan kedisiplinan dan tanggung jawab.',
            );

        $this->assertSame(AssignmentStatus::DRAFT, $assignment->refresh()->status);
        $this->assertSame(AssignmentStatus::SUBMITTED, $submittedAssignment->refresh()->status);
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
            ->asts()
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
            'extracurricular',
            'achievement',
            'homeroom_note',
        ];

        $astsComponent = Livewire::actingAs($teacher)
            ->test(AstsHomeroomRecap::class)
            ->set('periodId', $astsPeriod->getKey())
            ->set('homeroomId', $astsHomeroom->getKey())
            ->call('loadReports')
            ->assertSet('bulkFillEmptyOnly', true);

        $this->assertSame(
            $expectedHeaders,
            array_column($astsComponent->instance()->getRecapFieldDefinitions(), 'header'),
        );
        $this->assertSame($expectedFields, array_keys($astsComponent->instance()->getBulkFieldOptions()));

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
            'extracurricular' => ["Pramuka\nBasket", "Pramuka\nBasket"],
            'achievement' => ['Juara 1 Olimpiade Sains', 'Juara 1 Olimpiade Sains'],
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
            [['description' => 'Pramuka'], ['description' => 'Basket']],
            $savedReport->extracurricular_data,
        );
        $this->assertSame(
            [['description' => 'Juara 1 Olimpiade Sains']],
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

    private function teacher(int $teacherId): User
    {
        Role::findOrCreate('guru', 'web');

        $user = User::query()->create([
            'name' => 'Putra Kamulyan',
            'username' => 'teacher-assessment',
            'email' => null,
            'password' => 'test-password',
            'guru_tendik_id' => $teacherId,
        ]);
        $user->assignRole('guru');

        return $user;
    }
}
