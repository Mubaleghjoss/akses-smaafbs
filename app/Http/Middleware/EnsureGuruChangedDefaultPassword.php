<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGuruChangedDefaultPassword
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->shouldForceDefaultPasswordChange()) {
            return $next($request);
        }

        $routeName = (string) optional($request->route())->getName();

        if ($this->isAllowedRoute($routeName)) {
            return $next($request);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        return redirect()->to('/admin');
    }

    protected function isAllowedRoute(string $routeName): bool
    {
        if (
            str_contains($routeName, 'force-guru-password-change')
            || str_contains($routeName, 'auth.logout')
            || $routeName === 'livewire.update'
        ) {
            return true;
        }

        return false;
    }
}
