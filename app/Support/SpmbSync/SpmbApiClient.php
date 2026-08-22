<?php

namespace App\Support\SpmbSync;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SpmbApiClient
{
    /**
     * @return array{total:int,api_version:string|null}
     */
    public function testConnection(): array
    {
        $response = $this->request()->get($this->endpoint(), [
            'page' => 1,
            'per_page' => 1,
        ]);

        $this->ensureSuccessful($response->status(), $response->json(), $response->body());

        return [
            'total' => (int) data_get($response->json(), 'meta.total', 0),
            'api_version' => data_get($response->json(), 'api_version'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(): array
    {
        $rows = [];
        $page = 1;
        $lastPage = 1;

        do {
            $response = $this->request()->get($this->endpoint(), [
                'page' => $page,
                'per_page' => 100,
            ]);

            $body = $response->json();
            $this->ensureSuccessful($response->status(), $body, $response->body());

            $data = data_get($body, 'data');
            if (! is_array($data)) {
                throw new RuntimeException('Respons API SPMB tidak memiliki daftar data yang valid.');
            }

            $rows = [...$rows, ...$data];
            $lastPage = max(1, (int) data_get($body, 'meta.last_page', 1));
            $page++;
        } while ($page <= $lastPage);

        return $rows;
    }

    private function request(): PendingRequest
    {
        $token = trim((string) config('services.spmb_sync.token'));
        if ($token === '') {
            throw new RuntimeException('SPMB_SYNC_TOKEN belum dikonfigurasi.');
        }

        return Http::acceptJson()
            ->withToken($token)
            ->timeout((int) config('services.spmb_sync.timeout', 30))
            ->connectTimeout(10)
            ->retry(2, 250, throw: false);
    }

    private function endpoint(): string
    {
        $baseUrl = rtrim((string) config('services.spmb_sync.base_url'), '/');
        if ($baseUrl === '' || ! str_starts_with($baseUrl, 'https://')) {
            throw new RuntimeException('SPMB_SYNC_BASE_URL harus menggunakan HTTPS.');
        }

        return $baseUrl.'/api/v1/integrations/akses/graduated-students';
    }

    private function ensureSuccessful(int $status, mixed $json, string $body): void
    {
        if ($status >= 200 && $status < 300) {
            return;
        }

        $message = is_array($json) ? data_get($json, 'message') : null;
        $message = filled($message) ? $message : str($body)->limit(180)->toString();

        throw new RuntimeException("API SPMB merespons HTTP {$status}: {$message}");
    }
}
