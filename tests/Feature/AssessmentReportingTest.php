<?php

namespace Tests\Feature;

use App\Actions\Assessment\PublishAssessmentPeriodAction;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\ReportGenerationStatus;
use App\Filament\Pages\Assessment\AstsReports;
use App\Jobs\Assessment\GenerateClassReportPipeline;
use App\Jobs\Assessment\GenerateClassReports;
use App\Jobs\Assessment\GenerateClassReportsJob;
use App\Jobs\Assessment\GenerateStudentReport;
use App\Jobs\Assessment\GenerateStudentReportJob;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use App\Models\Assessment\Semester;
use App\Models\Assessment\StudentSubjectResult;
use App\Models\Assessment\Subject;
use App\Models\User;
use App\Support\Assessment\Reporting\AssessmentReportQueueGate;
use App\Support\Assessment\Reporting\AssessmentReportRenderer;
use App\Support\Assessment\Reporting\AssessmentReportShareService;
use App\Support\Assessment\Reporting\AssessmentReportStorage;
use App\Support\Assessment\Reporting\AssessmentReportWatermark;
use App\Support\Assessment\Reporting\CreateReportSnapshotsAction;
use App\Support\Assessment\Reporting\RetryReportGenerationAction;
use App\Support\Assessment\Reporting\ScheduleReportClassesAction;
use App\Support\Assessment\Reporting\StopAssessmentReportQueueAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Tests\TestCase;

class AssessmentReportingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['assessment.enabled' => true]);
        $userMigration = require database_path('migrations/0001_01_01_000000_create_users_table.php');
        $userMigration->up();
        $permissionMigration = require database_path('migrations/2026_01_12_111708_create_permission_tables.php');
        $permissionMigration->up();
        $migration = require database_path('migrations/2026_07_31_080000_create_assessment_foundation_tables.php');
        $migration->up();
        $pipelineMigration = require database_path('migrations/2026_07_31_190000_add_assessment_report_generation_runs.php');
        $pipelineMigration->up();
        DB::table('users')->insert([
            'id' => 99,
            'name' => 'Kurikulum Test',
            'username' => 'kurikulum-test',
            'email' => 'kurikulum@example.test',
            'password' => bcrypt('secret-test-password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Role::findOrCreate('admin', 'web');
        User::query()->findOrFail(99)->assignRole('admin');

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

    public function test_share_default_expiry_uses_only_supported_day_choices(): void
    {
        config(['assessment.share_links.default_expiry_hours' => 72]);
        $this->assertSame(3, AssessmentReportShareService::defaultExpiryDays());

        config(['assessment.share_links.default_expiry_hours' => 48]);
        $this->assertSame(1, AssessmentReportShareService::defaultExpiryDays());
    }

    public function test_student_pdf_is_generated_from_immutable_snapshot_on_private_disk(): void
    {
        Storage::fake('local');
        [$period, $rombel, $students, $template] = $this->reportingFoundation();
        $snapshot = $this->snapshot($period, $students[0], $template, 1);
        $job = new GenerateStudentReportJob($snapshot->getKey());

        $job->handle(app(AssessmentReportRenderer::class), app(AssessmentReportStorage::class));
        $snapshot->refresh();

        $this->assertSame('completed', $snapshot->generation_status->value);
        $this->assertNotNull($snapshot->generated_at);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $snapshot->checksum);
        Storage::disk('local')->assertExists($snapshot->pdf_path);
        $this->assertStringStartsWith(
            '%PDF-',
            Storage::disk('local')->get($snapshot->pdf_path),
        );
        $this->assertSame(
            hash('sha256', Storage::disk('local')->get($snapshot->pdf_path)),
            $snapshot->checksum,
        );

        $firstChecksum = $snapshot->checksum;
        $job->handle(app(AssessmentReportRenderer::class), app(AssessmentReportStorage::class));

        $this->assertSame($firstChecksum, $snapshot->fresh()->checksum);
        $this->assertDatabaseCount('assessment_report_snapshots', 1);
        $this->assertDatabaseCount('assessment_audit_logs', 1);
    }

    public function test_class_pdf_requires_and_renders_every_active_student_snapshot(): void
    {
        Storage::fake('local');
        [$period, $rombel, $students, $template] = $this->reportingFoundation(studentCount: 2);
        $this->snapshot($period, $students[0], $template, 1);
        $this->snapshot($period, $students[1], $template, 1);
        $artifact = ClassReportArtifact::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 1,
            'generation_status' => 'pending',
            'queued_at' => now(),
            'generated_by' => 99,
        ]);

        (new GenerateClassReportsJob($artifact->getKey()))
            ->handle(app(AssessmentReportRenderer::class), app(AssessmentReportStorage::class));

        $artifact->refresh();
        $this->assertSame('completed', $artifact->generation_status->value);
        Storage::disk('local')->assertExists($artifact->pdf_path);
        $this->assertSame(
            hash('sha256', Storage::disk('local')->get($artifact->pdf_path)),
            $artifact->checksum,
        );
    }

    public function test_report_paths_are_isolated_between_templates_with_the_same_revision(): void
    {
        [$period, $rombel, $students, $firstTemplate] = $this->reportingFoundation();
        $secondTemplate = ReportTemplate::query()->create([
            'code' => 'ASTS-ALTERNATIVE',
            'type' => AssessmentType::ASTS,
            'name' => 'Template ASTS Alternatif',
            'version' => 1,
            'view_path' => 'assessment.reports.asts',
            'settings' => ['principal_name' => 'Kepala Sekolah'],
            'is_active' => true,
        ]);
        $firstSnapshot = $this->snapshot($period, $students[0], $firstTemplate, 1);
        $secondSnapshot = $this->snapshot($period, $students[0], $secondTemplate, 1);
        $firstArtifact = ClassReportArtifact::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_report_template_id' => $firstTemplate->getKey(),
            'revision' => 1,
            'generation_status' => 'pending',
        ]);
        $secondArtifact = ClassReportArtifact::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_report_template_id' => $secondTemplate->getKey(),
            'revision' => 1,
            'generation_status' => 'pending',
        ]);
        $storage = app(AssessmentReportStorage::class);

        $this->assertNotSame(
            $storage->individualPath($firstSnapshot),
            $storage->individualPath($secondSnapshot),
        );
        $this->assertNotSame(
            $storage->classPath($firstArtifact),
            $storage->classPath($secondArtifact),
        );
        $this->assertStringContainsString(
            '/template-'.$firstTemplate->getKey().'/',
            $storage->individualPath($firstSnapshot),
        );
        $this->assertStringContainsString(
            '/template-'.$secondTemplate->getKey().'/',
            $storage->classPath($secondArtifact),
        );
    }

    public function test_report_paths_remain_unique_when_student_identifiers_or_class_labels_match(): void
    {
        [$period, $firstRombel, $students, $template] = $this->reportingFoundation(studentCount: 2);
        $students[1]->forceFill([
            'nis_snapshot' => $students[0]->nis_snapshot,
            'nisn_snapshot' => $students[0]->nisn_snapshot,
            'rombel_name_snapshot' => $students[0]->rombel_name_snapshot,
        ])->save();
        $secondRombel = AssessmentPeriodRombel::query()->create([
            'assessment_period_id' => $period->getKey(),
            'source_rombel_id' => 102,
            'rombel_name_snapshot' => $firstRombel->rombel_name_snapshot,
            'grade_level' => 'XI',
            'is_active' => true,
        ]);
        $firstSnapshot = $this->snapshot($period, $students[0], $template, 1);
        $secondSnapshot = $this->snapshot($period, $students[1], $template, 1);
        $firstArtifact = ClassReportArtifact::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $firstRombel->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 1,
            'generation_status' => 'pending',
        ]);
        $secondArtifact = ClassReportArtifact::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $secondRombel->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 1,
            'generation_status' => 'pending',
        ]);
        $storage = app(AssessmentReportStorage::class);

        $this->assertNotSame(
            $storage->individualPath($firstSnapshot),
            $storage->individualPath($secondSnapshot),
        );
        $this->assertNotSame(
            $storage->classPath($firstArtifact),
            $storage->classPath($secondArtifact),
        );
        $this->assertStringContainsString(
            '/student-'.$students[0]->getKey().'/',
            $storage->individualPath($firstSnapshot),
        );
        $this->assertStringContainsString(
            '/class-'.$secondRombel->getKey().'/',
            $storage->classPath($secondArtifact),
        );
    }

    public function test_parent_and_canonical_jobs_share_locks_and_finish_before_database_retry(): void
    {
        $legacyStudentJob = new GenerateStudentReport(10);
        $studentJob = new GenerateStudentReportJob(10);
        $legacyClassJob = new GenerateClassReports(20);
        $classJob = new GenerateClassReportsJob(20);
        $legacyStudentLock = $legacyStudentJob->middleware()[0];
        $studentLock = $studentJob->middleware()[0];
        $legacyClassLock = $legacyClassJob->middleware()[0];
        $classLock = $classJob->middleware()[0];

        $this->assertTrue($studentLock->shareKey);
        $this->assertTrue($classLock->shareKey);
        $this->assertSame(
            $legacyStudentLock->getLockKey($legacyStudentJob),
            $studentLock->getLockKey($studentJob),
        );
        $this->assertSame(
            $legacyClassLock->getLockKey($legacyClassJob),
            $classLock->getLockKey($classJob),
        );
        $this->assertLessThan(180, $studentJob->timeout);
        $this->assertLessThan(180, $classJob->timeout);
        $this->assertGreaterThan($studentJob->timeout, $studentLock->expiresAfter);
        $this->assertGreaterThan($classJob->timeout, $classLock->expiresAfter);
        $this->assertLessThan(180, $studentLock->expiresAfter);
        $this->assertLessThan(180, $classLock->expiresAfter);
    }

    public function test_report_storage_rejects_a_public_or_non_local_disk(): void
    {
        config(['assessment.reports.disk' => 'public']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('wajib "local"');

        app(AssessmentReportStorage::class)->disk();
    }

    public function test_report_snapshot_payload_cannot_be_changed_after_creation(): void
    {
        [$period, , $students, $template] = $this->reportingFoundation();
        $snapshot = $this->snapshot($period, $students[0], $template, 1);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('immutable');

        $snapshot->forceFill([
            'snapshot_data' => ['tampered' => true],
        ])->save();
    }

    public function test_class_report_identity_cannot_be_changed_after_creation(): void
    {
        [$period, $rombel, , $template] = $this->reportingFoundation();
        $artifact = ClassReportArtifact::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 1,
            'generation_status' => 'pending',
            'generated_by' => 99,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('immutable');

        $artifact->forceFill(['revision' => 2])->save();
    }

    public function test_temporary_share_token_is_hashed_expiring_revocable_and_audited(): void
    {
        Storage::fake('local');
        [$period, , $students, $template] = $this->reportingFoundation(
            status: AssessmentPeriodStatus::PUBLISHED,
        );
        $snapshot = $this->snapshot($period, $students[0], $template, 1);
        (new GenerateStudentReportJob($snapshot->getKey()))
            ->handle(app(AssessmentReportRenderer::class), app(AssessmentReportStorage::class));
        $snapshot->refresh();
        $this->markPublishedSet($period, $template, 1);
        $shares = app(AssessmentReportShareService::class);

        $issued = $shares->issue($snapshot, createdBy: 99, expiryDays: 1);

        $this->assertSame(43, strlen($issued['token']));
        $this->assertNotSame($issued['token'], $issued['link']->getRawOriginal('token_hash'));
        $this->assertSame(hash('sha256', $issued['token']), $issued['link']->getRawOriginal('token_hash'));
        $resolved = $shares->resolve($issued['token']);
        $resolved = $shares->recordDownload($resolved, '127.0.0.1', 'Assessment test');
        $this->assertSame(1, $resolved->download_count);
        $this->assertNotNull($resolved->last_accessed_at);
        $this->assertDatabaseHas('assessment_audit_logs', [
            'event' => 'report_downloaded_from_share_link',
            'subject_id' => $issued['link']->getKey(),
        ]);

        $shares->revoke($issued['link']->fresh(), actorId: 99, reason: 'Rapor direvisi.');

        $this->expectException(GoneHttpException::class);
        $shares->resolve($issued['token']);
    }

    public function test_share_link_rejects_an_old_or_non_published_revision(): void
    {
        Storage::fake('local');
        [$period, , $students, $template] = $this->reportingFoundation(
            status: AssessmentPeriodStatus::PUBLISHED,
        );
        $oldSnapshot = $this->snapshot($period, $students[0], $template, 1);
        $latestSnapshot = $this->snapshot($period, $students[0], $template, 2);
        (new GenerateStudentReportJob($oldSnapshot->getKey()))
            ->handle(app(AssessmentReportRenderer::class), app(AssessmentReportStorage::class));
        (new GenerateStudentReportJob($latestSnapshot->getKey()))
            ->handle(app(AssessmentReportRenderer::class), app(AssessmentReportStorage::class));
        $this->markPublishedSet($period, $template, 2);

        $this->expectException(GoneHttpException::class);
        $this->expectExceptionMessage('revisi aktif');

        app(AssessmentReportShareService::class)->issue(
            $oldSnapshot->fresh(),
            createdBy: 99,
            expiryDays: 1,
        );
    }

    public function test_retry_is_transactional_failed_only_latest_and_audited(): void
    {
        Queue::fake();
        [$period, $rombel, $students, $template] = $this->reportingFoundation();
        $snapshot = $this->snapshot($period, $students[0], $template, 1);
        $snapshot->forceFill([
            'generation_status' => ReportGenerationStatus::FAILED,
            'error_message' => 'Dompdf gagal.',
        ])->save();
        $artifact = ClassReportArtifact::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 1,
            'generation_status' => ReportGenerationStatus::FAILED,
            'error_message' => 'PDF kelas gagal.',
            'generated_by' => 99,
        ]);
        $actor = User::query()->findOrFail(99);
        $retry = app(RetryReportGenerationAction::class);

        $retriedSnapshot = $retry->retrySnapshot($actor, $snapshot);
        $retriedArtifact = $retry->retryClass($actor, $artifact);

        $this->assertSame(ReportGenerationStatus::PENDING, $retriedSnapshot->generation_status);
        $this->assertSame(ReportGenerationStatus::PENDING, $retriedArtifact->generation_status);
        $this->assertNull($retriedSnapshot->error_message);
        $this->assertNotNull($retriedArtifact->queued_at);
        Queue::assertPushed(GenerateStudentReportJob::class, 1);
        Queue::assertPushed(GenerateClassReportPipeline::class, 1);
        $this->assertDatabaseHas('assessment_audit_logs', [
            'event' => 'student_report_retry_requested',
            'subject_id' => $snapshot->getKey(),
        ]);
        $this->assertDatabaseHas('assessment_audit_logs', [
            'event' => 'class_report_retry_requested',
            'subject_id' => $artifact->getKey(),
        ]);
    }

    public function test_retry_rejects_completed_or_historical_revision(): void
    {
        Queue::fake();
        [$period, , $students, $template] = $this->reportingFoundation();
        $completed = $this->snapshot($period, $students[0], $template, 1);
        $completed->forceFill(['generation_status' => ReportGenerationStatus::COMPLETED])->save();
        $this->snapshot($period, $students[0], $template, 2);

        try {
            app(RetryReportGenerationAction::class)->retrySnapshot(
                User::query()->findOrFail(99),
                $completed,
            );
            $this->fail('Revisi completed/historis seharusnya tidak dapat dijadwalkan ulang.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('report', $exception->errors());
        }

        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('assessment_audit_logs', [
            'event' => 'student_report_retry_requested',
            'subject_id' => $completed->getKey(),
        ]);
    }

    public function test_published_regeneration_requires_publish_permission_same_template_and_relocks_period(): void
    {
        Storage::fake('local');
        Queue::fake();
        [$period, , $students, $template] = $this->reportingFoundation(
            status: AssessmentPeriodStatus::PUBLISHED,
        );
        $snapshot = $this->snapshot($period, $students[0], $template, 1);
        (new GenerateStudentReportJob($snapshot->getKey()))
            ->handle(app(AssessmentReportRenderer::class), app(AssessmentReportStorage::class));
        $this->markPublishedSet($period, $template, 1);
        $issued = app(AssessmentReportShareService::class)->issue(
            $snapshot->fresh(),
            createdBy: 99,
            expiryDays: 1,
        );
        $generateOnly = User::query()->create([
            'name' => 'Generator Rapor',
            'username' => 'generator-rapor',
            'email' => 'generator-rapor@example.test',
            'password' => bcrypt('secret-test-password'),
        ]);
        Permission::findOrCreate('penilaian.view', 'web');
        Permission::findOrCreate('penilaian.report.generate', 'web');
        $generateOnly->givePermissionTo([
            'penilaian.view',
            'penilaian.report.generate',
        ]);
        $action = app(CreateReportSnapshotsAction::class);

        try {
            $action->execute(
                $period->fresh(),
                $template,
                (int) $generateOnly->getKey(),
                regenerate: true,
                reason: 'Perbaikan tata letak rapor.',
            );
            $this->fail('Generator tanpa permission publish seharusnya ditolak.');
        } catch (AuthorizationException) {
            $this->assertSame(AssessmentPeriodStatus::PUBLISHED, $period->fresh()->status);
        }

        $otherTemplate = ReportTemplate::query()->create([
            'code' => 'ASTS-OTHER',
            'type' => AssessmentType::ASTS,
            'name' => 'Template Lain',
            'version' => 1,
            'view_path' => 'assessment.reports.asts',
            'is_active' => true,
        ]);

        try {
            $action->execute(
                $period->fresh(),
                $otherTemplate,
                generatedBy: 99,
                regenerate: true,
                reason: 'Mencoba mengganti template langsung.',
            );
            $this->fail('Template berbeda pada periode terbit seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template', $exception->errors());
        }

        $revised = $action->execute(
            $period->fresh(),
            $template,
            generatedBy: 99,
            regenerate: true,
            reason: 'Perbaikan tata letak rapor.',
        );

        $this->assertSame(2, (int) $revised->first()->revision);
        $this->assertSame(AssessmentPeriodStatus::LOCKED, $period->fresh()->status);
        $this->assertSame(2, (int) data_get($period->fresh()->settings, '_reporting.pending.revision'));
        $this->assertNotNull($issued['link']->fresh()->revoked_at);
        $this->assertDatabaseHas('assessment_audit_logs', [
            'event' => 'published_report_revision_started',
            'subject_id' => $period->getKey(),
        ]);

        try {
            $action->execute(
                $period->fresh(),
                $template,
                generatedBy: 99,
                regenerate: true,
                reason: 'Mencoba membuat revisi berikutnya sebelum revisi aktif selesai.',
            );
            $this->fail('Revisi baru seharusnya ditolak selama revisi published masih diproses.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reports', $exception->errors());
        }
    }

    public function test_publish_rejects_missing_or_corrupt_student_and_class_files(): void
    {
        Storage::fake('local');
        [$period, $rombel, $students, $template] = $this->reportingFoundation();
        $snapshot = $this->snapshot($period, $students[0], $template, 1);
        $snapshot->forceFill([
            'generation_status' => ReportGenerationStatus::COMPLETED,
            'pdf_path' => 'assessment-reports/missing-student.pdf',
            'checksum' => str_repeat('a', 64),
        ])->save();
        $artifact = ClassReportArtifact::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 1,
            'generation_status' => ReportGenerationStatus::COMPLETED,
            'pdf_path' => 'assessment-reports/missing-class.pdf',
            'checksum' => str_repeat('b', 64),
            'generated_by' => 99,
        ]);
        $action = app(PublishAssessmentPeriodAction::class);
        $actor = User::query()->findOrFail(99);

        try {
            $action->execute($actor, $period);
            $this->fail('Publish seharusnya ditolak ketika PDF siswa tidak valid.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('checksum', $exception->errors()['reports'][0]);
        }

        $studentPdf = "%PDF-1.4\nstudent\n%%EOF";
        Storage::disk('local')->put($snapshot->pdf_path, $studentPdf);
        $snapshot->forceFill(['checksum' => hash('sha256', $studentPdf)])->save();

        try {
            $action->execute($actor, $period->fresh());
            $this->fail('Publish seharusnya ditolak ketika PDF kelas tidak valid.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('PDF gabungan kelas', $exception->errors()['reports'][0]);
        }

        $this->assertSame(AssessmentPeriodStatus::LOCKED, $period->fresh()->status);
        $this->assertNull(data_get($period->fresh()->settings, '_reporting.published'));
        $this->assertSame(ReportGenerationStatus::COMPLETED, $artifact->fresh()->generation_status);
    }

    public function test_snapshot_action_is_idempotent_and_keeps_student_and_result_values_frozen(): void
    {
        Storage::fake('public');
        Queue::fake();
        [$period, $rombel, $students, $template] = $this->reportingFoundation();
        $subject = Subject::query()->create([
            'code' => 'MAT',
            'name' => 'Matematika',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $assignment = AssessmentPeriodAssignment::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'teacher_id' => 41,
            'assessment_subject_id' => $subject->getKey(),
            'teacher_name_snapshot' => 'Guru Matematika',
            'subject_name_snapshot' => 'Matematika',
            'rombel_name_snapshot' => $rombel->rombel_name_snapshot,
            'status' => 'locked',
            'lock_version' => 1,
        ]);
        StudentSubjectResult::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_student_id' => $students[0]->getKey(),
            'assessment_period_assignment_id' => $assignment->getKey(),
            'final_score' => 88.50,
            'predicate' => 'B',
            'description' => 'Menguasai operasi numerik.',
            'calculation_detail' => ['formula' => 'v1'],
            'formula_version' => 'v1',
            'calculated_at' => now(),
        ]);
        $action = app(CreateReportSnapshotsAction::class);

        $first = $action->execute($period, $template, generatedBy: 99);
        $second = $action->execute($period, $template, generatedBy: 99);
        $students[0]->forceFill(['student_name_snapshot' => 'Nama Setelah Snapshot'])->save();
        $template->forceFill([
            'settings' => ['principal_name' => 'Kepala Sekolah Setelah Snapshot'],
        ])->save();
        $stored = $first->first()->fresh();

        $this->assertCount(1, $first);
        $this->assertSame($first->modelKeys(), $second->modelKeys());
        $this->assertSame('Siswa 1', data_get($stored->snapshot_data, 'student.name'));
        $this->assertSame('88.50', data_get($stored->snapshot_data, 'subjects.0.final_score'));
        $this->assertSame('Matematika', data_get($stored->snapshot_data, 'subjects.0.name'));
        $this->assertSame(
            'Kepala Sekolah',
            data_get($stored->snapshot_data, 'template.settings.principal_name'),
        );
        $this->assertDatabaseCount('assessment_report_snapshots', 1);
        $this->assertDatabaseCount('assessment_class_report_artifacts', 1);
        $this->assertDatabaseCount('assessment_report_generation_runs', 1);
        $this->assertSame('not_scheduled', $stored->generation_status->value);
        Queue::assertNothingPushed();
    }

    public function test_promotion_status_snapshot_follows_period_configuration(): void
    {
        Queue::fake();
        [$period, $rombel, $students, $template] = $this->reportingFoundation();
        DB::table('assessment_homeroom_reports')->insert([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_student_id' => $students[0]->getKey(),
            'sick_days' => 0,
            'permission_days' => 0,
            'absent_days' => 0,
            'extracurricular_data' => null,
            'achievement_data' => null,
            'homeroom_note' => null,
            'promotion_status' => 'Naik Kelas',
            'updated_by' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $action = app(CreateReportSnapshotsAction::class);

        $astsDefault = $action->execute($period, $template, generatedBy: 99)->firstOrFail();
        $this->assertNull(data_get($astsDefault->snapshot_data, 'homeroom.promotion_status'));

        $period->forceFill([
            'settings' => ['collect_promotion_status' => true],
        ])->save();
        $configured = $action->execute(
            $period->fresh(),
            $template,
            generatedBy: 99,
            regenerate: true,
            reason: 'Mengaktifkan status akhir semester pada periode ini.',
        )->firstOrFail();

        $this->assertSame('Naik Kelas', data_get($configured->snapshot_data, 'homeroom.promotion_status'));
    }

    public function test_class_pipeline_schedules_one_job_instead_of_one_job_per_student(): void
    {
        Queue::fake();
        [$period, $rombel, , $template] = $this->reportingFoundation(studentCount: 5);
        $snapshots = app(CreateReportSnapshotsAction::class)->execute($period, $template, generatedBy: 99);

        $run = app(ScheduleReportClassesAction::class)->execute(
            User::query()->findOrFail(99),
            $period,
            $template,
            [$rombel->getKey()],
        );

        $this->assertCount(5, $snapshots);
        $this->assertSame('running', $run->status->value);
        $this->assertSame(5, $run->total_students);
        $this->assertSame(1, $run->total_classes);
        $this->assertSame(
            5,
            ReportSnapshot::query()->where('generation_status', 'pending')->count(),
        );
        Queue::assertPushed(GenerateClassReportPipeline::class, 1);
        Queue::assertNotPushed(GenerateStudentReportJob::class);
    }

    public function test_class_pipeline_generates_individual_and_combined_pdf_in_one_bounded_run(): void
    {
        Storage::fake('local');
        Queue::fake();
        config([
            'assessment.reports.pipeline.students_per_job' => 3,
            'assessment.reports.pipeline.max_seconds' => 40,
        ]);
        [$period, $rombel, , $template] = $this->reportingFoundation(studentCount: 2);
        app(CreateReportSnapshotsAction::class)->execute($period, $template, generatedBy: 99);
        app(ScheduleReportClassesAction::class)->execute(
            User::query()->findOrFail(99),
            $period,
            $template,
            [$rombel->getKey()],
        );
        $artifact = ClassReportArtifact::query()->firstOrFail();

        (new GenerateClassReportPipeline($artifact->getKey()))->handle(
            app(AssessmentReportRenderer::class),
            app(AssessmentReportStorage::class),
        );

        $this->assertSame(2, ReportSnapshot::query()->where('generation_status', 'completed')->count());
        $this->assertSame('completed', $artifact->fresh()->generation_status->value);
        $this->assertSame('completed', $artifact->fresh()->generationRun->status->value);
        Storage::disk('local')->assertExists($artifact->fresh()->pdf_path);
    }

    public function test_stop_all_removes_only_assessment_report_jobs_and_preserves_completed_pdf(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);
        [$period, $rombel, $students, $template] = $this->reportingFoundation(studentCount: 2);
        app(CreateReportSnapshotsAction::class)->execute($period, $template, generatedBy: 99);
        app(ScheduleReportClassesAction::class)->execute(
            User::query()->findOrFail(99),
            $period,
            $template,
            [$rombel->getKey()],
        );
        $completed = ReportSnapshot::query()->firstOrFail();
        $completed->forceFill([
            'generation_status' => 'completed',
            'pdf_path' => 'assessment-reports/completed.pdf',
            'checksum' => str_repeat('a', 64),
        ])->save();

        foreach (['assessment-reports', 'default', 'literacy-analysis'] as $queue) {
            DB::table('jobs')->insert([
                'queue' => $queue,
                'payload' => '{}',
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ]);
        }

        $result = app(StopAssessmentReportQueueAction::class)->execute(
            User::query()->findOrFail(99),
            'Menghentikan antrean lama untuk beralih ke pipeline kelas.',
        );

        $this->assertSame(1, $result['jobs']);
        $this->assertDatabaseMissing('jobs', ['queue' => 'assessment-reports']);
        $this->assertDatabaseHas('jobs', ['queue' => 'default']);
        $this->assertDatabaseHas('jobs', ['queue' => 'literacy-analysis']);
        $this->assertSame('completed', $completed->fresh()->generation_status->value);
        $this->assertSame(
            1,
            ReportSnapshot::query()->where('generation_status', 'cancelled')->count(),
        );

        $cancelledSnapshot = ReportSnapshot::query()
            ->where('generation_status', 'cancelled')
            ->firstOrFail();
        $cancelledArtifact = ClassReportArtifact::query()->firstOrFail();
        (new GenerateStudentReportJob($cancelledSnapshot->getKey()))
            ->failed(new RuntimeException('Worker lama selesai setelah penghentian.'));
        (new GenerateClassReportsJob($cancelledArtifact->getKey()))
            ->failed(new RuntimeException('Worker lama selesai setelah penghentian.'));

        $this->assertSame('cancelled', $cancelledSnapshot->fresh()->generation_status->value);
        $this->assertSame('cancelled', $cancelledArtifact->fresh()->generation_status->value);
    }

    public function test_report_worker_waits_for_higher_priority_queues(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'assessment-reports',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
        $gate = app(AssessmentReportQueueGate::class);

        $this->assertTrue($gate->shouldRun());
        $this->assertSame('ready', $gate->status()['reason']);

        config(['literacy.similarity_queue' => 'literacy-priority-custom']);
        DB::table('jobs')->insert([
            'queue' => 'literacy-priority-custom',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->assertFalse($gate->shouldRun());
        $this->assertSame('priority_queue_not_empty', $gate->status()['reason']);
    }

    public function test_shared_hosting_scheduler_is_bounded_and_uses_configured_literacy_queue(): void
    {
        $consoleRoutes = File::get(base_path('routes/console.php'));

        $this->assertStringContainsString(
            "config('literacy.similarity_queue', 'literacy-analysis')",
            $consoleRoutes,
        );
        $this->assertStringContainsString("'--max-jobs' => 1", $consoleRoutes);
        $this->assertGreaterThanOrEqual(2, substr_count($consoleRoutes, '->withoutOverlapping(10)'));
    }

    public function test_all_report_download_actions_return_not_found_when_module_is_disabled(): void
    {
        config(['assessment.enabled' => false]);
        [$period, $rombel, $students, $template] = $this->reportingFoundation();
        $snapshot = $this->snapshot($period, $students[0], $template, 1);
        $artifact = ClassReportArtifact::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 1,
            'generation_status' => 'pending',
            'queued_at' => now(),
            'generated_by' => 99,
        ]);
        $this->actingAs(User::query()->findOrFail(99));

        $this->get(route('assessment.reports.snapshot.download', $snapshot))
            ->assertNotFound();
        $this->get(route('assessment.reports.class.download', $artifact))
            ->assertNotFound();
        $this->get(route('assessment.reports.shared.download', str_repeat('a', 43)))
            ->assertNotFound();
        $this->assertDatabaseCount('assessment_audit_logs', 0);
    }

    public function test_report_page_and_private_preview_render_with_card_workflow(): void
    {
        Storage::fake('local');
        [$period, , $students, $template] = $this->reportingFoundation();
        $snapshot = $this->snapshot($period, $students[0], $template, 1);
        $this->actingAs(User::query()->findOrFail(99));

        $this->get(AstsReports::getUrl([
            'period' => $period->getKey(),
            'template' => $template->getKey(),
        ]))
            ->assertOk()
            ->assertSee('Pipeline PDF ringan')
            ->assertSee('Hentikan Semua Antrean PDF')
            ->assertSeeHtml('assessment-report-card');

        $previewResponse = $this->get(route('assessment.reports.preview', $snapshot));
        $previewResponse
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', (string) $previewResponse->headers->get('Cache-Control'));
    }

    public function test_watermark_is_frozen_as_private_data_without_leaking_path(): void
    {
        Storage::fake('local');
        $path = 'assessment-report-template-assets/optimized/watermark.png';
        Storage::disk('local')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        ));

        $settings = app(AssessmentReportWatermark::class)->freezeSettings([
            'watermark_enabled' => true,
            'watermark_path' => $path,
            'watermark_opacity' => 10,
        ]);

        $this->assertArrayNotHasKey('watermark_path', $settings);
        $this->assertStringStartsWith('data:image/png;base64,', $settings['watermark_data_uri']);
        $this->assertSame(10, $settings['watermark_opacity']);
    }

    public function test_public_share_route_uses_configured_rate_limit(): void
    {
        $route = Route::getRoutes()->getByName('assessment.reports.shared.download');
        $expected = 'throttle:'.max(
            1,
            (int) config('assessment.share_links.rate_limit_per_minute', 30),
        ).',1';

        $this->assertNotNull($route);
        $this->assertContains($expected, $route->gatherMiddleware());
    }

    public function test_failed_report_retry_buttons_are_permission_guarded(): void
    {
        $blade = File::get(resource_path('views/filament/pages/assessment/reports.blade.php'));

        $this->assertStringContainsString(
            'wire:click="retryClass',
            $blade,
        );
        $this->assertStringContainsString(
            'wire:click="retrySnapshot',
            $blade,
        );
        $this->assertGreaterThanOrEqual(3, substr_count($blade, '$this->canGenerateReports()'));
    }

    /**
     * @return array{AssessmentPeriod,AssessmentPeriodRombel,array<int,AssessmentPeriodStudent>,ReportTemplate}
     */
    private function reportingFoundation(
        int $studentCount = 1,
        AssessmentPeriodStatus $status = AssessmentPeriodStatus::LOCKED,
    ): array {
        $year = AcademicYear::query()->create([
            'code' => '2526',
            'name' => '2025/2026',
            'is_active' => true,
        ]);
        $semester = Semester::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'code' => 'GANJIL',
            'name' => 'Ganjil',
            'is_active' => true,
        ]);
        $period = AssessmentPeriod::query()->create([
            'assessment_academic_year_id' => $year->getKey(),
            'assessment_semester_id' => $semester->getKey(),
            'code' => 'ASTS-2526-GANJIL',
            'name' => 'ASTS 2025/2026 Ganjil',
            'type' => AssessmentType::ASTS,
            'status' => $status,
            'report_date' => '2026-10-10',
        ]);
        $rombel = AssessmentPeriodRombel::query()->create([
            'assessment_period_id' => $period->getKey(),
            'source_rombel_id' => 101,
            'rombel_name_snapshot' => 'XI 1',
            'grade_level' => 'XI',
            'is_active' => true,
        ]);
        $students = [];

        foreach (range(1, $studentCount) as $index) {
            $students[] = AssessmentPeriodStudent::query()->create([
                'assessment_period_id' => $period->getKey(),
                'assessment_period_rombel_id' => $rombel->getKey(),
                'student_id' => 200 + $index,
                'nis_snapshot' => 'NIS'.$index,
                'nisn_snapshot' => 'NISN'.$index,
                'student_name_snapshot' => 'Siswa '.$index,
                'gender_snapshot' => 'L',
                'rombel_name_snapshot' => 'XI 1',
                'is_active' => true,
            ]);
        }

        $template = ReportTemplate::query()->create([
            'code' => 'ASTS-STANDARD',
            'type' => AssessmentType::ASTS,
            'name' => 'Template ASTS Standar',
            'version' => 1,
            'view_path' => 'assessment.reports.asts',
            'settings' => [
                'principal_name' => 'Kepala Sekolah',
                'signature_place' => 'Bogor',
            ],
            'is_active' => true,
        ]);

        return [$period, $rombel, $students, $template];
    }

    private function snapshot(
        AssessmentPeriod $period,
        AssessmentPeriodStudent $student,
        ReportTemplate $template,
        int $revision,
    ): ReportSnapshot {
        return ReportSnapshot::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_student_id' => $student->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => $revision,
            'template_version' => $template->version,
            'snapshot_data' => [
                'meta' => ['revision' => $revision],
                'school' => ['name' => 'SMA AFBS'],
                'period' => [
                    'code' => $period->code,
                    'type' => 'asts',
                    'academic_year' => '2025/2026',
                    'semester' => 'Ganjil',
                    'report_date' => '10-10-2026',
                ],
                'student' => [
                    'name' => $student->student_name_snapshot,
                    'nis' => $student->nis_snapshot,
                    'nisn' => $student->nisn_snapshot,
                    'class_name' => $student->rombel_name_snapshot,
                ],
                'subjects' => [[
                    'name' => 'Matematika',
                    'final_score' => '88.50',
                    'predicate' => 'B',
                    'description' => 'Baik.',
                ]],
                'homeroom' => [
                    'sick_days' => 0,
                    'permission_days' => 0,
                    'absent_days' => 0,
                ],
                'signatures' => [],
                'template' => [
                    'version' => $template->version,
                    'settings' => $template->settings,
                ],
            ],
            'generation_status' => 'pending',
            'generated_by' => 99,
        ]);
    }

    private function markPublishedSet(
        AssessmentPeriod $period,
        ReportTemplate $template,
        int $revision,
    ): void {
        $settings = is_array($period->settings) ? $period->settings : [];
        data_set($settings, '_reporting.published', [
            'template_id' => (int) $template->getKey(),
            'revision' => $revision,
            'published_at' => now()->toIso8601String(),
            'published_by' => 99,
        ]);
        $period->forceFill([
            'status' => AssessmentPeriodStatus::PUBLISHED,
            'settings' => $settings,
        ])->save();
    }
}
