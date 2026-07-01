<x-filament-panels::page>
    <div class="literasi-student-history-page">
        <section class="literasi-history-hero">
            <div>
                <h2>History Pengerjaan Siswa</h2>
                <p>Gunakan halaman ini untuk melihat riwayat pengiriman tugas Literasi setiap siswa, status nilai, dan akun admin/guru yang menilai.</p>
            </div>

            <x-filament::button
                tag="a"
                :href="\App\Filament\Resources\PerpustakaanLiterasiMaterialResource::getUrl('index')"
                color="gray"
                icon="heroicon-o-arrow-left"
                size="sm"
            >
                Kembali ke Materi
            </x-filament::button>
        </section>

        <section class="literasi-history-metrics">
            <article>
                <span>Siswa Pernah Mengisi</span>
                <strong>{{ number_format((int) ($summaryMetrics['students'] ?? 0), 0, ',', '.') }}</strong>
            </article>
            <article>
                <span>Total Pengiriman</span>
                <strong>{{ number_format((int) ($summaryMetrics['responses'] ?? 0), 0, ',', '.') }}</strong>
            </article>
            <article>
                <span>History Terhapus</span>
                <strong>{{ number_format((int) ($summaryMetrics['deleted_responses'] ?? 0), 0, ',', '.') }}</strong>
            </article>
            <article>
                <span>History Tanpa Materi</span>
                <strong>{{ number_format((int) ($summaryMetrics['orphaned_responses'] ?? 0), 0, ',', '.') }}</strong>
            </article>
            <article>
                <span>Materi Terhapus</span>
                <strong>{{ number_format((int) ($summaryMetrics['deleted_materials'] ?? 0), 0, ',', '.') }}</strong>
            </article>
            <article>
                <span>Pengiriman Dinilai Lengkap</span>
                <strong>{{ number_format((int) ($summaryMetrics['graded_responses'] ?? 0), 0, ',', '.') }}</strong>
            </article>
            <article>
                <span>Akun Penilai Aktif</span>
                <strong>{{ number_format((int) ($summaryMetrics['graders'] ?? 0), 0, ',', '.') }}</strong>
            </article>
        </section>

        <section class="literasi-history-table-panel">
            {{ $this->getTable()->render() }}
        </section>
    </div>
</x-filament-panels::page>
