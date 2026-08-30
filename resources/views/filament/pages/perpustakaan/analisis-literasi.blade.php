@php
    // $shareSections dikirim dari getViewData(); fallback dipakai bila partial
    // ini dirender di luar halaman (mis. pengujian view langsung).
    $shareSections = $shareSections ?? [];
@endphp

<x-filament-panels::page>
    <div class="lit-stack">
        @include('filament.pages.perpustakaan.partials.filter-analisis')

        @include('filament.pages.perpustakaan.partials.ringkasan-responden', [
            'salinTeks' => $shareSections['ringkasan'] ?? '',
        ])

        @include('filament.pages.perpustakaan.partials.partisipasi-kelas', [
            'salinTeks' => $shareSections['partisipasi'] ?? '',
            'salinBelumTeks' => $shareSections['belum'] ?? '',
        ])

        @include('filament.pages.perpustakaan.partials.timeline-walas')

        @include('filament.pages.perpustakaan.partials.dispensasi-ringkas', [
            'salinTeks' => $shareSections['dispensasi'] ?? '',
        ])

        @include('filament.pages.perpustakaan.partials.ranking-literasi', [
            'salinTeks' => $shareSections['ranking'] ?? '',
        ])

        @include('filament.pages.perpustakaan.partials.plagiasi-literasi', [
            'salinTeks' => $shareSections['plagiasi'] ?? '',
        ])
    </div>
</x-filament-panels::page>
