<div class="space-y-3">
    <p class="text-sm text-gray-600 dark:text-gray-300">
        Salin teks di bawah lalu tempel ke WhatsApp. Isi teks mengikuti filter yang aktif saat modal ini dibuka.
    </p>

    <textarea
        id="analisis-literasi-share-text"
        rows="16"
        readonly
        class="w-full rounded-lg border-gray-300 bg-gray-50 font-mono text-xs shadow-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
    >{{ $text }}</textarea>

    <button
        type="button"
        class="rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white hover:bg-primary-500"
        onclick="
            const field = document.getElementById('analisis-literasi-share-text');
            field.select();
            navigator.clipboard.writeText(field.value).then(() => {
                this.textContent = 'Tersalin';
                setTimeout(() => { this.textContent = 'Salin Teks'; }, 2000);
            });
        "
    >
        Salin Teks
    </button>
</div>
