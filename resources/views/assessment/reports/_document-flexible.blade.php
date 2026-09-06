{{-- Layout konfigurabel lama dinormalisasi ke dua halaman A4 agar semua rapor konsisten. --}}
@include('assessment.reports._document', [
    'snapshot' => $snapshot,
    'templateSettings' => $templateSettings,
    'reportKind' => $reportKind,
])
