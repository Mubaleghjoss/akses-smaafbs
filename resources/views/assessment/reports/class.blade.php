<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rapor Kelas</title>
    @include('assessment.reports._styles')
</head>
<body>
    @foreach ($reports as $report)
        <div class="report-page-break">
            @include('assessment.reports._document', [
                'snapshot' => $report['snapshot'],
                'templateSettings' => $report['template_settings'],
                'reportKind' => strtoupper((string) data_get($report, 'snapshot.period.type', 'ASTS')),
            ])
        </div>
    @endforeach
</body>
</html>
