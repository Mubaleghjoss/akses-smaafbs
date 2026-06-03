@props([
    'record',
    'active' => null,
])

@php
    $items = [
        'bacaan' => [
            'number' => '1',
            'title' => "Materi Qur'an Bacaan",
            'subtitle' => "Input hasil bacaan Qur'an",
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('bacaan', ['record' => $record]),
            'activeFor' => ['bacaan'],
        ],
        'makna_quran' => [
            'number' => '2',
            'title' => "Materi Qur'an Makna",
            'subtitle' => "Progress makna Qur'an",
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('makna', ['record' => $record]),
            'activeFor' => ['makna', 'makna_quran'],
        ],
        'makna_hadits' => [
            'number' => '3',
            'title' => 'Materi Hadits Makna',
            'subtitle' => 'Progress makna hadits',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('makna', ['record' => $record]),
            'activeFor' => ['makna_hadits'],
        ],
        'pengetesan_makna' => [
            'number' => '4',
            'title' => 'Pengetesan Makna',
            'subtitle' => 'Nilai dan keterangan',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('materi', ['record' => $record]).'#materi-boarding-editor',
            'activeFor' => ['materi', 'pengetesan_makna'],
        ],
        'hafalan' => [
            'number' => '5',
            'title' => 'Materi Hafalan',
            'subtitle' => 'Nilai hafalan per kelas',
            'url' => \App\Filament\Resources\BoardingPencapaianResource::getUrl('hafalan', ['record' => $record]),
            'activeFor' => ['hafalan'],
        ],
    ];
@endphp

<section class="boarding-material-menu">
    <div class="boarding-material-menu__head">
        <div>
            <span class="boarding-material-menu__eyebrow">Menu Materi</span>
            <h3>Menu Materi Boarding</h3>
        </div>
        <p>Pilih bagian materi yang akan diisi untuk murid ini.</p>
    </div>

    <div class="boarding-material-menu__grid">
        @foreach ($items as $key => $item)
            @php
                $isActive = in_array($active, $item['activeFor'], true);
            @endphp

            <a
                href="{{ $item['url'] }}"
                @class([
                    'boarding-material-card',
                    'boarding-material-card--active' => $isActive,
                ])
                @if ($isActive) aria-current="page" @endif
            >
                <span class="boarding-material-card__number">
                    {{ $item['number'] }}
                </span>

                <span class="boarding-material-card__body">
                    <span class="boarding-material-card__title">
                        {{ $item['title'] }}
                    </span>
                    <span class="boarding-material-card__subtitle">
                        {{ $item['subtitle'] }}
                    </span>
                </span>

                <span class="boarding-material-card__arrow" aria-hidden="true">&rsaquo;</span>
            </a>
        @endforeach
    </div>
</section>
