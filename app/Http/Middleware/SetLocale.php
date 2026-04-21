<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->session()->get('locale');

        if (! is_string($locale) || $locale === '') {
            $locale = config('app.locale');
        }

        if (in_array($locale, ['id', 'en'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
