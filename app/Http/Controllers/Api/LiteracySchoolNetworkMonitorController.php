<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerpustakaanLiterasiNetworkCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiteracySchoolNetworkMonitorController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['nullable', 'string', 'max:40'],
            'status' => ['required', 'in:ok,failed,recovered'],
            'dns_ok' => ['required', 'boolean'],
            'tcp_ok' => ['required', 'boolean'],
            'http_status' => ['nullable', 'integer', 'min:0', 'max:599'],
            'duration_ms' => ['nullable', 'integer', 'min:0', 'max:300000'],
            'consecutive_failures' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'error_code' => ['nullable', 'string', 'max:80'],
            'checked_at' => ['required', 'date'],
            'context' => ['nullable', 'array'],
            'context.client_version' => ['nullable', 'string', 'max:30'],
            'context.monitor_enabled' => ['nullable', 'boolean'],
            'context.event_type' => ['nullable', 'in:heartbeat,state_change'],
        ]);

        $check = PerpustakaanLiterasiNetworkCheck::query()->create([
            'source' => trim((string) ($validated['source'] ?? 'school')) ?: 'school',
            'status' => $validated['status'],
            'dns_ok' => (bool) $validated['dns_ok'],
            'tcp_ok' => (bool) $validated['tcp_ok'],
            'http_status' => $validated['http_status'] ?? null,
            'duration_ms' => $validated['duration_ms'] ?? null,
            'consecutive_failures' => $validated['consecutive_failures'] ?? 0,
            'error_code' => $validated['error_code'] ?? null,
            'checked_at' => $validated['checked_at'],
            'context' => $validated['context'] ?? null,
        ]);

        return response()->json([
            'recorded' => true,
            'id' => $check->getKey(),
        ], 201);
    }
}
