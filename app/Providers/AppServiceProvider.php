<?php

namespace App\Providers;

use App\Contracts\Auth\WebAuthnChallengeFlow;
use App\Contracts\Auth\WebAuthnCredentialDomain;
use App\Contracts\SiteSettingsAccessor;
use App\Support\Auth\WebAuthn\DatabaseWebAuthnChallengeFlow;
use App\Support\Auth\WebAuthn\DatabaseWebAuthnCredentialDomain;
use App\Support\Perpustakaan\LiteracySubmissionEventRecorder;
use App\Support\Security\EndpointProtectionPolicy;
use App\Support\SiteSettings\PengaturanSiteSettingsAccessor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View as BladeView;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SiteSettingsAccessor::class, PengaturanSiteSettingsAccessor::class);
        $this->app->singleton(WebAuthnCredentialDomain::class, DatabaseWebAuthnCredentialDomain::class);
        $this->app->singleton(WebAuthnChallengeFlow::class, DatabaseWebAuthnChallengeFlow::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        EndpointProtectionPolicy::registerNamedLimiters();
        $this->registerLiteracyRateLimiters();
        $this->registerSlowAdminQueryLogger();

        View::composer('layouts.app', function (BladeView $view): void {
            /** @var SiteSettingsAccessor $settings */
            $settings = app(SiteSettingsAccessor::class);
            $siteSettings = $settings->all();

            $viewData = $view->getData();
            $metaOverrides = $viewData['meta'] ?? [];

            if (! is_array($metaOverrides)) {
                $metaOverrides = [];
            }

            $title = trim((string) ($metaOverrides['title'] ?? $viewData['title'] ?? $siteSettings['default_seo_title']));
            if ($title === '') {
                $title = $siteSettings['default_seo_title'];
            }

            $description = trim((string) ($metaOverrides['description'] ?? $siteSettings['default_seo_description']));
            if ($description === '') {
                $description = $siteSettings['default_seo_description'];
            }

            $ogTitle = trim((string) ($metaOverrides['og_title'] ?? $siteSettings['default_og_title']));
            if ($ogTitle === '') {
                $ogTitle = $title;
            }

            $ogDescription = trim((string) ($metaOverrides['og_description'] ?? $siteSettings['default_og_description']));
            if ($ogDescription === '') {
                $ogDescription = $description;
            }

            $fallbackOgImage = $siteSettings['default_og_image']
                ?? $siteSettings['logo_path']
                ?? $siteSettings['favicon_path']
                ?? asset('favicon.ico');

            $ogImage = $metaOverrides['og_image'] ?? $fallbackOgImage;

            $twitterCard = trim((string) ($metaOverrides['twitter_card'] ?? ($ogImage ? 'summary_large_image' : 'summary')));
            if ($twitterCard === '') {
                $twitterCard = $ogImage ? 'summary_large_image' : 'summary';
            }

            $view->with('siteSettings', $siteSettings);
            $view->with('meta', [
                'title' => $title,
                'description' => $description,
                'canonical_url' => (string) ($metaOverrides['canonical_url'] ?? request()->fullUrl()),
                'theme_color' => (string) ($metaOverrides['theme_color'] ?? $siteSettings['theme_color']),
                'manifest_url' => (string) ($metaOverrides['manifest_url'] ?? url('/manifest.webmanifest')),
                'favicon_url' => (string) ($metaOverrides['favicon_url'] ?? ($siteSettings['favicon_path'] ?? asset('favicon.ico'))),
                'apple_touch_icon' => $metaOverrides['apple_touch_icon'] ?? ($siteSettings['favicon_path'] ?? null),
                'og_type' => (string) ($metaOverrides['og_type'] ?? 'website'),
                'og_site_name' => (string) ($metaOverrides['og_site_name'] ?? $siteSettings['site_name']),
                'og_title' => $ogTitle,
                'og_description' => $ogDescription,
                'og_image' => $ogImage,
                'og_image_secure_url' => $metaOverrides['og_image_secure_url'] ?? $ogImage,
                'og_image_type' => $metaOverrides['og_image_type'] ?? null,
                'og_image_width' => $metaOverrides['og_image_width'] ?? null,
                'og_image_height' => $metaOverrides['og_image_height'] ?? null,
                'og_image_alt' => $metaOverrides['og_image_alt'] ?? null,
                'og_url' => (string) ($metaOverrides['og_url'] ?? request()->fullUrl()),
                'twitter_card' => $twitterCard,
                'twitter_title' => (string) ($metaOverrides['twitter_title'] ?? $ogTitle),
                'twitter_description' => (string) ($metaOverrides['twitter_description'] ?? $ogDescription),
                'twitter_image' => $metaOverrides['twitter_image'] ?? $ogImage,
            ]);
        });

        if (app()->runningInConsole()) {
            return;
        }

        if (! app()->environment('local')) {
            return;
        }

        $rootUrl = request()->root();

        config(['app.url' => $rootUrl]);
        URL::forceRootUrl($rootUrl);
    }

    protected function registerLiteracyRateLimiters(): void
    {
        RateLimiter::for('literacy_queue_ticket', fn (Request $request): array => [
            $this->literacyLimit(30, 'ticket_session', 'literacy-ticket-session|'.$this->literacySessionKey($request)),
            $this->literacyLimit(1200, 'ticket_ip', 'literacy-ticket-ip|'.$request->ip()),
        ]);

        RateLimiter::for('literacy_queue_status', fn (Request $request): array => [
            $this->literacyLimit(120, 'status_session', 'literacy-status-session|'.$this->literacySessionKey($request)),
            $this->literacyLimit(3000, 'status_ip', 'literacy-status-ip|'.$request->ip()),
        ]);

        RateLimiter::for('literacy_submit', fn (Request $request): array => [
            $this->literacyLimit(4, 'submit_request', 'literacy-submit-request|'.$this->literacySubmissionKey($request)),
            $this->literacyLimit(30, 'submit_session', 'literacy-submit-session|'.$this->literacySessionKey($request)),
            $this->literacyLimit(1200, 'submit_ip', 'literacy-submit-ip|'.$request->ip()),
        ]);

        RateLimiter::for('literacy_integrity', fn (Request $request): array => [
            Limit::perMinute(60)->by('literacy-integrity-session|'.$this->literacySessionKey($request)),
            Limit::perMinute(1200)->by('literacy-integrity-ip|'.$request->ip()),
        ]);

        RateLimiter::for('literacy_events', fn (Request $request): array => [
            Limit::perMinute(30)->by('literacy-events-session|'.$this->literacySessionKey($request)),
            Limit::perMinute(600)->by('literacy-events-ip|'.$request->ip()),
        ]);

        RateLimiter::for('literacy_school_monitor', fn (Request $request): array => [
            Limit::perMinute(30)->by('literacy-school-monitor|'.$request->ip()),
        ]);
    }

    protected function literacySessionKey(Request $request): string
    {
        return $request->hasSession() && $request->session()->getId() !== ''
            ? $request->session()->getId()
            : (string) $request->ip();
    }

    protected function literacySubmissionKey(Request $request): string
    {
        $value = $request->string('submission_ticket')->toString()
            ?: $request->string('submission_request_id')->toString()
            ?: $this->literacySessionKey($request);

        return hash('sha256', $value);
    }

    protected function literacyLimit(int $attempts, string $scope, string $key): Limit
    {
        return Limit::perMinute($attempts)
            ->by($key)
            ->response(fn (Request $request, array $headers) => $this->literacyThrottleResponse($request, $headers, $scope));
    }

    protected function literacyThrottleResponse(Request $request, array $headers, string $scope)
    {
        $traceId = $request->string('submission_request_id')->toString();

        if (! Str::isUuid($traceId)) {
            $traceId = (string) Str::uuid();
        }

        app(LiteracySubmissionEventRecorder::class)->record('throttled', [
            'http_status' => 429,
            'context' => [
                'operation' => str_contains($scope, 'submit') ? 'submit' : 'queue',
                'reason' => 'application_rate_limit',
                'limiter_scope' => $scope,
                'trace_id' => $traceId,
            ],
        ]);

        return response()->json([
            'status' => 'throttled',
            'message' => 'Permintaan sedang dijeda singkat oleh aplikasi. Jawaban tidak perlu dikirim berulang.',
            'retry_after_seconds' => (int) ($headers['Retry-After'] ?? 5),
        ], 429, $headers + [
            'Cache-Control' => 'no-store',
            'X-Literacy-Protocol' => '2',
            'X-Literacy-Trace-Id' => $traceId,
            'X-Literacy-Throttle' => $scope,
        ]);
    }

    protected function registerSlowAdminQueryLogger(): void
    {
        if (! EndpointProtectionPolicy::performanceMonitoringEnabled()) {
            return;
        }

        if (app()->runningInConsole()) {
            return;
        }

        DB::listen(function (QueryExecuted $query): void {
            if (! $this->shouldLogSlowAdminQuery($query)) {
                return;
            }

            $request = request();

            Log::channel(EndpointProtectionPolicy::adminQueryLogChannel())->warning('Slow admin query detected', [
                'time_ms' => (int) round($query->time),
                'connection' => $query->connectionName,
                'route_name' => $request->route()?->getName(),
                'path' => '/'.ltrim($request->path(), '/'),
                'method' => $request->method(),
                'user_id' => $request->user()?->getAuthIdentifier(),
                'sql' => $query->sql,
            ]);
        });
    }

    protected function shouldLogSlowAdminQuery(QueryExecuted $query): bool
    {
        if ($query->time < EndpointProtectionPolicy::adminSlowSingleQueryThresholdMs()) {
            return false;
        }

        $request = request();
        $path = '/'.ltrim((string) $request->path(), '/');

        if (str_starts_with($path, '/admin')) {
            return true;
        }

        if (str_contains($path, '/livewire-')) {
            return str_contains((string) $request->headers->get('referer'), '/admin');
        }

        return false;
    }
}
