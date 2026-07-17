<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTagihanStudentIntegration
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('tagihan_student_integration.require_https', false) && ! $request->secure()) {
            return $this->jsonError('HTTPS is required.', Response::HTTP_FORBIDDEN);
        }

        $expectedToken = trim((string) config('tagihan_student_integration.token'));
        $providedToken = trim((string) $request->bearerToken());

        if (strlen($expectedToken) < 32
            || $providedToken === ''
            || ! hash_equals($expectedToken, $providedToken)) {
            return $this->jsonError('Unauthenticated.', Response::HTTP_UNAUTHORIZED);
        }

        /** @var Response $response */
        $response = $next($request);

        return $this->preventCaching($response);
    }

    private function jsonError(string $message, int $status): JsonResponse
    {
        return $this->preventCaching(response()->json([
            'message' => $message,
        ], $status));
    }

    private function preventCaching(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
