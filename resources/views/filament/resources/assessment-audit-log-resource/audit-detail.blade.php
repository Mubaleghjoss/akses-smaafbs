<div class="min-w-0 space-y-4">
    <dl class="grid min-w-0 gap-3 sm:grid-cols-2">
        @foreach ([
            'Waktu' => $record->created_at?->format('d/m/Y H:i:s') ?? '-',
            'Pelaku' => $record->actor?->name ?? 'Sistem',
            'Periode' => $record->period?->name ?? '-',
            'Subjek' => class_basename($record->subject_type).' #'.$record->subject_id,
            'Alamat IP' => $record->ip_address ?: '-',
            'Alasan' => $record->reason ?: '-',
        ] as $label => $value)
            <div class="min-w-0 rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</dt>
                <dd class="mt-1 break-words text-sm text-gray-950 dark:text-white">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>

    @foreach (['Sebelum' => $record->old_values, 'Sesudah' => $record->new_values] as $label => $values)
        <section class="min-w-0">
            <h3 class="mb-2 text-sm font-bold text-gray-950 dark:text-white">{{ $label }}</h3>
            <pre class="max-h-72 min-w-0 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-gray-950 p-3 text-xs leading-5 text-gray-100">{{ $values === null ? '-' : json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </section>
    @endforeach

    @if ($record->user_agent)
        <section class="min-w-0">
            <h3 class="mb-2 text-sm font-bold text-gray-950 dark:text-white">Perangkat</h3>
            <p class="break-words rounded-xl border border-gray-200 p-3 text-xs text-gray-600 dark:border-white/10 dark:text-gray-300">{{ $record->user_agent }}</p>
        </section>
    @endif
</div>
