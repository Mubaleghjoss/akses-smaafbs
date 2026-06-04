@php
    $accessDenied = session(\App\Support\Admin\AdminAccessDenied::FLASH_KEY);
@endphp

@if (is_array($accessDenied))
    <div
        id="admin-access-denied-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="admin-access-denied-title"
        aria-describedby="admin-access-denied-description"
        style="position: fixed; inset: 0; z-index: 2147483000; display: flex; align-items: center; justify-content: center; padding: 16px; font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;"
    >
        <div
            data-access-denied-close
            style="position: absolute; inset: 0; background: rgba(15, 23, 42, .64); backdrop-filter: blur(4px);"
        ></div>

        <div
            style="position: relative; width: min(100%, 28rem); overflow: hidden; border: 1px solid rgba(245, 158, 11, .35); border-radius: 18px; background: #fff; color: #0f172a; box-shadow: 0 24px 80px rgba(15, 23, 42, .32);"
        >
            <div style="padding: 20px;">
                <div style="display: flex; gap: 14px; align-items: flex-start;">
                    <div
                        aria-hidden="true"
                        style="display: flex; width: 42px; height: 42px; flex: 0 0 auto; align-items: center; justify-content: center; border-radius: 999px; background: #fef3c7; color: #d97706;"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                        </svg>
                    </div>

                    <div style="min-width: 0; flex: 1;">
                        <p style="margin: 0; font-size: 11px; font-weight: 700; letter-spacing: .16em; color: #d97706; text-transform: uppercase;">Pemberitahuan Akses</p>
                        <h3 id="admin-access-denied-title" style="margin: 4px 0 0; font-size: 18px; line-height: 1.35; font-weight: 700; color: #0f172a;">
                            {{ $accessDenied['title'] ?? 'Akses dibatasi' }}
                        </h3>
                        <p id="admin-access-denied-description" style="margin: 10px 0 0; font-size: 14px; line-height: 1.65; color: #475569;">
                            {{ $accessDenied['message'] ?? 'Akun Anda belum diberi akses untuk membuka halaman atau menjalankan aksi ini. Silakan hubungi admin untuk menambahkan izin yang dibutuhkan.' }}
                        </p>
                    </div>
                </div>

                <div style="margin-top: 18px; display: flex; justify-content: flex-end;">
                    <button
                        id="admin-access-denied-close"
                        type="button"
                        data-access-denied-close
                        style="min-height: 42px; border: 1px solid #cbd5e1; border-radius: 12px; background: #fff; padding: 9px 16px; color: #334155; font-size: 14px; font-weight: 600; cursor: pointer;"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('admin-access-denied-modal')
            if (!modal) return

            const close = () => {
                modal.remove()
                document.removeEventListener('keydown', handleEscape)
            }

            const handleEscape = (event) => {
                if (event.key === 'Escape') close()
            }

            modal.querySelectorAll('[data-access-denied-close]').forEach((element) => {
                element.addEventListener('click', close)
            })

            document.addEventListener('keydown', handleEscape)
            document.getElementById('admin-access-denied-close')?.focus()
        })()
    </script>
@endif
