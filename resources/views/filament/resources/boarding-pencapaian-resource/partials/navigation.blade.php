@props([
    'record',
    'active' => 'hafalan',
])

@php
    $materiRapotScope = \App\Models\BoardingPencapaian::normalizeMateriRapotScope($record->materi_rapot_scope ?? null);

    $links = $materiRapotScope === \App\Models\BoardingPencapaian::MATERI_RAPOT_SCOPE_MT ? [
        'mt' => [
            'label' => 'Materi MT',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('mt', ['record' => $record]),
        ],
    ] : [
        'materi' => [
            'label' => 'Materi Boarding',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('materi', ['record' => $record]),
        ],
    ];

    $backUrl = \App\Filament\Resources\BoardingPencapaianResource::getUrl('index');
@endphp

<div class="flex flex-col gap-2 md:items-end">
    <div class="text-xs font-medium text-gray-600 dark:text-gray-300">
        Target rapot aktif:
        <span class="font-semibold text-gray-950 dark:text-white">
            {{ \App\Models\BoardingPencapaian::materiRapotScopeLabel($materiRapotScope) }}
        </span>
    </div>

    <div class="flex flex-wrap gap-2 md:justify-end">
        @foreach ($links as $key => $link)
            @php
                $isActive = $active === $key || ($key === 'materi' && in_array($active, ['hafalan', 'makna', 'bacaan'], true));
            @endphp

            <a
                href="{{ $link['url'] }}"
                @class([
                    'inline-flex min-h-9 items-center justify-center rounded-lg border bg-white px-3 py-1.5 text-sm font-semibold text-green-700 shadow-sm transition hover:border-green-300 hover:bg-green-50 hover:text-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:bg-white dark:text-green-700 dark:hover:bg-green-50',
                    'border-green-500 ring-2 ring-green-500 ring-offset-1' => $isActive,
                    'border-gray-200 dark:border-white/20' => ! $isActive,
                ])
                @if ($isActive) aria-current="page" @endif
            >
                {{ $link['label'] }}
            </a>
        @endforeach

        <a
            href="{{ $backUrl }}"
            class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-semibold text-green-700 shadow-sm transition hover:border-green-300 hover:bg-green-50 hover:text-green-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:border-white/20 dark:bg-white dark:text-green-700 dark:hover:bg-green-50"
        >
            <span aria-hidden="true">&larr;</span>
            Kembali
        </a>
    </div>
</div>
