<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PreventAdminResponseCaching
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->shouldPreventCaching($request)) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        if ($response->getStatusCode() === 419) {
            Log::warning('Admin session token mismatch response', [
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'route_name' => $request->route()?->getName(),
                'referer' => $request->headers->get('referer'),
                'content_length' => $request->headers->get('content-length'),
                'content_type' => $request->headers->get('content-type'),
                'has_session' => $request->hasSession(),
                'has_session_cookie' => $request->cookies->has((string) config('session.cookie')),
                'has_json_token' => filled($request->input('_token')),
                'has_csrf_header' => $request->headers->has('X-CSRF-TOKEN'),
                'has_xsrf_header' => $request->headers->has('X-XSRF-TOKEN'),
                'user_id' => $request->user()?->getAuthIdentifier(),
            ]);
        }

        return $response;
    }

    protected function shouldPreventCaching(Request $request): bool
    {
        $path = '/'.ltrim($request->path(), '/');

        if (str_starts_with($path, '/admin')) {
            return true;
        }

        return str_contains($path, '/livewire-')
            && str_contains((string) $request->headers->get('referer'), '/admin');
    }
}
