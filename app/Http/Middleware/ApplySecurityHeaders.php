<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! (bool) config('security.headers_enabled', true)) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', (string) config('security.referrer_policy'));
        $response->headers->set('Permissions-Policy', (string) config('security.permissions_policy'));
        $response->headers->remove('X-Powered-By');

        $this->applyContentSecurityPolicy($response);
        $this->applyStrictTransportSecurity($request, $response);

        return $response;
    }

    private function applyContentSecurityPolicy(Response $response): void
    {
        $policy = trim((string) config('security.csp_policy'));
        $mode = strtolower(trim((string) config('security.csp_mode', 'report-only')));

        if ($policy === '' || $mode === 'disabled') {
            return;
        }

        $header = $mode === 'enforce'
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';

        $response->headers->set($header, $policy);
    }

    private function applyStrictTransportSecurity(Request $request, Response $response): void
    {
        $maxAge = (int) config('security.hsts_max_age', 0);

        if (! $request->isSecure() || $maxAge <= 0) {
            return;
        }

        $response->headers->set('Strict-Transport-Security', 'max-age='.$maxAge);
    }
}
