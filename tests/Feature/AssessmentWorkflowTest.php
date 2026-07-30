<?php

namespace Tests\Feature;

use App\Actions\Assessment\CloseAssessmentEntryAction;
use App\Actions\Assessment\CreateAssessmentPeriodSnapshotAction;
use App\Actions\Assessment\LockAssessmentPeriodAction;
use App\Actions\Assessment\PublishAssessmentPeriodAction;
use App\Actions\Assessment\ReopenAssessmentPeriodAction;
use App\Actions\Assessment\ReturnAssessmentAssignmentAction;
use App\Actions\Assessment\SaveAssessmentScoresAction;
use App\Actions\Assessment\StartAssessmentVerificationAction;
use App\Actions\Assessment\SubmitAssessmentAssignmentAction;
use App\Actions\Assessment\VerifyAssessmentAssignmentAction;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssignmentStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\ReportGenerationStatus;
use App\Enums\Assessment\ScoreSource;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentComponent;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentScheme;
use App\Models\Assessment\AssessmentScore;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\HomeroomAssignment;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\Semester;
use App\Models\Assessment\StudentSubjectResult;
use App\Models\Assessment\Subject;
use App\Models\Assessment\TeachingAssignment;
use App\Models\DataSiswa;
use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use App\Support\Admin\AdminModuleAccess;
use App\Support\Assessment\Reporting\AssessmentReportStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Concerns\BootstrapsStudentAndTeacherTables;
use Tests\Feature\Concerns\BootstrapsUserAndPermissionTables;
use Tests\TestCase;

