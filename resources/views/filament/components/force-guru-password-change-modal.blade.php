@php
    /** @var \App\Models\User|null $user */
    $user = auth()->user();
    $mustForcePasswordChange = $user instanceof \App\Models\User && $user->shouldForceDefaultPasswordChange();
@endphp

@if ($mustForcePasswordChange)
    <div x-data="{ showCurrent: false, showPassword: false, showPasswordConfirmation: false }" x-cloak>
        <div class="fixed inset-0 z-[120] flex items-end justify-center p-3 sm:items-center sm:p-4">
            <div class="absolute inset-0 bg-gray-950/70 backdrop-blur-sm"></div>

            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="force-password-change-title"
                aria-describedby="force-password-change-description"
                class="relative z-[121] w-full max-w-lg overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-2xl dark:border-amber-500/30 dark:bg-gray-900"
            >
                <div class="space-y-4 p-4 sm:space-y-5 sm:p-5">
                    <div class="space-y-2">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-600 dark:text-amber-300">Wajib Ganti Password</p>
                        <h3 id="force-password-change-title" class="text-base font-semibold leading-6 text-gray-950 sm:text-lg dark:text-white">
                            Demi keamanan akun, buat password baru sekarang.
                        </h3>
                        <p id="force-password-change-description" class="text-sm leading-6 text-gray-700 dark:text-gray-300">
                            Password default masih aktif. Anda tidak bisa melanjutkan ke halaman admin lain sebelum mengganti password.
                        </p>
                    </div>

                    <form action="{{ route('admin.force-guru-password-change.update') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ request()->getPathInfo() }}">

                        <div>
                            <label for="forced-current-password" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Password Saat Ini</label>
                            <div class="flex overflow-hidden rounded-xl border border-gray-300 focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-200 dark:border-white/15 dark:focus-within:border-amber-300">
                                <input
                                    id="forced-current-password"
                                    :type="showCurrent ? 'text' : 'password'"
                                    name="current_password"
                                    value="{{ old('current_password') }}"
                                    class="block w-full border-0 bg-white px-3 py-2 text-sm focus:outline-none dark:bg-gray-950 dark:text-white"
                                    autocomplete="current-password"
                                    required
                                >
                                <button
                                    type="button"
                                    x-on:click="showCurrent = !showCurrent"
                                    class="inline-flex min-w-20 items-center justify-center border-l border-gray-300 px-3 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/15 dark:text-gray-200 dark:hover:bg-gray-800"
                                >
                                    <span x-text="showCurrent ? 'Sembunyi' : 'Lihat'"></span>
                                </button>
                            </div>
                            @error('current_password')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="forced-new-password" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Password Baru</label>
                            <div class="flex overflow-hidden rounded-xl border border-gray-300 focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-200 dark:border-white/15 dark:focus-within:border-amber-300">
                                <input
                                    id="forced-new-password"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    class="block w-full border-0 bg-white px-3 py-2 text-sm focus:outline-none dark:bg-gray-950 dark:text-white"
                                    autocomplete="new-password"
                                    required
                                >
                                <button
                                    type="button"
                                    x-on:click="showPassword = !showPassword"
                                    class="inline-flex min-w-20 items-center justify-center border-l border-gray-300 px-3 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/15 dark:text-gray-200 dark:hover:bg-gray-800"
                                >
                                    <span x-text="showPassword ? 'Sembunyi' : 'Lihat'"></span>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="forced-new-password-confirmation" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Konfirmasi Password Baru</label>
                            <div class="flex overflow-hidden rounded-xl border border-gray-300 focus-within:border-amber-500 focus-within:ring-2 focus-within:ring-amber-200 dark:border-white/15 dark:focus-within:border-amber-300">
                                <input
                                    id="forced-new-password-confirmation"
                                    :type="showPasswordConfirmation ? 'text' : 'password'"
                                    name="password_confirmation"
                                    class="block w-full border-0 bg-white px-3 py-2 text-sm focus:outline-none dark:bg-gray-950 dark:text-white"
                                    autocomplete="new-password"
                                    required
                                >
                                <button
                                    type="button"
                                    x-on:click="showPasswordConfirmation = !showPasswordConfirmation"
                                    class="inline-flex min-w-20 items-center justify-center border-l border-gray-300 px-3 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/15 dark:text-gray-200 dark:hover:bg-gray-800"
                                >
                                    <span x-text="showPasswordConfirmation ? 'Sembunyi' : 'Lihat'"></span>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                            <a
                                href="{{ route('filament.admin.auth.logout') }}"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                            >
                                Logout
                            </a>

                            <button
                                type="submit"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-300"
                            >
                                Simpan Password Baru
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif
