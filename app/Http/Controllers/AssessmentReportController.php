<?php

namespace App\Http\Controllers;

use App\Models\Assessment\AuditLog;
use App\Models\Assessment\ClassReportArtifact;
use App\Models\Assessment\ReportSnapshot;
use App\Support\Assessment\Reporting\AssessmentReportRenderer;
use App\Support\Assessment\Reporting\AssessmentReportShareService;
use App\Support\Assessment\Reporting\AssessmentReportStorage;
use App\Support\Assessment\Reporting\AssessmentReportWatermark;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentReportController extends Controller
{
    public function preview(
        ReportSnapshot $reportSnapshot,
        AssessmentReportRenderer $renderer,
        AssessmentReportWatermark $watermark,
    ): Response {
        $this->abortUnlessEnabled();
        Gate::authorize('view', $reportSnapshot);
        $reportSnapshot->loadMissing('template');
        $template = $reportSnapshot->template;
        abort_unless($template, 404);

        $snapshotData = is_array($reportSnapshot->snapshot_data) ? $reportSnapshot->snapshot_data : [];
        data_set(
            $snapshotData,
            'template.settings',
            $watermark->freezeSettings(is_array($template->settings) ? $template->settings : []),
        );
        data_set($snapshotData, 'meta.preview', true);

        $preview = new ReportSnapshot([
            'assessment_period_id' => $reportSnapshot->assessment_period_id,
            'assessment_period_student_id' => $reportSnapshot->assessment_period_student_id,
            'assessment_report_template_id' => $reportSnapshot->assessment_report_template_id,
            'assessment_report_generation_run_id' => $reportSnapshot->assessment_report_generation_run_id,
            'revision' => $reportSnapshot->revision,
            'template_version' => $template->version,
            'snapshot_data' => $snapshotData,
        ]);

        return response($renderer->renderStudent($preview), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="pratinjau-rapor.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    public function downloadSnapshot(
        ReportSnapshot $reportSnapshot,
        AssessmentReportStorage $storage,
    ): StreamedResponse {
        $this->abortUnlessEnabled();
        Gate::authorize('view', $reportSnapshot);
        $this->abortUnlessValid($reportSnapshot, $storage);
        $this->auditPrivateDownload($reportSnapshot, request());

        return $storage->disk()->download(
            $reportSnapshot->pdf_path,
            $storage->downloadName($reportSnapshot),
            $this->downloadHeaders(),
        );
    }

    public function downloadClass(
        ClassReportArtifact $classReportArtifact,
        AssessmentReportStorage $storage,
    ): StreamedResponse {
        $this->abortUnlessEnabled();
        Gate::authorize('view', $classReportArtifact);
        $this->abortUnlessValid($classReportArtifact, $storage);
        $this->auditPrivateDownload($classReportArtifact, request());

        return $storage->disk()->download(
            $classReportArtifact->pdf_path,
            $storage->downloadName($classReportArtifact),
            $this->downloadHeaders(),
        );
    }

    public function downloadShared(
        Request $request,
        string $token,
        AssessmentReportShareService $shares,
        AssessmentReportStorage $storage,
    ): StreamedResponse {
        $this->abortUnlessEnabled();
        $link = $shares->resolve($token);
        $link = $shares->recordDownload($link, $request->ip(), $request->userAgent());
        $snapshot = $link->getRelation('snapshot');

        return $storage->disk()->download(
            $snapshot->pdf_path,
            $storage->downloadName($snapshot),
            $this->downloadHeaders(),
        );
    }

    private function abortUnlessEnabled(): void
    {
        abort_unless((bool) config('assessment.enabled', false), 404);
    }

    private function abortUnlessValid(
        ReportSnapshot|ClassReportArtifact $report,
        AssessmentReportStorage $storage,
    ): void {
        $status = $report->generation_status;
        $status = $status instanceof \BackedEnum ? $status->value : (string) $status;

        abort_unless(
            $status === 'completed' && $storage->isValid($report->pdf_path, $report->checksum),
            404,
            'PDF rapor belum tersedia atau tidak valid.',
        );
    }

    private function auditPrivateDownload(
        ReportSnapshot|ClassReportArtifact $report,
        Request $request,
    ): void {
        AuditLog::query()->create([
            'assessment_period_id' => $report->assessment_period_id,
            'actor_id' => $request->user()?->getAuthIdentifier(),
            'event' => $report instanceof ReportSnapshot
                ? 'student_report_pdf_downloaded'
                : 'class_report_pdf_downloaded',
            'subject_type' => $report::class,
            'subject_id' => $report->getKey(),
            'old_values' => null,
            'new_values' => ['checksum' => $report->checksum],
            'reason' => null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function downloadHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}
