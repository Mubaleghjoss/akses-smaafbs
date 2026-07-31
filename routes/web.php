<?php

use App\Contracts\SiteSettingsAccessor;
use App\Http\Controllers\Admin\AdminUserCredentialDocumentController;
use App\Http\Controllers\Admin\AssessmentMasterTemplateController;
use App\Http\Controllers\Admin\BerkasGuruDocumentController;
use App\Http\Controllers\Admin\BoardingBacaanAssessmentExportController;
use App\Http\Controllers\Admin\BoardingRapotDocumentController;
use App\Http\Controllers\Admin\DataSiswaExportController;
use App\Http\Controllers\Admin\DataSiswaImportReviewExportController;
use App\Http\Controllers\Admin\DataSiswaImportTemplateController;
use App\Http\Controllers\Admin\DataSiswaProfileExportController;
use App\Http\Controllers\Admin\ForceGuruPasswordChangeController;
use App\Http\Controllers\Admin\GuruTendikExportController;
use App\Http\Controllers\Admin\GuruTendikImportTemplateController;
use App\Http\Controllers\Admin\PerpustakaanLiterasiDispensationController;
use App\Http\Controllers\Admin\ProkerExportController;
use App\Http\Controllers\Admin\ProkerImportTemplateController;
use App\Http\Controllers\Admin\SarprasActivityDocumentController;
use App\Http\Controllers\Admin\SarprasBospInventoryDocumentController;
use App\Http\Controllers\Admin\SarprasMonthlyAgendaDocumentController;
use App\Http\Controllers\Admin\SarprasRoomInventoryDocumentController;
use App\Http\Controllers\Admin\UksRecordExportController;
use App\Http\Controllers\Admin\UksRecordImportTemplateController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AssessmentReportController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\GuruTendikProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\PerpustakaanLiteracyProgramController;
use App\Http\Controllers\SarprasBospInventoryPublicController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SurveiPublicController;
use App\Http\Middleware\AdminAwareVerifyCsrfToken;
use App\Support\Media\PublicImageOptimizer;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/admin/locale/{locale}', function (Request $request, string $locale) {
    if (! in_array($locale, ['id', 'en'], true)) {
        abort(404);
    }

    $request->session()->put('locale', $locale);

    return redirect()->back();
})->name('admin.locale');

Route::middleware('auth')->get(
    '/admin/prokers/import-template',
    ProkerImportTemplateController::class
)->name('admin.prokers.import-template');

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/admin/penilaian/master/template',
        AssessmentMasterTemplateController::class,
    )->name('admin.assessment.master-template');

    Route::get(
        '/admin/penilaian/rapor/siswa/{reportSnapshot}/download',
        [AssessmentReportController::class, 'downloadSnapshot'],
    )->name('assessment.reports.snapshot.download');

    Route::get(
        '/admin/penilaian/rapor/siswa/{reportSnapshot}/preview',
        [AssessmentReportController::class, 'preview'],
    )
        ->middleware('throttle:10,1')
        ->name('assessment.reports.preview');

    Route::get(
        '/admin/penilaian/rapor/pratinjau/{assessmentPeriod}/{reportTemplate}/{periodStudent}',
        [AssessmentReportController::class, 'livePreview'],
    )
        ->middleware('throttle:10,1')
        ->name('assessment.reports.live-preview');

    Route::get(
        '/admin/penilaian/rapor/kelas/{classReportArtifact}/download',
        [AssessmentReportController::class, 'downloadClass'],
    )->name('assessment.reports.class.download');
});

Route::get(
    '/rapor/penilaian/{token}',
    [AssessmentReportController::class, 'downloadShared'],
)
    ->middleware('throttle:'.max(1, (int) config('assessment.share_links.rate_limit_per_minute', 30)).',1')
    ->name('assessment.reports.shared.download');

Route::middleware('auth')
    ->prefix('/admin/perpustakaan-literasi-materials/{material}/dispensations/{student}')
    ->group(function (): void {
        Route::post('/', [PerpustakaanLiterasiDispensationController::class, 'store'])
            ->name('admin.perpustakaan-literasi.dispensations.store');
        Route::delete('/', [PerpustakaanLiterasiDispensationController::class, 'destroy'])
            ->name('admin.perpustakaan-literasi.dispensations.destroy');
    });

Route::middleware('auth')->get(
    '/admin/prokers/export/{periode_tahun}',
    ProkerExportController::class
)->whereNumber('periode_tahun')->name('admin.prokers.export');

