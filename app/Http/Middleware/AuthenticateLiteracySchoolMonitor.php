<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateLiteracySchoolMonitor
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = trim((string) config('literacy.school_monitor.token'));
        $providedToken = trim((string) $request->bearerToken());

        if ($configuredToken === ''
            || $providedToken === ''
            || ! hash_equals($configuredToken, $providedToken)) {
            abort(401, 'Token monitor jaringan tidak valid.');
        }

        return $next($request);
    }
}
