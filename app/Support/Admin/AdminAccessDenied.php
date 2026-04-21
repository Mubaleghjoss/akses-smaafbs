<?php

namespace App\Support\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminAccessDenied
{
    public const FLASH_KEY = 'admin_access_denied';

    public static function shouldHandle(Request $request): bool
    {
        if (! $request->is('admin') && ! $request->is('admin/*')) {
            return false;
        }

        if (! $request->user()) {
            return false;
        }

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        if ($request->hasHeader('X-Livewire')) {
            return false;
        }

        if ($request->route()?->named('*livewire.update')) {
            return false;
        }

        return ! $request->expectsJson();
    }

    public static function redirectResponse(Request $request, ?string $message = null)
    {
        $request->session()->flash(static::FLASH_KEY, [
            'title' => 'Akses dibatasi',
            'message' => static::resolveMessage($message),
            'requested_url' => $request->fullUrl(),
        ]);

        return new RedirectResponse(static::resolveRedirectUrl($request));
    }

    protected static function resolveRedirectUrl(Request $request): string
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer !== '') {
            $refererParts = parse_url($referer);
            $refererPath = parse_url($referer, PHP_URL_PATH);
            $currentPath = '/'.ltrim($request->path(), '/');
            $isRelativeAdminPath = is_string($refererPath) && str_starts_with($refererPath, '/admin') && ! isset($refererParts['host']) && ! isset($refererParts['scheme']);
            $isSameHost = ($refererParts['host'] ?? null) === $request->getHost();
            $isSameScheme = ($refererParts['scheme'] ?? null) === $request->getScheme();
            $refererPort = $refererParts['port'] ?? static::defaultPortForScheme($refererParts['scheme'] ?? null);
            $appPort = $request->getPort() ?: static::defaultPortForScheme($request->getScheme());
            $isSamePort = $refererPort === $appPort;

            if (
                ($isRelativeAdminPath || ($isSameHost && $isSameScheme && $isSamePort))
                && is_string($refererPath)
                && str_starts_with($refererPath, '/admin')
                && $refererPath !== $currentPath
            ) {
                $query = isset($refererParts['query']) && $refererParts['query'] !== ''
                    ? '?'.$refererParts['query']
                    : '';

                return url($refererPath.$query);
            }
        }

        return url('/admin');
    }

    protected static function defaultPortForScheme(?string $scheme): ?int
    {
        return match ($scheme) {
            'http' => 80,
            'https' => 443,
            default => null,
        };
    }

    protected static function resolveMessage(?string $message): string
    {
        $normalized = trim((string) $message);

        if ($normalized === '' || $normalized === 'This action is unauthorized.') {
            return 'Akun Anda belum diberi akses untuk membuka halaman atau menjalankan aksi ini. Silakan hubungi admin untuk menambahkan izin yang dibutuhkan.';
        }

        return $normalized;
    }
}
