@props([
    'record',
    'active' => null,
])

@php
    $items = [
        'bacaan' => [
            'number' => '1',
            'title' => "Materi Qur'an",
            'subtitle' => 'Bacaan',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('bacaan', ['record' => $record]),
            'activeFor' => ['bacaan'],
        ],
        'makna_quran' => [
            'number' => '2',
            'title' => "Materi Qur'an",
            'subtitle' => 'Makna',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('makna', ['record' => $record]),
            'activeFor' => ['makna', 'makna_quran'],
        ],
        'makna_hadits' => [
            'number' => '3',
            'title' => 'Materi Hadits',
            'subtitle' => 'Makna',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('makna', ['record' => $record]),
            'activeFor' => ['makna', 'makna_hadits'],
        ],
        'pengetesan_makna' => [
            'number' => '4',
            'title' => 'Pengetesan',
            'subtitle' => 'Makna',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('materi', ['record' => $record]).'#materi-boarding-editor',
            'activeFor' => ['materi', 'pengetesan_makna'],
        ],
        'hafalan' => [
            'number' => '5',
            'title' => 'Materi',
            'subtitle' => 'Hafalan',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('hafalan', ['record' => $record]),
            'activeFor' => ['hafalan'],
        ],
    ];
@endphp

<section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Menu Materi Boarding</h3>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($items as $key => $item)
            @php
                $isActive = in_array($active, $item['activeFor'], true);
            @endphp

            <a
                href="{{ $item['url'] }}"
                @class([
                    'group flex h-full min-h-20 items-start gap-3 rounded-xl border bg-white p-3 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-green-400 hover:bg-green-50 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:bg-white dark:hover:bg-green-50',
                    'border-green-500 ring-2 ring-green-500 ring-offset-1' => $isActive,
                    'border-gray-200 dark:border-white/20' => ! $isActive,
                ])
                @if ($isActive) aria-current="page" @endif
            >
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-green-100 text-sm font-bold text-green-700 transition group-hover:bg-green-200">
                    {{ $item['number'] }}
                </span>

                <span class="min-w-0">
                    <span class="block text-sm font-semibold leading-5 text-green-800">
                        {{ $item['title'] }}
                    </span>
                    <span class="mt-1 block text-xs font-medium uppercase tracking-wide text-gray-500">
                        {{ $item['subtitle'] }}
                    </span>
                </span>
            </a>
        @endforeach
    </div>
</section>
