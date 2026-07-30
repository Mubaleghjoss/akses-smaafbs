@php
    $assessmentNavigation = $this->getAssessmentNavigationData();
    $assessmentAccess = $this->getAssessmentAccessSummary();
@endphp

<div class="assessment-context-stack">
    <nav class="assessment-context-nav" aria-label="Navigasi Penilaian">
        <div class="assessment-context-nav__crumbs">
            <a href="{{ $assessmentNavigation['dashboard_url'] }}" wire:navigate>
                <x-filament::icon icon="heroicon-o-arrow-left" />
                Penilaian
            </a>
            <span aria-hidden="true">/</span>
            <a href="{{ $assessmentNavigation['hub_url'] }}" wire:navigate>{{ $assessmentNavigation['type_label'] }}</a>
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
</div>
