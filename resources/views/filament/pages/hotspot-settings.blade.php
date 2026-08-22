<x-filament-panels::page>
    <form wire:submit="simpan" class="max-w-2xl space-y-4">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            @foreach ($this->getCachedFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>
</x-filament-panels::page>