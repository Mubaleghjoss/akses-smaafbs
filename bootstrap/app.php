<?php

use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\LogSlowAdminRequests;
use App\Support\Admin\AdminAccessDenied;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(
            Illuminate\Http\Middleware\TrustProxies::class,
            TrustProxies::class,
        );

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
