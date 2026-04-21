<?php

namespace App\Support\Security;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EndpointProtectionPolicy
{
    public static function endpointCategories(): array
    {
        return config('endpoint_protection.endpoint_categories', []);
    }

    public static function namedLimiters(): array
    {
        return config('endpoint_protection.named_limiters', []);
    }

    public static function registerNamedLimiters(): void
    {
        foreach (self::namedLimiters() as $name => $definition) {
            $attempts = max(1, (int) ($definition['attempts'] ?? 60));
            $decaySeconds = max(1, (int) ($definition['decay_seconds'] ?? 60));
            $by = (string) ($definition['by'] ?? 'ip');

            RateLimiter::for($name, function (Request $request) use ($attempts, $decaySeconds, $by, $definition): Limit {
                $limit = Limit::perSecond(
                    $attempts,
                    $decaySeconds,
                )->by(self::resolveLimiterKey($request, $by));

                if (! is_array($definition['response'] ?? null)) {
                    return $limit;
                }

                return $limit->response(fn (Request $request, array $headers): SymfonyResponse => self::buildThrottleResponse(
                    $request,
                    $headers,
                    $definition,
                ));
            });
        }
    }

    public static function adminLoginAttempts(): int
    {
        $attempts = data_get(
            self::endpointCategories(),
            'admin_auth.livewire_rate_limit_attempts',
            5,
        );

        return max(1, (int) $attempts);
    }

    public static function adminLoginDecaySeconds(): int
    {
        $seconds = data_get(
            self::endpointCategories(),
            'admin_auth.livewire_rate_limit_decay_seconds',
            60,
        );

        return max(1, (int) $seconds);
    }

    public static function adminLoginRateLimitKey(
        ?string $username,
        ?string $ip,
        string $component,
        string $method,
    ): string {
        $normalizedUsername = Str::of((string) $username)
            ->trim()
            ->lower()
            ->value();

        $ipAddress = trim((string) $ip);

        return 'livewire-rate-limiter:'.sha1(
            $component
            .'|'.$method
            .'|'.($normalizedUsername !== '' ? $normalizedUsername : 'unknown-username')
            .'|'.($ipAddress !== '' ? $ipAddress : 'unknown-ip'),
        );
    }

    public static function isDegradationEnabled(): bool
    {
        return (bool) config('endpoint_protection.graceful_degradation.enabled', false);
    }

    public static function shouldSkipExpensiveAdminMenuSections(string $profile = 'admin_heavy_widgets'): bool
    {
        return self::isDegradationEnabled() && (bool) data_get(
            self::degradationProfiles(),
            $profile.'.menu.skip_expensive_dynamic_sections',
            false,
        );
    }

    public static function shouldSkipExpensiveAdminDashboardWidgets(string $profile = 'admin_heavy_widgets'): bool
    {
        return self::isDegradationEnabled() && (bool) data_get(
            self::degradationProfiles(),
            $profile.'.dashboard.skip_expensive_widgets',
            false,
        );
    }

    public static function shouldSkipPublicDecorativeChrome(string $profile = 'public_chrome'): bool
    {
        return self::isDegradationEnabled() && (bool) data_get(
            self::degradationProfiles(),
            $profile.'.layout.skip_decorative_surfaces',
            false,
        );
    }

    public static function degradationProfiles(): array
    {
        return config('endpoint_protection.graceful_degradation.profiles', []);
    }

    public static function performanceMonitoringEnabled(): bool
    {
        return (bool) config('endpoint_protection.performance_monitoring.enabled', false);
    }

    public static function adminSlowRequestThresholdMs(): int
    {
        return max(1, (int) config('endpoint_protection.performance_monitoring.request_threshold_ms', 1500));
    }

    public static function adminSlowAggregateQueryThresholdMs(): int
    {
        return max(1, (int) config('endpoint_protection.performance_monitoring.query_threshold_ms', 800));
    }

    public static function adminSlowSingleQueryThresholdMs(): int
    {
        return max(1, (int) config('endpoint_protection.performance_monitoring.single_query_threshold_ms', 400));
    }

    public static function adminPerformanceLogChannel(): string
    {
        return (string) config('endpoint_protection.performance_monitoring.log_channel', 'admin_performance');
    }

    public static function adminQueryLogChannel(): string
    {
        return (string) config('endpoint_protection.performance_monitoring.query_log_channel', 'admin_queries');
    }

    protected static function resolveLimiterKey(Request $request, string $strategy): string
    {
        return match ($strategy) {
            'user_or_ip' => (string) ($request->user()?->getAuthIdentifier() ?? $request->ip()),
            'route_ip' => (string) Str::of((string) $request->route()?->getName())
                ->append('|')
                ->append((string) $request->ip()),
            default => (string) $request->ip(),
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, string>  $headers
     */
    protected static function buildThrottleResponse(Request $request, array $headers, array $definition): SymfonyResponse
    {
        $responseType = (string) data_get($definition, 'response.type', 'json');
        $message = (string) data_get($definition, 'response.message', 'Terlalu banyak permintaan. Silakan coba lagi nanti.');

        $response = match ($responseType) {
            'redirect_route_flash' => redirect()
                ->route((string) data_get($definition, 'response.route', 'home'))
                ->with('error', $message),
            'redirect_back_error' => redirect()
                ->back()
                ->withErrors(['throttle' => $message])
                ->withInput(),
            'redirect_back_flash' => redirect()->back()->with('error', $message),
            default => response()->json([
                'message' => $message,
            ], Response::HTTP_TOO_MANY_REQUESTS, $headers),
        };

        foreach ($headers as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }
}
