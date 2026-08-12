@php
    $draftLabel = $draftLabel ?? 'Jawaban';
@endphp

<div class="hidden rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950" role="status" aria-live="polite" data-literacy-queue-panel>
    <div class="flex items-start gap-3">
        <span class="mt-0.5 inline-block h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-sky-200 border-t-sky-600" aria-hidden="true"></span>
        <div class="min-w-0 flex-1">
            <div class="font-semibold" data-literacy-queue-title>Menyiapkan jalur pengiriman...</div>
            <div class="mt-1 leading-6 text-sky-900" data-literacy-queue-message>{{ $draftLabel }} tetap tersimpan sebagai draf di tab ini. Jangan tutup halaman.</div>
        </div>
    </div>

    <div class="mt-3 rounded-xl border border-sky-200 bg-white/75 p-3 text-xs leading-5 text-slate-700">
        <div class="font-semibold text-slate-900">Patokan status jawaban</div>
        <ol class="mt-2 space-y-2">
            <li class="flex gap-2"><span class="font-semibold tabular-nums text-sky-700">1.</span><span><strong>Memeriksa data:</strong> kesalahan nama, verifikasi, atau jawaban akan ditunjukkan pada kolom terkait.</span></li>
            <li class="flex gap-2"><span class="font-semibold tabular-nums text-sky-700">2.</span><span><strong>Mengirim atau menunggu:</strong> isian tetap menjadi draf di tab ini. Jangan menekan Kirim berulang.</span></li>
            <li class="flex gap-2"><span class="font-semibold tabular-nums text-sky-700">3.</span><span><strong>Struk dan kode edit sudah tampil:</strong> barulah jawaban dikonfirmasi tersimpan. Simpan kode edit pada Struk.</span></li>
        </ol>
    </div>

    <div class="mt-3 flex flex-wrap gap-2">
        <button class="min-h-11 rounded-xl border border-sky-300 bg-white px-4 py-2 text-xs font-semibold text-sky-900" type="button" data-literacy-queue-cancel>Berhenti mencoba otomatis</button>
        <button class="hidden min-h-11 rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-800" type="button" data-literacy-queue-repair>Kembali Perbaiki Jawaban</button>
    </div>
</div>
