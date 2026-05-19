<x-filament-panels::page>
    @include('filament.pages.partials.sarpras-sticker-preview', [
        'settings' => array_merge(
            \App\Support\Sarpras\SarprasStickerSettings::defaults(),
            collect($this->data ?? [])
                ->map(fn ($value) => is_array($value) ? (array_values($value)[0] ?? '') : $value)
                ->all(),
        ),
    ])

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Simpan Pengaturan
            </x-filament::button>

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Logo, ukuran, dan teks ini dipakai untuk download stiker satuan maupun bulk.
            </p>
        </div>
    </form>
</x-filament-panels::page>
