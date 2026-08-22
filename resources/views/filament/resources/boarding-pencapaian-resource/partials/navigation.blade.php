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

<div class="boarding-target-nav">
    <div class="boarding-target-nav__scope">
        <span>Target rapot aktif</span>
        <strong>
            {{ \App\Models\BoardingPencapaian::materiRapotScopeLabel($materiRapotScope) }}
        </strong>
    </div>

    <div class="boarding-target-nav__actions">
        @foreach ($links as $key => $link)
            @php
                $isActive = $active === $key || ($key === 'materi' && in_array($active, ['hafalan', 'makna', 'bacaan'], true));
            @endphp

            <a
                href="{{ $link['url'] }}"
                @class([
                    'boarding-target-nav__button',
                    'boarding-target-nav__button--active' => $isActive,
                ])
                @if ($isActive) aria-current="page" @endif
            >
                {{ $link['label'] }}
            </a>
        @endforeach

        <a
            href="{{ $backUrl }}"
            class="boarding-target-nav__button boarding-target-nav__button--back"
        >
            <span aria-hidden="true">&larr;</span>
            Kembali
        </a>
    </div>
</div>
