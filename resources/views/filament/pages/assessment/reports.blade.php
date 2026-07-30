<x-filament-panels::page>
    <div class="space-y-5">
        @include('filament.pages.assessment.partials.type-navigation')

        <section class="grid gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 md:grid-cols-2">
            <label class="min-w-0 text-sm font-semibold">Periode
                <select wire:model.live="periodId" class="mt-2 w-full min-w-0 rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950">
                    @foreach ($this->getPeriodOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                </select>
            </label>
            <label class="min-w-0 text-sm font-semibold">Template
                <select wire:model.live="templateId" class="mt-2 w-full min-w-0 rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950">
                    @foreach ($this->getTemplateOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                </select>
            </label>
        </section>

        @can('penilaian.report.generate')
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                    <div class="min-w-0">
                        <label class="flex items-center gap-2 text-sm font-semibold">
                            <input type="checkbox" wire:model.live="regenerate" class="rounded border-gray-300">
                            Buat revisi baru
                        </label>
                        @if ($regenerate)
                            <textarea wire:model="regenerationReason" rows="2" class="mt-3 w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950" placeholder="Alasan revisi wajib diisi"></textarea>
                        @else
                            <p class="mt-2 text-sm text-gray-500">Jika snapshot terbaru sudah ada, sistem tidak menggandakan rapor.</p>
                        @endif
                    </div>
                    <x-filament::button wire:click="generateReports" wire:loading.attr="disabled" icon="heroicon-o-play">Buat PDF Bertahap</x-filament::button>
                </div>
            </section>
        @endcan

        @if ($latestShareUrl)
            <section x-data="{ copied:false }" class="rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-950/30">
                <h2 class="font-semibold text-success-800 dark:text-success-200">Tautan sementara baru</h2>
                <div class="mt-2 flex min-w-0 flex-col gap-2 sm:flex-row">
                    <input readonly value="{{ $latestShareUrl }}" class="min-w-0 flex-1 rounded-lg border-success-300 bg-white text-sm dark:bg-gray-950">
                    <x-filament::button color="success" x-on:click="navigator.clipboard.writeText(@js($latestShareUrl)); copied=true" x-text="copied ? 'Tersalin' : 'Salin Tautan'"></x-filament::button>
                </div>
                <p class="mt-2 text-xs text-success-700 dark:text-success-300">Token tidak disimpan dalam bentuk asli dan hanya ditampilkan pada pembuatan ini.</p>
            </section>
        @endif

        @php($classRows = $this->getClassRows())
        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <h2 class="font-bold text-gray-950 dark:text-white">PDF Gabungan Per Kelas</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @forelse ($classRows as $row)
                    <article class="min-w-0 rounded-xl border border-gray-200 p-3 dark:border-white/10">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0"><strong class="break-words">{{ $row['rombel'] }}</strong><p class="text-xs text-gray-500">Revisi {{ $row['revision'] }}</p></div>
                            <span class="shrink-0 rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold dark:bg-white/10">{{ $row['status_label'] }}</span>
                        </div>
                        @if ($row['error'])<p class="mt-2 break-words text-xs text-danger-600">{{ $row['error'] }}</p>@endif
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($row['download_url'])<x-filament::button tag="a" href="{{ $row['download_url'] }}" target="_blank" size="sm" color="gray">Download</x-filament::button>@endif
                            @can('penilaian.report.generate')
                                @if ($row['status'] === 'failed')<x-filament::button wire:click="retryClass({{ $row['id'] }})" size="sm" color="warning">Coba Lagi</x-filament::button>@endif
                            @endcan
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-gray-500">Belum ada PDF kelas.</p>
                @endforelse
            </div>
        </section>

        @php($snapshotRows = $this->getSnapshotRows())
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-4 dark:border-white/10">
                <h2 class="font-bold text-gray-950 dark:text-white">Rapor Individual</h2>
                @can('penilaian.publish')
                    <label class="mt-3 block max-w-xs text-sm font-semibold">Masa Tautan
                        <select wire:model="shareExpiryDays" class="mt-1 w-full rounded-lg border-gray-300 dark:border-white/10 dark:bg-gray-950">
                            <option value="1">1 hari</option><option value="3">3 hari</option><option value="7">7 hari</option>
                        </select>
                    </label>
                @endcan
            </div>
            <div class="divide-y divide-gray-200 dark:divide-white/10">
                @forelse ($snapshotRows as $row)
                    <article class="grid min-w-0 gap-3 p-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <strong class="break-words">{{ $row['student'] }}</strong>
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-white/10">{{ $row['rombel'] }}</span>
                                <span class="rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-white/10">{{ $row['status_label'] }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Revisi {{ $row['revision'] }} · tautan aktif {{ $row['active_links'] }}</p>
                            @if ($row['error'])<p class="mt-1 break-words text-xs text-danger-600">{{ $row['error'] }}</p>@endif
                        </div>
                        <div class="flex flex-wrap gap-2 md:justify-end">
                            @if ($row['download_url'])<x-filament::button tag="a" href="{{ $row['download_url'] }}" target="_blank" size="sm" color="gray">Download</x-filament::button>@endif
                            @can('penilaian.report.generate')
                                @if ($row['status'] === 'failed')<x-filament::button wire:click="retrySnapshot({{ $row['id'] }})" size="sm" color="warning">Coba Lagi</x-filament::button>@endif
                            @endcan
                            @can('penilaian.publish')
                                @if ($row['status'] === 'completed')<x-filament::button wire:click="issueShareLink({{ $row['id'] }})" size="sm" color="success">Buat Tautan</x-filament::button>@endif
                                @if ($row['active_links'] > 0)<x-filament::button wire:click="revokeShareLinks({{ $row['id'] }})" wire:confirm="Cabut seluruh tautan aktif rapor ini?" size="sm" color="danger">Cabut</x-filament::button>@endif
                            @endcan
                        </div>
                    </article>
                @empty
                    <div class="p-8 text-center text-sm text-gray-500">Belum ada snapshot rapor.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
