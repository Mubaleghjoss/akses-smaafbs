<?php

namespace App\Support\StudentSync;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StudentServerPushClient
{
    public function __construct(private readonly StudentSyncRequestSigner $signer) {}

    /** @return array<string, mixed> */
    public function preview(array $payload): array
    {
        return $this->send(
            '/api/internal/student-sync/preview',
            $payload,
            'preview-'.bin2hex(random_bytes(16)),
        );
    }

    /** @return array<string, mixed> */
    public function apply(string $previewToken, string $checksum, string $idempotencyKey): array
    {
        return $this->send('/api/internal/student-sync/apply', [
            'preview_token' => $previewToken,
            'payload_checksum' => $checksum,
        ], $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(string $path, array $payload, string $idempotencyKey): array
    {
        $this->ensureConfigured();
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = $this->signer->headers('POST', $path, $body, $idempotencyKey);

        try {
            $response = $this->request($headers)->retry(
                3,
                0,
                static fn (\Throwable $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            )->withBody($body, 'application/json')->post($this->url($path));
        } catch (ConnectionException) {
            throw new RuntimeException('Student sync server is unavailable.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Student sync server rejected the request.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Student sync server returned an invalid response.');
        }

        return $data;
    }

    /** @param array<string, string> $headers */
    private function request(array $headers): PendingRequest
    {
        return Http::timeout(max(1, (int) config('student_sync.client.timeout', 60)))
            ->acceptJson()
            ->withHeaders($headers);
    }

    private function ensureConfigured(): void
    {
        if (! config('student_sync.client.enabled', false)) {
            throw new RuntimeException('Student sync client is disabled.');
        }

        $url = $this->url('');

        if (config('app.env') === 'production' && parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('Student sync server URL must use HTTPS in production.');
        }
    }

    private function url(string $path): string
    {
        return rtrim((string) config('student_sync.client.server_url'), '/').$path;
    }
}
