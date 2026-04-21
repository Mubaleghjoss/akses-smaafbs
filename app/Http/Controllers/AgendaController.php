<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AgendaController extends Controller
{
    public function index()
    {
        return view('agenda', [
            'title' => 'Agenda',
        ]);
    }

    public function events(Request $request)
    {
        $start = Carbon::parse($request->query('start', now()->startOfMonth()));
        $end = Carbon::parse($request->query('end', now()->endOfMonth()));

        $events = CalendarEvent::query()
            ->where('visibility', 'external')
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start', [$start, $end])
                    ->orWhereBetween('end', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end) {
                        $query->where('start', '<=', $start)
                            ->where('end', '>=', $end);
                    });
            })
            ->orderBy('start')
            ->orderBy('title')
            ->get()
            ->map(fn (CalendarEvent $event) => $this->formatCalendarEvent($event))
            ->values();

        return response()->json($events);
    }

    protected function formatCalendarEvent(CalendarEvent $event): array
    {
        $allDay = (bool) ($event->all_day ?? true);
        $start = $event->start;
        $end = $event->end;

        $payload = [
            'id' => $event->id,
            'title' => (string) $event->title,
            'allDay' => $allDay,
            'description' => (string) ($event->description ?? ''),
        ];

        if ($start) {
            $payload['start'] = $allDay
                ? $start->toDateString()
                : $start->toIso8601String();
        }

        if ($end) {
            $payload['end'] = $allDay
                ? $end->copy()->addDay()->toDateString()
                : $end->toIso8601String();
        }

        return $payload;
    }
}
