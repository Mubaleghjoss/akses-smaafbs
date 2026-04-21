@php
    $nodes = collect($nodes ?? []);
    $periods = collect($periods ?? []);
    $emptyMessage = $emptyMessage ?? 'Data profil belum dipublikasikan saat ini.';
    $showProfileLinks = $showProfileLinks ?? true;
@endphp

@if($periods->isNotEmpty())
    @php
        $latestPeriod = $periods->first();
        $latestNodes = collect(data_get($latestPeriod, 'nodes', []));
        $archivedPeriods = $periods->slice(1)->values();
    @endphp

    @if($latestNodes->isEmpty())
        <div class="card p-5 text-sm text-slate-500">{{ $emptyMessage }}</div>
    @else
        <div class="space-y-4">
            <div
                class="rounded-3xl border border-slate-200 bg-slate-50/80 p-4 md:p-5"
                data-committee-period-current="{{ data_get($latestPeriod, 'label', 'Periode terbaru') }}"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Periode terbaru</div>
                        <h3 class="mt-2 text-lg font-semibold text-slate-900 md:text-xl">{{ data_get($latestPeriod, 'label', 'Periode terbaru') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Frontend menampilkan periode komite terbaru terlebih dahulu agar bagan aktif tetap menjadi fokus utama.</p>
                    </div>
                    <div class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                        {{ number_format((int) data_get($latestPeriod, 'count', $latestNodes->count())) }} posisi
                    </div>
                </div>
            </div>

            @include('partials.home-profile-org-tree', [
                'nodes' => $latestNodes,
                'showProfileLinks' => $showProfileLinks,
            ])

            @if($archivedPeriods->isNotEmpty())
                <div class="space-y-3 rounded-3xl border border-slate-200 bg-white p-4 md:p-5" data-committee-period-archive>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Arsip Periode</div>
                            <p class="mt-1 text-sm text-slate-500">Periode yang lebih lama disimpan di bagian paling bawah agar bagan terbaru tetap ringkas.</p>
                        </div>
                        <div class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                            {{ number_format($archivedPeriods->count()) }} arsip
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach($archivedPeriods as $period)
                            @php
                                $periodNodes = collect(data_get($period, 'nodes', []));
                            @endphp

                            <details class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50/70" data-committee-period-item="{{ data_get($period, 'label', 'Periode lama') }}">
                                <summary class="committee-period__summary cursor-pointer px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-semibold text-slate-900">{{ data_get($period, 'label', 'Periode lama') }}</div>
                                            <div class="mt-1 text-xs text-slate-500">Klik untuk membuka arsip bagan periode ini.</div>
                                        </div>
                                        <div class="inline-flex rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                                            {{ number_format((int) data_get($period, 'count', $periodNodes->count())) }} posisi
                                        </div>
                                    </div>
                                </summary>

                                <div class="border-t border-slate-200 px-3 pb-3 pt-4 md:px-4 md:pb-4">
                                    @include('partials.home-profile-org-tree', [
                                        'nodes' => $periodNodes,
                                        'showProfileLinks' => $showProfileLinks,
                                    ])
                                </div>
                            </details>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
@elseif($nodes->isEmpty())
    <div class="card p-5 text-sm text-slate-500">{{ $emptyMessage }}</div>
@else
    @include('partials.home-profile-org-tree', [
        'nodes' => $nodes,
        'showProfileLinks' => $showProfileLinks,
    ])
@endif
