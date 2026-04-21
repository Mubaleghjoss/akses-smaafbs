@php
    $accessDenied = session(\App\Support\Admin\AdminAccessDenied::FLASH_KEY);
@endphp

@if (is_array($accessDenied))
    <div
        x-data="{
            open: true,
            previouslyFocusedElement: null,
            init() {
                this.previouslyFocusedElement = document.activeElement
                this.$nextTick(() => this.$refs.closeButton?.focus())
            },
            closeDialog() {
                this.open = false
                this.previouslyFocusedElement?.focus?.()
            },
        }"
        x-cloak
        x-on:keydown.escape.window="closeDialog()"
    >
        <div
            x-show="open"
            x-transition.opacity.duration.150ms
            class="fixed inset-0 z-[110] flex items-end justify-center p-3 sm:items-center sm:p-4"
        >
            <div class="absolute inset-0 bg-gray-950/60 backdrop-blur-sm" x-on:click="closeDialog()"></div>

            <div
                x-ref="dialog"
                x-show="open"
                x-transition.duration.150ms
                role="dialog"
                aria-modal="true"
                aria-labelledby="admin-access-denied-title"
                aria-describedby="admin-access-denied-description"
                tabindex="-1"
                class="relative z-[111] w-full max-w-sm overflow-hidden rounded-2xl border border-amber-200 bg-white shadow-2xl sm:max-w-md dark:border-amber-500/30 dark:bg-gray-900"
            >
                <div class="space-y-4 p-4 sm:space-y-5 sm:p-5">
                    <div class="flex items-start gap-3 sm:gap-4">
                        <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 sm:h-10 sm:w-10 dark:bg-amber-500/15 dark:text-amber-300">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 sm:h-5 sm:w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>

                        <div class="min-w-0 flex-1 space-y-1.5 sm:space-y-2">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-600 dark:text-amber-300">Pemberitahuan Akses</p>
                                <h3 id="admin-access-denied-title" class="mt-1 text-base font-semibold leading-6 text-gray-950 sm:text-lg dark:text-white">{{ $accessDenied['title'] ?? 'Akses dibatasi' }}</h3>
                            </div>

                            <p id="admin-access-denied-description" class="break-words text-sm leading-6 text-gray-700 dark:text-gray-300">
                                {{ $accessDenied['message'] ?? 'Akun Anda belum diberi izin untuk membuka halaman ini.' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <button
                            x-ref="closeButton"
                            type="button"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                            x-on:click="closeDialog()"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
