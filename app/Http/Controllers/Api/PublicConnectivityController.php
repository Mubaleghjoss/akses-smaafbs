<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PublicConnectivityEvent;
use App\Support\Perpustakaan\AnonymousConnectivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PublicConnectivityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'uuid'],
            'events' => ['required', 'array', 'min:1', 'max:10'],
            'events.*.event_uuid' => ['required', 'uuid'],
            'events.*.event_type' => ['required', 'in:session_seen,navigation_network_error,navigation_server_unavailable'],
            'events.*.route_group' => ['required', 'in:home,literacy_list,literacy_material,login,other_public'],
            'events.*.http_status' => ['nullable', 'integer', 'min:0', 'max:599'],
            'events.*.service_worker_version' => ['nullable', 'string', 'max:30'],
            'events.*.occurred_at' => ['required', 'date'],
            'events.*.recovered_at' => ['nullable', 'date'],
        ]);

        $now = now();
        $clientHash = AnonymousConnectivity::hashClient((string) $validated['client_id']);
        $ipHash = AnonymousConnectivity::hashIp($request->ip());
        $networkScope = AnonymousConnectivity::networkScope($ipHash);
        $rows = collect($validated['events'])
            ->map(function (array $event) use ($now, $clientHash, $ipHash, $networkScope): array {
                $occurredAt = Carbon::parse($event['occurred_at']);
                $recoveredAt = filled($event['recovered_at'] ?? null)
                    ? Carbon::parse($event['recovered_at'])
                    : null;

                if ($occurredAt->greaterThan($now->copy()->addMinutes(5))) {
                    $occurredAt = $now->copy();
                }

                if ($occurredAt->lessThan($now->copy()->subDays(2))) {
                    $occurredAt = $now->copy()->subDays(2);
                }

                if ($recoveredAt?->greaterThan($now->copy()->addMinutes(5))) {
                    $recoveredAt = $now->copy();
                }

                return [
                    'event_uuid' => $event['event_uuid'],
                    'event_type' => $event['event_type'],
                    'route_group' => $event['route_group'],
                    'client_hash' => $clientHash,
                    'recovery_ip_hash' => $ipHash,
                    'network_scope' => $networkScope,
                    'http_status' => $event['http_status'] ?? null,
                    'service_worker_version' => filled($event['service_worker_version'] ?? null)
                        ? mb_substr((string) $event['service_worker_version'], 0, 30)
                        : null,
                    'occurred_at' => $occurredAt,
                    'recovered_at' => $recoveredAt,
                    'offline_duration_seconds' => AnonymousConnectivity::offlineDuration($occurredAt, $recoveredAt),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();

        $accepted = PublicConnectivityEvent::query()->insertOrIgnore($rows);

        return response()->json([
            'accepted' => $accepted,
            'received' => count($rows),
        ], 202, ['Cache-Control' => 'no-store']);
    }
}
