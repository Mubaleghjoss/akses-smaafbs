<?php

namespace App\Support\Perpustakaan;

use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSubmissionEvent;
use App\Models\PerpustakaanLiterasiSubmissionTicket;
use App\Models\PublicConnectivityEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LiteracyConnectivityAnalytics
{
    public function snapshot(string $date, string $from, string $to): array
    {
        [$start, $end] = $this->range($date, $from, $to);
        $connectivity = Schema::hasTable('public_connectivity_events')
            ? PublicConnectivityEvent::query()
                ->whereBetween('occurred_at', [$start, $end])
                ->get(['event_type', 'client_hash', 'network_scope', 'occurred_at', 'recovered_at'])
            : collect();
        $tickets = Schema::hasTable('perpustakaan_literasi_submission_tickets')
            ? PerpustakaanLiterasiSubmissionTicket::query()
                ->whereBetween('created_at', [$start, $end])
                ->get(['created_at'])
            : collect();
        $responses = Schema::hasTable('perpustakaan_literasi_responses')
            ? PerpustakaanLiterasiResponse::withTrashed()
                ->whereBetween('submitted_at', [$start, $end])
                ->get(['submitted_at'])
            : collect();
        $receiptViews = Schema::hasTable('perpustakaan_literasi_submission_events')
            ? PerpustakaanLiterasiSubmissionEvent::query()
                ->where('event_code', 'receipt_viewed')
                ->whereBetween('occurred_at', [$start, $end])
                ->get(['occurred_at'])
            : collect();

        $hourly = collect(range((int) $start->format('G'), (int) $end->format('G')))
            ->map(function (int $hour) use ($connectivity, $tickets, $responses, $receiptViews): array {
                $eventsAtHour = $connectivity->filter(
                    fn (PublicConnectivityEvent $event): bool => (int) $event->occurred_at->format('G') === $hour,
                );

                return [
                    'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
                    'devices' => $eventsAtHour->pluck('client_hash')->unique()->count(),
                    'offline' => $eventsAtHour->where('event_type', PublicConnectivityEvent::TYPE_NETWORK_ERROR)->count(),
                    'unavailable' => $eventsAtHour->where('event_type', PublicConnectivityEvent::TYPE_SERVER_UNAVAILABLE)->count(),
                    'tickets' => $this->countAtHour($tickets, 'created_at', $hour),
                    'responses' => $this->countAtHour($responses, 'submitted_at', $hour),
                    'receipts' => $this->countAtHour($receiptViews, 'occurred_at', $hour),
                ];
            })
            ->filter(fn (array $row): bool => collect($row)->except('label')->sum() > 0)
            ->values();

        return [
            'date' => $start->format('d/m/Y'),
            'range' => $start->format('H:i').'–'.$end->format('H:i').' WIB',
            'devices' => $connectivity->pluck('client_hash')->unique()->count(),
            'sessions' => $connectivity->where('event_type', PublicConnectivityEvent::TYPE_SESSION_SEEN)->count(),
            'tickets' => $tickets->count(),
            'responses' => $responses->count(),
            'receipts' => $receiptViews->count(),
            'offline' => $connectivity->where('event_type', PublicConnectivityEvent::TYPE_NETWORK_ERROR)->count(),
            'unavailable' => $connectivity->where('event_type', PublicConnectivityEvent::TYPE_SERVER_UNAVAILABLE)->count(),
            'recovered' => $connectivity->whereNotNull('recovered_at')->count(),
            'school_events' => $connectivity->where('network_scope', 'school')->count(),
            'hourly' => $hourly,
            'start' => $start,
            'end' => $end,
        ];
    }

    private function range(string $date, string $from, string $to): array
    {
        $timezone = (string) config('app.timezone', 'Asia/Jakarta');

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $date = now($timezone)->format('Y-m-d');
        }

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $from) !== 1) {
            $from = '00:00';
        }

        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $to) !== 1) {
            $to = '23:59';
        }

        try {
            $start = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$from, $timezone)->startOfMinute();
            $end = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$to, $timezone)->endOfMinute();
        } catch (\Throwable) {
            $start = now($timezone)->startOfDay();
            $end = now($timezone)->endOfDay();
        }

        if ($end->lessThan($start)) {
            $end = $start->copy()->endOfDay();
        }

        return [$start, $end];
    }

    private function countAtHour(Collection $items, string $field, int $hour): int
    {
        return $items->filter(function (mixed $item) use ($field, $hour): bool {
            $value = data_get($item, $field);

            return $value && (int) Carbon::parse($value)->format('G') === $hour;
        })->count();
    }
}