Route::get(
    '/admin/data-siswa-tools/import-template',
    DataSiswaImportTemplateController::class
)->name('admin.data-siswa.import-template');

Route::get(
    '/admin/data-siswa-tools/import-review/{token}',
    DataSiswaImportReviewExportController::class
)->name('admin.data-siswa.import-review.export');

Route::middleware('auth')->get(
    '/admin/data-siswa-tools/export',
    DataSiswaExportController::class
)->name('admin.data-siswa.export');

Route::middleware('auth')->get(
    '/admin/data-siswa-tools/export-profile',
    DataSiswaProfileExportController::class
)->name('admin.data-siswa.export-profile');

Route::middleware('auth')->get(
    '/admin/guru-tendiks/import-template',
    GuruTendikImportTemplateController::class
)->name('admin.guru-tendiks.import-template');

Route::middleware('auth')->get(
    '/admin/guru-tendiks/export',
    GuruTendikExportController::class
)->name('admin.guru-tendiks.export');

Route::middleware('auth')->get(
    '/admin/uks-records/import-template',
    UksRecordImportTemplateController::class
)->name('admin.uks-records.import-template');

Route::middleware('auth')->get(
    '/admin/uks-records/export',
    UksRecordExportController::class
)->name('admin.uks-records.export');

Route::middleware('auth')->get(
    '/admin/boarding-pencapaians/{boardingPencapaian}/bacaan/export',
    BoardingBacaanAssessmentExportController::class
)->name('admin.boarding-pencapaians.bacaan.export');

Route::middleware('auth')->get(
    '/admin/boarding-rapots/print-all',
    [BoardingRapotDocumentController::class, 'printAllReady']
)->name('admin.boarding-rapots.print-all');

Route::middleware('auth')->prefix('/admin/boarding-rapots/{boardingRapot}')->group(function (): void {
    Route::get('/preview', [BoardingRapotDocumentController::class, 'preview'])
        ->name('admin.boarding-rapots.preview');
    Route::get('/rekap', [BoardingRapotDocumentController::class, 'rekap'])
        ->name('admin.boarding-rapots.rekap');
    Route::get('/print', [BoardingRapotDocumentController::class, 'print'])
        ->name('admin.boarding-rapots.print');
    Route::get('/export', [BoardingRapotDocumentController::class, 'export'])
        ->name('admin.boarding-rapots.export');
});

Route::middleware('auth')->prefix('/admin/berkas-gurus/{berkasGuru}')->group(function (): void {
    Route::get('/preview', [BerkasGuruDocumentController::class, 'preview'])
        ->name('admin.berkas-gurus.preview');
    Route::get('/download', [BerkasGuruDocumentController::class, 'download'])
        ->name('admin.berkas-gurus.download');
});

Route::middleware('auth')->prefix('/admin/sarpras-bosp-inventories')->group(function (): void {
    Route::get('/print', [SarprasBospInventoryDocumentController::class, 'print'])
        ->name('admin.sarpras-bosp-inventories.print');
    Route::get('/pdf', [SarprasBospInventoryDocumentController::class, 'pdf'])
        ->name('admin.sarpras-bosp-inventories.pdf');
    Route::get('/export', [SarprasBospInventoryDocumentController::class, 'export'])
        ->name('admin.sarpras-bosp-inventories.export');
    Route::get('/stickers', [SarprasBospInventoryDocumentController::class, 'stickers'])
        ->name('admin.sarpras-bosp-inventories.stickers');
    Route::get('/{sarprasBospInventory}/sticker', [SarprasBospInventoryDocumentController::class, 'sticker'])
        ->whereNumber('sarprasBospInventory')
        ->name('admin.sarpras-bosp-inventories.sticker');
});

Route::middleware('auth')->get(
    '/admin/sarpras-room-inventories/export-all',
    [SarprasRoomInventoryDocumentController::class, 'exportAll']
)->name('admin.sarpras-room-inventories.export-all');

Route::middleware('auth')->prefix('/admin/sarpras-room-inventories/{sarprasRoomInventory}')->group(function (): void {
    Route::get('/print', [SarprasRoomInventoryDocumentController::class, 'print'])
        ->name('admin.sarpras-room-inventories.print');
    Route::get('/pdf', [SarprasRoomInventoryDocumentController::class, 'pdf'])
        ->name('admin.sarpras-room-inventories.pdf');
    Route::get('/export', [SarprasRoomInventoryDocumentController::class, 'export'])
        ->name('admin.sarpras-room-inventories.export');
});

