<?php

use App\Http\Middleware\AdminAwareVerifyCsrfToken;
use App\Http\Middleware\AuthenticateTagihanStudentIntegration;
use App\Http\Middleware\AuthenticateLiteracySchoolMonitor;
use App\Http\Middleware\LogSlowAdminRequests;
use App\Http\Middleware\PreventAdminResponseCaching;
use App\Http\Middleware\TrustProxies;
use App\Support\Admin\AdminAccessDenied;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tagihan.student.integration' => AuthenticateTagihanStudentIntegration::class,
            'literacy.school.monitor' => AuthenticateLiteracySchoolMonitor::class,
        ]);

        $middleware->replace(
            Illuminate\Http\Middleware\TrustProxies::class,
            TrustProxies::class,
        );

        $middleware->web(replace: [
            ValidateCsrfToken::class => AdminAwareVerifyCsrfToken::class,
            VerifyCsrfToken::class => AdminAwareVerifyCsrfToken::class,
        ]);

        $middleware->append(PreventAdminResponseCaching::class);
        $middleware->append(LogSlowAdminRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! AdminAccessDenied::shouldHandle($request)) {
                return null;
            }

            return AdminAccessDenied::redirectResponse($request, $exception->getMessage());
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() !== 403 || ! AdminAccessDenied::shouldHandle($request)) {
                return null;
            }

            return AdminAccessDenied::redirectResponse($request, $exception->getMessage());
        });
    })->create();
