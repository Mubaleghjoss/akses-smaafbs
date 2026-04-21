<x-filament-widgets::widget>
    <x-filament::section>
        <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Kelengkapan Data Tes</div>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $completionStatus['classes'] }}">
                            {{ $completionStatus['label'] }}
                        </span>
                    </div>
                    <div class="mt-1 text-sm text-slate-700">
                        {{ number_format((int) ($dataTesSummary['filled'] ?? 0)) }} dari {{ number_format((int) ($dataTesSummary['total'] ?? 0)) }} siswa sudah memiliki Data Tes Siswa lengkap.
                    </div>
                </div>
                <div class="inline-flex items-center rounded-full border border-sky-300 bg-white px-3 py-1 text-sm font-semibold text-sky-800">
                    {{ (int) ($dataTesSummary['completion_percentage'] ?? 0) }}%
                </div>
            </div>

            <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-slate-200">
                <div
                    class="h-full rounded-full bg-gradient-to-r from-sky-500 via-emerald-500 to-emerald-600 transition-all"
                    style="width: {{ max(0, min(100, (int) ($dataTesSummary['completion_percentage'] ?? 0))) }}%;"
                ></div>
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                <a
                    href="{{ $filterUrls['missing'] }}"
                    class="inline-flex items-center rounded-xl border border-amber-300 bg-white px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-50"
                >
                    Lihat yang Belum Ada
                </a>
                <a
                    href="{{ $templateUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    data-navigate="false"
                    class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Unduh Template
                </a>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <a
                href="{{ $filterUrls['filled'] }}"
                class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold transition {{ $currentDataTesStatus === 'filled'
                    ? 'border-emerald-400 bg-emerald-600 text-white shadow-sm'
                    : 'border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' }}"
            >
                Sudah Ada Data Tes: {{ number_format((int) ($dataTesSummary['filled'] ?? 0)) }}
            </a>
            <a
                href="{{ $filterUrls['missing'] }}"
                class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold transition {{ $currentDataTesStatus === 'missing'
                    ? 'border-amber-400 bg-amber-500 text-white shadow-sm'
                    : 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100' }}"
            >
                Belum Ada Data Tes: {{ number_format((int) ($dataTesSummary['missing'] ?? 0)) }}
            </a>
            @if (filled($currentDataTesStatus))
                <a
                    href="{{ $filterUrls['all'] }}"
                    class="inline-flex items-center rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                >
                    Tampilkan Semua
                </a>
            @endif
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Langkah 1</div>
                <h3 class="mt-2 text-sm font-semibold text-slate-900">Unduh Template</h3>
                <p class="mt-1 text-sm leading-6 text-slate-600">
                    Gunakan <span class="font-semibold">Download Template Data</span> agar format data lengkap dan data tes selalu sesuai sistem.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                        TEMPLATE RESMI
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-sky-700">Langkah 2</div>
                <h3 class="mt-2 text-sm font-semibold text-sky-950">Import Sesuai Kebutuhan</h3>
                <p class="mt-1 text-sm leading-6 text-sky-900/80">
                    Pilih <span class="font-semibold">Import Data Lengkap</span> untuk biodata penuh, atau <span class="font-semibold">Import Data Tes Siswa</span> untuk 4 field tes saja.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full border border-slate-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                        DATA LENGKAP
                    </span>
                    <span class="inline-flex items-center rounded-full border border-sky-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-sky-800">
                        DATA TES
                    </span>
                </div>
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Langkah 3</div>
                <h3 class="mt-2 text-sm font-semibold text-emerald-950">Review dan Filter</h3>
                <p class="mt-1 text-sm leading-6 text-emerald-900/80">
                    Cek hasil review import, lalu gunakan filter <span class="font-semibold">Lihat Data Tes Siswa</span> untuk memverifikasi data yang sudah masuk.
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full border border-emerald-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-emerald-800">
                        REVIEW
                    </span>
                    <span class="inline-flex items-center rounded-full border border-amber-300 bg-white px-2.5 py-1 text-[11px] font-semibold text-amber-800">
                        FILTER
                    </span>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
