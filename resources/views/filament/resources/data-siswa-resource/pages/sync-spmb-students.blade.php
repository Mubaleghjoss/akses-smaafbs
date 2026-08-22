<x-filament-panels::page>
    <style>
        .spmb-sync-page {
            --sync-card: #ffffff;
            --sync-card-soft: #f8fafc;
            --sync-border: #e5e7eb;
            --sync-text: #111827;
            --sync-muted: #64748b;
            --sync-subtle: #94a3b8;
            --sync-primary: #2563eb;
            --sync-success: #16a34a;
            --sync-warning: #d97706;
            --sync-danger: #dc2626;
            --sync-shadow: 0 1px 2px rgba(15, 23, 42, .06), 0 8px 24px rgba(15, 23, 42, .06);
            color: var(--sync-text);
        }

        .dark .spmb-sync-page {
            --sync-card: #111827;
            --sync-card-soft: rgba(255, 255, 255, .045);
            --sync-border: rgba(255, 255, 255, .11);
            --sync-text: #f8fafc;
            --sync-muted: #cbd5e1;
            --sync-subtle: #94a3b8;
            --sync-shadow: 0 1px 2px rgba(0, 0, 0, .25), 0 16px 40px rgba(0, 0, 0, .24);
        }

        .spmb-sync-stack {
            display: grid;
            gap: 18px;
        }

        .spmb-sync-summary {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
        }

        .spmb-sync-card {
            border: 1px solid var(--sync-border);
            border-radius: 8px;
            background: var(--sync-card);
            box-shadow: var(--sync-shadow);
        }

        .spmb-sync-metric {
            position: relative;
            min-height: 104px;
            overflow: hidden;
            padding: 16px;
        }

        .spmb-sync-metric::before {
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            content: "";
            background: var(--metric-color, var(--sync-primary));
        }

        .spmb-sync-metric__label {
            color: var(--sync-muted);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .spmb-sync-metric__value {
            margin-top: 10px;
            color: var(--metric-color, var(--sync-text));
            font-size: 32px;
            font-weight: 800;
            line-height: 1;
        }

        .spmb-sync-metric__caption {
            margin-top: 8px;
            color: var(--sync-subtle);
            font-size: 12px;
        }

        .spmb-sync-panel__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid var(--sync-border);
            padding: 16px 18px;
        }

        .spmb-sync-panel__title {
            margin: 0;
            color: var(--sync-text);
            font-size: 16px;
            font-weight: 750;
            line-height: 1.25;
        }

        .spmb-sync-panel__meta {
            margin-top: 4px;
            color: var(--sync-muted);
            font-size: 13px;
        }

        .spmb-sync-pills {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
        }

        .spmb-sync-pill,
        .spmb-sync-badge {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .spmb-sync-pill {
            border: 1px solid var(--sync-border);
            color: var(--sync-muted);
            background: var(--sync-card-soft);
        }

        .spmb-sync-badge.is-new {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .spmb-sync-badge.is-update {
            color: #92400e;
            background: #fef3c7;
        }

        .spmb-sync-badge.is-unchanged {
            color: #166534;
            background: #dcfce7;
        }

        .spmb-sync-badge.is-conflict,
        .spmb-sync-badge.is-failed {
            color: #991b1b;
            background: #fee2e2;
        }

        .spmb-sync-badge.is-success {
            color: #166534;
            background: #dcfce7;
        }

        .dark .spmb-sync-badge.is-new {
            color: #93c5fd;
            background: rgba(37, 99, 235, .18);
        }

        .dark .spmb-sync-badge.is-update {
            color: #fbbf24;
            background: rgba(217, 119, 6, .18);
        }

        .dark .spmb-sync-badge.is-unchanged,
        .dark .spmb-sync-badge.is-success {
            color: #86efac;
            background: rgba(22, 163, 74, .18);
        }

        .dark .spmb-sync-badge.is-conflict,
        .dark .spmb-sync-badge.is-failed {
            color: #fca5a5;
            background: rgba(220, 38, 38, .18);
        }

        .spmb-sync-table-wrap {
            overflow-x: auto;
        }

        .spmb-sync-table {
            width: 100%;
            min-width: 1040px;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
            font-size: 14px;
        }

        .spmb-sync-table thead th {
            border-bottom: 1px solid var(--sync-border);
            color: var(--sync-muted);
            background: var(--sync-card-soft);
            padding: 12px 14px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .spmb-sync-table tbody td {
            border-bottom: 1px solid var(--sync-border);
            padding: 14px;
            vertical-align: top;
        }

        .spmb-sync-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .spmb-sync-table tbody tr:hover td {
            background: rgba(59, 130, 246, .045);
        }

        .spmb-sync-name {
            color: var(--sync-text);
            font-weight: 750;
            line-height: 1.35;
        }

        .spmb-sync-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }

        .spmb-sync-chip {
            display: inline-flex;
            align-items: center;
            border: 1px solid var(--sync-border);
            border-radius: 999px;
            background: var(--sync-card-soft);
            color: var(--sync-muted);
            padding: 3px 8px;
            font-size: 12px;
            font-weight: 650;
        }

        .spmb-sync-muted {
            color: var(--sync-muted);
        }

        .spmb-sync-empty {
            padding: 42px 18px;
            text-align: center;
            color: var(--sync-muted);
        }

        .spmb-sync-empty strong {
            color: var(--sync-text);
        }

        .spmb-sync-diff-list,
        .spmb-sync-error-list {
            display: grid;
            gap: 7px;
            margin: 0;
            padding: 0;
            list-style: none;
            font-size: 12px;
        }

        .spmb-sync-diff-list li {
            border: 1px solid var(--sync-border);
            border-radius: 8px;
            background: var(--sync-card-soft);
            padding: 8px 10px;
        }

        .spmb-sync-diff-field {
            display: block;
            margin-bottom: 4px;
            color: var(--sync-text);
            font-weight: 750;
        }

        .spmb-sync-diff-values {
            color: var(--sync-muted);
            line-height: 1.45;
        }

        .spmb-sync-error-list {
            color: var(--sync-danger);
        }

        .spmb-sync-resolution-label {
            display: block;
            margin-bottom: 6px;
            color: var(--sync-muted);
            font-size: 12px;
            font-weight: 750;
        }

        .spmb-sync-select {
            width: 100%;
            min-height: 38px;
            border: 1px solid var(--sync-border);
            border-radius: 8px;
            background: var(--sync-card);
            color: var(--sync-text);
            padding: 7px 10px;
            font-size: 13px;
        }

        .spmb-sync-checkbox {
            width: 18px;
            height: 18px;
            border-radius: 5px;
            accent-color: var(--sync-primary);
        }

        .spmb-sync-history-status {
            text-transform: capitalize;
        }

        @media (max-width: 1180px) {
            .spmb-sync-summary {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 780px) {
            .spmb-sync-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .spmb-sync-panel__header {
                display: block;
            }

            .spmb-sync-pills {
                justify-content: flex-start;
                margin-top: 12px;
            }
        }

        @media (max-width: 520px) {
            .spmb-sync-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @php
        $selectedCount = count($selected);
        $actionableCount = collect($rows)->whereIn('status', ['baru', 'update', 'konflik'])->count();
        $rowCount = count($rows);
        $metrics = [
            ['label' => 'Ditemukan', 'value' => $stats['fetched'], 'caption' => 'Dari API SPMB', 'color' => '#64748b'],
            ['label' => 'Siswa Baru', 'value' => $stats['new'], 'caption' => 'Siap dibuat', 'color' => '#2563eb'],
            ['label' => 'Perlu Update', 'value' => $stats['update'], 'caption' => 'Ada perubahan data', 'color' => '#d97706'],
            ['label' => 'Tidak Berubah', 'value' => $stats['unchanged'], 'caption' => 'Sudah sinkron', 'color' => '#16a34a'],
            ['label' => 'Konflik', 'value' => $stats['conflict'], 'caption' => 'Perlu keputusan', 'color' => '#dc2626'],
        ];
    @endphp

    <div class="spmb-sync-page">
        <div class="spmb-sync-stack">
            <section class="spmb-sync-summary" aria-label="Ringkasan sinkronisasi">
                @foreach($metrics as $metric)
                    <article class="spmb-sync-card spmb-sync-metric" style="--metric-color: {{ $metric['color'] }}">
                        <div class="spmb-sync-metric__label">{{ $metric['label'] }}</div>
                        <div class="spmb-sync-metric__value">{{ $metric['value'] }}</div>
                        <div class="spmb-sync-metric__caption">{{ $metric['caption'] }}</div>
                    </article>
                @endforeach
            </section>

            <section class="spmb-sync-card">
                <div class="spmb-sync-panel__header">
                    <div>
                        <h2 class="spmb-sync-panel__title">Preview Data SPMB</h2>
                        <div class="spmb-sync-panel__meta">
                            @if($lastFetchedAt)
                                Preview terakhir: {{ $lastFetchedAt }}. Baris baru dan update dipilih otomatis.
                            @else
                                Tekan Ambil Preview untuk memuat siswa lulus dari SPMB.
                            @endif
                        </div>
                    </div>
                    <div class="spmb-sync-pills" aria-label="Status pilihan">
                        <span class="spmb-sync-pill">{{ $rowCount }} baris</span>
                        <span class="spmb-sync-pill">{{ $selectedCount }} dipilih</span>
                        <span class="spmb-sync-pill">{{ $actionableCount }} perlu proses</span>
                    </div>
                </div>

                <div class="spmb-sync-table-wrap">
                    <table class="spmb-sync-table">
                        <thead>
                            <tr>
                                <th style="width: 58px;">Pilih</th>
                                <th>Siswa SPMB</th>
                                <th style="width: 150px;">Status</th>
                                <th>Data Lokal</th>
                                <th>Perubahan / Konflik</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                                @php
                                    $statusClass = match($row['status']) {
                                        'baru' => 'is-new',
                                        'update' => 'is-update',
                                        'tidak_berubah' => 'is-unchanged',
                                        default => 'is-conflict',
                                    };
                                    $statusLabel = match($row['status']) {
                                        'baru' => 'Baru',
                                        'update' => 'Update',
                                        'tidak_berubah' => 'Tidak berubah',
                                        default => 'Konflik',
                                    };
                                @endphp
                                <tr wire:key="spmb-row-{{ $row['source_id'] }}">
                                    <td>
                                        <input
                                            type="checkbox"
                                            wire:model="selected"
                                            value="{{ $row['source_id'] }}"
                                            @disabled($row['status'] === 'tidak_berubah')
                                            class="spmb-sync-checkbox"
                                            aria-label="Pilih {{ $row['nama'] }}"
                                        >
                                    </td>
                                    <td>
                                        <div class="spmb-sync-name">{{ $row['nama'] }}</div>
                                        <div class="spmb-sync-meta">
                                            <span class="spmb-sync-chip">{{ $row['nomor_pendaftaran'] }}</span>
                                            <span class="spmb-sync-chip">NISN: {{ $row['nisn'] ?: '-' }}</span>
                                            <span class="spmb-sync-chip">JK: {{ $row['jk'] ?: '-' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="spmb-sync-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td>
                                        @if($row['target'])
                                            <div class="spmb-sync-name">{{ $row['target']['nama'] }}</div>
                                            <div class="spmb-sync-meta">
                                                <span class="spmb-sync-chip">ID {{ $row['target']['id'] }}</span>
                                                <span class="spmb-sync-chip">NISN {{ $row['target']['nisn'] ?: '-' }}</span>
                                                <span class="spmb-sync-chip">{{ $row['target']['rombel'] ?: 'Belum ada rombel' }}</span>
                                            </div>
                                        @else
                                            <span class="spmb-sync-muted">Belum ada pasangan</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($row['errors'])
                                            <ul class="spmb-sync-error-list">
                                                @foreach($row['errors'] as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        @if($row['status'] === 'konflik' && !$row['errors'])
                                            <label class="spmb-sync-resolution-label">
                                                Penyelesaian
                                            </label>
                                            <select
                                                wire:model="resolutions.{{ $row['source_id'] }}"
                                                class="spmb-sync-select"
                                            >
                                                <option value="skip">Lewati</option>
                                                <option value="new">Buat sebagai siswa baru</option>
                                                @foreach($row['candidates'] as $candidate)
                                                    <option value="{{ $candidate['id'] }}">
                                                        Hubungkan: {{ $candidate['nama'] }} | {{ $candidate['nisn'] ?: 'tanpa NISN' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @elseif($row['differences'])
                                            <ul class="spmb-sync-diff-list">
                                                @foreach(array_slice($row['differences'], 0, 5) as $difference)
                                                    <li>
                                                        <span class="spmb-sync-diff-field">{{ str($difference['field'])->replace('_', ' ')->title() }}</span>
                                                        <span class="spmb-sync-diff-values">
                                                            {{ filled($difference['local']) ? $difference['local'] : '-' }}
                                                            &rarr;
                                                            {{ filled($difference['source']) ? $difference['source'] : '-' }}
                                                        </span>
                                                    </li>
                                                @endforeach
                                                @if(count($row['differences']) > 5)
                                                    <li>
                                                        <span class="spmb-sync-muted">+{{ count($row['differences']) - 5 }} perubahan lain</span>
                                                    </li>
                                                @endif
                                            </ul>
                                        @else
                                            <span class="spmb-sync-muted">Tidak ada perubahan</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="spmb-sync-empty">
                                            Tekan <strong>Ambil Preview</strong> untuk memeriksa siswa lulus dari SPMB.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="spmb-sync-card">
                <div class="spmb-sync-panel__header">
                    <div>
                        <h2 class="spmb-sync-panel__title">Riwayat Sinkronisasi</h2>
                        <div class="spmb-sync-panel__meta">Aktivitas sinkronisasi terbaru.</div>
                    </div>
                </div>

                <div class="spmb-sync-table-wrap">
                    <table class="spmb-sync-table" style="min-width: 760px;">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Operator</th>
                                <th>Status</th>
                                <th>Ringkasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentRuns as $run)
                                @php
                                    $runStatusClass = $run->status === 'berhasil'
                                        ? 'is-success'
                                        : ($run->status === 'gagal' ? 'is-failed' : 'is-update');
                                @endphp
                                <tr>
                                    <td>{{ $run->started_at?->format('d M Y H:i:s') }}</td>
                                    <td>{{ $run->user?->name ?: $run->user?->email ?: '-' }}</td>
                                    <td>
                                        <span class="spmb-sync-badge spmb-sync-history-status {{ $runStatusClass }}">
                                            {{ str($run->status)->title() }}
                                        </span>
                                    </td>
                                    <td>{{ $run->message ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4">
                                        <div class="spmb-sync-empty">Belum ada riwayat sinkronisasi.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
