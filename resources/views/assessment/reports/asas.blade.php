<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rapor ASAS</title>
    @include('assessment.reports._styles')
</head>
<body>
    @if (data_get($templateSettings, 'layout.version') === \App\Support\Assessment\Reporting\AssessmentReportLayout::VERSION)
        @include('assessment.reports._document-flexible', ['reportKind' => 'ASAS'])
    @else
        @include('assessment.reports._document', ['reportKind' => 'ASAS'])
    @endif
</body>
</html>
