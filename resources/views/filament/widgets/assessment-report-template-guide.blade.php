<x-filament-widgets::widget>
    <section class="assessment-template-guide">
        <div>
            <span class="assessment-report-eyebrow">Panduan Template Rapor</span>
            <h2>Satu template utama untuk setiap jenis rapor</h2>
            <ul>
                <li>Status hijau kelengkapan berarti data siap, bukan PDF sudah dibuat.</li>
                <li>Template yang sudah memiliki snapshot dikunci. Gunakan <strong>Buat Versi Baru</strong> untuk perubahan berikutnya.</li>
                <li>Pratinjau tidak membuat file atau job. Job baru dibuat setelah kelas dijadwalkan dari Cetak Rapor.</li>
            </ul>
        </div>
        <div class="assessment-template-guide__status">
            <article class="{{ $primaryAsts ? 'is-ready' : 'is-warning' }}">
                <span>Template Utama ASTS</span>
                <strong>{{ $primaryAsts?->name ?: 'Belum ditentukan' }}</strong>
            </article>
            <article class="{{ $primaryAsas ? 'is-ready' : 'is-warning' }}">
                <span>Template Utama ASAS</span>
                <strong>{{ $primaryAsas?->name ?: 'Belum ditentukan' }}</strong>
            </article>
        </div>
    </section>
</x-filament-widgets::widget>
