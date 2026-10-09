<?php

namespace Tests\Feature;

use App\Actions\Assessment\CancelOpenReportRevisionsAction;
use App\Actions\Assessment\PublishAssessmentPeriodAction;
use App\Actions\Assessment\SetPrimaryReportTemplateAction;
use App\Console\Commands\InstallAssessmentDefaults;
use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Enums\Assessment\AssessmentType;
use App\Enums\Assessment\ReportGenerationStatus;
use App\Filament\Pages\Assessment\AstsReports;
use App\Filament\Resources\AssessmentReportTemplateResource;
use App\Filament\Resources\AssessmentReportTemplateResource\Pages\EditAssessmentReportTemplate;
use App\Filament\Resources\AssessmentReportTemplateResource\Pages\ViewAssessmentReportTemplate;
use App\Jobs\Assessment\GenerateClassReportPipeline;
use App\Jobs\Assessment\GenerateClassReports;
use App\Jobs\Assessment\GenerateClassReportsJob;
use App\Jobs\Assessment\GenerateStudentReport;
use App\Jobs\Assessment\GenerateStudentReportJob;
use App\Models\Assessment\AcademicYear;
use App\Models\Assessment\AssessmentPeriod;
use App\Models\Assessment\AssessmentPeriodAssignment;
use App\Models\Assessment\AssessmentPeriodHomeroom;
use App\Models\Assessment\AssessmentPeriodRombel;
use App\Models\Assessment\AssessmentPeriodStudent;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\HomeroomReport;
use App\Models\Assessment\ReportGenerationRun;
use App\Models\Assessment\ReportShareLink;
use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use App\Models\Assessment\Semester;
use App\Models\Assessment\StudentSubjectResult;
use App\Models\Assessment\Subject;
use App\Models\Assessment\TeachingAssignment;
use App\Models\User;
use App\Support\Assessment\AssessmentActionFailureNotification;
use App\Support\Assessment\Reporting\AssessmentReportQueueGate;
use App\Support\Assessment\Reporting\AssessmentReportLayout;
use App\Support\Assessment\Reporting\AssessmentReportPreflight;
use App\Support\Assessment\Reporting\AssessmentReportRenderer;
use App\Support\Assessment\Reporting\AssessmentReportShareService;
use App\Support\Assessment\Reporting\AssessmentReportStorage;
use App\Support\Assessment\Reporting\AssessmentReportWatermark;
use App\Support\Assessment\Reporting\BuildAssessmentReportPreviewSnapshot;
use App\Support\Assessment\Reporting\AssessmentReportCacheCleaner;
use App\Support\Assessment\Reporting\AssessmentReportDocxRenderer;
use App\Support\Assessment\Reporting\CreateReportSnapshotsAction;
use App\Support\Assessment\Reporting\RetryReportGenerationAction;
use App\Support\Assessment\Reporting\ScheduleReportClassesAction;
use App\Support\Assessment\Reporting\StopAssessmentReportQueueAction;
use App\Support\Storage\AssessmentReportOrphanManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use ZipArchive;
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
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('guru_tendik_id')->nullable();
            $table->json('module_access_levels')->nullable();
        });
        $permissionMigration = require database_path('migrations/2026_01_12_111708_create_permission_tables.php');
        $permissionMigration->up();
        $migration = require database_path('migrations/2026_07_31_080000_create_assessment_foundation_tables.php');
        $migration->up();
        $reportStructureMigration = require database_path('migrations/2026_07_31_120000_extend_assessment_report_structure.php');
        $reportStructureMigration->up();
        (require database_path('migrations/2026_08_06_150000_add_assessment_subject_categories.php'))->up();
        $pipelineMigration = require database_path('migrations/2026_07_31_190000_add_assessment_report_generation_runs.php');
        $pipelineMigration->up();
        $streamDeliveryMigration = require database_path('migrations/2026_08_03_080000_add_stream_delivery_to_assessment_reports.php');
        $streamDeliveryMigration->up();
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
        $this->assertInstanceOf(
            GenerateStudentReportJob::class,
            unserialize(serialize($studentJob)),
        );
        $this->assertInstanceOf(
            GenerateClassReportsJob::class,
            unserialize(serialize($classJob)),
        );
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

    public function test_parent_share_link_allows_an_available_unpublished_revision(): void
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

        $issued = app(AssessmentReportShareService::class)->issue(
            $oldSnapshot->fresh(),
            createdBy: 99,
            expiryDays: 1,
        );

        $this->assertInstanceOf(ReportShareLink::class, $issued['link']);
        $this->assertSame($oldSnapshot->getKey(), $issued['link']->assessment_report_snapshot_id);
        $this->assertSame($issued['link']->getKey(), app(AssessmentReportShareService::class)->resolve($issued['token'])->getKey());
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

    public function test_publish_rejects_invalid_student_report_but_does_not_require_class_cache(): void
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

        $action->execute($actor, $period->fresh());

        $this->assertSame(AssessmentPeriodStatus::PUBLISHED, $period->fresh()->status);
        $this->assertSame(0, data_get($period->fresh()->settings, '_reporting.published.class_report_count'));
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
        $homeroomReport = HomeroomReport::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_student_id' => $students[0]->getKey(),
            'sick_days' => 0,
            'permission_days' => 0,
            'absent_days' => 0,
            'extracurricular_data' => [[
                'name' => 'Pramuka',
                'description' => 'Sangat Baik',
            ]],
            'achievement_data' => [[
                'name' => 'Juara Olimpiade',
                'description' => 'Tingkat Kota',
            ]],
            'updated_by' => 99,
        ]);
        $action = app(CreateReportSnapshotsAction::class);

        $first = $action->execute($period, $template, generatedBy: 99);
        $homeroomReport->forceFill([
            'extracurricular_data' => [['name' => 'Basket', 'description' => 'Baik']],
            'achievement_data' => [['name' => 'Juara Kelas', 'description' => 'Semester Ganjil']],
        ])->save();
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
        $renderedReport = view('assessment.reports.asts', [
            'snapshot' => $stored->snapshot_data,
            'templateSettings' => [],
            'pdfMode' => false,
        ])->render();
        $this->assertStringContainsString('89', $renderedReport);
        $this->assertStringNotContainsString('Menguasai operasi numerik.', $renderedReport);
        $this->assertStringNotContainsString('Capaian Kompetensi', $renderedReport);
        $this->assertStringNotContainsString('(belum diisi)', $renderedReport);
        $this->assertSame(
            [['name' => 'Pramuka', 'description' => 'Sangat Baik']],
            data_get($stored->snapshot_data, 'homeroom.extracurricular_data'),
        );
        $this->assertSame(
            [['name' => 'Juara Olimpiade', 'description' => 'Tingkat Kota']],
            data_get($stored->snapshot_data, 'homeroom.achievement_data'),
        );
        $this->assertSame(
            'Kepala Sekolah',
            data_get($stored->snapshot_data, 'template.settings.principal_name'),
        );
        $this->assertDatabaseCount('assessment_report_snapshots', 1);
        $this->assertDatabaseCount('assessment_class_report_artifacts', 1);
        $this->assertDatabaseCount('assessment_report_generation_runs', 1);
        $this->assertSame('ready', $stored->generation_status->value);
        $this->assertSame('stream', $stored->delivery_mode);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $stored->snapshot_checksum);
        Queue::assertNothingPushed();
    }

    public function test_simplified_asts_page_omits_advanced_pipeline_polling(): void
    {
        [$period, $rombel, , $template] = $this->reportingFoundation();
        ReportGenerationRun::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 1,
            'status' => 'running',
            'total_students' => 1,
            'completed_students' => 0,
            'total_classes' => 1,
            'completed_classes' => 0,
            'requested_by' => 99,
            'started_at' => now(),
        ]);

        $component = Livewire::actingAs(User::query()->findOrFail(99))
            ->test(AstsReports::class)
            ->set('periodId', $period->getKey())
            ->set('templateId', $template->getKey())
            ->set('previewClassId', $rombel->getKey());

        $html = $component->html();
        $this->assertStringNotContainsString('wire:poll.5s.visible', $html);
        $reportView = (string) file_get_contents(resource_path('views/filament/pages/assessment/reports.blade.php'));
        $this->assertStringContainsString('Download PDF', $reportView);
        $this->assertStringContainsString('Download MS Word', $reportView);
        $this->assertStringContainsString('Share Link', $reportView);
        $this->assertStringContainsString('data-share-copy-result', $reportView);
    }

    public function test_asts_pages_share_title_and_identity_and_word_export_keeps_report_data(): void
    {
        $snapshotData = [
            'period' => ['type' => 'ASTS', 'academic_year' => '2026/2027', 'semester' => 'Semester Ganjil'],
            'student' => ['name' => 'Ahmad Azka Maximilian', 'nis' => '262710002', 'nisn' => '0114431247', 'class_name' => 'X 1'],
            'subjects' => [['name' => 'Matematika', 'final_score' => '88', 'predicate' => 'B']],
            'homeroom' => ['sick_days' => 0, 'permission_days' => 2, 'absent_days' => 0, 'extracurricular_data' => [['name' => 'Pramuka', 'description' => 'A']]],
            'signatures' => [['label' => 'Wali Kelas', 'name' => 'Ibu Wali']],
            'template' => ['settings' => []],
        ];
        $templateSettings = ['report_layout' => ['semester_value_override' => ' Genap ']];
        $html = view('assessment.reports.asts', ['snapshot' => $snapshotData, 'templateSettings' => $templateSettings, 'pdfMode' => false])->render();
        $this->assertSame(2, substr_count($html, 'LAPORAN HASIL ASESMEN SUMATIF TENGAH SEMESTER (ASTS)'));
        $this->assertSame(2, substr_count($html, '<p class="report-subtitle">Tahun Pelajaran 2026/2027</p>'));
        $this->assertSame(2, substr_count($html, 'Ahmad Azka Maximilian'));
        $this->assertStringContainsString('>Genap<', $html);
        $this->assertStringNotContainsString('>Ganjil<', $html);
        $this->assertStringContainsString('>Semester<', $html);
        $this->assertStringNotContainsString('>Jenis Laporan<', $html);
        $this->assertStringNotContainsString('>Semester Ganjil<', $html);

        $document = app(AssessmentReportDocxRenderer::class)->render(new ReportSnapshot(['snapshot_data' => $snapshotData]));
        $path = tempnam(sys_get_temp_dir(), 'asts-docx-');
        file_put_contents($path, $document);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);
        $this->assertStringContainsString('Ahmad Azka Maximilian', $xml);
        $this->assertStringContainsString('Matematika', $xml);
        $this->assertStringContainsString('Pramuka', $xml);
    }

    public function test_attendance_values_render_zero_as_dash_and_nonzero_as_days_in_legacy_and_flexible_reports(): void
    {
        $snapshot = [
            'school' => ['name' => 'SMA AFBS'],
            'period' => ['type' => 'ASTS'],
            'student' => ['name' => 'Siswa Uji'],
            'homeroom' => [
                'sick_days' => 0,
                'permission_days' => 2,
                'absent_days' => 0,
            ],
            'subjects' => [],
            'signatures' => [],
        ];
        $legacy = view('assessment.reports.asts', [
            'snapshot' => $snapshot,
            'templateSettings' => [],
            'pdfMode' => false,
        ])->render();
        $flexible = view('assessment.reports.asts', [
            'snapshot' => $snapshot,
            'templateSettings' => [
                'layout' => [
                    'version' => AssessmentReportLayout::VERSION,
                    'sections' => AssessmentReportLayout::threePageDefaults(),
                ],
            ],
            'pdfMode' => false,
        ])->render();

        foreach ([$legacy, $flexible] as $html) {
            $this->assertStringContainsString('summary-table summary-table--attendance', $html);
            $this->assertStringContainsString('attendance-value', $html);
            $this->assertStringContainsString('>-<', $html);
            $this->assertStringNotContainsString('0&nbsp;hari', $html);
            $this->assertStringContainsString('2&nbsp;hari', $html);
        }
    }

    public function test_template_edit_page_exposes_the_saved_template_preview_action(): void
    {
        $template = ReportTemplate::query()->create([
            'code' => 'PREVIEW-ACTION',
            'type' => AssessmentType::ASTS,
            'name' => 'Template Pratinjau',
            'version' => 1,
            'view_path' => 'assessment.reports.asts',
            'settings' => [],
        ]);

        Livewire::actingAs(User::query()->findOrFail(99))
            ->test(EditAssessmentReportTemplate::class, ['record' => $template->getKey()])
            ->assertSee('Pratinjau Template')
            ->assertSee('Jarak & Kerapian Tabel Rapor')
            ->assertSee('Simpan lalu Pratinjau Template')
            ->assertSee('identitas laporan menggunakan Label Semester')
            ->assertSee('Semester : Ganjil')
            ->assertSee('Pada rapor ASTS')
            ->assertSee('Jarak antar kelompok mapel')
            ->assertSee('Padding/tinggi baris tabel nilai')
            ->assertSee(url('/admin/penilaian/pengaturan/template-rapor/'.$template->getRouteKey().'/preview'));
    }

    public function test_template_view_page_exposes_the_saved_template_preview_action_with_literal_url(): void
    {
        $template = ReportTemplate::query()->create([
            'code' => 'PREVIEW-VIEW-ACTION',
            'type' => AssessmentType::ASTS,
            'name' => 'Template Pratinjau Tampilan',
            'version' => 1,
            'view_path' => 'assessment.reports.asts',
            'settings' => [],
        ]);

        Livewire::actingAs(User::query()->findOrFail(99))
            ->test(ViewAssessmentReportTemplate::class, ['record' => $template->getKey()])
            ->assertSee('Pratinjau Template')
            ->assertSee(url('/admin/penilaian/pengaturan/template-rapor/'.$template->getRouteKey().'/preview'));
    }

    public function test_template_view_page_tolerates_legacy_malformed_settings(): void
    {
        $template = ReportTemplate::query()->create([
            'code' => 'LEGACY-MALFORMED',
            'type' => AssessmentType::ASTS,
            'name' => 'Template Legacy',
            'version' => 0,
            'view_path' => 'assessment.reports.asts',
            'settings' => [
                'layout' => ['sections' => 'legacy-section-list'],
                'watermark_enabled' => true,
                'watermark_opacity' => ['unexpected'],
                'watermark_position' => ['unexpected'],
                'school_name' => ['unexpected'],
                'school_address' => ['unexpected'],
                'principal_name' => ['unexpected'],
                'principal_identifier' => ['unexpected'],
                'place' => ['unexpected'],
                'homeroom_title' => ['unexpected'],
                'report_layout' => 'legacy-layout',
            ],
        ]);

        // Bypass model casts to mirror legacy data that predates enum validation.
        DB::table('assessment_report_templates')
            ->whereKey($template->getKey())
            ->update([
                'type' => 'historical',
                'effective_from' => 'not-a-date',
            ]);

        $this->actingAs(User::query()->findOrFail(99))
            ->get(AssessmentReportTemplateResource::getUrl('view', ['record' => $template]))
            ->assertOk()
            ->assertSee('Layout standar satu halaman.')
            ->assertSee('Aktif')
            ->assertSee('-');

        Livewire::actingAs(User::query()->findOrFail(99))
            ->test(ViewAssessmentReportTemplate::class, ['record' => $template->getKey()])
            ->assertOk()
            ->assertSee('Template Legacy');

        Livewire::actingAs(User::query()->findOrFail(99))
            ->test(EditAssessmentReportTemplate::class, ['record' => $template->getKey()])
            ->assertOk();
    }

    public function test_template_index_tolerates_legacy_type_date_and_settings_values(): void
    {
        $template = ReportTemplate::query()->create([
            'code' => 'LEGACY-INDEX',
            'type' => AssessmentType::ASTS,
            'name' => 'Template Legacy Index',
            'version' => 1,
            'view_path' => 'assessment.reports.asts',
            'settings' => [
                'school_name' => ['not', 'a', 'string'],
                'watermark_enabled' => ['not-a-boolean'],
            ],
        ]);

        DB::table('assessment_report_templates')
            ->whereKey($template->getKey())
            ->update([
                'type' => 'historical',
                'effective_from' => 'not-a-date',
            ]);

        $this->actingAs(User::query()->findOrFail(99))
            ->get(AssessmentReportTemplateResource::getUrl())
            ->assertOk()
            ->assertSee('Template Legacy Index');
    }

    public function test_saved_template_preview_renders_custom_settings_with_sample_data_without_persisting_artifacts(): void
    {
        Storage::fake('local');
        $template = ReportTemplate::query()->create([
            'code' => 'PREVIEW-ASTS',
            'type' => AssessmentType::ASTS,
            'name' => 'Template Pratinjau',
            'version' => 1,
            'view_path' => 'assessment.reports.asts',
            'settings' => [
                'school_name' => 'SMA Pratinjau Aktif',
                'report_title' => 'RAPOR KHUSUS PRATINJAU',
                'report_layout' => [
                    'labels' => ['student_name' => 'Peserta Contoh'],
                    'subject_group_spacing' => 8,
                    'subject_group_table_spacing' => 3,
                    'score_table_row_padding' => 4,
                    'score_table_kktp_spacing' => 7,
                    'kktp_next_section_spacing' => 6,
                ],
            ],
        ]);

        $response = $this->actingAs(User::query()->findOrFail(99))
            ->get(route('assessment.reports.template-preview', $template));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertSee('SMA Pratinjau Aktif')
            ->assertSee('RAPOR KHUSUS PRATINJAU')
            ->assertSee('Peserta Contoh')
            ->assertSee('Siswa Contoh')
            ->assertSee('--asts-subject-group-spacing: 8pt;')
            ->assertSee('--asts-subject-group-table-spacing: 3pt;')
            ->assertSee('--asts-score-row-padding: 4pt;')
            ->assertSee('--asts-score-kktp-spacing: 7pt;')
            ->assertSee('--asts-kktp-next-section-spacing: 6pt;');
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('assessment_report_snapshots', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_saved_template_preview_rejects_unauthorized_users(): void
    {
        $template = ReportTemplate::query()->create([
            'code' => 'PREVIEW-DENIED',
            'type' => AssessmentType::ASTS,
            'name' => 'Template Pratinjau',
            'version' => 1,
            'view_path' => 'assessment.reports.asts',
            'settings' => [],
        ]);
        $user = User::query()->create([
            'name' => 'Unauthorized',
            'username' => 'unauthorized-preview',
            'email' => 'unauthorized-preview@example.test',
            'password' => bcrypt('secret-test-password'),
        ]);

        $this->actingAs($user)
            ->getJson(route('assessment.reports.template-preview', $template))
            ->assertForbidden();
    }

    public function test_asts_report_header_layout_defaults_preserve_the_compact_two_column_layout(): void
    {
        $data = AssessmentReportTemplateResource::validateTemplateData([
            'type' => AssessmentType::ASTS->value,
            'view_path' => 'assessment.reports.asts',
            'version' => 1,
            'settings' => [],
        ]);

        $this->assertSame('two_column_compact', data_get($data, 'settings.report_layout.identity_style'));
        $this->assertSame(9.0, data_get($data, 'settings.report_layout.identity_font_size'));
        $this->assertSame(3.0, data_get($data, 'settings.report_layout.identity_table_spacing'));
        $this->assertSame('center', data_get($data, 'settings.report_layout.kop_alignment'));
        $this->assertTrue(data_get($data, 'settings.report_layout.show_logo'));
        $this->assertSame(48.0, data_get($data, 'settings.report_layout.logo_size'));
        $this->assertSame(7.0, data_get($data, 'settings.report_layout.kop_title_spacing'));
        $this->assertSame(4.0, data_get($data, 'settings.report_layout.subject_group_spacing'));
        $this->assertSame(1.0, data_get($data, 'settings.report_layout.subject_group_table_spacing'));
        $this->assertSame(2.5, data_get($data, 'settings.report_layout.score_table_row_padding'));
        $this->assertSame(3.0, data_get($data, 'settings.report_layout.score_table_kktp_spacing'));
        $this->assertSame(3.0, data_get($data, 'settings.report_layout.kktp_next_section_spacing'));
        $this->assertSame('Nama Siswa', data_get($data, 'settings.report_layout.labels.student_name'));

        $html = view('assessment.reports.asts', [
            'snapshot' => ['student' => ['name' => 'Siswa Uji'], 'subjects' => []],
            'templateSettings' => [],
            'pdfMode' => false,
        ])->render();
        $this->assertStringContainsString('--asts-subject-group-spacing: 4pt;', $html);
        $this->assertStringContainsString('--asts-subject-group-table-spacing: 1pt;', $html);
        $this->assertStringContainsString('--asts-score-row-padding: 2.5pt;', $html);
        $this->assertStringContainsString('--asts-score-kktp-spacing: 3pt;', $html);
        $this->assertStringContainsString('--asts-kktp-next-section-spacing: 3pt;', $html);
    }

    public function test_asts_report_header_layout_renders_custom_labels_spacing_font_and_one_column_style(): void
    {
        $html = view('assessment.reports.asts', [
            'snapshot' => [
                'period' => ['academic_year' => '2025/2026', 'semester' => 'GANJIL'],
                'student' => ['name' => 'Siswa Uji', 'nis' => '123', 'nisn' => '456', 'class_name' => 'XI IPA 1'],
                'subjects' => [['name' => 'Matematika', 'final_score' => 90]],
                'homeroom' => [],
                'signatures' => [],
            ],
            'templateSettings' => [
                'foundation_name' => 'Yayasan Uji',
                'school_name' => 'SMA Konfigurasi',
                'school_address' => 'Jalan Pengujian 1',
                'school_contact' => 'WA 0812 | uji@example.test | sekolah.test',
                'report_layout' => [
                    'kop_alignment' => 'left',
                    'show_logo' => false,
                    'logo_size' => 40,
                    'kop_title_spacing' => 10,
                    'title_identity_spacing' => 8,
                    'identity_style' => 'one_column_full',
                    'identity_font_size' => 11,
                    'identity_table_spacing' => 12,
                    'labels' => [
                        'student_name' => 'Peserta Didik',
                        'student_number' => 'Nomor Induk',
                        'class' => 'Rombel',
                        'semester' => 'Periode Belajar',
                    ],
                ],
            ],
            'pdfMode' => false,
        ])->render();

        $this->assertStringContainsString('identity--asts-one_column_full', $html);
        $this->assertStringContainsString('letterhead--left', $html);
        $this->assertStringContainsString('--letterhead-logo-size: 40px;', $html);
        $this->assertStringContainsString('--kop-title-spacing: 10pt; --title-identity-spacing: 8pt;', $html);
        $this->assertStringContainsString('--identity-font-size: 11pt; --identity-table-spacing: 12pt;', $html);
        foreach (['Yayasan Uji', 'SMA Konfigurasi', 'Jalan Pengujian 1', 'WA 0812 | uji@example.test | sekolah.test'] as $value) {
            $this->assertStringContainsString($value, $html);
        }
        foreach (['Peserta Didik', 'Nomor Induk', 'Rombel', 'Periode Belajar'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
    }

    public function test_all_report_views_use_sumative_titles_two_page_structure_and_school_footer(): void
    {
        $snapshot = [
            'school' => ['name' => 'SMA AFBS'],
            'period' => ['academic_year' => '2025/2026', 'semester' => 'GANJIL'],
            'student' => ['name' => 'Siswa Uji'],
            'subjects' => [['name' => 'Matematika', 'final_score' => 90]],
            'homeroom' => ['homeroom_note' => str_repeat('Catatan wali kelas. ', 100)],
            'signatures' => [],
        ];

        foreach ([
            'ASTS' => 'ASESMEN SUMATIF TENGAH SEMESTER (ASTS)',
            'ASAS' => 'ASESMEN SUMATIF AKHIR SEMESTER (ASAS)',
            'ASAT' => 'ASESMEN SUMATIF AKHIR TAHUN (ASAT)',
        ] as $kind => $expectedTitle) {
            $html = view('assessment.reports.'.strtolower($kind), [
                'snapshot' => $snapshot,
                'templateSettings' => [],
                'pdfMode' => false,
            ])->render();

            $this->assertStringContainsString($expectedTitle, $html);
            $this->assertStringContainsString('Dengan Teladan Menjadi Mulia', $html);
            $this->assertStringContainsString('Tahun Pelajaran 2025/2026', $html);
            $this->assertStringContainsString('report-page-break', $html);
            $this->assertStringContainsString('font-family: "Times New Roman", Times, serif', $html);
            $this->assertStringNotContainsString('Gill Sans MT', $html);
            $this->assertStringContainsString('font-size: 12pt', $html);
            $this->assertStringContainsString('font-size: 13pt', $html);
            $this->assertStringContainsString('font-size: 14pt', $html);
            $this->assertStringContainsString('font-size: 10pt', $html);
            $this->assertStringNotContainsString('font-size: 9.4px', $html);
            $this->assertStringNotContainsString('font-size: 9.5px', $html);
            $this->assertStringNotContainsString('Dokumen snapshot', $html);
            $this->assertStringNotContainsString('Template v', $html);
        }
    }

    public function test_report_template_content_overrides_render_safely(): void
    {
        $snapshot = [
            'school' => ['name' => 'SMA Snapshot'],
            'period' => ['academic_year' => '2025/2026', 'semester' => 'GANJIL'],
            'student' => ['name' => 'Siswa Uji', 'nis' => '123', 'nisn' => '456', 'class_name' => 'XI 1'],
            'subjects' => [['name' => 'Matematika', 'final_score' => 90, 'predicate' => 'A', 'description' => 'Tuntas.']],
            'homeroom' => [],
            'signatures' => [
                ['label' => 'Wali Murid', 'name' => '-'],
                ['label' => 'Pembimbing Kelas', 'name' => 'Ibu Wali', 'place_date' => 'Bogor, 1 Juli 2026'],
            ],
        ];
        $settings = [
            'report_title' => 'HASIL BELAJAR UJI',
            'footer_text' => 'Motto Rapor Uji',
            'score_label' => 'Skor',
            'predicate_label' => 'Mutu',
            'description_label' => 'Uraian Capaian',
            'report_layout' => [
                'table_signature_spacing' => 18,
                'signature_spacing' => 80,
                'labels' => [
                    'student_name' => 'Peserta', 'student_number' => 'Nomor Peserta',
                    'class' => 'Rombel', 'semester' => 'Periode', 'report_type' => 'Dokumen',
                ],
            ],
        ];

        foreach (['asts', 'asas'] as $report) {
            $html = view('assessment.reports.'.$report, compact('snapshot', 'settings') + [
                'templateSettings' => $settings,
                'pdfMode' => false,
            ])->render();
            foreach (['HASIL BELAJAR UJI', 'Motto Rapor Uji', 'Skor', 'Mutu', '--table-signature-spacing: 18pt;', '--signature-space-height: 80pt;'] as $value) {
                $this->assertStringContainsString($value, $html);
            }
        }

        $asas = view('assessment.reports.asas', ['snapshot' => $snapshot, 'templateSettings' => $settings, 'pdfMode' => false])->render();
        foreach (['Peserta', 'Nomor Peserta', 'Rombel', 'Dokumen', 'Uraian Capaian'] as $value) {
            $this->assertStringContainsString($value, $asas);
        }
    }

    public function test_asas_and_asat_render_manual_homeroom_sections_and_only_asas_shows_promotion_status(): void
    {
        $snapshot = [
            'school' => ['name' => 'SMA AFBS'],
            'period' => ['academic_year' => '2025/2026', 'semester' => 'GENAP', 'collect_promotion_status' => true],
            'student' => ['name' => 'Siswa Uji', 'class_name' => 'XI 1'],
            'subjects' => [['name' => 'Matematika', 'final_score' => 90, 'predicate' => 'A', 'description' => 'Menguasai kompetensi numerasi.']],
            'homeroom' => [
                'kokurikuler' => 'Aktif dalam projek kokurikuler.',
                'extracurricular_data' => [['name' => 'Pramuka', 'description' => 'Baik']],
                'achievement_data' => [['name' => 'Juara Kelas', 'description' => 'Tingkat sekolah']],
                'homeroom_note' => 'Pertahankan semangat belajar.',
                'promotion_status' => 'Naik Kelas',
            ],
            'signatures' => [],
        ];

        $asas = view('assessment.reports.asas', ['snapshot' => $snapshot, 'templateSettings' => [], 'pdfMode' => false])->render();
        $asat = view('assessment.reports.asat', ['snapshot' => $snapshot, 'templateSettings' => [], 'pdfMode' => false])->render();

        foreach ([$asas, $asat] as $html) {
            foreach (['Capaian Kompetensi', 'B. Kokurikuler', 'C. Ekstrakurikuler', 'E. Prestasi', 'F. Ketidakhadiran', 'G. Catatan Wali Kelas'] as $text) {
                $this->assertStringContainsString($text, $html);
            }
            $this->assertSame(1, substr_count($html, '<div class="report-page-break"></div>'));
            $this->assertStringContainsString('font-family: "Times New Roman", Times, serif', $html);
        }

        $this->assertStringContainsString('Keterangan Naik Kelas', $asas);
        $this->assertStringContainsString('Naik Kelas', $asas);
        $this->assertStringNotContainsString('Keterangan Naik Kelas', $asat);
        $this->assertStringNotContainsString('Status Semester', $asat);
        $this->assertStringNotContainsString('Naik Kelas', $asat);
    }

    public function test_asts_uses_two_page_score_and_homeroom_layout_without_competency_descriptions(): void
    {
        $longStudentName = 'Dea '.str_repeat('Rahmawati Kusuma ', 12);
        $snapshot = [
            'school' => ['name' => 'SMA AFBS'],
            'period' => ['academic_year' => 'Demo 2025/2026', 'semester' => 'Ganjil'],
            'student' => ['name' => $longStudentName, 'nisn' => '0085713398', 'class_name' => 'XI IPA 1'],
            'subjects' => [
                // Deliberately stale predicates verify ASTS uses the displayed KKTP scale.
                ['name' => 'Nilai 69', 'final_score' => 69, 'predicate' => 'A', 'description' => 'Tidak boleh tampil.', 'group_code' => 'WAJIB'],
                ['name' => 'Nilai 70', 'final_score' => 70, 'predicate' => 'D', 'description' => 'Tidak boleh tampil.', 'group_code' => 'WAJIB'],
                ['name' => 'Nilai 75', 'final_score' => 75, 'predicate' => 'A', 'description' => 'Tidak boleh tampil.', 'group_code' => 'WAJIB'],
                ['name' => 'Nilai 76', 'final_score' => 76, 'predicate' => 'C', 'description' => 'Tidak boleh tampil.', 'group_code' => 'WAJIB'],
                ['name' => 'Nilai 85', 'final_score' => 85, 'predicate' => 'D', 'description' => 'Tidak boleh tampil.', 'group_code' => 'WAJIB'],
                ['name' => 'Nilai 86', 'final_score' => 86, 'predicate' => 'C', 'description' => 'Tidak boleh tampil.', 'group_code' => 'WAJIB'],
                ['name' => 'Matematika', 'final_score' => 90, 'predicate' => 'D', 'description' => 'Tidak boleh tampil.', 'group_code' => 'WAJIB'],
                ['name' => 'Biologi Lanjut', 'final_score' => 82, 'predicate' => 'A', 'description' => 'Tidak boleh tampil.', 'group_code' => 'PILIHAN'],
            ],
            'homeroom' => [
                'sick_days' => 1,
                'spiritual_predicate' => 'Spiritual tidak boleh tampil',
                'spiritual_description' => 'Deskripsi spiritual tidak boleh tampil',
                'social_predicate' => 'Sosial tidak boleh tampil',
                'social_description' => 'Deskripsi sosial tidak boleh tampil',
                'kokurikuler' => 'Kokurikuler tidak boleh tampil',
                'achievement_data' => [['name' => 'Prestasi tidak boleh tampil', 'description' => 'Tidak boleh tampil']],
                'extracurricular_data' => [['name' => 'Pramuka', 'description' => 'A']],
            ],
            'signatures' => [
                ['label' => 'Orang Tua/Wali', 'name' => '-'],
                ['label' => 'Wali Kelas', 'name' => 'Ibu Wali', 'place_date' => 'Tangerang, 10 Oktober 2026'],
                ['label' => 'Kepala Sekolah', 'name' => 'Tidak Boleh Tampil'],
            ],
        ];

        $html = view('assessment.reports.asts', [
            'snapshot' => $snapshot,
            'templateSettings' => [],
            'pdfMode' => false,
        ])->render();

        $this->assertStringContainsString('Tabel Interval berdasarkan KKTP', $html);
        $this->assertLessThan(
            strpos($html, 'Tabel Interval berdasarkan KKTP'),
            strpos($html, 'class="scores asts-scores"'),
            'KKTP must follow the ASTS score table.',
        );
        [$scorePage, $summaryPage] = explode('<div class="report-page-break"></div>', $html, 2);
        $this->assertStringContainsString('Nama Siswa', $summaryPage);
        $this->assertStringContainsString('NIS/NISN', $summaryPage);
        $this->assertStringContainsString('Kelas', $summaryPage);
        $this->assertSame(2, substr_count($html, '<table class="identity identity--asts'));
        $this->assertSame(2, substr_count($html, '<col class="identity__col--asts-primary-value">'));
        $this->assertSame(2, substr_count($html, '<col class="identity__col--asts-secondary-value">'));
        $this->assertSame(4, substr_count($html, 'identity__value--asts identity__value--asts-primary identity__value--asts-nowrap'));
        $this->assertSame(4, substr_count($html, 'identity__value--asts identity__value--asts-secondary identity__value--asts-nowrap'));
        $this->assertSame(5, substr_count($html, 'identity__separator--asts-left'));
        $this->assertSame(5, substr_count($html, 'identity__separator--asts-right'));
        foreach ([$scorePage, $summaryPage] as $page) {
            $this->assertStringContainsString($longStudentName, $page);
            $this->assertMatchesRegularExpression(
                '/identity__value--asts-primary identity__value--asts-nowrap">'.preg_quote($longStudentName, '/').'<\\/td>\\s*<td class="identity__spacer"[^>]*><\\/td>\\s*<td class="identity__label identity__label--asts-secondary">Kelas<\\/td>/s',
                $page,
            );
            $this->assertMatchesRegularExpression(
                '/identity__label identity__label--asts-secondary">Semester<\\/td>\\s*<td class="identity__separator identity__separator--asts[^"]*">:<\\/td>\\s*<td class="identity__value--asts identity__value--asts-secondary identity__value--asts-nowrap">Ganjil<\\/td>/s',
                $page,
            );
        }
        $this->assertStringContainsString('Semester', $scorePage);
        $this->assertStringContainsString('Semester', $summaryPage);
        $this->assertStringNotContainsString('Jenis Laporan', $html);
        $this->assertStringContainsString('ASTS', $summaryPage);
        $this->assertStringContainsString('- / 0085713398', $html);
        $this->assertStringContainsString('class="scores asts-scores"', $scorePage);
        foreach ([69 => 'D', 70 => 'C', 75 => 'C', 76 => 'B', 85 => 'B', 86 => 'A'] as $score => $predicate) {
            $this->assertMatchesRegularExpression('/'.$score.'<\/td><td class="scores__predicate">'.$predicate.'<\/td>/', $html);
        }
        $this->assertStringContainsString('Kelompok Umum', $html);
        $this->assertStringContainsString('Kelompok Pilihan', $html);
        $this->assertStringContainsString('Ekstrakurikuler', $html);
        $this->assertStringContainsString('Nama Ekstrakurikuler</th><th>Predikat', $html);
        $this->assertStringContainsString('Pramuka</td><td>A</td>', $html);
        $this->assertStringNotContainsString('Spiritual tidak boleh tampil', $html);
        $this->assertStringNotContainsString('Deskripsi spiritual tidak boleh tampil', $html);
        $this->assertStringNotContainsString('Sosial tidak boleh tampil', $html);
        $this->assertStringNotContainsString('Deskripsi sosial tidak boleh tampil', $html);
        $this->assertStringNotContainsString('Kokurikuler tidak boleh tampil', $html);
        $this->assertStringNotContainsString('Prestasi tidak boleh tampil', $html);
        $this->assertStringContainsString('asts-signatures', $html);
        $this->assertStringContainsString('(................................................)', $html);
        $this->assertStringNotContainsString('Capaian Kompetensi', $html);
        $this->assertStringNotContainsString('Tidak boleh tampil.', $html);
        $this->assertStringNotContainsString('Kepala Sekolah', $html);
        $this->assertSame(1, substr_count($html, '<div class="report-page-break"></div>'));
    }

    public function test_streamed_student_report_download_creates_no_permanent_pdf_file(): void
    {
        Storage::fake('local');
        config(['cache.default' => 'array']);
        [$period, , , $template] = $this->reportingFoundation();
        $snapshot = app(CreateReportSnapshotsAction::class)
            ->execute($period, $template, generatedBy: 99)
            ->firstOrFail();

        app(PublishAssessmentPeriodAction::class)->execute(User::query()->findOrFail(99), $period);

        $this->actingAs(User::query()->findOrFail(99))
            ->get(route('assessment.reports.snapshot.download', $snapshot))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertSame('stream', $snapshot->fresh()->delivery_mode);
        $this->assertNull($snapshot->fresh()->pdf_path);
        $this->assertSame([], Storage::disk('local')->allFiles('assessment-reports'));
    }

    public function test_streamed_report_returns_retry_page_when_render_slot_is_busy(): void
    {
        Storage::fake('local');
        config(['cache.default' => 'array']);
        [$period, , , $template] = $this->reportingFoundation();
        $snapshot = app(CreateReportSnapshotsAction::class)
            ->execute($period, $template, generatedBy: 99)
            ->firstOrFail();
        $lock = \Illuminate\Support\Facades\Cache::lock('assessment-report-render-slot-1', 60);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs(User::query()->findOrFail(99))
                ->get(route('assessment.reports.snapshot.download', $snapshot))
                ->assertStatus(429)
                ->assertHeader('Retry-After', '10')
                ->assertSee('PDF rapor sedang disiapkan');
        } finally {
            $lock->release();
        }
    }

    public function test_expired_class_cache_is_reported_then_deleted_safely(): void
    {
        Storage::fake('local');
        [$period, $rombel, , $template] = $this->reportingFoundation();
        $path = 'assessment-reports/expired-class.pdf';
        Storage::disk('local')->put($path, "%PDF-1.4\nexpired\n%%EOF");
        $artifact = ClassReportArtifact::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 1,
            'generation_status' => 'completed',
            'pdf_path' => $path,
            'checksum' => hash('sha256', Storage::disk('local')->get($path)),
            'generated_at' => now()->subDays(2),
            'cache_expires_at' => now()->subDay(),
            'generated_by' => 99,
        ]);

        $dryRun = app(AssessmentReportCacheCleaner::class)->clean(false);
        $this->assertSame(1, $dryRun['files']);
        Storage::disk('local')->assertExists($path);

        app(AssessmentReportCacheCleaner::class)->clean(true);
        Storage::disk('local')->assertMissing($path);
        $this->assertSame('expired', $artifact->fresh()->generation_status->value);
        $this->assertNull($artifact->fresh()->pdf_path);
    }

    public function test_orphan_report_files_are_quarantined_without_touching_referenced_reports(): void
    {
        Storage::fake('local');
        [$period, $rombel, , $template] = $this->reportingFoundation();
        $referencedPath = 'assessment-reports/referenced-class.pdf';
        $orphanPath = 'assessment-reports/legacy/orphan.pdf';
        Storage::disk('local')->put($referencedPath, "%PDF-1.4\nreferenced\n%%EOF");
        Storage::disk('local')->put($orphanPath, "%PDF-1.4\norphan\n%%EOF");
        ClassReportArtifact::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 1,
            'generation_status' => 'completed',
            'pdf_path' => $referencedPath,
            'checksum' => hash('sha256', Storage::disk('local')->get($referencedPath)),
            'generated_by' => 99,
        ]);

        $manager = app(AssessmentReportOrphanManager::class);
        $this->assertSame(1, $manager->inspect()['files']);
        $this->assertSame(1, $manager->quarantine(false)['files']);
        Storage::disk('local')->assertExists($orphanPath);

        $manager->quarantine(true);

        Storage::disk('local')->assertExists($referencedPath);
        Storage::disk('local')->assertMissing($orphanPath);
        $this->assertCount(1, Storage::disk('local')->allFiles('orphan-quarantine/assessment-reports'));
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

        app(CancelOpenReportRevisionsAction::class)->execute(
            User::query()->findOrFail(99),
            $period,
            $template,
            'Mengganti revisi setelah konfigurasi status semester diperbarui.',
        );
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
            ReportSnapshot::query()->where('generation_status', 'ready')->count(),
        );
        Queue::assertPushed(GenerateClassReportPipeline::class, 1);
        Queue::assertNotPushed(GenerateStudentReportJob::class);
    }

    public function test_class_pipeline_keeps_student_snapshots_streamable_and_caches_one_class_pdf(): void
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
            app(\App\Support\Assessment\Reporting\AssessmentReportRenderGate::class),
        );

        $this->assertSame(2, ReportSnapshot::query()->where('generation_status', 'ready')->count());
        $this->assertSame(0, ReportSnapshot::query()->whereNotNull('pdf_path')->count());
        $this->assertSame('completed', $artifact->fresh()->generation_status->value);
        $this->assertSame('completed', $artifact->fresh()->generationRun->status->value);
        $this->assertTrue($artifact->fresh()->cache_expires_at->isFuture());
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
        $this->assertSame(0, ReportSnapshot::query()->where('generation_status', 'cancelled')->count());

        $cancelledArtifact = ClassReportArtifact::query()->firstOrFail();
        (new GenerateClassReportsJob($cancelledArtifact->getKey()))
            ->failed(new RuntimeException('Worker lama selesai setelah penghentian.'));

        $this->assertSame('ready', ReportSnapshot::query()->where('id', '!=', $completed->getKey())->firstOrFail()->generation_status->value);
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
        $this->get(route('assessment.reports.live-download', [$period, $template, $students[0]]))
            ->assertNotFound();
        $this->get(route('assessment.reports.shared.download', str_repeat('a', 43)))
            ->assertNotFound();
        $this->assertDatabaseCount('assessment_audit_logs', 0);
    }

    public function test_asts_report_page_lists_students_without_optional_distribution(): void
    {
        Storage::fake('local');
        [$period, , $students, $template] = $this->reportingFoundation();
        $this->actingAs(User::query()->findOrFail(99));

        $previewUrl = route('assessment.reports.live-preview', [$period, $template, $students[0]]);
        $downloadUrl = route('assessment.reports.live-download', [$period, $template, $students[0]]);

        $this->get(AstsReports::getUrl([
            'period' => $period->getKey(),
            'template' => $template->getKey(),
        ]))
            ->assertOk()
            ->assertSee('Rapor per kelas')
            ->assertSee('Download ZIP Kelas')
            ->assertSee($students[0]->student_name_snapshot)
            ->assertSee('Preview')
            ->assertSee('Download')
            ->assertSee('Share Orang Tua')
            ->assertSeeHtml('wire:click="issueParentShareLink('.$students[0]->getKey().')"')
            ->assertSeeHtml('href="'.$previewUrl.'"')
            ->assertSeeHtml('href="'.$previewUrl.'" target="_blank"')
            ->assertSeeHtml('href="'.$downloadUrl.'"')
            ->assertDontSee('Distribusi opsional')
            ->assertDontSee('Periksa tampilan PDF dan watermark')
            ->assertSeeHtml('assessment-report-card');

        $previewResponse = $this->get($previewUrl);
        $previewResponse->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $previewResponse->assertSee('LAPORAN HASIL ASESMEN SUMATIF TENGAH SEMESTER (ASTS)')
            ->assertSee($students[0]->student_name_snapshot)
            ->assertSee('Mata Pelajaran')
            ->assertSee('<article class="report-document"', false)
            ->assertSee(route('assessment.reports.live-preview-stream', [$period, $template, $students[0]]), false)
            ->assertSee('Download PDF');

        $streamResponse = $this->get(route('assessment.reports.live-preview-stream', [$period, $template, $students[0]]));
        $streamResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $streamDisposition = (string) $streamResponse->headers->get('Content-Disposition');
        $this->assertStringContainsString('inline', $streamDisposition);
        $this->assertStringNotContainsString('attachment', $streamDisposition);
        $this->assertStringContainsString('Preview - Siswa 1 - XI 1 - Rapor ASTS.pdf', $streamDisposition);
        $this->assertStringContainsString('no-store', (string) $streamResponse->headers->get('Cache-Control'));

        $downloadResponse = $this->get($downloadUrl);
        $downloadResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $downloadDisposition = (string) $downloadResponse->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $downloadDisposition);
        $this->assertStringContainsString('Siswa 1 - XI 1 - Rapor ASTS.pdf', $downloadDisposition);
    }

    public function test_live_report_uses_current_student_identity_and_subject_master_without_changing_snapshots(): void
    {
        [$period, $rombel, $students, $template] = $this->reportingFoundation();
        Schema::create('data_siswa', function (Blueprint $table): void {
            $table->id();
            $table->string('nipd')->nullable();
            $table->string('nisn')->nullable();
            $table->string('status')->default('aktif');
            $table->string('rombel_saat_ini')->nullable();
        });
        DB::table('data_siswa')->insert([
            'id' => $students[0]->student_id,
            'nipd' => 'NIPD-Terbaru',
            'nisn' => 'NISN-Terbaru',
            'status' => 'aktif',
            'rombel_saat_ini' => 'XI 1',
        ]);
        $subject = Subject::query()->create([
            'code' => 'MAT',
            'name' => 'Matematika Terbaru',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $assignment = AssessmentPeriodAssignment::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'teacher_id' => 41,
            'assessment_subject_id' => $subject->getKey(),
            'teacher_name_snapshot' => 'Guru Matematika',
            'subject_name_snapshot' => 'Matematika Lama',
            'rombel_name_snapshot' => $rombel->rombel_name_snapshot,
            'status' => 'locked',
            'lock_version' => 1,
        ]);
        StudentSubjectResult::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_student_id' => $students[0]->getKey(),
            'assessment_period_assignment_id' => $assignment->getKey(),
            'final_score' => 88,
            'predicate' => 'B',
        ]);

        $preview = app(BuildAssessmentReportPreviewSnapshot::class)->build($period, $template, $students[0]);
        $html = view('assessment.reports.asts', [
            'snapshot' => $preview->snapshot_data,
            'templateSettings' => data_get($preview->snapshot_data, 'template.settings', []),
            'pdfMode' => false,
        ])->render();

        $this->assertSame('NIPD-Terbaru', data_get($preview->snapshot_data, 'student.nis'));
        $this->assertSame('NISN-Terbaru', data_get($preview->snapshot_data, 'student.nisn'));
        $this->assertSame('Matematika Terbaru', data_get($preview->snapshot_data, 'subjects.0.name'));
        $this->assertStringContainsString('NIPD-Terbaru / NISN-Terbaru', $html);
        $this->assertStringContainsString('Matematika Terbaru', $html);
        $this->assertStringNotContainsString('Matematika Lama', $html);
        $stored = app(CreateReportSnapshotsAction::class)->execute($period, $template, generatedBy: 99)->firstOrFail();
        $this->assertSame('NIPD-Terbaru', data_get($stored->snapshot_data, 'student.nis'));
        $this->assertSame('NISN-Terbaru', data_get($stored->snapshot_data, 'student.nisn'));
        $this->assertSame('Matematika Terbaru', data_get($stored->snapshot_data, 'subjects.0.name'));
        $this->assertStringContainsString('Tahun Pelajaran 2025/2026</p>', $html);
        $this->assertStringNotContainsString('Tahun Pelajaran Tahun Pelajaran', $html);
        $this->assertStringNotContainsString('Semester Ganjil</p>', $html);
    }

    public function test_parent_share_landing_hides_scores_and_serves_pdf_with_a_valid_token(): void
    {
        Storage::fake('local');
        [$period, , $students, $template] = $this->reportingFoundation(status: AssessmentPeriodStatus::PUBLISHED);
        $snapshot = $this->snapshot($period, $students[0], $template, 1);
        (new GenerateStudentReportJob($snapshot->getKey()))
            ->handle(app(AssessmentReportRenderer::class), app(AssessmentReportStorage::class));
        $snapshot->refresh();
        $this->markPublishedSet($period, $template, 1);
        $issued = app(AssessmentReportShareService::class)->issue($snapshot, createdBy: 99, expiryDays: 1);

        $this->get(route('assessment.reports.shared.landing', ['token' => $issued['token']]))
            ->assertOk()
            ->assertSee('og:title', false)
            ->assertSee('Rapor ASTS - Siswa 1 - XI 1')
            ->assertDontSee('Preview Rapor')
            ->assertDontSee('88.50')
            ->assertSee(route('assessment.reports.shared.preview', ['token' => $issued['token']]), false)
            ->assertSee(route('assessment.reports.shared.download', ['token' => $issued['token']]), false);

        $this->get(route('assessment.reports.shared.preview', ['token' => $issued['token']]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('assessment.reports.shared.download', ['token' => $issued['token']]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $issued['link']->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->get(route('assessment.reports.shared.landing', ['token' => $issued['token']]))->assertGone();
        $this->get(route('assessment.reports.shared.landing', ['token' => str_repeat('a', 43)]))->assertNotFound();
    }

    public function test_parent_share_uses_a_live_stream_snapshot_before_the_period_is_published(): void
    {
        Storage::fake('local');
        [$period, $rombel, $students, $template] = $this->reportingFoundation();
        $this->actingAs(User::query()->findOrFail(99));
        $page = Livewire::test(AstsReports::class)
            ->set('periodId', $period->getKey())
            ->set('templateId', $template->getKey())
            ->set('previewClassId', $rombel->getKey())
            ->call('issueParentShareLink', $students[0]->getKey());
        $token = basename((string) parse_url((string) $page->instance()->latestShareUrl, PHP_URL_PATH));

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $token);
        $this->assertDatabaseHas('assessment_report_snapshots', [
            'assessment_period_id' => $period->getKey(),
            'assessment_period_student_id' => $students[0]->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 0,
            'generation_status' => 'ready',
            'delivery_mode' => 'stream',
            'pdf_path' => null,
        ]);

        $this->get(route('assessment.reports.shared.landing', ['token' => $token]))
            ->assertOk()
            ->assertSee('Rapor ASTS - Siswa 1 - XI 1')
            ->assertDontSee('Preview Rapor')
            ->assertDontSee('88.50');
        $this->get(route('assessment.reports.shared.preview', ['token' => $token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('assessment.reports.shared.download', ['token' => $token]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_live_report_signature_date_defaults_to_today_without_mutating_the_period(): void
    {
        Carbon::setTestNow('2026-08-17 09:00:00');
        try {
            [$period, , $students, $template] = $this->reportingFoundation();
            $period->forceFill(['report_date' => null])->save();

            $preview = app(BuildAssessmentReportPreviewSnapshot::class)->build($period->fresh(), $template, $students[0]);

            $this->assertSame('Bogor, '.now()->translatedFormat('d F Y'), data_get($preview->snapshot_data, 'signatures.1.place_date'));
            $this->assertNull($period->fresh()->report_date);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_asts_identity_fixed_columns_reserve_class_and_semester_fields(): void
    {
        [$period, , $students, $template] = $this->reportingFoundation();
        $template->forceFill(['settings' => ['school_name' => 'SMA Template Test']])->save();

        $preview = app(BuildAssessmentReportPreviewSnapshot::class)->build($period, $template, $students[0]);
        $html = view('assessment.reports.asts', [
            'snapshot' => $preview->snapshot_data,
            'templateSettings' => data_get($preview->snapshot_data, 'template.settings', []),
            'pdfMode' => true,
        ])->render();

        $this->assertSame(2, substr_count($html, 'identity identity--asts'));
        $this->assertSame(2, substr_count($html, 'identity__label--asts-secondary">Semester'));
        $this->assertSame(2, substr_count($html, '<col class="identity__col--asts-primary-value">'));
        $this->assertSame(2, substr_count($html, '<col class="identity__col--asts-secondary-value">'));
        $this->assertSame(4, substr_count($html, 'identity__value--asts identity__value--asts-primary identity__value--asts-nowrap'));
        $this->assertSame(4, substr_count($html, 'identity__value--asts identity__value--asts-secondary identity__value--asts-nowrap'));
        $this->assertSame(2, substr_count($html, 'identity__label--asts-primary">NIS/NISN<'));
        $this->assertSame(5, substr_count($html, 'identity__separator--asts-left'));
        $this->assertSame(5, substr_count($html, 'identity__separator--asts-right'));
        $this->assertStringContainsString('.identity--asts .identity__label--asts-primary { width: 15%; }', $html);
        $this->assertStringContainsString('.identity--asts .identity__label--asts-secondary { width: 10%; }', $html);
        $this->assertStringContainsString('.identity--asts .identity__separator--asts-left { width: 2%; }', $html);
        $this->assertStringContainsString('.identity--asts .identity__separator--asts-right { width: 2%; }', $html);
        $this->assertStringContainsString('.identity--asts .identity__col--asts-primary-value { width: 47%; }', $html);
        $this->assertStringContainsString('.identity--asts .identity__col--asts-secondary-value { width: 24%; }', $html);
        $this->assertStringContainsString('.identity--asts .identity__value--asts-primary { width: 47%; }', $html);
        $this->assertStringContainsString('.identity--asts .identity__value--asts-secondary { width: 24%; }', $html);
        $this->assertStringContainsString('.identity--asts .identity__label, .identity--asts .identity__separator--asts, .identity--asts .identity__value--asts-nowrap { line-height: 1.2; vertical-align: middle; }', $html);
        $this->assertStringContainsString('.identity--asts td { font-size: var(--identity-font-size, 9pt); }', $html);
        $this->assertStringContainsString('.identity--asts .identity__value--asts-nowrap { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }', $html);
        $this->assertStringContainsString('SMA Template Test', $html);
    }

    public function test_report_letterheads_render_the_official_kop_defaults(): void
    {
        $foundation = 'YAYASAN DAR AL FURQON AL HAKIM';
        $schoolName = 'SMA AL FURQON BOARDING SCHOOL';
        $address = 'Jl. Untung Suropati 1 No.8 RT/RW 003/003, Kel. Cimone Jaya, Kec. Karawaci, Kota Tangerang, Banten';
        $contact = 'No Wa +6285178494207 | email : smaafbs@gmail.com | website: smaafbs.sch.id';
        $snapshot = ['school' => [], 'period' => [], 'student' => [], 'subjects' => [], 'homeroom' => [], 'signatures' => []];

        foreach (['asts', 'asas', 'asat'] as $report) {
            $html = view('assessment.reports.'.$report, [
                'snapshot' => $snapshot,
                'templateSettings' => [],
                'pdfMode' => false,
            ])->render();

            foreach ([$foundation, $schoolName, $address, $contact] as $value) {
                $this->assertStringContainsString($value, $html);
            }
        }

        $defaults = InstallAssessmentDefaults::defaultTemplates();
        foreach ($defaults as $template) {
            $this->assertSame($foundation, data_get($template, 'settings.foundation_name'));
            $this->assertSame($schoolName, data_get($template, 'settings.school_name'));
            $this->assertSame($address, data_get($template, 'settings.school_address'));
            $this->assertSame($contact, data_get($template, 'settings.school_contact'));
        }
    }

    public function test_asts_omits_the_extracurricular_column_when_no_valid_items_exist(): void
    {
        $snapshot = [
            'school' => [],
            'period' => [],
            'student' => [],
            'subjects' => [],
            'homeroom' => ['extracurricular_data' => [['name' => ''], []]],
            'signatures' => [],
        ];

        $empty = view('assessment.reports.asts', ['snapshot' => $snapshot, 'templateSettings' => [], 'pdfMode' => false])->render();
        $withItem = view('assessment.reports.asts', [
            'snapshot' => array_replace_recursive($snapshot, [
                'homeroom' => ['extracurricular_data' => [['name' => 'Pramuka', 'description' => 'Baik']]],
            ]),
            'templateSettings' => [],
            'pdfMode' => false,
        ])->render();

        $this->assertStringNotContainsString('Nama Ekstrakurikuler', $empty);
        $this->assertStringContainsString('asts-summary-grid--attendance-only', $empty);
        $this->assertStringContainsString('Nama Ekstrakurikuler', $withItem);
        $this->assertStringContainsString('Pramuka', $withItem);
        $this->assertSame(1, substr_count($withItem, '<td>Pramuka</td>'));
    }

    public function test_homeroom_teacher_can_preview_own_class_reports_but_not_another_class(): void
    {
        [$period, $ownRombel, $students, $template] = $this->reportingFoundation();
        $ownSnapshot = $this->snapshot($period, $students[0], $template, 1);
        $otherRombel = AssessmentPeriodRombel::query()->create([
            'assessment_period_id' => $period->getKey(),
            'source_rombel_id' => 102,
            'rombel_name_snapshot' => 'XI 2',
            'grade_level' => 'XI',
            'is_active' => true,
        ]);
        $otherStudent = AssessmentPeriodStudent::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $otherRombel->getKey(),
            'student_id' => 299,
            'nis_snapshot' => 'NIS299',
            'nisn_snapshot' => 'NISN299',
            'student_name_snapshot' => 'Siswa Kelas Lain',
            'gender_snapshot' => 'L',
            'rombel_name_snapshot' => 'XI 2',
            'is_active' => true,
        ]);
        $otherSnapshot = $this->snapshot($period, $otherStudent, $template, 1);
        AssessmentPeriodHomeroom::factory()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_rombel_id' => $ownRombel->getKey(),
            'teacher_id' => 501,
            'rombel_name_snapshot' => 'XI 1',
        ]);
        Role::findOrCreate('wali_kelas', 'web');
        $homeroomTeacher = User::query()->create([
            'name' => 'Wali Kelas XI 1',
            'username' => 'wali-report-test',
            'password' => 'test-password',
            'guru_tendik_id' => 501,
            'module_access_levels' => ['penilaian' => 'view'],
        ]);
        $homeroomTeacher->assignRole('wali_kelas');

        $this->actingAs($homeroomTeacher);
        $this->assertTrue(AstsReports::canAccess());
        $this->assertTrue(Gate::allows('view', $ownSnapshot));
        $this->assertFalse(Gate::allows('view', $otherSnapshot));
        $this->assertTrue(Gate::allows('view', $ownRombel));
        $this->assertFalse(Gate::allows('view', $otherRombel));

        Livewire::test(AstsReports::class)
            ->set('periodId', $period->getKey())
            ->set('templateId', $template->getKey())
            ->set('previewClassId', $ownRombel->getKey())
            ->assertSee($students[0]->student_name_snapshot)
            ->assertSee('Preview')
            ->assertSee('Download')
            ->assertSee('Download ZIP Kelas')
            ->assertDontSee('Distribusi opsional')
            ->assertDontSee('XI 2')
            ->assertDontSee($otherStudent->student_name_snapshot);

        $this->get(route('assessment.reports.preview', $ownSnapshot))->assertOk();
        $this->get(route('assessment.reports.preview', $otherSnapshot))->assertRedirect();
        $this->get(route('assessment.reports.live-preview', [$period, $template, $students[0]]))->assertOk();
        $this->get(route('assessment.reports.live-preview', [$period, $template, $otherStudent]))->assertRedirect();
        $this->get(route('assessment.reports.live-download', [$period, $template, $students[0]]))->assertOk();
        $this->get(route('assessment.reports.live-download', [$period, $template, $otherStudent]))->assertRedirect();
    }

    public function test_report_authorization_failure_links_to_period_report_access_help(): void
    {
        [$period] = $this->reportingFoundation();
        $this->actingAs(User::query()->findOrFail(99));

        $notification = AssessmentActionFailureNotification::make(
            new AuthorizationException('This action is unauthorized.'),
            'Mulai Ulang dengan Revisi Baru',
            $period,
        )->toArray();

        $this->assertSame('Cek Hak Akses Cetak Rapor', $notification['actions'][0]['label']);
        $this->assertSame(
            AstsReports::getUrl(['period' => $period->getKey()]),
            $notification['actions'][0]['url'],
        );
        $this->assertStringContainsString('hak untuk mengubah revisi', (string) $notification['body']);
    }

    public function test_zip_export_failure_notification_returns_to_the_selected_report_period(): void
    {
        [$period] = $this->reportingFoundation();
        $this->actingAs(User::query()->findOrFail(99));

        $notification = AssessmentActionFailureNotification::make(
            new RuntimeException('Ekstensi ZIP PHP belum aktif.'),
            'Download ZIP Rapor Kelas Ini',
            $period,
        )->toArray();

        $this->assertSame('Buka Cetak Rapor Periode Ini', $notification['actions'][0]['label']);
        $this->assertSame(AstsReports::getUrl(['period' => $period->getKey()]), $notification['actions'][0]['url']);
    }

    public function test_asts_class_student_list_uses_live_preview_and_download_without_jobs(): void
    {
        Queue::fake();
        [$period, $rombel, $students, $template] = $this->reportingFoundation(studentCount: 2);
        $this->actingAs(User::query()->findOrFail(99));

        $page = Livewire::test(AstsReports::class)
            ->set('periodId', $period->getKey())
            ->set('templateId', $template->getKey())
            ->set('previewClassId', $rombel->getKey());

        $rows = $page->instance()->getSimpleClassStudentRows();

        $this->assertCount(2, $rows);
        $this->assertSame(route('assessment.reports.live-preview', [$period, $template, $students[0]]), $rows[0]['preview_url']);
        $this->assertSame(route('assessment.reports.live-download', [$period, $template, $students[0]]), $rows[0]['download_url']);
        $page->assertSee('Download ZIP Kelas');
        $page->assertSee($students[0]->student_name_snapshot);
        Queue::assertNothingPushed();
    }

    public function test_class_report_zip_download_renders_each_student_without_queue_or_jobs(): void
    {
        Queue::fake();
        Storage::fake('local');
        [$period, $rombel, $students, $template] = $this->reportingFoundation(studentCount: 2);
        $this->snapshot($period, $students[0], $template, 1);
        $this->actingAs(User::query()->findOrFail(99));

        $response = $this->get(route('assessment.reports.class.zip', [
            'assessmentPeriod' => $period,
            'reportTemplate' => $template,
            'periodRombel' => $rombel,
        ]));

        $response->assertOk()->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString('rapor-asts-asts-2526-ganjil-xi-1.zip', (string) $response->headers->get('Content-Disposition'));
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $this->assertSame(2, $zip->numFiles);
        $this->assertStringEndsWith('.pdf', (string) $zip->getNameIndex(0));
        $this->assertStringStartsWith('%PDF', (string) $zip->getFromIndex(0));
        $zip->close();
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_class_report_zip_rejects_a_class_from_another_period(): void
    {
        [$period, $rombel, , $template] = $this->reportingFoundation();
        $otherPeriod = $period->replicate();
        $otherPeriod->code = 'ASAS-2526-GANJIL';
        $otherPeriod->name = 'ASAS 2025/2026 Ganjil';
        $otherPeriod->type = AssessmentType::ASAS;
        $otherPeriod->save();
        $otherRombel = $rombel->replicate();
        $otherRombel->assessment_period_id = $otherPeriod->getKey();
        $otherRombel->save();
        $this->actingAs(User::query()->findOrFail(99));

        $this->get(route('assessment.reports.class.zip', [
            'assessmentPeriod' => $period,
            'reportTemplate' => $template,
            'periodRombel' => $otherRombel,
        ]))->assertNotFound();
    }

    public function test_live_preview_remains_available_when_preflight_is_incomplete_without_creating_jobs_or_snapshots(): void
    {
        Storage::fake('local');
        [$period, , $students, $template] = $this->reportingFoundation();
        $template->forceFill([
            'settings' => [
                'school_name' => 'SMA AFBS',
                'report_title' => 'Rapor ASTS',
                'layout' => ['sections' => AssessmentReportLayout::threePageDefaults()],
            ],
        ])->save();
        $this->actingAs(User::query()->findOrFail(99));

        $preflight = app(AssessmentReportPreflight::class)->inspect($period, $template);
        $this->assertFalse($preflight['ready']);
        $this->assertNotEmpty($preflight['groups']['master']['issues']);

        $response = $this->get(route('assessment.reports.live-preview', [
            'assessmentPeriod' => $period,
            'reportTemplate' => $template,
            'periodStudent' => $students[0],
        ]));

        $response->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $streamResponse = $this->get(route('assessment.reports.live-preview-stream', [
            'assessmentPeriod' => $period,
            'reportTemplate' => $template,
            'periodStudent' => $students[0],
        ]));
        $streamResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', (string) $streamResponse->headers->get('Cache-Control'));
        $this->assertDatabaseCount('assessment_report_snapshots', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_asts_live_preview_uses_class_assignments_and_blanks_missing_scores_without_jobs(): void
    {
        Queue::fake();
        [$period, $rombel, $students, $template] = $this->reportingFoundation();
        $assignedSubject = Subject::query()->create([
            'code' => 'FIS-ASSIGNED',
            'name' => 'Fisika Kelas Ini',
            'report_group_code' => 'A',
            'report_group_name' => 'Kelompok A',
            'report_group_sort_order' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $otherSubject = Subject::query()->create([
            'code' => 'KIM-OTHER',
            'name' => 'Kimia Kelas Lain',
            'report_group_code' => 'A',
            'report_group_name' => 'Kelompok A',
            'report_group_sort_order' => 1,
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $otherRombel = AssessmentPeriodRombel::query()->create([
            'assessment_period_id' => $period->getKey(),
            'source_rombel_id' => 102,
            'rombel_name_snapshot' => 'XI 2',
            'grade_level' => 'XI',
            'is_active' => true,
        ]);
        foreach ([[$rombel, $assignedSubject], [$otherRombel, $otherSubject]] as [$assignedRombel, $subject]) {
            AssessmentPeriodAssignment::query()->create([
                'assessment_period_id' => $period->getKey(),
                'assessment_period_rombel_id' => $assignedRombel->getKey(),
                'teacher_id' => 41,
                'assessment_subject_id' => $subject->getKey(),
                'teacher_name_snapshot' => 'Guru Mapel',
                'subject_name_snapshot' => $subject->name,
                'subject_group_code_snapshot' => 'A',
                'subject_group_name_snapshot' => 'Kelompok A',
                'subject_group_sort_order_snapshot' => 1,
                'subject_sort_order_snapshot' => $subject->sort_order,
                'rombel_name_snapshot' => $assignedRombel->rombel_name_snapshot,
                'status' => 'locked',
                'lock_version' => 1,
            ]);
        }

        $preview = app(BuildAssessmentReportPreviewSnapshot::class)->build($period, $template, $students[0]);

        $this->assertSame([[
            'subject_id' => $assignedSubject->getKey(),
            'name' => 'Fisika Kelas Ini',
            'teacher_name' => 'Guru Mapel',
            'group_code' => 'A',
            'group_name' => 'Kelompok A',
            'group_sort_order' => 1,
            'sort_order' => 1,
            'final_score' => null,
            'predicate' => null,
            'description' => null,
            'calculation_detail' => [],
            'formula_version' => null,
        ]], data_get($preview->snapshot_data, 'subjects'));
        $snapshot = app(CreateReportSnapshotsAction::class)->execute($period, $template, generatedBy: 99)->sole();
        $this->assertSame(data_get($preview->snapshot_data, 'subjects'), data_get($snapshot->snapshot_data, 'subjects'));
        $rendered = view('assessment.reports.asts', [
            'snapshot' => $preview->snapshot_data,
            'templateSettings' => [],
            'pdfMode' => false,
        ])->render();
        $this->assertStringContainsString('Fisika Kelas Ini', $rendered);
        $this->assertStringNotContainsString('Kimia Kelas Lain', $rendered);
        $this->assertStringNotContainsString('(belum diisi)', $rendered);
        $this->assertStringContainsString('<td class="scores__score"></td><td class="scores__predicate"></td>', $rendered);

        $this->actingAs(User::query()->findOrFail(99));
        $previewResponse = $this->get(route('assessment.reports.live-preview', [$period, $template, $students[0]]));
        $previewResponse->assertOk()->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('no-store', (string) $previewResponse->headers->get('Cache-Control'));

        $streamResponse = $this->get(route('assessment.reports.live-preview-stream', [$period, $template, $students[0]]));
        $streamResponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', (string) $streamResponse->headers->get('Cache-Control'));
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('assessment_report_snapshots', 1);
    }

    public function test_live_reports_exclude_retained_results_when_the_matrix_cell_is_blank(): void
    {
        [$period, $rombel, $students, $template] = $this->reportingFoundation();
        $subject = Subject::query()->create([
            'code' => 'FIS-XII-3',
            'name' => 'Fisika',
            'is_active' => true,
        ]);
        $matrix = TeachingAssignment::query()->create([
            'assessment_semester_id' => $period->assessment_semester_id,
            'assessment_subject_id' => $subject->getKey(),
            'teacher_id' => 41,
            'rombel_id' => $rombel->source_rombel_id,
            'teacher_name_snapshot' => 'Guru Lama',
            'subject_name_snapshot' => 'Fisika',
            'rombel_name_snapshot' => $rombel->rombel_name_snapshot,
            'is_active' => false,
        ]);
        $assignment = AssessmentPeriodAssignment::query()->create([
            'assessment_period_id' => $period->getKey(),
            'source_teaching_assignment_id' => $matrix->getKey(),
            'assessment_period_rombel_id' => $rombel->getKey(),
            'teacher_id' => 41,
            'assessment_subject_id' => $subject->getKey(),
            'teacher_name_snapshot' => 'Guru Lama',
            'subject_name_snapshot' => 'Fisika',
            'rombel_name_snapshot' => $rombel->rombel_name_snapshot,
            'status' => 'locked',
            'lock_version' => 1,
        ]);
        StudentSubjectResult::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_student_id' => $students[0]->getKey(),
            'assessment_period_assignment_id' => $assignment->getKey(),
            'final_score' => 91,
            'predicate' => 'A',
            'description' => 'Nilai lama tetap tersimpan.',
            'calculation_detail' => [],
            'formula_version' => 'v1',
        ]);

        $preview = app(BuildAssessmentReportPreviewSnapshot::class)->build($period, $template, $students[0]);
        $snapshot = app(CreateReportSnapshotsAction::class)->execute($period, $template, generatedBy: 99)->sole();

        $this->assertSame([], data_get($preview->snapshot_data, 'subjects'));
        $this->assertSame([], data_get($snapshot->snapshot_data, 'subjects'));
        $this->assertDatabaseHas('assessment_period_assignments', ['id' => $assignment->getKey()]);
        $this->assertDatabaseHas('assessment_student_subject_results', [
            'assessment_period_assignment_id' => $assignment->getKey(),
            'final_score' => 91,
        ]);
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

    public function test_primary_template_activation_is_atomic_and_rejects_incomplete_or_future_templates(): void
    {
        [, , , $current] = $this->reportingFoundation();
        $current->forceFill([
            'settings' => [
                'school_name' => 'SMA AFBS',
                'principal_name' => 'Kepala Sekolah',
                'place' => 'Bogor',
            ],
        ])->save();
        $candidate = ReportTemplate::query()->create([
            'code' => 'ASTS-SMAAFBS-3P',
            'type' => AssessmentType::ASTS,
            'name' => 'SMA AFBS 3 Halaman',
            'version' => 1,
            'view_path' => 'assessment.reports.asts',
            'settings' => [
                'school_name' => 'SMA AFBS',
                'principal_name' => 'Kepala Sekolah',
                'place' => 'Bogor',
            ],
            'is_active' => false,
        ]);
        $actor = User::query()->findOrFail(99);

        app(SetPrimaryReportTemplateAction::class)->execute($actor, $candidate);

        $this->assertFalse($current->fresh()->is_active);
        $this->assertTrue($candidate->fresh()->is_active);
        $this->assertSame(
            1,
            ReportTemplate::query()->where('type', AssessmentType::ASTS->value)->where('is_active', true)->count(),
        );

        $incomplete = ReportTemplate::query()->create([
            'code' => 'ASTS-INCOMPLETE',
            'type' => AssessmentType::ASTS,
            'name' => 'Belum Lengkap',
            'version' => 1,
            'view_path' => 'assessment.reports.asts',
            'settings' => ['school_name' => 'SMA AFBS'],
            'is_active' => false,
        ]);
        try {
            app(SetPrimaryReportTemplateAction::class)->execute($actor, $incomplete);
            $this->fail('Template belum lengkap seharusnya tidak dapat dijadikan utama.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template', $exception->errors());
        }

        $future = ReportTemplate::query()->create([
            'code' => 'ASTS-FUTURE',
            'type' => AssessmentType::ASTS,
            'name' => 'Belum Berlaku',
            'version' => 1,
            'view_path' => 'assessment.reports.asts',
            'settings' => [
                'school_name' => 'SMA AFBS',
                'principal_name' => 'Kepala Sekolah',
                'place' => 'Bogor',
            ],
            'effective_from' => now()->addDay(),
            'is_active' => false,
        ]);
        try {
            app(SetPrimaryReportTemplateAction::class)->execute($actor, $future);
            $this->fail('Template dengan tanggal berlaku di masa depan seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('template', $exception->errors());
        }

        $this->assertTrue($candidate->fresh()->is_active);
    }

    public function test_cancel_open_revisions_preserves_completed_history_and_allows_revision_three_without_queue(): void
    {
        Queue::fake();
        [$period, $rombel, $students, $template] = $this->reportingFoundation();
        $firstSnapshots = app(CreateReportSnapshotsAction::class)->execute($period, $template, generatedBy: 99);
        $firstRun = ReportGenerationRun::query()->firstOrFail();
        $completed = $firstSnapshots->firstOrFail();
        $completed->forceFill([
            'generation_status' => 'completed',
            'pdf_path' => 'assessment-reports/history.pdf',
            'checksum' => str_repeat('a', 64),
        ])->save();
        $secondRun = ReportGenerationRun::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'revision' => 2,
            'status' => 'prepared',
            'total_students' => 1,
            'completed_students' => 0,
            'total_classes' => 1,
            'completed_classes' => 0,
            'requested_by' => 99,
        ]);
        $secondSnapshot = ReportSnapshot::query()->create([
            'assessment_period_id' => $period->getKey(),
            'assessment_period_student_id' => $students[0]->getKey(),
            'assessment_report_template_id' => $template->getKey(),
            'assessment_report_generation_run_id' => $secondRun->getKey(),
            'revision' => 2,
            'template_version' => 1,
            'snapshot_data' => ['student' => ['name' => 'Siswa 1']],
            'generation_status' => 'not_scheduled',
            'generated_by' => 99,
        ]);

        $result = app(CancelOpenReportRevisionsAction::class)->execute(
            User::query()->findOrFail(99),
            $period,
            $template,
            'Revisi uji lama digantikan sebelum pilot produksi.',
        );

        $this->assertSame(2, $result['runs']);
        $this->assertSame('cancelled', $firstRun->fresh()->status->value);
        $this->assertSame('cancelled', $secondRun->fresh()->status->value);
        $this->assertSame('completed', $completed->fresh()->generation_status->value);
        $this->assertSame('cancelled', $secondSnapshot->fresh()->generation_status->value);

        $third = app(CreateReportSnapshotsAction::class)->execute(
            $period,
            $template,
            generatedBy: 99,
            regenerate: true,
            reason: 'Menyiapkan revisi tiga sesudah rekonsiliasi.',
        );

        $this->assertSame(3, (int) $third->firstOrFail()->revision);
        $this->assertSame('prepared', ReportGenerationRun::query()->latest('revision')->firstOrFail()->status->value);
        Queue::assertNothingPushed();
    }

    public function test_asas_preflight_requires_semester_status_when_enabled_by_period_and_layout(): void
    {
        [$period, , , $template] = $this->reportingFoundation();
        $period->forceFill([
            'type' => AssessmentType::ASAS,
            'settings' => ['collect_promotion_status' => true],
        ])->save();
        $template->forceFill([
            'type' => AssessmentType::ASAS,
            'view_path' => 'assessment.reports.asas',
            'settings' => [
                'school_name' => 'SMA AFBS',
                'principal_name' => 'Kepala Sekolah',
                'place' => 'Bogor',
                'layout' => [
                    'version' => AssessmentReportLayout::VERSION,
                    'sections' => AssessmentReportLayout::threePageAsasDefaults(),
                ],
            ],
        ])->save();

        $preflight = app(AssessmentReportPreflight::class)->inspect($period->fresh(), $template->fresh());
        $codes = collect($preflight['groups'])->flatMap(
            fn (array $group): array => array_column($group['issues'], 'code'),
        );

        $this->assertTrue($codes->contains('semester_status_missing'));
    }

    public function test_reconcile_command_installs_asas_and_selects_one_primary_per_type_without_jobs(): void
    {
        Queue::fake();
        [$period, , , $standard] = $this->reportingFoundation();
        $standard->forceFill(['is_active' => false])->save();
        $astsThreePage = ReportTemplate::query()->create([
            'code' => 'ASTS-SMAAFBS-3P',
            'type' => AssessmentType::ASTS,
            'name' => 'SMA AFBS 3 Halaman · ASTS',
            'version' => 1,
            'view_path' => 'assessment.reports.asts',
            'settings' => [
                'school_name' => 'SMA AFBS',
                'school_address' => 'Bogor',
                'principal_name' => 'Kepala Sekolah',
                'principal_identifier' => 'NIY-001',
                'place' => 'Bogor',
                'watermark_enabled' => false,
                'layout' => [
                    'version' => AssessmentReportLayout::VERSION,
                    'sections' => AssessmentReportLayout::threePageDefaults(),
                ],
            ],
            'is_active' => true,
        ]);
        app(CreateReportSnapshotsAction::class)->execute(
            $period,
            $astsThreePage,
            generatedBy: 99,
        );

        $this->assertSame(0, Artisan::call('assessment:reconcile-report-templates'));
        $this->assertDatabaseMissing('assessment_report_templates', ['code' => 'ASAS-SMAAFBS-3P']);

        $this->assertSame(0, Artisan::call('assessment:reconcile-report-templates', [
            '--apply' => true,
            '--actor' => 99,
            '--period' => $period->getKey(),
            '--cancel-open' => true,
            '--prepare-new' => true,
            '--sync-kop' => true,
        ]));

        $asas = ReportTemplate::query()->where('code', 'ASAS-SMAAFBS-3P')->firstOrFail();
        $this->assertTrue($asas->is_active);
        $this->assertSame('Kepala Sekolah', data_get($asas->settings, 'principal_name'));
        $this->assertSame('YAYASAN DAR AL FURQON AL HAKIM', data_get($asas->settings, 'foundation_name'));
        $this->assertSame('SMA AL FURQON BOARDING SCHOOL', data_get($asas->settings, 'school_name'));
        $this->assertSame('No Wa +6285178494207 | email : smaafbs@gmail.com | website: smaafbs.sch.id', data_get($asas->settings, 'school_contact'));
        $this->assertTrue(app(AssessmentReportLayout::class)->requiresSemesterStatus($asas->settings));

        // Setiap jenis yang PUNYA template harus punya tepat satu template utama.
        // ASAT sengaja belum punya template sendiri — memakai template ASAS
        // (keputusan sekolah: bentuk rapornya sama), lihat
        // AssessmentType::templateTypeCandidates(). Karena itu jenis tanpa
        // template dilewati, bukan dianggap gagal.
        foreach (AssessmentType::cases() as $type) {
            $jumlahTemplate = ReportTemplate::query()->where('type', $type->value)->count();

            if ($jumlahTemplate === 0) {
                continue;
            }

            $this->assertSame(
                1,
                ReportTemplate::query()->where('type', $type->value)->where('is_active', true)->count(),
                "Jenis {$type->value} harus punya tepat satu template utama.",
            );
        }
        $this->assertSame('cancelled', ReportGenerationRun::query()->where('revision', 1)->firstOrFail()->status->value);
        $this->assertSame('prepared', ReportGenerationRun::query()->where('revision', 2)->firstOrFail()->status->value);
        Queue::assertNothingPushed();
    }

    public function test_template_resource_renders_editable_template_ui_and_detail_actions(): void
    {
        [, , , $template] = $this->reportingFoundation();
        $template->forceFill([
            'settings' => [
                'school_name' => 'SMA AFBS',
                'principal_name' => 'Kepala Sekolah',
                'place' => 'Bogor',
            ],
        ])->save();
        $this->actingAs(User::query()->findOrFail(99));

        $this->get(AssessmentReportTemplateResource::getUrl())
            ->assertOk()
            ->assertSee('Template Utama')
            ->assertSee('Buat Template dari Awal')
            ->assertSee('Panduan Template Rapor');

        $this->get(AssessmentReportTemplateResource::getUrl('view', ['record' => $template]))
            ->assertOk()
            ->assertSee('Sumber Data Rapor')
            ->assertSee('Riwayat Penggunaan')
            ->assertSee('Pratinjau Template')
            ->assertSee('Edit')
            ->assertSee('Hapus');

        $this->get(AssessmentReportTemplateResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Identitas Template')
            ->assertSee('Kop & Identitas Sekolah')
            ->assertSee('Watermark');

        $this->get(AssessmentReportTemplateResource::getUrl('edit', ['record' => $template]))
            ->assertOk()
            ->assertSee('Identitas Template')
            ->assertSee('Kop & Identitas Sekolah')
            ->assertSee('Judul & Identitas Siswa')
            ->assertSee('Jarak & Kerapian Tabel Rapor')
            ->assertSee('Tanda Tangan & Footer')
            ->assertSee('Simpan perubahan lalu klik Pratinjau Template');
    }

    public function test_used_template_remains_editable_and_shows_delete_action_with_integrity_warning(): void
    {
        [$period, , $students, $template] = $this->reportingFoundation();
        $this->snapshot($period, $students[0], $template, 1);
        $this->actingAs(User::query()->findOrFail(99));

        $usedTemplate = $template->fresh();

        $this->assertTrue(AssessmentReportTemplateResource::canEdit($usedTemplate));
        $this->assertTrue(AssessmentReportTemplateResource::canDelete($usedTemplate));
        $this->assertStringContainsString('snapshot rapor', AssessmentReportTemplateResource::deletionBlockedMessage($usedTemplate));

        $this->get(AssessmentReportTemplateResource::getUrl('edit', ['record' => $usedTemplate]))
            ->assertOk()
            ->assertSee('Template yang Sudah Dipakai')
            ->assertSee('Hapus')
            ->assertSee('Pratinjau Template');
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
