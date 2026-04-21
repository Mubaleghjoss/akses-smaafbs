@props([
    'record',
    'active' => 'hafalan',
])

@php
    $links = [
        'hafalan' => [
            'label' => 'Hafalan',
            'icon' => 'heroicon-o-book-open',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('hafalan', ['record' => $record]),
        ],
        'makna' => [
            'label' => 'Makna',
            'icon' => 'heroicon-o-document-text',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('makna', ['record' => $record]),
        ],
        'bacaan' => [
            'label' => 'Bacaan',
            'icon' => 'heroicon-o-clipboard-document-list',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('bacaan', ['record' => $record]),
        ],
    ];
@endphp

<div class="flex flex-wrap gap-2 md:justify-end">
    @foreach ($links as $key => $link)
        <x-filament::button
            tag="a"
            :href="$link['url']"
            :color="$active === $key ? 'primary' : 'gray'"
            :icon="$link['icon']"
            size="sm"
        >
            {{ $link['label'] }}
        </x-filament::button>
    @endforeach

    <x-filament::button
        tag="a"
        :href="\App\Filament\Resources\BoardingPencapaianResource::getUrl('index')"
        color="gray"
        icon="heroicon-o-arrow-left"
        size="sm"
    >
        Kembali
    </x-filament::button>
</div>
