<x-filament-panels::page>
    <div class="mx-auto w-full max-w-3xl space-y-4">
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-100">
            Simpan passkey hanya pada perangkat pribadi/tepercaya. Passkey bersifat opsional: login password di <strong>/admin/login</strong> tetap tersedia.
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Daftarkan Passkey</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Form ini memakai seam WebAuthn backend yang sudah ada. Untuk integrasi browser penuh, isi credential dari perangkat autentikator.</p>

            <form wire:submit="enrollPasskey" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="passkey-label" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Label Perangkat</label>
                    <input id="passkey-label" type="text" wire:model.defer="label" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-white/15 dark:bg-gray-950 dark:text-white" placeholder="Laptop kantor" />
                    @error('label')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="passkey-sign-count" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Sign Count Awal</label>
                    <input id="passkey-sign-count" type="number" min="0" wire:model.defer="signCount" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-white/15 dark:bg-gray-950 dark:text-white" placeholder="0" />
                    @error('signCount')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="passkey-credential-id" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Credential ID</label>
                    <input id="passkey-credential-id" type="text" wire:model.defer="credentialId" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-white/15 dark:bg-gray-950 dark:text-white" required />
                    @error('credentialId')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="passkey-public-key" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Public Key</label>
                    <textarea id="passkey-public-key" rows="3" wire:model.defer="publicKey" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-white/15 dark:bg-gray-950 dark:text-white" required></textarea>
                    @error('publicKey')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label for="passkey-transports" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Transport (opsional)</label>
                    <input id="passkey-transports" type="text" wire:model.defer="transports" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-white/15 dark:bg-gray-950 dark:text-white" placeholder="internal, usb, hybrid" />
                    @error('transports')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-300">
                        Tambahkan Passkey
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Passkey Terdaftar</h3>

            <div class="mt-4 space-y-3">
                @forelse ($this->credentials as $credential)
                    <article class="rounded-xl border border-gray-200 p-4 text-sm dark:border-white/10">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 space-y-1">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $credential->label ?: 'Tanpa label' }}</div>
                                <div class="break-all text-xs text-gray-600 dark:text-gray-300">ID: {{ $credential->credential_id }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Transport: {{ collect($credential->transports ?? [])->implode(', ') ?: '-' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Terakhir dipakai: {{ $credential->last_used_at?->diffForHumans() ?? 'Belum pernah' }}</div>
                            </div>

                            @if ($credential->revoked_at)
                                <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-1 text-xs font-medium text-rose-700 dark:bg-rose-500/20 dark:text-rose-200">Dinonaktifkan</span>
                            @else
                                <button type="button" wire:click="revokePasskey('{{ $credential->credential_id }}')" class="inline-flex items-center rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-500/40 dark:text-rose-200 dark:hover:bg-rose-500/10">
                                    Nonaktifkan
                                </button>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 px-4 py-5 text-sm text-gray-600 dark:border-white/20 dark:text-gray-300">
                        Belum ada passkey untuk akun ini. Tambahkan satu passkey agar opsi login passkey muncul di halaman login admin.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
