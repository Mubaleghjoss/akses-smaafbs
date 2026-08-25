@php
    $assessmentNavigation = $this->getAssessmentNavigationData();
    $showAccess = $showAccess ?? true;
    $assessmentAccess = $showAccess ? $this->getAssessmentAccessSummary() : null;
@endphp

<div class="assessment-context-stack">
    {{-- Pemilih JENIS penilaian. Satu tempat, bukan tiga cabang menu:
         berpindah jenis MEMPERTAHANKAN halaman yang sedang dibuka
         (Status ASTS -> Status ASAS), bukan kembali ke beranda. --}}
    <div class="asmt-typebar" role="tablist" aria-label="Jenis Penilaian">
        <span class="asmt-typebar__label">Jenis</span>
        <div class="asmt-typebar__tabs">
            @foreach ($assessmentNavigation['type_tabs'] as $tab)
                @if ($tab['url'])
                    <a
                        href="{{ $tab['url'] }}"
                        wire:navigate
                        role="tab"
                        title="{{ $tab['long_label'] }}"
                        @class(['asmt-typebar__tab', 'is-active' => $tab['active']])
                        @if ($tab['active']) aria-selected="true" @endif
                    >{{ $tab['label'] }}</a>
                @else
                    <span
                        class="asmt-typebar__tab is-disabled"
                        title="Tidak tersedia untuk akun ini"
                    >{{ $tab['label'] }}</span>
                @endif
            @endforeach
        </div>
        <span class="asmt-typebar__hint">{{ $assessmentNavigation['type_long_label'] }}</span>
    </div>

    <nav class="assessment-context-nav" aria-label="Navigasi Penilaian">
        <div class="assessment-context-nav__crumbs">
            <a href="{{ $assessmentNavigation['is_hub'] ? $assessmentNavigation['dashboard_url'] : $assessmentNavigation['hub_url'] }}" wire:navigate>
                <x-filament::icon icon="heroicon-o-arrow-left" />
                {{ $assessmentNavigation['is_hub'] ? 'Kembali ke Penilaian' : 'Kembali ke '.$assessmentNavigation['type_label'] }}
            </a>
        </div>

        <div class="assessment-context-nav__tabs">
            @foreach ($assessmentNavigation['items'] as $item)
                @if ($item['url'])
                    <a
                        href="{{ $item['url'] }}"
                        wire:navigate
                        @class(['is-active' => $item['active']])
                        @if ($item['active']) aria-current="page" @endif
                    >
                        <x-filament::icon :icon="$item['icon']" />
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </nav>

    @if ($showAccess)
        <section @class([
            'assessment-access-card',
            'is-empty' => $assessmentAccess['mode'] === 'empty',
            'is-all' => $assessmentAccess['mode'] === 'all',
        ])>
            <span class="assessment-access-card__icon">
                <x-filament::icon :icon="$assessmentAccess['mode'] === 'empty' ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-identification'" />
            </span>
            <div class="assessment-access-card__body">
                <h2>{{ $assessmentAccess['title'] }}</h2>
                <p>{{ $assessmentAccess['description'] }}</p>

                @if ($assessmentAccess['subjects'] !== [] || $assessmentAccess['homerooms'] !== [])
                    <div class="assessment-access-card__details">
                        @foreach ($assessmentAccess['subjects'] as $subject)
                            <div class="assessment-access-card__detail">
                                <strong>Mapel {{ $subject['subject'] }}</strong>
                                <span>{{ implode(', ', $subject['classes']) }}</span>
                            </div>
                        @endforeach
                        @if ($assessmentAccess['homerooms'] !== [])
                            <div class="assessment-access-card__detail is-homeroom">
                                <strong>Wali Kelas</strong>
                                <span>{{ implode(', ', $assessmentAccess['homerooms']) }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    @endif
</div>
