<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class LimiterPrerequisitesTest extends TestCase
{
    public function test_app_uses_project_owned_trust_proxies_middleware(): void
    {
        $globalMiddleware = $this->app->make(Kernel::class)->getGlobalMiddleware();

        $this->assertContains(TrustProxies::class, $globalMiddleware);
        $this->assertNotContains(\Illuminate\Http\Middleware\TrustProxies::class, $globalMiddleware);
    }

    public function test_limiter_store_is_explicit_and_points_to_a_defined_cache_store(): void
    {
        $limiterStore = config('cache.limiter');

        $this->assertIsString($limiterStore);
        $this->assertArrayHasKey($limiterStore, config('cache.stores'));
    }

    public function test_cache_config_falls_back_to_file_when_cache_env_is_missing(): void
    {
        $cacheConfig = $this->withEnv([
            'CACHE_STORE' => null,
            'CACHE_LIMITER_STORE' => null,
        ], fn (): array => require config_path('cache.php'));

        $this->assertSame('file', $cacheConfig['default']);
        $this->assertSame('file', $cacheConfig['limiter']);
    }

    public function test_limiter_store_falls_back_to_cache_store_when_not_set_explicitly(): void
    {
        $cacheConfig = $this->withEnv([
            'CACHE_STORE' => 'database',
            'CACHE_LIMITER_STORE' => null,
        ], fn (): array => require config_path('cache.php'));

        $this->assertSame('database', $cacheConfig['default']);
        $this->assertSame('database', $cacheConfig['limiter']);
    }

    public function test_limiter_store_can_be_set_independently_from_default_cache_store(): void
    {
        $cacheConfig = $this->withEnv([
            'CACHE_STORE' => 'file',
            'CACHE_LIMITER_STORE' => 'database',
        ], fn (): array => require config_path('cache.php'));

        $this->assertSame('file', $cacheConfig['default']);
        $this->assertSame('database', $cacheConfig['limiter']);
    }

    public function test_trusted_proxy_defaults_prioritize_shared_hosting_forwarded_context(): void
    {
        $resolved = $this->withEnv([
            'TRUSTED_PROXIES' => null,
            'TRUSTED_PROXY_HEADERS' => null,
        ], function (): array {
            $middleware = new TrustProxies;

            return [
                'proxies' => $this->invokeProtectedMethod($middleware, 'proxies'),
                'headers' => $this->invokeProtectedMethod($middleware, 'headers'),
            ];
        });

        $this->assertSame('*', $resolved['proxies']);
        $this->assertSame($this->defaultTrustedProxyHeaders(), $resolved['headers']);
    }

    public function test_trusted_proxy_configuration_supports_safe_overrides_from_environment(): void
    {
        $resolved = $this->withEnv([
            'TRUSTED_PROXIES' => 'null',
            'TRUSTED_PROXY_HEADERS' => 'HEADER_X_FORWARDED_FOR|HEADER_X_FORWARDED_PROTO',
        ], function (): array {
            $middleware = new TrustProxies;

            return [
                'proxies' => $this->invokeProtectedMethod($middleware, 'proxies'),
                'headers' => $this->invokeProtectedMethod($middleware, 'headers'),
            ];
        });

        $this->assertNull($resolved['proxies']);
        $this->assertSame(
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
            $resolved['headers'],
        );
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function withEnv(array $values, callable $callback): mixed
    {
        $snapshots = [];

        foreach ($values as $name => $value) {
            $snapshots[$name] = [
                'getenv' => getenv($name),
                '_ENV' => $_ENV[$name] ?? null,
                '_SERVER' => $_SERVER[$name] ?? null,
                'has_env' => array_key_exists($name, $_ENV),
                'has_server' => array_key_exists($name, $_SERVER),
            ];

            if ($value === null) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);

                continue;
            }

            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        try {
            return $callback();
        } finally {
            foreach ($snapshots as $name => $snapshot) {
                if ($snapshot['getenv'] === false) {
                    putenv($name);
                } else {
                    putenv($name.'='.$snapshot['getenv']);
                }

                if ($snapshot['has_env']) {
                    $_ENV[$name] = $snapshot['_ENV'];
                } else {
                    unset($_ENV[$name]);
                }

                if ($snapshot['has_server']) {
                    $_SERVER[$name] = $snapshot['_SERVER'];
                } else {
                    unset($_SERVER[$name]);
                }
            }
        }
    }

    private function invokeProtectedMethod(object $subject, string $method): mixed
    {
        $reflection = new ReflectionMethod($subject, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($subject);
    }

    private function defaultTrustedProxyHeaders(): int
    {
        return Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_PREFIX |
            Request::HEADER_X_FORWARDED_AWS_ELB;
    }
}
