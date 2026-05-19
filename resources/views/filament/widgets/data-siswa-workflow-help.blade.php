<x-filament-widgets::widget>
    <div class="data-siswa-workflow-card">
        <div class="data-siswa-workflow-summary">
            <div class="data-siswa-workflow-summary__head">
                <div>
                    <div class="data-siswa-workflow-title-row">
                        <div class="data-siswa-workflow-eyebrow">Kelengkapan Data Tes</div>
                        <span class="data-siswa-workflow-status {{ $completionStatus['classes'] }}">
                            {{ $completionStatus['label'] }}
                        </span>
                    </div>
                    <div class="data-siswa-workflow-description">
                        {{ number_format((int) ($dataTesSummary['filled'] ?? 0)) }} dari {{ number_format((int) ($dataTesSummary['total'] ?? 0)) }} siswa sudah memiliki Data Tes Siswa lengkap.
                    </div>
                </div>
                <div class="data-siswa-workflow-percent">
                    {{ (int) ($dataTesSummary['completion_percentage'] ?? 0) }}%
                </div>
            </div>

            <div class="data-siswa-workflow-progress" aria-hidden="true">
                <div
                    class="data-siswa-workflow-progress__bar"
                    style="width: {{ max(0, min(100, (int) ($dataTesSummary['completion_percentage'] ?? 0))) }}%;"
                ></div>
            </div>

            <div class="data-siswa-workflow-actions">
                <a href="{{ $filterUrls['missing'] }}" class="data-siswa-workflow-button data-siswa-workflow-button--warning">
                    Lihat yang Belum Ada
                </a>
                <a
                    href="{{ $templateUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    data-navigate="false"
                    class="data-siswa-workflow-button data-siswa-workflow-button--neutral"
                >
                    Unduh Template
                </a>
            </div>
        </div>

        <div class="data-siswa-workflow-filters">
            <a
                href="{{ $filterUrls['filled'] }}"
                class="data-siswa-workflow-pill {{ $currentDataTesStatus === 'filled' ? 'is-active is-success' : 'is-success' }}"
            >
                Sudah Ada Data Tes: {{ number_format((int) ($dataTesSummary['filled'] ?? 0)) }}
            </a>
            <a
                href="{{ $filterUrls['missing'] }}"
                class="data-siswa-workflow-pill {{ $currentDataTesStatus === 'missing' ? 'is-active is-warning' : 'is-warning' }}"
            >
                Belum Ada Data Tes: {{ number_format((int) ($dataTesSummary['missing'] ?? 0)) }}
            </a>
            @if (filled($currentDataTesStatus))
                <a href="{{ $filterUrls['all'] }}" class="data-siswa-workflow-pill">
                    Tampilkan Semua
                </a>
            @endif
        </div>

        <div class="data-siswa-workflow-steps">
            <div class="data-siswa-workflow-step">
                <div class="data-siswa-workflow-step__number">Langkah 1</div>
                <h3>Unduh Template</h3>
                <p>Gunakan <strong>Download Template Data</strong> agar format data lengkap dan data tes selalu sesuai sistem.</p>
                <div class="data-siswa-workflow-step__badges">
                    <span>TEMPLATE RESMI</span>
                </div>
            </div>

            <div class="data-siswa-workflow-step data-siswa-workflow-step--info">
                <div class="data-siswa-workflow-step__number">Langkah 2</div>
                <h3>Import Sesuai Kebutuhan</h3>
                <p>Pilih <strong>Import Data Lengkap</strong> untuk biodata penuh, atau <strong>Import Data Tes Siswa</strong> untuk 4 field tes saja.</p>
                <div class="data-siswa-workflow-step__badges">
                    <span>DATA LENGKAP</span>
                    <span>DATA TES</span>
                </div>
            </div>

            <div class="data-siswa-workflow-step data-siswa-workflow-step--success">
                <div class="data-siswa-workflow-step__number">Langkah 3</div>
                <h3>Review dan Filter</h3>
                <p>Cek hasil review import, lalu gunakan filter <strong>Lihat Data Tes Siswa</strong> untuk memverifikasi data yang sudah masuk.</p>
                <div class="data-siswa-workflow-step__badges">
                    <span>REVIEW</span>
                    <span>FILTER</span>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
