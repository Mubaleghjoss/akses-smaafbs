<?php

use App\Contracts\SiteSettingsAccessor;
use App\Http\Controllers\Admin\BerkasGuruDocumentController;
use App\Http\Controllers\Admin\BoardingRapotDocumentController;
use App\Http\Controllers\Admin\AdminUserCredentialDocumentController;
use App\Http\Controllers\Admin\DataSiswaExportController;
use App\Http\Controllers\Admin\DataSiswaProfileExportController;
use App\Http\Controllers\Admin\DataSiswaImportReviewExportController;
use App\Http\Controllers\Admin\DataSiswaImportTemplateController;
use App\Http\Controllers\Admin\ForceGuruPasswordChangeController;
use App\Http\Controllers\Admin\GuruTendikExportController;
use App\Http\Controllers\Admin\GuruTendikImportTemplateController;
use App\Http\Controllers\Admin\ProkerExportController;
use App\Http\Controllers\Admin\ProkerImportTemplateController;
use App\Http\Controllers\Admin\SarprasActivityDocumentController;
use App\Http\Controllers\Admin\SarprasBospInventoryDocumentController;
use App\Http\Controllers\Admin\SarprasMonthlyAgendaDocumentController;
use App\Http\Controllers\Admin\SarprasRoomInventoryDocumentController;
use App\Http\Controllers\Admin\UksRecordExportController;
use App\Http\Controllers\Admin\UksRecordImportTemplateController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\GuruTendikProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SurveiPublicController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
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

Route::middleware('auth')->prefix('/admin/boarding-rapots/{boardingRapot}')->group(function (): void {
    Route::get('/preview', [BoardingRapotDocumentController::class, 'preview'])
        ->name('admin.boarding-rapots.preview');
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

    $iconUrl = $siteSettings['favicon_path']
        ?? $siteSettings['logo_path']
        ?? asset('favicon.ico');

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
                'src' => $iconUrl,
                'sizes' => '192x192',
                'type' => 'image/png',
            ],
            [
                'src' => $iconUrl,
                'sizes' => '512x512',
                'type' => 'image/png',
            ],
        ],
    ])->header('Content-Type', 'application/manifest+json');
})->name('manifest.webmanifest');

Route::get('/service-worker.js', function () {
    $script = <<<'JS'
const CACHE_NAME = 'akses-public-shell-v2';
const NETWORK_ONLY_PREFIXES = [
    '/admin',
    '/livewire',
    '/storage',
    '/login',
    '/logout',
    '/register',
    '/password',
    '/sanctum',
    '/broadcasting',
    '/up',
    '/tagihan',
];

const AUTH_POST_PATHS = ['/login', '/logout', '/register', '/password', '/forgot-password', '/reset-password'];

const shouldBypassCache = (request, url) => {
    if (request.method !== 'GET') {
        return true;
    }

    if (request.headers.get('X-Livewire') || request.headers.get('X-CSRF-TOKEN')) {
        return true;
    }

    if (NETWORK_ONLY_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
        return true;
    }

    if (AUTH_POST_PATHS.some((prefix) => url.pathname.startsWith(prefix))) {
        return true;
    }

    return false;
};

self.addEventListener('install', (event) => {
    self.skipWaiting();

    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(['/']))
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (shouldBypassCache(request, url)) {
        return;
    }

    event.respondWith(
        fetch(request)
            .then((response) => {
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }

                const responseClone = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, responseClone));

                return response;
            })
            .catch(() => caches.match(request).then((cached) => cached || caches.match('/')))
    );
});
JS;

    return response($script)
        ->header('Content-Type', 'application/javascript; charset=UTF-8')
        ->header('Service-Worker-Allowed', '/')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
})->name('service-worker');

Route::get('/', HomeController::class)->name('home');
Route::get('/student-search', [HomeController::class, 'studentSearch'])
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
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

Route::get('/perpustakaan', [LibraryController::class, 'index'])->name('library.index');
Route::get('/perpustakaan/buku/{book}', [LibraryController::class, 'show'])->whereNumber('book')->name('library.show');
Route::middleware('throttle:public_library_downloads')
    ->get('/perpustakaan/buku/{book}/download', [LibraryController::class, 'download'])
    ->whereNumber('book')
    ->name('library.download');