class AssessmentWorkflowTest extends TestCase
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
        Queue::fake();
    }

    public function test_scores_are_separated_by_period_and_optimistic_locking_is_retry_safe(): void
    {
        $context = $this->createContext();
        $asts = $this->createOpenedPeriod($context, AssessmentType::ASTS);
        $asas = $this->createOpenedPeriod($context, AssessmentType::ASAS);
        $astsAssignment = $asts->assignments()->firstOrFail();
        $asasAssignment = $asas->assignments()->firstOrFail();
        $astsStudent = $asts->students()->firstOrFail();
        $asasStudent = $asas->students()->firstOrFail();
        $astsComponent = $asts->schemes()->firstOrFail()->components()->firstOrFail();
        $asasComponent = $asas->schemes()->firstOrFail()->components()->firstOrFail();
        $save = app(SaveAssessmentScoresAction::class);

        $updatedAsts = $save->execute(
            $context['teacher_user'],
            $astsAssignment,
            [[
                'assessment_period_student_id' => $astsStudent->getKey(),
                'scores' => [$astsComponent->getKey() => 80],
            ]],
            1,
        );
        $save->execute(
            $context['teacher_user'],
            $asasAssignment,
            [[
                'assessment_period_student_id' => $asasStudent->getKey(),
                'scores' => [$asasComponent->getKey() => 92],
            ]],
            1,
        );

        $this->assertSame(80.0, (float) AssessmentScore::query()
            ->where('assessment_period_assignment_id', $astsAssignment->getKey())
            ->value('score'));
        $this->assertSame(92.0, (float) AssessmentScore::query()
            ->where('assessment_period_assignment_id', $asasAssignment->getKey())
            ->value('score'));
        $this->assertSame(80.0, (float) StudentSubjectResult::query()
            ->where('assessment_period_id', $asts->getKey())
            ->value('final_score'));
        $this->assertSame(92.0, (float) StudentSubjectResult::query()
            ->where('assessment_period_id', $asas->getKey())
            ->value('final_score'));

        // Lost-response retry with the same lock version and payload is idempotent.
        $retried = $save->execute(
            $context['teacher_user'],
            $astsAssignment,
            [[
                'assessment_period_student_id' => $astsStudent->getKey(),
                'scores' => [$astsComponent->getKey() => 80],
            ]],
            1,
        );
        $this->assertSame($updatedAsts->lock_version, $retried->lock_version);
        $this->assertSame(1, AssessmentScore::query()
            ->where('assessment_period_assignment_id', $astsAssignment->getKey())
            ->count());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Data nilai telah berubah di tab lain.');
        $save->execute(
            $context['teacher_user'],
            $astsAssignment,
            [[
                'assessment_period_student_id' => $astsStudent->getKey(),
                'scores' => [$astsComponent->getKey() => 81],
            ]],
            1,
        );
    }

    public function test_null_range_authorization_submit_return_and_lock_workflow_are_enforced(): void
    {
        $context = $this->createContext();
        $period = $this->createOpenedPeriod($context, AssessmentType::ASTS);
        $assignment = $period->assignments()->firstOrFail();
        $student = $period->students()->firstOrFail();
        $component = $period->schemes()->firstOrFail()->components()->firstOrFail();
        $save = app(SaveAssessmentScoresAction::class);

        $assignment = $save->execute(
            $context['teacher_user'],
            $assignment,
            [[
                'assessment_period_student_id' => $student->getKey(),
                'scores' => [$component->getKey() => null],
            ]],
            1,
        );
        $this->assertNull(AssessmentScore::query()->value('score'));
        $this->assertNull(StudentSubjectResult::query()->value('final_score'));

        try {
            $save->execute(
                $context['teacher_user'],
                $assignment,
                [[
                    'assessment_period_student_id' => $student->getKey(),
                    'scores' => [$component->getKey() => 101],
                ]],
                $assignment->lock_version,
            );
            $this->fail('Nilai di luar rentang seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('rentang', $exception->getMessage());
        }

        try {
            app(SubmitAssessmentAssignmentAction::class)->execute($context['teacher_user'], $assignment);
            $this->fail('Submit dengan komponen wajib kosong seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('belum lengkap', $exception->getMessage());
        }

        $assignment = $save->execute(
            $context['teacher_user'],
            $assignment,
            [[
                'assessment_period_student_id' => $student->getKey(),
                'scores' => [$component->getKey() => 88],
                'description' => 'Deskripsi yang disunting guru.',
            ]],
            $assignment->lock_version,
        );
        $assignment = app(SubmitAssessmentAssignmentAction::class)
            ->execute($context['teacher_user'], $assignment);
        $this->assertSame(AssignmentStatus::SUBMITTED, $assignment->status);

        try {
            $save->execute(
                $context['teacher_user'],
                $assignment,
                [[
                    'assessment_period_student_id' => $student->getKey(),
                    'scores' => [$component->getKey() => 90],
                ]],
                $assignment->lock_version,
            );
            $this->fail('Nilai submitted seharusnya read-only.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $assignment = app(ReturnAssessmentAssignmentAction::class)->execute(
            $context['curriculum_user'],
            $assignment,
            'Mohon periksa kembali nilai komponen utama.',
        );
        $this->assertSame(AssignmentStatus::RETURNED, $assignment->status);
        $assignment = $save->execute(
            $context['teacher_user'],
            $assignment,
            [[
                'assessment_period_student_id' => $student->getKey(),
                'scores' => [$component->getKey() => 90],
            ]],
            $assignment->lock_version,
        );
        $assignment = app(SubmitAssessmentAssignmentAction::class)
            ->execute($context['teacher_user'], $assignment);
        $period = app(CloseAssessmentEntryAction::class)
            ->execute($context['curriculum_user'], $period);
        $period = app(StartAssessmentVerificationAction::class)
            ->execute($context['curriculum_user'], $period);

        $this->expectAuthorizationFailure(
            fn () => app(VerifyAssessmentAssignmentAction::class)
                ->execute($context['teacher_user'], $assignment),
        );
        $assignment = app(VerifyAssessmentAssignmentAction::class)
            ->execute($context['curriculum_user'], $assignment);
        $this->assertSame(AssignmentStatus::VERIFIED, $assignment->status);

        $period = app(LockAssessmentPeriodAction::class)
            ->execute($context['curriculum_user'], $period);
        $this->assertSame(AssessmentPeriodStatus::LOCKED, $period->status);
        $this->assertSame(AssignmentStatus::LOCKED, $assignment->refresh()->status);
        $this->assertSame(1, ReportSnapshot::query()->where('assessment_period_id', $period->getKey())->count());

        $period = app(ReopenAssessmentPeriodAction::class)->execute(
            $context['curriculum_user'],
            $period,
            [$assignment->getKey()],
            'Perbaikan nilai setelah pemeriksaan kurikulum.',
        );
        $this->assertSame(AssessmentPeriodStatus::OPEN, $period->status);
        $this->assertSame(AssignmentStatus::RETURNED, $assignment->refresh()->status);
    }

    public function test_explicit_hidden_module_blocks_direct_score_action_even_when_role_has_permission(): void
    {
        $context = $this->createContext();
        $period = $this->createOpenedPeriod($context, AssessmentType::ASTS);
        $assignment = $period->assignments()->firstOrFail();
        $student = $period->students()->firstOrFail();
        $component = $period->schemes()->firstOrFail()->components()->firstOrFail();
        $teacher = $context['teacher_user'];
        $teacher->forceFill([
            'module_access_levels' => ['penilaian' => AdminModuleAccess::NONE],
        ])->save();

        $this->expectAuthorizationFailure(
            fn () => app(SaveAssessmentScoresAction::class)->execute(
                $teacher->fresh(),
                $assignment,
                [[
                    'assessment_period_student_id' => $student->getKey(),
                    'scores' => [$component->getKey() => 85],
                ]],
                $assignment->lock_version,
            ),
        );
        $this->assertDatabaseCount('assessment_scores', 0);
    }

    public function test_publish_uses_locked_snapshot_even_when_active_template_changes(): void
    {
        Storage::fake('local');
        $context = $this->createContext();
        $period = $this->createOpenedPeriod($context, AssessmentType::ASTS);
        $assignment = $period->assignments()->firstOrFail();
        $student = $period->students()->firstOrFail();
        $component = $period->schemes()->firstOrFail()->components()->firstOrFail();
        $assignment = app(SaveAssessmentScoresAction::class)->execute(
            $context['teacher_user'],
            $assignment,
            [[
                'assessment_period_student_id' => $student->getKey(),
                'scores' => [$component->getKey() => 95],
            ]],
            1,
        );
        $assignment = app(SubmitAssessmentAssignmentAction::class)
            ->execute($context['teacher_user'], $assignment);
        $period = app(CloseAssessmentEntryAction::class)
            ->execute($context['curriculum_user'], $period);
        $period = app(StartAssessmentVerificationAction::class)
            ->execute($context['curriculum_user'], $period);
        app(VerifyAssessmentAssignmentAction::class)
            ->execute($context['curriculum_user'], $assignment);
        $period = app(LockAssessmentPeriodAction::class)
            ->execute($context['curriculum_user'], $period);
        $snapshot = ReportSnapshot::query()
            ->where('assessment_period_id', $period->getKey())
            ->firstOrFail();
        $storage = app(AssessmentReportStorage::class);
        $studentPdf = "%PDF-1.4\nstudent report\n%%EOF";
        $studentPath = $storage->individualPath($snapshot);
        Storage::disk('local')->put($studentPath, $studentPdf);
        $snapshot->forceFill([
            'generation_status' => ReportGenerationStatus::COMPLETED,
            'pdf_path' => $studentPath,
            'checksum' => hash('sha256', $studentPdf),
            'generated_at' => now(),
        ])->save();
        ClassReportArtifact::query()
            ->where('assessment_period_id', $period->getKey())
            ->get()
            ->each(function (ClassReportArtifact $artifact) use ($storage): void {
                $classPdf = "%PDF-1.4\nclass report {$artifact->getKey()}\n%%EOF";
                $classPath = $storage->classPath($artifact);
                Storage::disk('local')->put($classPath, $classPdf);
                $artifact->forceFill([
                    'generation_status' => ReportGenerationStatus::COMPLETED,
                    'pdf_path' => $classPath,
                    'checksum' => hash('sha256', $classPdf),
                    'generated_at' => now(),
                ])->save();
            });
        $snapshot->template->update(['is_active' => false]);
        $snapshot->template->replicate()->fill([
            'code' => 'ASTS-REPLACEMENT',
            'version' => 99,
            'is_active' => true,
        ])->save();

        $published = app(PublishAssessmentPeriodAction::class)
            ->execute($context['curriculum_user'], $period);

        $this->assertSame(AssessmentPeriodStatus::PUBLISHED, $published->status);
        $this->assertSame(
            $snapshot->assessment_report_template_id,
            data_get($published->auditLogs()->where('event', 'period.published')->first()?->new_values, 'template_id'),
        );
        $this->assertSame(
            $snapshot->assessment_report_template_id,
            data_get($published->settings, '_reporting.published.template_id'),
        );
        $this->assertSame(
            (int) $snapshot->revision,
            data_get($published->settings, '_reporting.published.revision'),
        );
    }

    public function test_asas_asts_reference_is_copied_as_reproducible_snapshot(): void
    {
        $context = $this->createContext();
        $asts = $this->createOpenedPeriod($context, AssessmentType::ASTS);
        $astsAssignment = $asts->assignments()->firstOrFail();
        $astsStudent = $asts->students()->firstOrFail();
        $astsComponent = $asts->schemes()->firstOrFail()->components()->firstOrFail();
        $astsAssignment = app(SaveAssessmentScoresAction::class)->execute(
            $context['teacher_user'],
            $astsAssignment,
            [[
                'assessment_period_student_id' => $astsStudent->getKey(),
                'scores' => [$astsComponent->getKey() => 76],
            ]],
            1,
        );
        $astsAssignment = app(SubmitAssessmentAssignmentAction::class)
            ->execute($context['teacher_user'], $astsAssignment);
        $asts = app(CloseAssessmentEntryAction::class)
            ->execute($context['curriculum_user'], $asts);
        $asts = app(StartAssessmentVerificationAction::class)
            ->execute($context['curriculum_user'], $asts);
        app(VerifyAssessmentAssignmentAction::class)
            ->execute($context['curriculum_user'], $astsAssignment);
        app(LockAssessmentPeriodAction::class)
            ->execute($context['curriculum_user'], $asts);
        $sourceResult = StudentSubjectResult::query()
            ->where('assessment_period_id', $asts->getKey())
            ->firstOrFail();
        $asas = $this->createOpenedPeriod(
            $context,
            AssessmentType::ASAS,
            ScoreSource::ASTS_SNAPSHOT,
        );
        $asasAssignment = $asas->assignments()->firstOrFail();
        $asasStudent = $asas->students()->firstOrFail();
        $asasReferenceComponent = $asas->schemes()->firstOrFail()->components()->firstOrFail();

        $asasAssignment = app(SaveAssessmentScoresAction::class)->execute(
            $context['teacher_user'],
            $asasAssignment,
            [[
                'assessment_period_student_id' => $asasStudent->getKey(),
                // Disabled UI cells are still present in the matrix payload;
                // their value must be ignored in favour of the server snapshot.
                'scores' => [$asasReferenceComponent->getKey() => 999],
            ]],
            1,
        );

        $copied = AssessmentScore::query()
            ->where('assessment_period_assignment_id', $asasAssignment->getKey())
            ->firstOrFail();
        $this->assertSame(76.0, (float) $copied->score);
        $this->assertSame(76.0, (float) $copied->source_score_snapshot);
        $this->assertSame($sourceResult->getKey(), $copied->source_result_id);
        $this->assertSame(ScoreSource::ASTS_SNAPSHOT, $copied->source);

        $sourceResult->forceFill(['final_score' => 20])->save();
        $this->assertSame(76.0, (float) $copied->refresh()->score);
        $this->assertSame(76.0, (float) $copied->source_score_snapshot);

        // A later draft save must not silently re-copy a changed ASTS result.
        // The first copied value is the reproducible source snapshot for ASAS.
        app(SaveAssessmentScoresAction::class)->execute(
            $context['teacher_user'],
            $asasAssignment,
            [[
                'assessment_period_student_id' => $asasStudent->getKey(),
                'scores' => [$asasReferenceComponent->getKey() => 999],
            ]],
            $asasAssignment->lock_version,
        );
        $this->assertSame(76.0, (float) $copied->refresh()->score);
        $this->assertSame(76.0, (float) $copied->source_score_snapshot);
    }

    public function test_reopened_selected_assignment_can_complete_without_reopening_other_locked_assignments(): void
    {
        $context = $this->createContext();
        $period = $this->createOpenedPeriodWithTwoAssignments($context);
        $assignments = $period->assignments()->orderBy('id')->get();
        $student = $period->students()->firstOrFail();
        $save = app(SaveAssessmentScoresAction::class);

        foreach ($assignments as $assignment) {
            $component = app(\App\Support\Assessment\AssessmentSchemeResolver::class)
                ->forAssignment($assignment)
                ->components
                ->firstOrFail();
            $assignment = $save->execute(
                $context['teacher_user'],
                $assignment,
                [[
                    'assessment_period_student_id' => $student->getKey(),
                    'scores' => [$component->getKey() => 80],
                ]],
                $assignment->lock_version,
            );
            app(SubmitAssessmentAssignmentAction::class)
                ->execute($context['teacher_user'], $assignment);
        }

        $period = app(CloseAssessmentEntryAction::class)
            ->execute($context['curriculum_user'], $period);
        $period = app(StartAssessmentVerificationAction::class)
            ->execute($context['curriculum_user'], $period);

        foreach ($assignments as $assignment) {
            app(VerifyAssessmentAssignmentAction::class)
                ->execute($context['curriculum_user'], $assignment->refresh());
        }

        $period = app(LockAssessmentPeriodAction::class)
            ->execute($context['curriculum_user'], $period);
        $settings = is_array($period->settings) ? $period->settings : [];
        data_set($settings, '_reporting.pending', [
            'template_id' => 123,
            'revision' => 2,
        ]);
        $period->forceFill(['settings' => $settings])->save();
        $selected = $assignments->first()->refresh();
        $untouched = $assignments->last()->refresh();
        $period = app(ReopenAssessmentPeriodAction::class)->execute(
            $context['curriculum_user'],
            $period,
            [$selected->getKey()],
            'Koreksi hanya untuk satu mata pelajaran.',
        );

        $this->assertSame(AssignmentStatus::RETURNED, $selected->refresh()->status);
        $this->assertSame(AssignmentStatus::LOCKED, $untouched->refresh()->status);
        $this->assertNull(data_get($period->settings, '_reporting.pending'));
        $period->forceFill(['entry_end_at' => now()->subMinute()])->save();

        $component = app(\App\Support\Assessment\AssessmentSchemeResolver::class)
            ->forAssignment($selected)
            ->components
            ->firstOrFail();
        $selected = $save->execute(
            $context['teacher_user'],
            $selected->refresh(),
            [[
                'assessment_period_student_id' => $student->getKey(),
                'scores' => [$component->getKey() => 85],
            ]],
            $selected->refresh()->lock_version,
        );
        app(SubmitAssessmentAssignmentAction::class)
            ->execute($context['teacher_user'], $selected);

        $period = app(CloseAssessmentEntryAction::class)
            ->execute($context['curriculum_user'], $period);
        $period = app(StartAssessmentVerificationAction::class)
            ->execute($context['curriculum_user'], $period);
        app(VerifyAssessmentAssignmentAction::class)
            ->execute($context['curriculum_user'], $selected->refresh());
        $period = app(LockAssessmentPeriodAction::class)
            ->execute($context['curriculum_user'], $period);

        $this->assertSame(AssessmentPeriodStatus::LOCKED, $period->status);
        $this->assertSame(AssignmentStatus::LOCKED, $selected->refresh()->status);
        $this->assertSame(AssignmentStatus::LOCKED, $untouched->refresh()->status);
    }

    public function test_expired_entry_window_still_rejects_new_draft_save_and_submit(): void
    {
        $context = $this->createContext();
        $period = $this->createOpenedPeriod($context, AssessmentType::ASTS);
        $assignment = $period->assignments()->firstOrFail();
        $student = $period->students()->firstOrFail();
        $component = $period->schemes()->firstOrFail()->components()->firstOrFail();
        $save = app(SaveAssessmentScoresAction::class);
        $assignment = $save->execute(
            $context['teacher_user'],
            $assignment,
            [[
                'assessment_period_student_id' => $student->getKey(),
                'scores' => [$component->getKey() => 80],
            ]],
            $assignment->lock_version,
        );
        $period->forceFill(['entry_end_at' => now()->subMinute()])->save();

        try {
            $save->execute(
                $context['teacher_user'],
                $assignment,
                [[
                    'assessment_period_student_id' => $student->getKey(),
                    'scores' => [$component->getKey() => 81],
                ]],
                $assignment->lock_version,
            );
            $this->fail('Draf baru setelah batas waktu seharusnya tidak dapat disimpan.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Batas waktu', $exception->getMessage());
        }

        try {
            app(SubmitAssessmentAssignmentAction::class)
                ->execute($context['teacher_user'], $assignment);
            $this->fail('Draf baru setelah batas waktu seharusnya tidak dapat dikirim.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Batas waktu', $exception->getMessage());
        }
    }

    public function test_generated_description_refreshes_after_score_change_but_manual_text_is_preserved(): void
    {
        $context = $this->createContext();
        $period = $this->createOpenedPeriodWithTwoComponents($context);
        $assignment = $period->assignments()->firstOrFail();
        $student = $period->students()->firstOrFail();
        $components = $period->schemes()->firstOrFail()->components()->orderBy('id')->get();
        $first = $components->first();
        $second = $components->last();
        $save = app(SaveAssessmentScoresAction::class);

        $assignment = $save->execute(
            $context['teacher_user'],
            $assignment,
            [[
                'assessment_period_student_id' => $student->getKey(),
                'scores' => [
                    $first->getKey() => 90,
                    $second->getKey() => 60,
                ],
            ]],
            $assignment->lock_version,
        );
        $result = StudentSubjectResult::query()
            ->where('assessment_period_assignment_id', $assignment->getKey())
            ->firstOrFail();
        $this->assertStringContainsString('Domain Pertama', $result->description);
        $this->assertStringContainsString('Domain Kedua', $result->description);
        $oldGeneratedDescription = $result->description;

        $assignment = $save->execute(
            $context['teacher_user'],
            $assignment,
            [[
                'assessment_period_student_id' => $student->getKey(),
                'scores' => [
                    $first->getKey() => 50,
                    $second->getKey() => 95,
                ],
                // The matrix sends the displayed generated value back even
                // when the teacher did not edit the description.
                'description' => $oldGeneratedDescription,
            ]],
            $assignment->lock_version,
        );
        $result->refresh();
        $this->assertNotSame($oldGeneratedDescription, $result->description);
        $this->assertStringContainsString('terbaik pada Domain Kedua', $result->description);

        $manualDescription = 'Catatan manual guru yang harus dipertahankan.';
        $assignment = $save->execute(
            $context['teacher_user'],
            $assignment,
            [[
                'assessment_period_student_id' => $student->getKey(),
                'scores' => [
                    $first->getKey() => 50,
                    $second->getKey() => 95,
                ],
                'description' => $manualDescription,
            ]],
            $assignment->lock_version,
        );
        $assignment = $save->execute(
            $context['teacher_user'],
            $assignment,
            [[
                'assessment_period_student_id' => $student->getKey(),
                'scores' => [
                    $first->getKey() => 100,
                    $second->getKey() => 40,
                ],
                'description' => $manualDescription,
            ]],
            $assignment->lock_version,
        );

        $this->assertSame($manualDescription, $result->refresh()->description);
    }

    /**
     * @return array<string, mixed>
     */
    private function createContext(): array
    {
        $rombel = Rombel::query()->create([
            'nama' => 'XI 1',
            'angkatan' => 'XI',
            'is_active' => true,
        ]);
        $student = DataSiswa::query()->create([
            'nama' => 'Siswa Penilaian',
            'nisn' => '1234567890',
            'rombel_saat_ini' => $rombel->nama,
            'jk' => 'L',
            'status' => 'aktif',
        ]);
        $teacher = GuruTendik::query()->create([
            'nama' => 'Guru Mapel',
            'jenis_ptk' => 'Guru',
            'status' => 'Aktif',
        ]);
        $teacherUser = User::query()->create([
            'name' => 'Guru Mapel',
            'username' => 'guru-mapel-assessment',
            'password' => 'secret123',
            'guru_tendik_id' => $teacher->getKey(),
        ]);
        $teacherUser->assignRole('guru_mapel');
        $curriculumUser = User::query()->create([
            'name' => 'Kurikulum',
            'username' => 'kurikulum-assessment',
            'password' => 'secret123',
        ]);
        $curriculumUser->assignRole('kurikulum');
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
        $subject = Subject::query()->create([
            'code' => 'MAT',
            'name' => 'Matematika',
            'is_active' => true,
        ]);
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
        HomeroomAssignment::query()->create([
            'assessment_semester_id' => $semester->getKey(),
            'teacher_id' => $teacher->getKey(),
            'rombel_id' => $rombel->getKey(),
            'teacher_name_snapshot' => $teacher->nama,
            'rombel_name_snapshot' => $rombel->nama,
            'is_active' => true,
        ]);

        return compact(
            'rombel',
            'student',
            'teacher',
            'teacherUser',
            'curriculumUser',
            'year',
            'semester',
            'subject',
        ) + [
            'teacher_user' => $teacherUser,
            'curriculum_user' => $curriculumUser,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function createOpenedPeriod(
        array $context,
        AssessmentType $type,
        ScoreSource $scoreSource = ScoreSource::MANUAL,
    ): AssessmentPeriod
    {
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $context['year']->getKey(),
            'assessment_semester_id' => $context['semester']->getKey(),
            'code' => $type->value.'-workflow',
            'name' => $type->label().' Workflow',
            'type' => $type,
            'status' => AssessmentPeriodStatus::DRAFT,
            'entry_start_at' => now()->subHour(),
            'entry_end_at' => now()->addHour(),
            'report_date' => now()->toDateString(),
            'settings' => ['rombel_ids' => [$context['rombel']->getKey()]],
            'created_by' => $context['curriculum_user']->getKey(),
        ]);
        $scheme = AssessmentScheme::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_subject_id' => $context['subject']->getKey(),
            'source_rombel_id' => $context['rombel']->getKey(),
            'assessment_period_rombel_id' => null,
            'name' => 'Skema '.$type->value,
            'rounding_precision' => 0,
            'minimum_score' => 0,
            'maximum_score' => 100,
            'settings' => [
                'predicates' => [
                    ['label' => 'A', 'minimum_score' => 90],
                    ['label' => 'B', 'minimum_score' => 80],
                ],
            ],
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
            'score_source' => $scoreSource,
        ]);

        return app(CreateAssessmentPeriodSnapshotAction::class)
            ->execute($context['curriculum_user'], $period);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function createOpenedPeriodWithTwoAssignments(array $context): AssessmentPeriod
    {
        $secondSubject = Subject::query()->create([
            'code' => 'BIN',
            'name' => 'Bahasa Indonesia',
            'is_active' => true,
        ]);
        TeachingAssignment::query()->create([
            'assessment_semester_id' => $context['semester']->getKey(),
            'assessment_subject_id' => $secondSubject->getKey(),
            'teacher_id' => $context['teacher']->getKey(),
            'rombel_id' => $context['rombel']->getKey(),
            'teacher_name_snapshot' => $context['teacher']->nama,
            'subject_name_snapshot' => $secondSubject->name,
            'rombel_name_snapshot' => $context['rombel']->nama,
            'is_active' => true,
        ]);
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $context['year']->getKey(),
            'assessment_semester_id' => $context['semester']->getKey(),
            'code' => 'asts-reopen-two-assignments',
            'name' => 'ASTS Reopen Dua Penugasan',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::DRAFT,
            'entry_start_at' => now()->subHour(),
            'entry_end_at' => now()->addHour(),
            'report_date' => now()->toDateString(),
            'settings' => ['rombel_ids' => [$context['rombel']->getKey()]],
            'created_by' => $context['curriculum_user']->getKey(),
        ]);

        foreach ([$context['subject'], $secondSubject] as $subject) {
            $scheme = AssessmentScheme::query()->create([
                'assessment_period_id' => $period->getKey(),
                'assessment_subject_id' => $subject->getKey(),
                'assessment_period_rombel_id' => null,
                'name' => 'Skema '.$subject->code,
                'rounding_precision' => 0,
                'minimum_score' => 0,
                'maximum_score' => 100,
                'is_active' => true,
            ]);
            AssessmentComponent::query()->create([
                'assessment_scheme_id' => $scheme->getKey(),
                'code' => 'UTAMA',
                'name' => 'Nilai Utama',
                'weight' => 100,
                'maximum_score' => 100,
                'is_required' => true,
                'sort_order' => 1,
                'score_source' => ScoreSource::MANUAL,
            ]);
        }

        return app(CreateAssessmentPeriodSnapshotAction::class)
            ->execute($context['curriculum_user'], $period);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function createOpenedPeriodWithTwoComponents(array $context): AssessmentPeriod
    {
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $context['year']->getKey(),
            'assessment_semester_id' => $context['semester']->getKey(),
            'code' => 'asts-description-regression',
            'name' => 'ASTS Deskripsi',
            'type' => AssessmentType::ASTS,
            'status' => AssessmentPeriodStatus::DRAFT,
            'entry_start_at' => now()->subHour(),
            'entry_end_at' => now()->addHour(),
            'report_date' => now()->toDateString(),
            'settings' => ['rombel_ids' => [$context['rombel']->getKey()]],
            'created_by' => $context['curriculum_user']->getKey(),
        ]);
        $scheme = AssessmentScheme::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_subject_id' => $context['subject']->getKey(),
            'assessment_period_rombel_id' => null,
            'name' => 'Skema Deskripsi',
            'rounding_precision' => 0,
            'minimum_score' => 0,
            'maximum_score' => 100,
            'is_active' => true,
        ]);

        foreach ([
            ['code' => 'SATU', 'name' => 'Komponen Pertama', 'domain' => 'Domain Pertama'],
            ['code' => 'DUA', 'name' => 'Komponen Kedua', 'domain' => 'Domain Kedua'],
        ] as $index => $component) {
            AssessmentComponent::query()->create([
                'assessment_scheme_id' => $scheme->getKey(),
                ...$component,
                'weight' => 50,
                'maximum_score' => 100,
                'is_required' => true,
                'sort_order' => $index + 1,
                'score_source' => ScoreSource::MANUAL,
            ]);
        }

        return app(CreateAssessmentPeriodSnapshotAction::class)
            ->execute($context['curriculum_user'], $period);
    }

    private function expectAuthorizationFailure(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Operasi tanpa izin seharusnya ditolak.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }
}
