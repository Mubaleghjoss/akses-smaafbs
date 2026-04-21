<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;

class TrustProxies extends \Illuminate\Http\Middleware\TrustProxies
{
    /**
     * Get the trusted proxies.
     *
     * @return array<int, string>|string|null
     */
    protected function proxies()
    {
        $configured = $this->environmentValue('TRUSTED_PROXIES');

        if ($configured === null) {
            return '*';
        }

        if (! is_string($configured)) {
            return $configured;
        }

        $configured = trim($configured);

        if ($configured === '' || strtolower($configured) === 'null') {
            return null;
        }

        return $configured;
    }

    /**
     * Get the trusted headers.
     */
    protected function headers(): int
    {
        $configured = $this->environmentValue('TRUSTED_PROXY_HEADERS');

        if ($configured === null) {
            return $this->defaultHeaders();
        }

        if (is_int($configured)) {
            return $configured;
        }

        if (! is_string($configured)) {
            return $this->defaultHeaders();
        }

        $configured = strtoupper(trim($configured));

        if ($configured === '' || $configured === 'ALL' || $configured === '*') {
            return $this->defaultHeaders();
        }

        if (is_numeric($configured)) {
            return (int) $configured;
        }

        $map = [
            'HEADER_FORWARDED' => Request::HEADER_FORWARDED,
            'HEADER_X_FORWARDED_FOR' => Request::HEADER_X_FORWARDED_FOR,
            'HEADER_X_FORWARDED_HOST' => Request::HEADER_X_FORWARDED_HOST,
            'HEADER_X_FORWARDED_PORT' => Request::HEADER_X_FORWARDED_PORT,
            'HEADER_X_FORWARDED_PROTO' => Request::HEADER_X_FORWARDED_PROTO,
            'HEADER_X_FORWARDED_PREFIX' => Request::HEADER_X_FORWARDED_PREFIX,
            'HEADER_X_FORWARDED_AWS_ELB' => Request::HEADER_X_FORWARDED_AWS_ELB,
        ];

        $headers = 0;

        foreach (preg_split('/[\s,|]+/', $configured, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $header) {
            $headers |= $map[$header] ?? 0;
        }

        return $headers > 0 ? $headers : $this->defaultHeaders();
    }

    private function defaultHeaders(): int
    {
        return Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_PREFIX |
            Request::HEADER_X_FORWARDED_AWS_ELB;
    }

    private function environmentValue(string $key): string|int|null
    {
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);

        return $value === false ? null : $value;
    }
}
