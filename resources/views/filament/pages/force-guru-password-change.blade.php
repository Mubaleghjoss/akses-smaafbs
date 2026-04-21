<x-filament-panels::page>
    <div class="mx-auto w-full max-w-xl space-y-4">
        <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-100">
            Password default masih aktif. Demi keamanan akun guru/tendik, Anda wajib membuat password baru sebelum melanjutkan ke halaman admin lain.
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <form wire:submit="changePassword" class="space-y-4">
                <div>
                    <label for="current-password" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Password Saat Ini</label>
                    <input
                        id="current-password"
                        type="password"
                        wire:model.defer="currentPassword"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-white/15 dark:bg-gray-950 dark:text-white"
                        autocomplete="current-password"
                        required
                    >
                    @error('currentPassword')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="new-password" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Password Baru</label>
                    <input
                        id="new-password"
                        type="password"
                        wire:model.defer="password"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-white/15 dark:bg-gray-950 dark:text-white"
                        autocomplete="new-password"
                        required
                    >
                    @error('password')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="new-password-confirmation" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Konfirmasi Password Baru</label>
                    <input
                        id="new-password-confirmation"
                        type="password"
                        wire:model.defer="password_confirmation"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-white/15 dark:bg-gray-950 dark:text-white"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-300"
                    >
                        Simpan Password Baru
                    </button>
                </div>
            </form>
        </section>
    </div>
</x-filament-panels::page>
