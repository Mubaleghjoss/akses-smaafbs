<x-filament-panels::page>
    <form wire:submit="preview" class="max-w-3xl space-y-4">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            @foreach ($this->getCachedFormActions() as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>

    @if (count($candidates) > 0)
        <div class="fi-section rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="px-5 py-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold">
                        Preview — {{ count($candidates) }} siswa
                        <span class="ml-2 text-sm font-normal text-gray-500">Terpilih: {{ count($selected) }}</span>
                    </h3>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="selectAll" class="rounded">
                        Pilih semua
                    </label>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2"></th>
                                <th class="px-3 py-2">Nama Siswa</th>
                                <th class="px-3 py-2">Rombel</th>
                                <th class="px-3 py-2">Username</th>
                                <th class="px-3 py-2">Password</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($candidates as $i => $c)
                                <tr class="border-b border-gray-100">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" wire:model="selected" value="{{ $i }}" class="rounded">
                                    </td>
                                    <td class="px-3 py-2 font-medium">{{ $c['nama'] }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ $c['rombel'] }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ $c['username'] }}</td>
                                    <td class="px-3 py-2 font-mono text-xs text-gray-500">{{ $c['password'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex justify-end">
                    <button type="button" wire:click="buat"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                            @disabled(count($selected) === 0)>
                        🚀 Buat {{ count($selected) }} Akun
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($studentPushShortcutUrl)
        <div class="mt-4">
            <a href="{{ $studentPushShortcutUrl }}" class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                Preview Push Data Siswa ke Server
            </a>
        </div>
    @endif
</x-filament-panels::page>