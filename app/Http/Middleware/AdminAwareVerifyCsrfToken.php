<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;

class AdminAwareVerifyCsrfToken extends VerifyCsrfToken
{
    /**
     * @param  Request  $request
     */
    protected function getTokenFromRequest($request)
    {
        if ($this->shouldPreferXsrfHeader($request)) {
            $token = $this->getTokenFromXsrfHeader($request);

            if (filled($token)) {
                return $token;
            }
        }

        return parent::getTokenFromRequest($request);
    }

    private function shouldPreferXsrfHeader(Request $request): bool
    {
        if (! $request->headers->has('X-XSRF-TOKEN')) {
            return false;
        }

        if (! $request->is('livewire*/update')) {
            return false;
        }

        $refererPath = (string) parse_url((string) $request->headers->get('referer', ''), PHP_URL_PATH);

        return $refererPath === '/admin' || str_starts_with($refererPath, '/admin/');
    }

    private function getTokenFromXsrfHeader(Request $request): ?string
    {
        try {
            return CookieValuePrefix::remove(
                $this->encrypter->decrypt((string) $request->header('X-XSRF-TOKEN'), static::serialized())
            );
        } catch (DecryptException) {
            return null;
        }
    }
}
