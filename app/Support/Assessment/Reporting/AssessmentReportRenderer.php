<?php

namespace App\Support\Assessment\Reporting;

use App\Models\Assessment\ReportSnapshot;
use App\Models\Assessment\ReportTemplate;
use Illuminate\Support\Collection;
use RuntimeException;

class AssessmentReportRenderer
{
    /**
     * The report-template table stores a view key, but only application-owned
     * templates are executable. Administrators never provide Blade/HTML.
     *
     * @var array<string, string>
     */
    private const ALLOWED_VIEWS = [
        'assessment.reports.asts' => 'assessment.reports.asts',
        'assessment.reports.asas' => 'assessment.reports.asas',
    ];

    public function renderStudent(ReportSnapshot $snapshot): string
    {
        $template = $this->templateFor($snapshot);
        $view = $this->allowedView($template, $snapshot->snapshot_data ?? []);

        return $this->pdf()
            ->loadView($view, [
                'snapshot' => $snapshot->snapshot_data ?? [],
                'templateSettings' => $this->templateSettings($snapshot, $template),
                'pdfMode' => true,
            ])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    /**
     * @param  Collection<int, ReportSnapshot>  $snapshots
     */
    public function renderClass(Collection $snapshots, ReportTemplate $template): string
    {
        if ($snapshots->isEmpty()) {
            throw new RuntimeException('Tidak ada snapshot rapor untuk kelas ini.');
        }

        $reports = $snapshots
            ->map(function (ReportSnapshot $snapshot) use ($template): array {
                $view = $this->allowedView($template, $snapshot->snapshot_data ?? []);

                return [
                    'snapshot' => $snapshot->snapshot_data ?? [],
                    'template_settings' => $this->templateSettings($snapshot, $template),
                    'view' => $view,
                ];
            })
            ->values();

        return $this->pdf()
            ->loadView('assessment.reports.class', [
                'reports' => $reports,
                'pdfMode' => true,
            ])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    private function templateFor(ReportSnapshot $snapshot): ReportTemplate
    {
        return ReportTemplate::query()->findOrFail($snapshot->assessment_report_template_id);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function allowedView(ReportTemplate $template, array $snapshot): string
    {
        $configured = trim((string) data_get(
            $snapshot,
            'template.view_path',
            $template->view_path,
        ));

        if (isset(self::ALLOWED_VIEWS[$configured])) {
            return self::ALLOWED_VIEWS[$configured];
        }

        $type = strtoupper((string) data_get($snapshot, 'period.type', $template->type));

        return match ($type) {
            'ASTS' => 'assessment.reports.asts',
            'ASAS' => 'assessment.reports.asas',
            default => throw new RuntimeException('Jenis template rapor tidak didukung.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function templateSettings(ReportSnapshot $snapshot, ReportTemplate $template): array
    {
        $frozen = data_get($snapshot->snapshot_data, 'template.settings');

        return is_array($frozen)
            ? $frozen
            : (is_array($template->settings) ? $template->settings : []);
    }

    private function pdf(): object
    {
        $pdf = app('dompdf.wrapper');
        $pdf->setOptions([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => false,
            'defaultFont' => 'DejaVu Sans',
        ]);

        return $pdf;
    }
}