Route::middleware('auth')->prefix('/admin/sarpras-activities')->group(function (): void {
    Route::get('/print', [SarprasActivityDocumentController::class, 'print'])
        ->name('admin.sarpras-activities.print');
    Route::get('/pdf', [SarprasActivityDocumentController::class, 'pdf'])
        ->name('admin.sarpras-activities.pdf');
    Route::get('/export', [SarprasActivityDocumentController::class, 'export'])
        ->name('admin.sarpras-activities.export');
});

Route::middleware('auth')->prefix('/admin/sarpras-monthly-agendas')->group(function (): void {
    Route::get('/print', [SarprasMonthlyAgendaDocumentController::class, 'print'])
        ->name('admin.sarpras-monthly-agendas.print');
    Route::get('/pdf', [SarprasMonthlyAgendaDocumentController::class, 'pdf'])
        ->name('admin.sarpras-monthly-agendas.pdf');
    Route::get('/export', [SarprasMonthlyAgendaDocumentController::class, 'export'])
        ->name('admin.sarpras-monthly-agendas.export');
});

Route::middleware('auth')->prefix('/admin/user-credentials/{token}')->group(function (): void {
    Route::get('/print', [AdminUserCredentialDocumentController::class, 'print'])
        ->name('admin.user-credentials.print');
    Route::get('/export', [AdminUserCredentialDocumentController::class, 'export'])
        ->name('admin.user-credentials.export');
});

Route::middleware('auth')->post('/admin/force-guru-password-change', ForceGuruPasswordChangeController::class)
    ->name('admin.force-guru-password-change.update');

Route::get('/manifest.webmanifest', function () {
    /** @var SiteSettingsAccessor $settings */
    $settings = app(SiteSettingsAccessor::class);
    $siteSettings = $settings->all();
    $imageOptimizer = app(PublicImageOptimizer::class);

    $iconUrl = $siteSettings['favicon_path']
        ?? $siteSettings['logo_path']
        ?? asset('favicon.ico');
    $icon192Url = $imageOptimizer->pwaIconUrl($iconUrl, 192) ?? $iconUrl;
    $icon512Url = $imageOptimizer->pwaIconUrl($iconUrl, 512) ?? $iconUrl;

    return response()->json([
        'name' => $siteSettings['pwa_app_name'],
        'short_name' => $siteSettings['pwa_short_name'],
        'description' => $siteSettings['default_seo_description'],
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => $siteSettings['theme_color'],
        'icons' => [
            [
                'src' => $icon192Url,
                'sizes' => '192x192',
                'type' => 'image/png',
            ],
            [
                'src' => $icon512Url,
                'sizes' => '512x512',
                'type' => 'image/png',
            ],
        ],
    ])->withHeaders([
        'Content-Type' => 'application/manifest+json',
        'Cache-Control' => 'public, max-age=86400',
    ]);
})->withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    AdminAwareVerifyCsrfToken::class,
    ValidateCsrfToken::class,
])->name('manifest.webmanifest');

Route::get('/service-worker.js', function () {
    $script = <<<'JS'
const CACHE_NAME = 'akses-public-shell-v6';
const PASSTHROUGH_PREFIXES = [
    '/admin',
    '/livewire',
    '/storage',
    '/build',
    '/login',
    '/logout',
    '/register',
    '/password',
    '/sanctum',
    '/broadcasting',
    '/up',
    '/tagihan',
];

const shouldPassThrough = (request, url) => {
    if (request.method !== 'GET') {
        return true;
    }

    if (request.headers.get('X-Livewire') || request.headers.get('X-CSRF-TOKEN')) {
        return true;
    }

    if (PASSTHROUGH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        return true;
    }

    return false;
};

const offlineResponse = () => new Response(
    '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#16a34a"><title>Koneksi terputus</title></head><body style="margin:0;background:#f8fafc;color:#0f172a"><main style="font-family:system-ui,sans-serif;max-width:36rem;margin:12vh auto;padding:1.25rem;line-height:1.6"><section style="border:1px solid #cbd5e1;border-radius:1rem;background:#fff;padding:1.25rem"><h1 style="margin:0;font-size:1.25rem">Koneksi ke server terputus</h1><p style="margin:.75rem 0 0">Jawaban tidak dikirim dari halaman offline. Periksa Wi-Fi atau jaringan seluler, lalu muat ulang halaman.</p><button type="button" onclick="location.reload()" style="margin-top:1rem;border:0;border-radius:.75rem;background:#16a34a;color:#fff;padding:.7rem 1rem;font-weight:700">Muat ulang</button></section></main></body></html>',
    {
        status: 503,
        statusText: 'Service Unavailable',
        headers: {
        'Cache-Control': 'no-store',
            'Content-Type': 'text/html; charset=UTF-8',
        },
    },
);

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys
                .filter((key) => key.startsWith('akses-public-shell-') && key !== CACHE_NAME)
                .map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Admin, Livewire, uploaded files, and hashed build assets use the browser's
    // native HTTP cache. Returning here is important: no forced no-store fetch.
    if (shouldPassThrough(request, url)) {
        return;
    }

    // Dynamic HTML (including literacy forms and CSRF tokens) is never cached.
    // Only provide a tiny offline explanation when a public navigation fails.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request, { cache: 'no-store' }).catch(offlineResponse));
    }
});
JS;

    return response($script)
        ->header('Content-Type', 'application/javascript; charset=UTF-8')
        ->header('Service-Worker-Allowed', '/')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
})->withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    AdminAwareVerifyCsrfToken::class,
    ValidateCsrfToken::class,
])->name('service-worker');

