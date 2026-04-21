@php
    use Filament\Support\Facades\FilamentView;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <div
        x-data="{
            open: false,
            selected: null,
            chartId: @js($chartIdentifier),
            currentPath: window.location.pathname,
            pageComponentName: 'App\\Filament\\Resources\\DataSiswaResource\\Pages\\ManageDataSiswas',
            resolvePageComponent() {
                return (window.Livewire?.all?.() ?? []).find((component) => component.name === this.pageComponentName) ?? null
            },
            applySamePageFilters(detail) {
                const payload = detail?.payload ?? null
                const url = payload?.url ?? null
                const filters = payload?.filters ?? null

                if (! url || ! filters) {
                    return false
                }

                const targetUrl = new URL(url, window.location.origin)
                const pageComponent = this.resolvePageComponent()

                if (targetUrl.pathname !== this.currentPath || typeof pageComponent?.$wire?.$call !== 'function') {
                    return false
                }

                window.history.replaceState({}, document.title, targetUrl.toString())
                pageComponent.$wire.$call('applyChartFiltersFromWidget', filters, payload?.chartQuery ?? {})

                return true
            },
            openDetail(detail) {
                if ((detail?.chartId ?? null) !== this.chartId) {
                    return
                }

                if (detail?.payload?.url) {
                    if (this.applySamePageFilters(detail)) {
                        return
                    }

                    window.location.assign(detail.payload.url)

                    return
                }

                this.selected = detail.payload ?? null
                this.open = !! this.selected
            },
            closeDetail() {
                this.open = false
                this.selected = null
            },
        }"
        x-on:interactive-doughnut-chart:segment-selected.window="openDetail($event.detail)"
        x-on:keydown.escape.window="closeDetail()"
    >
        <x-filament::section :description="$description" :heading="$heading">
            @if ($filters)
                <x-slot name="headerEnd">
                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="filter"
                        class="w-max sm:-my-2"
                    >
                        <x-filament::input.select
                            inline-prefix
                            wire:model.live="filter"
                        >
                            @foreach ($filters as $value => $label)
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </x-slot>
            @endif

            <div
                @if ($pollingInterval = $this->getPollingInterval())
                    wire:poll.{{ $pollingInterval }}="updateChartData"
                @endif
            >
                <div
                    @if (FilamentView::hasSpaMode())
                        x-load="visible"
                    @else
                        x-load
                    @endif
                    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                    wire:ignore
                    x-data="chart({
                                cachedData: @js($this->getCachedData()),
                                options: @js($this->getOptions()),
                                type: @js($this->getType()),
                            })"
                    @class([
                        match ($color) {
                            'gray' => null,
                            default => 'fi-color-custom',
                        },
                        is_string($color) ? "fi-color-{$color}" : null,
                    ])
                    class="min-h-[16rem]"
                >
                    <canvas
                        x-ref="canvas"
                        @if ($maxHeight = $this->getMaxHeight())
                            style="max-height: {{ $maxHeight }}"
                        @endif
                    ></canvas>

                    <span
                        x-ref="backgroundColorElement"
                        @class([
                            match ($color) {
                                'gray' => 'text-gray-100 dark:text-gray-800',
                                default => 'text-custom-50 dark:text-custom-400/10',
                            },
                        ])
                        @style([
                            \Filament\Support\get_color_css_variables(
                                $color,
                                shades: [50, 400],
                                alias: 'widgets::chart-widget.background',
                            ) => $color !== 'gray',
                        ])
                    ></span>

                    <span
                        x-ref="borderColorElement"
                        @class([
                            match ($color) {
                                'gray' => 'text-gray-400',
                                default => 'text-custom-500 dark:text-custom-400',
                            },
                        ])
                        @style([
                            \Filament\Support\get_color_css_variables(
                                $color,
                                shades: [400, 500],
                                alias: 'widgets::chart-widget.border',
                            ) => $color !== 'gray',
                        ])
                    ></span>

                    <span
                        x-ref="gridColorElement"
                        class="text-gray-200 dark:text-gray-800"
                    ></span>

                    <span
                        x-ref="textColorElement"
                        class="text-gray-500 dark:text-gray-400"
                    ></span>
                </div>
            </div>
        </x-filament::section>

        <template x-teleport="body">
            <div
                x-cloak
                x-show="open"
                x-transition.opacity.duration.150ms
                class="fixed inset-0 z-[100] flex items-center justify-center p-4"
            >
                <div
                    class="absolute inset-0 bg-gray-950/60 backdrop-blur-sm"
                    x-on:click="closeDetail()"
                ></div>

                <div
                    x-show="open"
                    x-transition.duration.150ms
                    class="relative z-[101] w-full max-w-lg overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="space-y-5 p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <p class="text-xs font-medium uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Detail Diagram</p>
                                <h3 class="text-lg font-semibold text-gray-950 dark:text-white" x-text="selected?.label ?? 'Detail Data'"></h3>
                            </div>

                            <button
                                type="button"
                                class="rounded-full p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                                x-on:click="closeDetail()"
                            >
                                <span class="sr-only">Tutup</span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800/70">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Jumlah Data</p>
                                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white" x-text="selected?.countLabel ?? '-'"></p>
                            </div>

                            <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800/70">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Filter Tujuan</p>
                                <p class="mt-2 text-sm font-medium text-gray-950 dark:text-white" x-text="selected?.filterLabel ?? '-'"></p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Penjelasan</p>
                            <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-gray-300" x-text="selected?.description ?? 'Tidak ada detail tambahan.'"></p>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                x-on:click="closeDetail()"
                            >
                                Tutup
                            </button>

                            <a
                                x-bind:href="selected?.url ?? '#'"
                                class="inline-flex items-center justify-center rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-500"
                            >
                                Buka Data Terfilter
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-filament-widgets::widget>
