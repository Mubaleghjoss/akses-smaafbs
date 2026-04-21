<?php

namespace App\Http\Middleware;

use App\Support\Security\EndpointProtectionPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogSlowAdminRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldMonitor($request)) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $startedAt = hrtime(true);
        $queryCount = 0;
        $queryTimeMs = 0.0;

        DB::listen(function ($query) use (&$queryCount, &$queryTimeMs): void {
            $queryCount++;
            $queryTimeMs += (float) $query->time;
        });

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;

        if (! $this->shouldLogRequest($durationMs, $queryTimeMs)) {
            return $response;
        }

        Log::channel($this->logChannel())->warning('Slow admin request detected', [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route_name' => $request->route()?->getName(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round($durationMs),
            'query_time_ms' => (int) round($queryTimeMs),
            'query_count' => $queryCount,
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'ip' => $request->ip(),
            'referer' => $request->headers->get('referer'),
        ]);

        return $response;
    }

    protected function shouldMonitor(Request $request): bool
    {
        if (! EndpointProtectionPolicy::performanceMonitoringEnabled()) {
            return false;
        }

        $path = '/'.ltrim($request->path(), '/');

        if (Str::startsWith($path, '/admin')) {
            return true;
        }

        if (Str::contains($path, '/livewire-')) {
            return Str::contains((string) $request->headers->get('referer'), '/admin');
        }

        return false;
    }

    protected function shouldLogRequest(float $durationMs, float $queryTimeMs): bool
    {
        return $durationMs >= EndpointProtectionPolicy::adminSlowRequestThresholdMs()
            || $queryTimeMs >= EndpointProtectionPolicy::adminSlowAggregateQueryThresholdMs();
    }

    protected function logChannel(): string
    {
        return EndpointProtectionPolicy::adminPerformanceLogChannel();
    }
}