Route::get('/', HomeController::class)->name('home');
Route::get('/student-search', [HomeController::class, 'studentSearch'])
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        AdminAwareVerifyCsrfToken::class,
        ValidateCsrfToken::class,
    ])
    ->middleware('throttle:30,1')
    ->name('home.student-search');
Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
Route::middleware('throttle:public_agenda_events')
    ->get('/agenda/events', [AgendaController::class, 'events'])
    ->name('agenda.events');

Route::get('/berita', [NewsController::class, 'index'])->name('news.index');
Route::get('/berita/{news}', [NewsController::class, 'show'])->whereNumber('news')->name('news.show');

Route::get('/siswa', [StudentController::class, 'index'])->name('students.index');
Route::get('/siswa/{student}', [StudentController::class, 'show'])->whereNumber('student')->name('students.show');
Route::get('/profil/guru-tendik/{guruTendik}', GuruTendikProfileController::class)
    ->whereNumber('guruTendik')
    ->name('guru-tendik.profile');
Route::middleware('throttle:30,1')
    ->get('/s/b/{sarprasBospInventory}', SarprasBospInventoryPublicController::class)
    ->whereNumber('sarprasBospInventory')
    ->name('sarpras.bosp-inventories.show');

Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/survei/{token}', [SurveiPublicController::class, 'show'])
        ->name('survei.public.show');
    Route::post('/survei/{token}', [SurveiPublicController::class, 'submit'])
        ->name('survei.public.submit');
});

Route::get('/tagihan', [BillingController::class, 'index'])->name('billing.index');
Route::middleware('throttle:public_billing_lookup')->group(function (): void {
    Route::get('/tagihan/detail', [BillingController::class, 'show'])->name('billing.show');
    Route::get('/tagihan/bayar', [BillingController::class, 'payForm'])->name('billing.pay.form');
    Route::get('/tagihan/{code}', [BillingController::class, 'show'])->name('billing.show.code');
});
Route::middleware('throttle:public_billing_payment_upload')
    ->post('/tagihan/bayar', [BillingController::class, 'paySubmit'])
    ->name('billing.pay.submit');

Route::redirect('/perpus', '/perpustakaan/aktivitas-literasi/form');
Route::redirect('/perpus/index.php', '/perpustakaan/aktivitas-literasi/form');
Route::redirect('/perpus/cari_buku.php', '/perpustakaan');
Route::redirect('/perpus/hasil_literasi.php', '/perpustakaan/hasil-literasi');
Route::get('/perpustakaan', [LibraryController::class, 'index'])->name('library.index');
Route::redirect('/literasi', '/perpustakaan/program-literasi-numerasi')
    ->name('library.literacy.shortcut');
Route::redirect('/perpustakaan/literacy-habituation-program', '/perpustakaan/program-literasi-numerasi');
Route::get('/perpustakaan/literacy-habituation-program/edit/{code}', function (string $code) {
    return redirect()->route('library.literacy.edit', ['code' => $code], 301);
})->where('code', '[A-Za-z0-9-]+');
Route::redirect('/perpustakaan/literacy-habituation-program/edit', '/perpustakaan/program-literasi-numerasi/edit');
Route::get('/perpustakaan/literacy-habituation-program/{slug}', function (string $slug) {
    return redirect()->route('library.literacy.show', ['slug' => $slug], 301);
});
Route::get('/perpustakaan/program-literasi-numerasi', [PerpustakaanLiteracyProgramController::class, 'index'])
    ->name('library.literacy.index');
