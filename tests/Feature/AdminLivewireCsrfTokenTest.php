<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminAwareVerifyCsrfToken;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminLivewireCsrfTokenTest extends TestCase
{
    public function test_admin_livewire_request_prefers_xsrf_header_over_stale_body_token(): void
    {
        $middleware = new class(app(), app('encrypter')) extends AdminAwareVerifyCsrfToken
        {
            public function publicTokenFromRequest(Request $request): ?string
            {
                return $this->getTokenFromRequest($request);
            }
        };

        $request = Request::create('/livewire-6fe06c41/update', 'POST', [
            '_token' => 'stale-body-token',
        ]);
        $request->headers->set('referer', 'http://127.0.0.1:8000/admin/boarding-rapots');
        $request->headers->set('X-XSRF-TOKEN', $this->encryptedXsrfToken('fresh-session-token'));

        $this->assertSame('fresh-session-token', $middleware->publicTokenFromRequest($request));
    }

    public function test_non_admin_livewire_request_keeps_standard_body_token_priority(): void
    {
        $middleware = new class(app(), app('encrypter')) extends AdminAwareVerifyCsrfToken
        {
            public function publicTokenFromRequest(Request $request): ?string
            {
                return $this->getTokenFromRequest($request);
            }
        };

        $request = Request::create('/livewire-6fe06c41/update', 'POST', [
            '_token' => 'body-token',
        ]);
        $request->headers->set('referer', 'http://127.0.0.1:8000/');
        $request->headers->set('X-XSRF-TOKEN', $this->encryptedXsrfToken('header-token'));

        $this->assertSame('body-token', $middleware->publicTokenFromRequest($request));
    }

    private function encryptedXsrfToken(string $token): string
    {
        /** @var Encrypter $encrypter */
        $encrypter = app('encrypter');
        $prefixedToken = CookieValuePrefix::create('XSRF-TOKEN', $encrypter->getKey()).$token;

        return $encrypter->encrypt($prefixedToken, AdminAwareVerifyCsrfToken::serialized());
    }
}
