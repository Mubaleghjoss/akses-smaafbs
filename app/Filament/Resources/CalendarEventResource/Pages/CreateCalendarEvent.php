<?php

namespace App\Filament\Resources\CalendarEventResource\Pages;

use App\Filament\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateCalendarEvent extends Page
{
    protected static string $resource = CalendarEventResource::class;

    protected string $view = 'filament.resources.calendar-event-resource.pages.create-calendar-event';

    public array $events = [];

    public string $importText = '';

    public string $importVisibility = 'external';

    public string $deleteMode = 'month';

    public int $deleteMonth = 0;

    public int $deleteYear = 0;

    public function mount(): void
    {
        abort_unless(static::getResource()::canCreate(), 403);

        if ($this->deleteMonth <= 0) {
            $this->deleteMonth = (int) now()->format('n');
        }
        if ($this->deleteYear <= 0) {
            $this->deleteYear = (int) now()->format('Y');
        }

        $this->events = CalendarEvent::query()
            ->orderBy('start')
            ->orderBy('title')
            ->get()
            ->map(fn (CalendarEvent $event) => $this->formatCalendarEvent($event))
            ->values()
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('index')
                ->label('Lihat daftar')
                ->url(static::getResource()::getUrl('index'))
                ->color('gray'),
        ];
    }

    public function createEvent(array $data): void
    {
        abort_unless(static::getResource()::canCreate(), 403);

        $validated = validator($data, [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'visibility' => ['required', 'in:external,internal'],
        ])->validate();

        [$start, $end] = $this->normalizeDateRange($validated['start'], $validated['end'] ?? null);

        [$events, $replacedIds] = $this->replaceCalendarEvents([[
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start' => $start,
            'end' => $end,
            'visibility' => $validated['visibility'],
        ]]);

        $this->dispatchCalendarEventsReplaced($events, $replacedIds);

        Notification::make()
            ->title('Agenda tersimpan')
            ->body($this->buildReplacementMessage($replacedIds))
            ->success()
            ->send();
    }

    public function createEvents(array $data): void
    {
        abort_unless(static::getResource()::canCreate(), 403);

        $validated = validator($data, [
            'titles' => ['required', 'array', 'min:1'],
            'titles.*' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'visibility' => ['required', 'in:external,internal'],
        ])->validate();

        $titles = array_values(array_filter(array_map('trim', $validated['titles'] ?? []), static function ($title) {
            return $title !== '';
        }));

        if ($titles === []) {
            Notification::make()
                ->title('Nama kegiatan masih kosong')
                ->warning()
                ->send();

            return;
        }

        [$start, $end] = $this->normalizeDateRange($validated['start'], $validated['end'] ?? null);

        $items = [];
        foreach ($titles as $title) {
            $items[] = [
                'title' => $title,
                'description' => $validated['description'] ?? null,
                'start' => $start,
                'end' => $end,
                'visibility' => $validated['visibility'],
            ];
        }

        [$created, $replacedIds] = $this->replaceCalendarEvents($items);

        $this->dispatchCalendarEventsReplaced($created, $replacedIds);

        Notification::make()
            ->title('Agenda tersimpan')
            ->body($this->buildReplacementMessage($replacedIds, 'Total '.count($created).' agenda disimpan.'))
            ->success()
            ->send();
    }

    public function importFromText(): void
    {
        abort_unless(static::getResource()::canCreate(), 403);

        $visibility = validator(
            ['visibility' => $this->importVisibility],
            ['visibility' => ['required', 'in:external,internal']]
        )->validate()['visibility'];

        $text = trim((string) $this->importText);
        if ($text === '') {
            Notification::make()
                ->title('Teks agenda masih kosong')
                ->warning()
                ->send();

            return;
        }

        [$parsedItems, $skipped] = $this->parseImportText($text);

        if (count($parsedItems) === 0) {
            Notification::make()
                ->title('Format teks belum dikenali')
                ->body('Pastikan ada baris tanggal dan daftar kegiatan.')
                ->warning()
                ->send();

            return;
        }

        $items = [];
        foreach ($parsedItems as $item) {
            $items[] = [
                'title' => $item['title'],
                'description' => $item['description'],
                'start' => $item['start'],
                'end' => $item['end'],
                'visibility' => $visibility,
            ];
        }

        [$created, $replacedIds] = $this->replaceCalendarEvents($items);

        $this->dispatchCalendarEventsReplaced($created, $replacedIds);

        $this->importText = '';

        $message = 'Agenda berhasil diimpor ('.count($created).' kegiatan).';
        if (count($replacedIds) > 0) {
            $message .= ' '.count($replacedIds).' agenda lama pada tanggal yang sama diganti.';
        }
        if ($skipped > 0) {
            $message .= ' Ada '.$skipped.' baris yang dilewati.';
        }

        Notification::make()
            ->title('Import selesai')
            ->body($message)
            ->success()
            ->send();
    }

    public function deleteSchedule(): void
    {
        abort_unless(static::getResource()::canDeleteAny(), 403);

        $data = validator([
            'mode' => $this->deleteMode,
            'month' => $this->deleteMonth,
            'year' => $this->deleteYear,
        ], [
            'mode' => ['required', 'in:month,year'],
            'month' => ['required_if:mode,month', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ])->validate();

        $year = (int) $data['year'];
        $month = (int) ($data['month'] ?? 1);

        if ($data['mode'] === 'year') {
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end = Carbon::create($year, 12, 31)->endOfDay();
        } else {
            $start = Carbon::create($year, $month, 1)->startOfDay();
            $end = $start->copy()->endOfMonth()->endOfDay();
        }

        $query = CalendarEvent::query()
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start', [$start, $end])
                    ->orWhereBetween('end', [$start, $end])
                    ->orWhere(function ($query) use ($start, $end) {
                        $query->where('start', '<=', $start)
                            ->where('end', '>=', $end);
                    });
            });

        $ids = $query->pluck('id')->all();
        if (count($ids) === 0) {
            Notification::make()
                ->title('Tidak ada agenda yang dihapus')
                ->warning()
                ->send();

            return;
        }

        CalendarEvent::query()->whereIn('id', $ids)->delete();

        $this->dispatch('calendar-events-deleted-bulk', calendarEventIds: $ids);

        Notification::make()
            ->title('Agenda dihapus')
            ->body('Total '.count($ids).' agenda berhasil dihapus.')
            ->success()
            ->send();
    }

    public function updateEvent(int $id, array $data): void
    {
        $event = CalendarEvent::query()->findOrFail($id);

        abort_unless(static::getResource()::canEdit($event), 403);

        $validated = validator($data, [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'visibility' => ['required', 'in:external,internal'],
        ])->validate();

        [$start, $end] = $this->normalizeDateRange($validated['start'], $validated['end'] ?? null);

        $replacedIds = $this->deleteOverlappingCalendarEvents([[
            'start' => $start,
            'end' => $end,
            'visibility' => $validated['visibility'],
        ]], $event->id);

        $event->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'start' => $start,
            'end' => $end,
            'all_day' => true,
            'visibility' => $validated['visibility'],
        ]);

        $this->dispatch('calendar-event-updated', calendarEvent: $this->formatCalendarEvent($event));
        if ($replacedIds !== []) {
            $this->dispatch('calendar-events-deleted-bulk', calendarEventIds: $replacedIds);
        }

        Notification::make()
            ->title('Agenda diperbarui')
            ->body($this->buildReplacementMessage($replacedIds))
            ->success()
            ->send();
    }

    public function deleteEvent(int $id): void
    {
        $event = CalendarEvent::query()->findOrFail($id);

        abort_unless(static::getResource()::canDelete($event), 403);

        $event->delete();

        $this->dispatch('calendar-event-deleted', calendarEventId: $id);

        Notification::make()
            ->title('Agenda dihapus')
            ->success()
            ->send();
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
            'visibility' => (string) ($event->visibility ?? 'external'),
        ];

        if (($event->visibility ?? 'external') === 'internal') {
            $payload['backgroundColor'] = '#f59e0b';
            $payload['borderColor'] = '#d97706';
            $payload['textColor'] = '#111827';
        } else {
            $payload['backgroundColor'] = '#2563eb';
            $payload['borderColor'] = '#1d4ed8';
            $payload['textColor'] = '#ffffff';
        }

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

    protected function normalizeDateRange(string $startValue, $endValue = null): array
    {
        $start = Carbon::parse($startValue)->startOfDay();
        $end = isset($endValue) && $endValue !== ''
            ? Carbon::parse($endValue)->startOfDay()
            : null;

        if ($end && $end->isSameDay($start)) {
            $end = null;
        }

        return [$start, $end];
    }

    protected function replaceCalendarEvents(array $items): array
    {
        return DB::transaction(function () use ($items): array {
            $replacedIds = $this->deleteOverlappingCalendarEvents($this->uniqueReplacementRanges($items));
            $created = [];

            foreach ($items as $item) {
                $created[] = CalendarEvent::query()->create([
                    'title' => $item['title'],
                    'description' => $item['description'] ?? null,
                    'start' => $item['start'],
                    'end' => $item['end'],
                    'all_day' => true,
                    'visibility' => $item['visibility'],
                ]);
            }

            return [$created, $replacedIds];
        });
    }

    protected function uniqueReplacementRanges(array $items): array
    {
        $ranges = [];

        foreach ($items as $item) {
            $start = $item['start'];
            $end = $item['end'] ?? null;
            $visibility = (string) ($item['visibility'] ?? 'external');
            $key = implode('|', [
                $start instanceof Carbon ? $start->toDateString() : (string) $start,
                $end instanceof Carbon ? $end->toDateString() : (string) ($end ?? ''),
                $visibility,
            ]);

            $ranges[$key] = [
                'start' => $start,
                'end' => $end,
                'visibility' => $visibility,
            ];
        }

        return array_values($ranges);
    }

    protected function deleteOverlappingCalendarEvents(array $ranges, ?int $exceptId = null): array
    {
        if ($ranges === []) {
            return [];
        }

        $query = CalendarEvent::query()
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->where(function ($query) use ($ranges) {
                foreach ($ranges as $range) {
                    $start = $range['start'] instanceof Carbon
                        ? $range['start']->copy()->startOfDay()
                        : Carbon::parse($range['start'])->startOfDay();
                    $end = ($range['end'] ?? null) instanceof Carbon
                        ? $range['end']->copy()->endOfDay()
                        : ($range['end'] ? Carbon::parse($range['end'])->endOfDay() : $start->copy()->endOfDay());
                    $visibility = (string) ($range['visibility'] ?? 'external');

                    $query->orWhere(function ($query) use ($start, $end, $visibility) {
                        $query
                            ->where(function ($query) use ($visibility) {
                                if ($visibility === 'external') {
                                    $query->where('visibility', 'external')->orWhereNull('visibility');

                                    return;
                                }

                                $query->where('visibility', $visibility);
                            })
                            ->where('start', '<=', $end)
                            ->where(function ($query) use ($start) {
                                $query
                                    ->where('end', '>=', $start)
                                    ->orWhere(function ($query) use ($start) {
                                        $query->whereNull('end')->where('start', '>=', $start);
                                    });
                            });
                    });
                }
            });

        $ids = $query->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($ids === []) {
            return [];
        }

        abort_unless(static::getResource()::canDeleteAny(), 403);

        CalendarEvent::query()->whereIn('id', $ids)->delete();

        return $ids;
    }

    protected function dispatchCalendarEventsReplaced(array $events, array $replacedIds): void
    {
        $payloads = array_map(fn (CalendarEvent $event) => $this->formatCalendarEvent($event), $events);

        $this->dispatch(
            'calendar-events-replaced',
            calendarEvents: $payloads,
            calendarEventIds: $replacedIds,
        );
    }

    protected function buildReplacementMessage(array $replacedIds, ?string $prefix = null): ?string
    {
        if ($replacedIds === []) {
            return $prefix;
        }

        $message = count($replacedIds).' agenda lama pada tanggal yang sama diganti.';

        return $prefix ? $prefix.' '.$message : $message;
    }

    protected function parseImportText(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $contextMonth = null;
        $contextYear = null;
        $currentStart = null;
        $currentEnd = null;
        $events = [];
        $lastIndex = null;
        $skipped = 0;

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if ($this->parseHeaderMonthYear($line, $contextMonth, $contextYear)) {
                continue;
            }

            $range = $this->parseDateLine($line, $contextMonth, $contextYear);
            if ($range !== null) {
                [$currentStart, $currentEnd] = $range;

                continue;
            }

            if ($this->isBulletLine($line)) {
                $title = $this->stripBullet($line);
                if ($title === '') {
                    continue;
                }

                if (! $currentStart) {
                    $skipped++;

                    continue;
                }

                $events[] = [
                    'title' => $title,
                    'description' => null,
                    'start' => $currentStart->copy(),
                    'end' => $currentEnd ? $currentEnd->copy() : null,
                ];
                $lastIndex = count($events) - 1;

                continue;
            }

            if ($lastIndex !== null) {
                $existing = $events[$lastIndex]['description'] ?? '';
                $extra = trim($line);
                if ($extra !== '') {
                    $events[$lastIndex]['description'] = $existing === '' ? $extra : ($existing."\n".$extra);
                }

                continue;
            }

            $skipped++;
        }

        return [$events, $skipped];
    }

    protected function parseHeaderMonthYear(string $line, ?int &$contextMonth, ?int &$contextYear): bool
    {
        $monthPattern = $this->getMonthPattern();

        if (preg_match('/\b\d{1,2}\b/', $line) === 1) {
            return false;
        }

        if (preg_match('/\b('.$monthPattern.')\s+(\d{4})\b/i', $line, $match) !== 1) {
            return false;
        }

        $month = $this->monthToNumber($match[1] ?? '');
        $year = isset($match[2]) ? (int) $match[2] : null;

        if (! $month || ! $year) {
            return false;
        }

        $contextMonth = $month;
        $contextYear = $year;

        return true;
    }

    protected function parseDateLine(string $line, ?int &$contextMonth, ?int &$contextYear): ?array
    {
        $line = preg_replace('/\b(senin|selasa|rabu|kamis|jum\'?at|jumat|sabtu|minggu|ahad)\b,?/i', '', $line);
        $line = trim((string) $line);
        $line = preg_replace('/\s+/', ' ', $line);

        $monthPattern = $this->getMonthPattern();
        $rangeSeparator = '(?:s\/d|sd|s\.d\.?|s\.d)';

        if (preg_match('/^(\d{1,2})\s+('.$monthPattern.')\s*(\d{4})?\s*'.$rangeSeparator.'\s*(\d{1,2})\s+('.$monthPattern.')\s*(\d{4})?$/i', $line, $match) === 1) {
            $startDay = (int) $match[1];
            $startMonth = $this->monthToNumber($match[2] ?? '');
            $startYear = isset($match[3]) && $match[3] !== '' ? (int) $match[3] : ($contextYear ?? now()->year);
            $endDay = (int) $match[4];
            $endMonth = $this->monthToNumber($match[5] ?? '');
            $endYear = isset($match[6]) && $match[6] !== '' ? (int) $match[6] : $startYear;

            if (! $startMonth || ! $endMonth) {
                return null;
            }

            $start = Carbon::create($startYear, $startMonth, $startDay)->startOfDay();
            $end = Carbon::create($endYear, $endMonth, $endDay)->startOfDay();

            if ($end->lessThan($start)) {
                [$start, $end] = [$end, $start];
            }

            $contextMonth = $startMonth;
            $contextYear = $startYear;

            return [$start, $end];
        }

        if (preg_match('/^(\d{1,2})\s*-\s*(\d{1,2})\s+('.$monthPattern.')\s*(\d{4})?$/i', $line, $match) === 1) {
            $startDay = (int) $match[1];
            $endDay = (int) $match[2];
            $month = $this->monthToNumber($match[3] ?? '');
            $year = isset($match[4]) && $match[4] !== '' ? (int) $match[4] : ($contextYear ?? now()->year);

            if (! $month) {
                return null;
            }

            $start = Carbon::create($year, $month, $startDay)->startOfDay();
            $end = Carbon::create($year, $month, $endDay)->startOfDay();

            if ($end->lessThan($start)) {
                [$start, $end] = [$end, $start];
            }

            $contextMonth = $month;
            $contextYear = $year;

            return [$start, $end];
        }

        if (preg_match('/^(\d{1,2})\s*'.$rangeSeparator.'\s*(\d{1,2})\s+('.$monthPattern.')\s*(\d{4})?$/i', $line, $match) === 1) {
            $startDay = (int) $match[1];
            $endDay = (int) $match[2];
            $month = $this->monthToNumber($match[3] ?? '');
            $year = isset($match[4]) && $match[4] !== '' ? (int) $match[4] : ($contextYear ?? now()->year);

            if (! $month) {
                return null;
            }

            $start = Carbon::create($year, $month, $startDay)->startOfDay();
            $end = Carbon::create($year, $month, $endDay)->startOfDay();

            if ($end->lessThan($start)) {
                [$start, $end] = [$end, $start];
            }

            $contextMonth = $month;
            $contextYear = $year;

            return [$start, $end];
        }

        if (preg_match('/^(\d{1,2})\s+('.$monthPattern.')\s*(\d{4})?$/i', $line, $match) === 1) {
            $day = (int) $match[1];
            $month = $this->monthToNumber($match[2] ?? '');
            $year = isset($match[3]) && $match[3] !== '' ? (int) $match[3] : ($contextYear ?? now()->year);

            if (! $month) {
                return null;
            }

            $start = Carbon::create($year, $month, $day)->startOfDay();

            $contextMonth = $month;
            $contextYear = $year;

            return [$start, null];
        }

        return null;
    }

    protected function isBulletLine(string $line): bool
    {
        return preg_match('/^\s*(?:[-*]|\x{2022}|\d+[.)])\s+/u', $line) === 1;
    }

    protected function stripBullet(string $line): string
    {
        $line = preg_replace('/^\s*(?:[-*]|\x{2022}|\d+[.)])\s+/u', '', $line);

        return trim((string) $line);
    }

    protected function getMonthPattern(): string
    {
        return 'Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember';
    }

    protected function monthToNumber(string $monthName): ?int
    {
        $map = [
            'januari' => 1,
            'februari' => 2,
            'maret' => 3,
            'april' => 4,
            'mei' => 5,
            'juni' => 6,
            'juli' => 7,
            'agustus' => 8,
            'september' => 9,
            'oktober' => 10,
            'november' => 11,
            'desember' => 12,
        ];

        $key = strtolower(trim($monthName));

        return $map[$key] ?? null;
    }
}