Route::get('/perpustakaan/program-literasi-numerasi/edit', [PerpustakaanLiteracyProgramController::class, 'editLookup'])
    ->name('library.literacy.edit.lookup');
Route::post('/perpustakaan/program-literasi-numerasi/edit/{code}/integrity', [PerpustakaanLiteracyProgramController::class, 'recordIntegrity'])
    ->middleware('throttle:literacy_integrity')
    ->where('code', '[A-Za-z0-9-]+')
    ->name('library.literacy.integrity');
Route::get('/perpustakaan/program-literasi-numerasi/edit/{code}', [PerpustakaanLiteracyProgramController::class, 'edit'])
    ->where('code', '[A-Za-z0-9-]+')
    ->name('library.literacy.edit');
Route::get('/perpustakaan/program-literasi-numerasi/selesai', [PerpustakaanLiteracyProgramController::class, 'completed'])
    ->name('library.literacy.completed');
Route::get('/perpustakaan/program-literasi-numerasi/{slug}/social-thumbnail.jpg', [PerpustakaanLiteracyProgramController::class, 'socialThumbnail'])
    ->name('library.literacy.social-thumbnail');
Route::post('/perpustakaan/program-literasi-numerasi/edit/{code}', [PerpustakaanLiteracyProgramController::class, 'update'])
    ->middleware('throttle:literacy_submit')
    ->where('code', '[A-Za-z0-9-]+')
    ->name('library.literacy.update');
Route::post('/perpustakaan/program-literasi-numerasi/{slug}/submission-event', [PerpustakaanLiteracyProgramController::class, 'recordSubmissionEvent'])
    ->middleware('throttle:literacy_events')
    ->name('library.literacy.submission-event');
Route::get('/perpustakaan/program-literasi-numerasi/{slug}', [PerpustakaanLiteracyProgramController::class, 'show'])
    ->name('library.literacy.show');
Route::post('/perpustakaan/program-literasi-numerasi/{slug}/submission-ticket', [PerpustakaanLiteracyProgramController::class, 'requestStoreTicket'])
    ->middleware('throttle:literacy_queue_ticket')
    ->name('library.literacy.queue.store');
Route::post('/perpustakaan/program-literasi-numerasi/edit/{code}/submission-ticket', [PerpustakaanLiteracyProgramController::class, 'requestUpdateTicket'])
    ->middleware('throttle:literacy_queue_ticket')
    ->where('code', '[A-Za-z0-9-]+')
    ->name('library.literacy.queue.update');
Route::get('/perpustakaan/program-literasi-numerasi/submission-queue/{token}', [PerpustakaanLiteracyProgramController::class, 'submissionTicketStatus'])
    ->middleware('throttle:literacy_queue_status')
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('library.literacy.queue.status');
Route::delete('/perpustakaan/program-literasi-numerasi/submission-queue/{token}', [PerpustakaanLiteracyProgramController::class, 'cancelSubmissionTicket'])
    ->middleware('throttle:literacy_queue_status')
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('library.literacy.queue.cancel');
Route::post('/perpustakaan/program-literasi-numerasi/{slug}', [PerpustakaanLiteracyProgramController::class, 'store'])
    ->middleware('throttle:literacy_submit')
    ->name('library.literacy.store');
Route::get('/perpustakaan/aktivitas-literasi', [LibraryController::class, 'activities'])->name('library.activities');
Route::get('/perpustakaan/aktivitas-literasi/export', [LibraryController::class, 'exportActivities'])->name('library.activities.export');
Route::get('/perpustakaan/aktivitas-literasi/form', [LibraryController::class, 'createActivity'])->name('library.activities.create');
Route::post('/perpustakaan/aktivitas-literasi/form', [LibraryController::class, 'storeActivity'])
    ->middleware('throttle:30,1')
    ->name('library.activities.store');
Route::get('/perpustakaan/hasil-literasi', [LibraryController::class, 'result'])->name('library.activities.result');
Route::get('/perpustakaan/hasil-literasi/lookup', [LibraryController::class, 'lookupResult'])
    ->middleware('throttle:30,1')
    ->name('library.activities.result.lookup');
Route::post('/perpustakaan/hasil-literasi', [LibraryController::class, 'storeResult'])
    ->middleware('throttle:30,1')
    ->name('library.activities.result.store');
Route::get('/perpustakaan/buku/{book}', [LibraryController::class, 'show'])->whereNumber('book')->name('library.show');
Route::middleware('throttle:public_library_downloads')
    ->get('/perpustakaan/buku/{book}/download', [LibraryController::class, 'download'])
    ->whereNumber('book')
    ->name('library.download');
