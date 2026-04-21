<x-filament-panels::page>
    <style>
        .proker-dashboard-local .pd-table-shell,
        .proker-dashboard-local .dashboard-proker-table-shell {
            overflow: hidden;
            border: 1px solid rgba(203, 213, 225, 0.95);
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        }

        .dark .proker-dashboard-local .pd-table-shell,
        .dark .proker-dashboard-local .dashboard-proker-table-shell {
            border-color: rgba(255, 255, 255, 0.12);
            background: rgba(17, 24, 39, 0.88);
            box-shadow: none;
        }

        .proker-dashboard-local .pd-table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .proker-dashboard-local .pd-table,
        .proker-dashboard-local table {
            width: 100%;
            min-width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .proker-dashboard-local .pd-table--wide {
            min-width: 880px;
        }

        .proker-dashboard-local .pd-table thead th,
        .proker-dashboard-local table thead th {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid rgba(203, 213, 225, 0.95);
            border-right: 1px solid rgba(226, 232, 240, 0.9);
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
            color: #475569;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            text-align: left;
            vertical-align: middle;
        }

        .proker-dashboard-local .pd-table thead th:last-child,
        .proker-dashboard-local .pd-table tbody td:last-child,
        .proker-dashboard-local table thead th:last-child,
        .proker-dashboard-local table tbody td:last-child {
            border-right: none;
        }

        .dark .proker-dashboard-local .pd-table thead th,
        .dark .proker-dashboard-local table thead th {
            border-bottom-color: rgba(255, 255, 255, 0.12);
            border-right-color: rgba(255, 255, 255, 0.08);
            background: linear-gradient(to right, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.04));
            color: #cbd5e1;
        }

        .proker-dashboard-local .pd-table tbody td,
        .proker-dashboard-local table tbody td {
            padding: 0.95rem 1rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
            border-right: 1px solid rgba(241, 245, 249, 1);
            color: #334155;
            font-size: 0.92rem;
            line-height: 1.45;
            vertical-align: top;
            background: #fff;
        }

        .proker-dashboard-local .pd-table tbody tr:nth-child(even) td,
        .proker-dashboard-local table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .proker-dashboard-local .pd-table tbody tr:hover td,
        .proker-dashboard-local table tbody tr:hover td {
            background: #fff7ed;
        }

        .proker-dashboard-local .pd-table tbody tr:last-child td,
        .proker-dashboard-local table tbody tr:last-child td {
            border-bottom: none;
        }

        .dark .proker-dashboard-local .pd-table tbody td,
        .dark .proker-dashboard-local table tbody td {
            border-bottom-color: rgba(255, 255, 255, 0.08);
            border-right-color: rgba(255, 255, 255, 0.06);
            color: #e5e7eb;
            background: transparent;
        }

        .dark .proker-dashboard-local .pd-table tbody tr:nth-child(even) td,
        .dark .proker-dashboard-local table tbody tr:nth-child(even) td {
            background: rgba(255, 255, 255, 0.025);
        }

        .dark .proker-dashboard-local .pd-table tbody tr:hover td,
        .dark .proker-dashboard-local table tbody tr:hover td {
            background: rgba(255, 255, 255, 0.05);
        }

        .proker-dashboard-local .pd-cell-main {
            font-weight: 600;
            color: #0f172a;
        }

        .dark .proker-dashboard-local .pd-cell-main {
            color: #fff;
        }

        .proker-dashboard-local .pd-cell-muted {
            margin-top: 0.25rem;
            font-size: 0.78rem;
            color: #64748b;
        }

        .dark .proker-dashboard-local .pd-cell-muted {
            color: #94a3b8;
        }

        .proker-dashboard-local .pd-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 0.5rem;
            align-items: center;
        }

        .proker-dashboard-local .pd-action-link,
        .proker-dashboard-local .pd-actions .fi-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.15rem;
            border-radius: 0.625rem;
            padding: 0.45rem 0.8rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            box-shadow: none;
        }

        .proker-dashboard-local .pd-action-link {
            border: 1px solid rgba(251, 191, 36, 0.45);
            color: #b45309;
            background: #fffaf0;
        }

        .proker-dashboard-local .pd-action-link:hover {
            background: #fef3c7;
        }

        .proker-dashboard-local .pd-actions .fi-btn {
            border: 1px solid rgba(203, 213, 225, 0.95);
            background: #ffffff;
            color: #334155;
        }

        .proker-dashboard-local .pd-actions .fi-btn:hover {
            background: #f8fafc;
            border-color: rgba(148, 163, 184, 0.9);
        }

        .proker-dashboard-local .pd-actions .fi-btn-color-success {
            border-color: rgba(16, 185, 129, 0.28);
            background: rgba(236, 253, 245, 0.95);
            color: #047857;
        }

        .proker-dashboard-local .pd-actions .fi-btn-color-success:hover {
            background: rgba(209, 250, 229, 0.95);
        }

        .proker-dashboard-local .pd-actions .fi-btn-label {
            font-size: 0.75rem;
            font-weight: 600;
        }

        .proker-dashboard-local .pd-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: end;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 1rem;
            background: #fff;
        }

        .dark .proker-dashboard-local .pd-toolbar {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(17, 24, 39, 0.7);
        }

        .proker-dashboard-local .pd-toolbar-field {
            min-width: 0;
            flex: 1 1 220px;
        }

        .proker-dashboard-local .pd-toolbar-field--narrow {
            flex-basis: 180px;
            max-width: 220px;
        }

        .proker-dashboard-local .pd-toolbar-label {
            display: block;
            margin-bottom: 0.4rem;
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .dark .proker-dashboard-local .pd-toolbar-label {
            color: #94a3b8;
        }

        .proker-dashboard-local .pd-toolbar-input {
            width: 100%;
            min-height: 2.75rem;
            border: 1px solid rgba(203, 213, 225, 0.95);
            border-radius: 0.85rem;
            background: #fff;
            padding: 0.7rem 0.95rem;
            color: #0f172a;
            font-size: 0.92rem;
            outline: none;
        }

        .proker-dashboard-local .pd-toolbar-input:focus {
            border-color: rgba(245, 158, 11, 0.8);
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.18);
        }

        .dark .proker-dashboard-local .pd-toolbar-input {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(17, 24, 39, 0.85);
            color: #f8fafc;
        }

        .dark .proker-dashboard-local .pd-toolbar-input:focus {
            border-color: rgba(251, 191, 36, 0.55);
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.14);
        }

        .proker-dashboard-local .pd-toolbar-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .proker-dashboard-local .pd-toolbar-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 9999px;
            background: #f8fafc;
            color: #475569;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .dark .proker-dashboard-local .pd-toolbar-chip {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.04);
            color: #cbd5e1;
        }

        .dark .proker-dashboard-local .pd-action-link {
            border-color: rgba(251, 191, 36, 0.25);
            color: #fde68a;
            background: rgba(251, 191, 36, 0.08);
        }

        .dark .proker-dashboard-local .pd-action-link:hover {
            background: rgba(251, 191, 36, 0.14);
        }

        .dark .proker-dashboard-local .pd-actions .fi-btn {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.04);
            color: #e5e7eb;
        }

        .dark .proker-dashboard-local .pd-actions .fi-btn:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .dark .proker-dashboard-local .pd-actions .fi-btn-color-success {
            border-color: rgba(16, 185, 129, 0.25);
            background: rgba(16, 185, 129, 0.1);
            color: #a7f3d0;
        }

        .dark .proker-dashboard-local .pd-actions .fi-btn-color-success:hover {
            background: rgba(16, 185, 129, 0.16);
        }

        .proker-dashboard-local .pd-section-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding-bottom: 0.9rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
        }

        .dark .proker-dashboard-local .pd-section-head {
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }

        .proker-dashboard-local .pd-section-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.35rem 0.65rem;
            border-radius: 9999px;
            background: #f8fafc;
            border: 1px solid rgba(226, 232, 240, 0.95);
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .dark .proker-dashboard-local .pd-section-kicker {
            background: rgba(255, 255, 255, 0.04);
            border-color: rgba(255, 255, 255, 0.08);
            color: #cbd5e1;
        }

        .proker-dashboard-local .pd-section-title {
            margin-top: 0.5rem;
            color: #0f172a;
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1.3;
        }

        .dark .proker-dashboard-local .pd-section-title {
            color: #fff;
        }

        .proker-dashboard-local .pd-section-desc {
            margin-top: 0.2rem;
            color: #64748b;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .dark .proker-dashboard-local .pd-section-desc {
            color: #94a3b8;
        }

        .proker-dashboard-local .pd-toggle-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.2rem;
            padding: 0.45rem 0.9rem;
            border-radius: 9999px;
            border: 1px solid rgba(203, 213, 225, 0.95);
            background: #fff;
            color: #475569;
            font-size: 0.78rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .proker-dashboard-local .pd-toggle-btn:hover {
            border-color: rgba(148, 163, 184, 0.9);
            background: #f8fafc;
        }

        .dark .proker-dashboard-local .pd-toggle-btn {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.04);
            color: #e5e7eb;
        }

        .dark .proker-dashboard-local .pd-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .proker-dashboard-local .pd-hero-shell {
            overflow: hidden;
            border: 1px solid rgba(251, 191, 36, 0.24);
            border-radius: 1.25rem;
            background: linear-gradient(135deg, rgba(255, 251, 235, 0.96), rgba(255, 255, 255, 0.98));
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .dark .proker-dashboard-local .pd-hero-shell {
            border-color: rgba(251, 191, 36, 0.16);
            background: linear-gradient(135deg, rgba(31, 41, 55, 0.96), rgba(17, 24, 39, 0.98));
            box-shadow: none;
        }

        .proker-dashboard-local .pd-hero-head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.1rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
        }

        .dark .proker-dashboard-local .pd-hero-head {
            border-bottom-color: rgba(255, 255, 255, 0.08);
        }

        .proker-dashboard-local .pd-hero-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.55rem;
            margin-bottom: 0.6rem;
        }

        .proker-dashboard-local .pd-hero-title {
            color: #0f172a;
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1.3;
        }

        .dark .proker-dashboard-local .pd-hero-title {
            color: #fff;
        }

        .proker-dashboard-local .pd-hero-summary {
            margin-top: 0.35rem;
            max-width: 60rem;
            color: #475569;
            font-size: 0.93rem;
            line-height: 1.6;
        }

        .dark .proker-dashboard-local .pd-hero-summary {
            color: #cbd5e1;
        }

        .proker-dashboard-local .pd-hero-toggle {
            flex: 0 0 auto;
        }

        .proker-dashboard-local .pd-top-stack {
            display: grid;
            gap: 1rem;
        }

        .proker-dashboard-local .pd-mobile-list {
            display: grid;
            gap: 1rem;
        }

        .proker-dashboard-local .pd-mobile-card {
            border: 1px solid rgba(229, 231, 235, 0.95);
            border-radius: 1.25rem;
            background: rgba(255, 255, 255, 0.96);
            padding: 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        }

        .dark .proker-dashboard-local .pd-mobile-card {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgba(17, 24, 39, 0.9);
            box-shadow: none;
        }

        .proker-dashboard-local .pd-mobile-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .proker-dashboard-local .pd-mobile-metric {
            border-radius: 1rem;
            background: #f8fafc;
            padding: 0.75rem;
            text-align: center;
        }

        .dark .proker-dashboard-local .pd-mobile-metric {
            background: rgba(255, 255, 255, 0.05);
        }

        .proker-dashboard-local .pd-mobile-metric-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
        }

        .dark .proker-dashboard-local .pd-mobile-metric-label {
            color: #94a3b8;
        }

        .proker-dashboard-local .pd-mobile-metric-value {
            margin-top: 0.3rem;
            display: block;
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
        }

        .dark .proker-dashboard-local .pd-mobile-metric-value {
            color: #fff;
        }

        .proker-dashboard-local .pd-mobile-actions {
            display: grid;
            gap: 0.5rem;
        }

        @media (max-width: 767.98px) {
            .proker-dashboard-local .pd-hero-head,
            .proker-dashboard-local .pd-section-head {
                gap: 0.85rem;
            }

            .proker-dashboard-local .pd-hero-shell,
            .proker-dashboard-local section.rounded-\[1\.75rem\] {
                border-radius: 1.25rem;
            }

            .proker-dashboard-local .pd-toolbar {
                padding: 0.9rem;
            }

            .proker-dashboard-local .pd-toolbar-field,
            .proker-dashboard-local .pd-toolbar-field--narrow {
                flex-basis: 100%;
                max-width: none;
            }

            .proker-dashboard-local .pd-toolbar-summary {
                width: 100%;
            }

            .proker-dashboard-local .pd-toolbar-chip {
                max-width: 100%;
            }

            .proker-dashboard-local .pd-desktop-table {
                display: none;
            }
        }

        @media (min-width: 768px) {
            .proker-dashboard-local .pd-mobile-list {
                display: none;
            }
        }
    </style>
    @php
        $summaryWidgetsVisible = $this->showSummaryWidgets && $this->dashboardDataReady;
        $analysisItems = $summaryWidgetsVisible ? $this->getAnalysisItems() : [];
        $quickFilterChips = $summaryWidgetsVisible ? $this->getQuickFilterChips() : [];
        $decisionRecommendations = $summaryWidgetsVisible ? $this->getDecisionRecommendations() : [];
        $indicatorRows = $this->getIndicatorSummaryByBidang();
        $indicatorMeta = $this->getIndicatorSummaryMeta();
        $quickChecklist = $this->getQuickChecklistProkers();
        $quickChecklistMeta = $this->getQuickChecklistMeta();
        $recentUpdates = $summaryWidgetsVisible ? $this->getRecentUpdates() : collect();
        $attentionProkers = $summaryWidgetsVisible ? $this->getAttentionProkers() : collect();
        $isDashboardDegraded = $this->isDegradedDashboardMode();
        $heroSummary = $summaryWidgetsVisible
            ? $this->getSummaryText()
            : 'Ringkasan dashboard ditunda untuk menjaga halaman tetap ringan. Klik Muat Semua Ringkasan jika ingin menampilkan analisa, widget, dan histori monitoring.';

        $statusBadgeClasses = static function (?string $status): string {
            return match ($status) {
                'selesai' => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/20',
                'berjalan' => 'bg-sky-100 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-400/20',
                'terkendala' => 'bg-rose-100 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/20',
                default => 'bg-gray-100 text-gray-700 ring-1 ring-gray-200 dark:bg-white/10 dark:text-gray-300 dark:ring-white/10',
            };
        };

        $toneBadgeClasses = static function (?string $tone): string {
            return match ($tone) {
                'danger' => 'bg-rose-100 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/20',
                'warning' => 'bg-amber-100 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/20',
                'success' => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/20',
                'primary' => 'bg-sky-100 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-400/20',
                default => 'bg-gray-100 text-gray-700 ring-1 ring-gray-200 dark:bg-white/10 dark:text-gray-300 dark:ring-white/10',
            };
        };

        $toneLabel = static function (?string $tone): string {
            return match ($tone) {
                'danger' => 'Urgent',
                'warning' => 'Perlu dorong',
                'success' => 'Aman',
                'primary' => 'Pantau',
                default => 'Info',
            };
        };

        $toneButtonColor = static function (?string $tone): string {
            return match ($tone) {
                'danger' => 'danger',
                'warning' => 'warning',
                'success' => 'success',
                'primary' => 'primary',
                default => 'gray',
            };
        };

        $attentionLevelClasses = static function (?string $level): string {
            return match ($level) {
                'tinggi' => 'bg-rose-100 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/20',
                'sedang' => 'bg-amber-100 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/20',
                default => 'bg-sky-100 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-400/20',
            };
        };

    @endphp

    <div class="proker-dashboard-local space-y-6">
        @if ($isDashboardDegraded)
            <section class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-100">
                Mode ringan aktif: ringkasan widget dan data monitoring dinamis disederhanakan sementara agar navigasi inti tetap responsif.
            </section>
        @endif

        <section x-data="{ open: true }" class="space-y-4">
            <div class="pd-hero-shell">
                <div class="pd-hero-head">
                    <div>
                        <div class="pd-hero-meta">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-700 ring-1 ring-amber-200 dark:bg-white/10 dark:text-amber-200 dark:ring-amber-400/20">
                                <span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span>
                                Monitoring Proker
                            </span>
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/20">
                                {{ $this->dashboardDataReady ? 'Data aktif' : 'Memuat data' }}
                            </span>
                        </div>

                        <h2 class="pd-hero-title">Dashboard eksekusi, indikator, dan tindak lanjut</h2>
                        <p class="pd-hero-summary">{{ $heroSummary }}</p>
                    </div>

                    <div class="pd-hero-toggle">
                        <button
                            type="button"
                            x-on:click="open = ! open"
                            class="pd-toggle-btn"
                        >
                            <span x-text="open ? 'Sembunyikan ringkasan' : 'Tampilkan ringkasan'"></span>
                        </button>
                    </div>
                </div>
            </div>
                        <div x-show="open" x-transition.opacity.duration.200ms class="pd-top-stack">
                @if (! $summaryWidgetsVisible)
                    <div class="rounded-2xl border border-dashed border-amber-300/80 bg-amber-50/80 px-4 py-4 text-sm leading-6 text-amber-800 dark:border-amber-400/20 dark:bg-amber-500/10 dark:text-amber-100">
                        Ringkasan dashboard sedang ditunda agar halaman lebih cepat. Klik <strong>Muat Semua Ringkasan</strong> di header untuk menampilkan widget, analisa, dan daftar monitoring lengkap.
                    </div>
                @endif

                <div class="pd-table-shell">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50/80 px-4 py-3 text-xs font-medium text-gray-500 dark:border-white/10 dark:bg-white/[0.04] dark:text-gray-400">
                        <span>Ringkasan monitoring aktif</span>
                        <span>{{ count($analysisItems) }} indikator · {{ count($quickFilterChips) }} filter</span>
                    </div>

                    <div class="border-b border-gray-200 bg-white px-4 py-4 dark:border-white/10 dark:bg-white/[0.03]">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Filter cepat prioritas</p>
                                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">Buka daftar proker sesuai kondisi yang paling perlu keputusan tanpa mengatur filter manual.</p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-3">
                            @foreach ($quickFilterChips as $chip)
                                <div class="w-full sm:w-auto">
                                    <x-filament::button
                                        :color="$toneButtonColor($chip['tone'] ?? null)"
                                        :href="$chip['url']"
                                        :icon="$chip['icon'] ?? 'heroicon-o-funnel'"
                                        tag="a"
                                        class="w-full sm:w-auto"
                                    >
                                        {{ $chip['label'] }} ({{ $chip['count'] }})
                                    </x-filament::button>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $chip['hint'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="pd-table-wrap">
                        <table class="pd-table">
                            <thead>
                                <tr>
                                    <th>Monitoring Proker</th>
                                    <th>Nilai</th>
                                    <th>Ringkasan</th>
                                    <th class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($analysisItems as $item)
                                    <tr>
                                        <td>
                                            <div class="pd-cell-main">{{ $item['label'] }}</div>
                                            <div class="pd-cell-muted">{{ $toneLabel($item['tone'] ?? null) }}</div>
                                        </td>
                                        <td>
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $toneBadgeClasses($item['tone'] ?? null) }}">
                                                {{ $item['value'] }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $item['description'] }}</div>
                                        </td>
                                        <td class="text-right">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <x-filament::button
                                                    :color="$toneButtonColor($item['tone'] ?? null)"
                                                    :href="$item['url']"
                                                    tag="a"
                                                    size="sm"
                                                    icon="heroicon-o-funnel"
                                                >
                                                    {{ $item['action_label'] ?? 'Buka detail' }}
                                                </x-filament::button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
        <div class="grid gap-6 2xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.7fr)]">
            <section id="ringkasan-keputusan" x-data="{ open: true }" class="rounded-[1.75rem] border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="pd-section-head">
                    <div>
                        <span class="pd-section-kicker">Ringkasan keputusan</span>
                        <h3 class="pd-section-title">Saran Tindak Lanjut</h3>
                        <p class="pd-section-desc">Keputusan prioritas yang perlu dibaca sebelum masuk ke tabel detail.</p>
                    </div>
                    <button
                        type="button"
                        x-on:click="open = ! open"
                        class="pd-toggle-btn"
                    >
                        <span x-text="open ? 'Minimalkan' : 'Tampilkan'"></span>
                    </button>
                </div>

                <div x-show="open" x-transition.opacity.duration.200ms class="mt-5">
                    <div class="pd-table-shell">
                        <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50/80 px-4 py-3 text-xs font-medium text-gray-500 dark:border-white/10 dark:bg-white/[0.04] dark:text-gray-400">
                            <span>Tabel keputusan monitoring</span>
                            <span>{{ count($decisionRecommendations) }} saran</span>
                        </div>

                        <div class="pd-table-wrap">
                            <table class="pd-table">
                                <thead>
                                    <tr>
                                        <th>Saran Tindak Lanjut</th>
                                        <th>Status</th>
                                        <th>Ringkasan</th>
                                        <th class="text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($decisionRecommendations as $decision)
                                        <tr>
                                            <td>
                                                <div class="pd-cell-main">{{ $decision['label'] }}</div>
                                                <div class="pd-cell-muted">{{ $decision['value'] }}</div>
                                            </td>
                                            <td>
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $toneBadgeClasses($decision['tone'] ?? null) }}">
                                                    {{ $toneLabel($decision['tone'] ?? null) }}
                                                </span>
                                            </td>
                                            <td>
                                                <div class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $decision['summary'] }}</div>
                                            </td>
                                            <td class="text-right">
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <x-filament::button
                                                        :color="$toneButtonColor($decision['tone'] ?? null)"
                                                        :href="$decision['url']"
                                                        tag="a"
                                                        size="sm"
                                                        icon="heroicon-o-arrow-top-right-on-square"
                                                    >
                                                        {{ $decision['action_label'] }}
                                                    </x-filament::button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
            <section x-data="{ open: true }" class="rounded-[1.75rem] border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="pd-section-head">
                    <div>
                        <span class="pd-section-kicker">Performa bidang</span>
                        <h3 class="pd-section-title">Data Indikator per Bidang</h3>
                        <p class="pd-section-desc">Perbandingan capaian bidang.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if (\App\Filament\Resources\ProkerBidangResource::canAccess())
                            <a
                                href="{{ \App\Filament\Resources\ProkerBidangResource::getUrl('index') }}"
                                class="pd-action-link"
                            >
                                Kelola bidang
                            </a>
                        @endif
                        <button
                            type="button"
                            x-on:click="open = ! open"
                            class="pd-toggle-btn"
                        >
                            <span x-text="open ? 'Minimalkan' : 'Tampilkan'"></span>
                        </button>
                    </div>
                </div>

                <div x-show="open" x-transition.opacity.duration.200ms class="mt-5 space-y-4">
                    <div class="pd-toolbar">
                        <label class="pd-toolbar-field pd-toolbar-field--narrow">
                            <span class="pd-toolbar-label">Periode</span>
                                <select
                                    wire:model.live="indicatorPeriodYear"
                                    class="pd-toolbar-input"
                                >
                                    @foreach ($this->getIndicatorPeriodOptions() as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                        </label>

                        <label class="pd-toolbar-field">
                            <span class="pd-toolbar-label">Cari Nama Proker</span>
                                <input
                                    wire:model.live.debounce.400ms="indicatorProkerSearch"
                                    type="text"
                                    placeholder="Contoh: Rapat Komite, ANBK, KSP"
                                    class="pd-toolbar-input"
                                >
                        </label>

                        <button
                            type="button"
                            wire:click="resetIndicatorFilters"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-amber-300 hover:text-amber-700 xl:w-auto dark:border-white/10 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-amber-400/30 dark:hover:text-amber-200"
                        >
                            Reset Filter
                        </button>

                        <div class="pd-toolbar-summary">
                            <span class="pd-toolbar-chip">Bidang {{ $indicatorMeta['matched_bidangs'] }}</span>
                            <span class="pd-toolbar-chip">Proker {{ $indicatorMeta['matched_prokers'] }}</span>
                            <span class="pd-toolbar-chip">{{ $indicatorMeta['active_period_label'] }}</span>
                            <span class="pd-toolbar-chip">{{ filled($this->indicatorProkerSearch) ? $this->indicatorProkerSearch : 'Semua proker' }}</span>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-[1.35rem] border-2 border-gray-300 bg-white shadow-sm shadow-gray-100/80 ring-1 ring-gray-200/70 dark:border-white/15 dark:bg-gray-950/60 dark:shadow-none dark:ring-white/10">
                        <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-gray-50/80 px-4 py-3 text-xs font-medium text-gray-500 dark:border-white/10 dark:bg-white/[0.04] dark:text-gray-400">
                            <span>Ringkasan indikator per bidang</span>
                            <span class="md:hidden">Mode kartu</span>
                        </div>

                        <div class="pd-mobile-list p-4">
                            @forelse ($indicatorRows as $row)
                                <article class="pd-mobile-card">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['bidang'] }}</h4>
                                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $row['proker_count'] }} proker aktif pada filter ini.</p>
                                        </div>
                                        <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-200">
                                            {{ $row['persen_indikator'] }}%
                                        </span>
                                    </div>

                                    <div class="pd-mobile-metrics mt-4">
                                        <div class="pd-mobile-metric">
                                            <span class="pd-mobile-metric-label">Checklist</span>
                                            <span class="pd-mobile-metric-value">{{ $row['indikator_selesai'] }}/{{ $row['total_indikator'] }}</span>
                                        </div>
                                        <div class="pd-mobile-metric">
                                            <span class="pd-mobile-metric-label">Avg Progress</span>
                                            <span class="pd-mobile-metric-value">{{ $row['avg_progress'] }}%</span>
                                        </div>
                                        <div class="pd-mobile-metric">
                                            <span class="pd-mobile-metric-label">Terkendala</span>
                                            <span class="pd-mobile-metric-value">{{ $row['terkendala_count'] }}</span>
                                        </div>
                                    </div>

                                    <a
                                        href="{{ $row['manage_url'] }}"
                                        class="mt-4 inline-flex w-full items-center justify-center rounded-xl border border-amber-200 px-3 py-2 text-sm font-semibold text-amber-700 transition hover:border-amber-300 hover:bg-amber-50 dark:border-amber-400/20 dark:text-amber-200 dark:hover:bg-amber-500/10"
                                    >
                                        Kelola Proker
                                    </a>
                                </article>
                            @empty
                                <div class="pd-mobile-card text-sm text-gray-500 dark:text-gray-400">
                                    Tidak ada data bidang yang cocok dengan filter periode atau pencarian nama proker.
                                </div>
                            @endforelse
                        </div>

                        <div class="pd-desktop-table w-full overflow-x-auto" style="-webkit-overflow-scrolling: touch; touch-action: pan-x;">
                            <div class="min-w-[860px]">
                                <table class="w-full border-separate border-spacing-0 text-sm">
                                    <thead class="bg-gradient-to-r from-gray-100 via-gray-50 to-amber-50/80 dark:from-white/[0.08] dark:via-white/[0.05] dark:to-amber-500/[0.08]">
                                        <tr>
                                            <th class="border-b border-r border-gray-300 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Bidang</th>
                                            <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Proker</th>
                                            <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Checklist</th>
                                            <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Capaian</th>
                                            <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Avg Progress</th>
                                            <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Terkendala</th>
                                            <th class="border-b border-gray-300 px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-transparent">
                                        @forelse ($indicatorRows as $row)
                                            <tr class="border-b border-gray-200/90 transition hover:bg-amber-50/40 dark:border-white/10 dark:hover:bg-white/5 even:bg-gray-50/60 dark:even:bg-white/[0.025]">
                                                <td class="border-r border-gray-200/80 px-4 py-4 align-top dark:border-white/10">
                                                    <div class="space-y-1">
                                                        <p class="font-semibold text-gray-950 dark:text-white">{{ $row['bidang'] }}</p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">Fokus pada kualitas checklist dan rata-rata progress bidang.</p>
                                                    </div>
                                                </td>
                                                <td class="border-r border-gray-200/80 px-4 py-4 text-center font-semibold text-gray-700 dark:border-white/10 dark:text-gray-200">{{ $row['proker_count'] }}</td>
                                                <td class="border-r border-gray-200/80 px-4 py-4 text-center text-gray-600 dark:border-white/10 dark:text-gray-300">{{ $row['indikator_selesai'] }}/{{ $row['total_indikator'] }}</td>
                                                <td class="border-r border-gray-200/80 px-4 py-4 text-center dark:border-white/10">
                                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-200">
                                                        {{ $row['persen_indikator'] }}%
                                                    </span>
                                                </td>
                                                <td class="border-r border-gray-200/80 px-4 py-4 text-center text-gray-600 dark:border-white/10 dark:text-gray-300">{{ $row['avg_progress'] }}%</td>
                                                <td class="border-r border-gray-200/80 px-4 py-4 text-center dark:border-white/10">
                                                    <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-200">
                                                        {{ $row['terkendala_count'] }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-4 text-right">
                                                    <a
                                                        href="{{ $row['manage_url'] }}"
                                                        class="inline-flex items-center rounded-lg border border-amber-200 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:border-amber-300 hover:bg-amber-50 dark:border-amber-400/20 dark:text-amber-200 dark:hover:bg-amber-500/10"
                                                    >
                                                        Kelola Proker
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                                    Tidak ada data bidang yang cocok dengan filter periode atau pencarian nama proker.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section x-data="{ open: true }" class="rounded-[1.75rem] border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="pd-section-head">
                    <div>
                        <span class="pd-section-kicker">Aksi operasional</span>
                        <h3 class="pd-section-title">Checklist Eksekusi Cepat</h3>
                        <p class="pd-section-desc">Aksi cepat monitoring harian.</p>
                    </div>
                    <button
                        type="button"
                        x-on:click="open = ! open"
                        class="pd-toggle-btn"
                    >
                        <span x-text="open ? 'Minimalkan' : 'Tampilkan'"></span>
                    </button>
                </div>

            <div x-show="open" x-transition.opacity.duration.200ms class="mt-5 space-y-4">
                <div class="pd-toolbar">
                    <label class="pd-toolbar-field pd-toolbar-field--narrow">
                        <span class="pd-toolbar-label">Periode</span>
                            <select
                                wire:model.live="quickChecklistPeriodYear"
                                class="pd-toolbar-input"
                            >
                                @foreach ($this->getIndicatorPeriodOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                    </label>

                    <label class="pd-toolbar-field">
                        <span class="pd-toolbar-label">Cari Nama Proker</span>
                            <input
                                wire:model.live.debounce.400ms="quickChecklistProkerSearch"
                                type="text"
                                placeholder="Contoh: Komite, ANBK, PDSS"
                                class="pd-toolbar-input"
                            >
                    </label>

                        <button
                            type="button"
                            wire:click="resetQuickChecklistFilters"
                            class="inline-flex w-full items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:border-emerald-300 hover:text-emerald-700 xl:w-auto dark:border-white/10 dark:bg-gray-950 dark:text-gray-200 dark:hover:border-emerald-400/30 dark:hover:text-emerald-200"
                        >
                            Reset Filter
                        </button>

                    <div class="pd-toolbar-summary">
                        <span class="pd-toolbar-chip">Proker {{ $quickChecklistMeta['matched_prokers'] }}</span>
                        <span class="pd-toolbar-chip">{{ $quickChecklistMeta['active_period_label'] }}</span>
                        <span class="pd-toolbar-chip">{{ filled($this->quickChecklistProkerSearch) ? $this->quickChecklistProkerSearch : 'Semua proker' }}</span>
                    </div>
                </div>

                <div class="overflow-hidden rounded-[1.35rem] border-2 border-gray-300 bg-white shadow-sm shadow-gray-100/80 ring-1 ring-gray-200/70 dark:border-white/15 dark:bg-gray-950/60 dark:shadow-none dark:ring-white/10">
                    <div class="pd-mobile-list p-4">
                        @forelse ($quickChecklist as $proker)
                            <article wire:key="quick-checklist-mobile-{{ $proker->id }}" class="pd-mobile-card">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $proker->nama }}</h4>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $proker->bidang?->nama ?? '-' }} � PIC {{ $proker->penanggung_jawab ?: '-' }}</p>
                                    </div>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $statusBadgeClasses($proker->status) }}">
                                        {{ $proker->status }}
                                    </span>
                                </div>

                                @if ($proker->jadwal_ringkas)
                                    <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($proker->jadwal_ringkas, 120) }}</p>
                                @endif

                                <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $proker->point_dari ?: 'Tanpa point dari' }}
                                </div>

                                <div class="pd-mobile-metrics mt-4">
                                    <div class="pd-mobile-metric">
                                        <span class="pd-mobile-metric-label">Checklist</span>
                                        <span class="pd-mobile-metric-value">{{ (int) ($proker->checked_indikators_count ?? 0) }}/{{ (int) ($proker->indikators_count ?? 0) }}</span>
                                    </div>
                                    <div class="pd-mobile-metric">
                                        <span class="pd-mobile-metric-label">Update</span>
                                        <span class="pd-mobile-metric-value">{{ (int) ($proker->updates_count ?? 0) }}</span>
                                    </div>
                                    <div class="pd-mobile-metric">
                                        <span class="pd-mobile-metric-label">Progress</span>
                                        <span class="pd-mobile-metric-value">{{ (int) $proker->progress_persen }}%</span>
                                    </div>
                                </div>

                                <div class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-white/[0.04] dark:text-gray-300">
                                    <span>Target</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $proker->target_selesai?->format('d M Y') ?? 'Tanpa target' }}</span>
                                </div>

                                @if ($this->canManageDashboard())
                                    <div class="pd-mobile-actions mt-4">
                                        <x-filament::button
                                            color="gray"
                                            icon="heroicon-o-pencil-square"
                                            size="sm"
                                            class="w-full"
                                            wire:click="mountAction('quickUpdate', { proker: {{ $proker->id }} })"
                                            wire:target="mountAction"
                                        >
                                            Update Monitoring
                                        </x-filament::button>
                                        <x-filament::button
                                            color="success"
                                            icon="heroicon-o-check-badge"
                                            size="sm"
                                            class="w-full"
                                            wire:click="mountAction('checklistTerlaksana', { proker: {{ $proker->id }} })"
                                            wire:target="mountAction"
                                        >
                                            Tandai Terlaksana
                                        </x-filament::button>
                                        @if (\App\Filament\Resources\ProkerResource::canEdit($proker))
                                            <a href="{{ \App\Filament\Resources\ProkerResource::getUrl('edit', ['record' => $proker]) }}" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                                                Edit Proker
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </article>
                        @empty
                            <div class="pd-mobile-card text-sm text-gray-500 dark:text-gray-400">
                                Tidak ada proker yang cocok dengan filter checklist cepat saat ini.
                            </div>
                        @endforelse
                    </div>

                    <div class="pd-desktop-table w-full overflow-x-auto" style="-webkit-overflow-scrolling: touch; touch-action: pan-x;">
                        <div class="min-w-[980px]">
                            <table class="w-full border-separate border-spacing-0 text-sm">
                                <thead class="bg-gradient-to-r from-gray-100 via-gray-50 to-amber-50/80 dark:from-white/[0.08] dark:via-white/[0.05] dark:to-amber-500/[0.08]">
                                    <tr>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Proker</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Bidang / PIC</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Checklist</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Update</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Progress</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Target</th>
                                        <th class="border-b border-gray-300 px-4 py-3 text-right text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-transparent">
                                    @forelse ($quickChecklist as $proker)
                                        <tr wire:key="quick-checklist-proker-{{ $proker->id }}" class="border-b border-gray-200/90 transition hover:bg-emerald-50/30 dark:border-white/10 dark:hover:bg-white/5 even:bg-gray-50/60 dark:even:bg-white/[0.025]">
                                            <td class="border-r border-gray-200/80 px-4 py-4 align-top dark:border-white/10">
                                                <div class="space-y-2">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <p class="font-semibold text-gray-950 dark:text-white">{{ $proker->nama }}</p>
                                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $statusBadgeClasses($proker->status) }}">
                                                            {{ $proker->status }}
                                                        </span>
                                                    </div>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                                        {{ $proker->point_dari ?: 'Tanpa point dari' }}
                                                    </p>
                                                    @if ($proker->jadwal_ringkas)
                                                        <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($proker->jadwal_ringkas, 120) }}</p>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 align-top text-sm text-gray-600 dark:border-white/10 dark:text-gray-300">
                                                <div>{{ $proker->bidang?->nama ?? '-' }}</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">PIC {{ $proker->penanggung_jawab ?: '-' }}</div>
                                            </td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 text-center font-semibold text-gray-700 dark:border-white/10 dark:text-gray-200">{{ (int) ($proker->checked_indikators_count ?? 0) }}/{{ (int) ($proker->indikators_count ?? 0) }}</td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 text-center font-semibold text-gray-700 dark:border-white/10 dark:text-gray-200">{{ (int) ($proker->updates_count ?? 0) }}</td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 text-center dark:border-white/10">
                                                <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200">
                                                    {{ (int) $proker->progress_persen }}%
                                                </span>
                                            </td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 text-sm text-gray-600 dark:border-white/10 dark:text-gray-300">{{ $proker->target_selesai?->format('d M Y') ?? 'Tanpa target' }}</td>
                                            <td class="px-4 py-4 text-right">
                                                @if ($this->canManageDashboard())
                                                    <div class="flex flex-wrap justify-end gap-2">
                                                        <x-filament::button
                                                            color="gray"
                                                            icon="heroicon-o-pencil-square"
                                                            size="sm"
                                                            wire:click="mountAction('quickUpdate', { proker: {{ $proker->id }} })"
                                                            wire:target="mountAction"
                                                        >
                                                            Update
                                                        </x-filament::button>
                                                        <x-filament::button
                                                            color="success"
                                                            icon="heroicon-o-check-badge"
                                                            size="sm"
                                                            wire:click="mountAction('checklistTerlaksana', { proker: {{ $proker->id }} })"
                                                            wire:target="mountAction"
                                                        >
                                                            Terlaksana
                                                        </x-filament::button>
                                                        @if (\App\Filament\Resources\ProkerResource::canEdit($proker))
                                                            <a href="{{ \App\Filament\Resources\ProkerResource::getUrl('edit', ['record' => $proker]) }}" class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:border-gray-300 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                                                                Edit
                                                            </a>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                                Tidak ada proker yang cocok dengan filter checklist cepat saat ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section x-data="{ open: true }" class="rounded-[1.75rem] border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="pd-section-head">
                    <div>
                        <span class="pd-section-kicker">Jejak aktivitas</span>
                        <h3 class="pd-section-title">History Monitoring Terbaru</h3>
                        <p class="pd-section-desc">Ringkasan update terakhir.</p>
                    </div>
                    <button
                        type="button"
                        x-on:click="open = ! open"
                        class="pd-toggle-btn"
                    >
                        <span x-text="open ? 'Minimalkan' : 'Tampilkan'"></span>
                    </button>
                </div>

                <div x-show="open" x-transition.opacity.duration.200ms class="mt-5 overflow-hidden rounded-[1.35rem] border-2 border-gray-300 bg-white shadow-sm shadow-gray-100/80 ring-1 ring-gray-200/70 dark:border-white/15 dark:bg-gray-950/60 dark:shadow-none dark:ring-white/10">
                    <div class="pd-mobile-list p-4">
                        @forelse ($recentUpdates as $update)
                            <article wire:key="recent-update-mobile-{{ $update->id }}" class="pd-mobile-card">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <a href="{{ \App\Filament\Resources\ProkerResource::getUrl('edit', ['record' => $update->proker]) }}" class="text-sm font-semibold text-gray-950 hover:text-amber-700 dark:text-white dark:hover:text-amber-200">
                                            {{ $update->proker?->nama ?? 'Proker tidak ditemukan' }}
                                        </a>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $update->proker?->bidang?->nama ?? '-' }}</p>
                                    </div>
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $statusBadgeClasses($update->status_snapshot) }}">
                                        {{ $update->status_snapshot }}
                                    </span>
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ optional($update->tanggal_update)->format('d M Y') }}</span>
                                    <span>{{ $update->creator?->name ?? 'Sistem' }}</span>
                                </div>

                                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $update->ringkasan ?: 'Belum ada ringkasan update.' }}</p>

                                @if ($update->tindak_lanjut)
                                    <p class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">Tindak lanjut: {{ \Illuminate\Support\Str::limit($update->tindak_lanjut, 100) }}</p>
                                @endif

                                <div class="pd-mobile-metrics mt-4">
                                    <div class="pd-mobile-metric">
                                        <span class="pd-mobile-metric-label">Progress</span>
                                        <span class="pd-mobile-metric-value">{{ $update->progress_persen ?? '-' }}%</span>
                                    </div>
                                    <div class="pd-mobile-metric">
                                        <span class="pd-mobile-metric-label">Dokumen</span>
                                        <span class="pd-mobile-metric-value">{{ count((array) ($update->dokumentasi ?? [])) }}</span>
                                    </div>
                                    <div class="pd-mobile-metric">
                                        <span class="pd-mobile-metric-label">Status</span>
                                        <span class="pd-mobile-metric-value">{{ \Illuminate\Support\Str::headline($update->status_snapshot ?? '-') }}</span>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="pd-mobile-card text-sm text-gray-500 dark:text-gray-400">
                                Belum ada history monitoring. Tambahkan update pada salah satu proker untuk melihat histori di dashboard ini.
                            </div>
                        @endforelse
                    </div>

                    <div class="pd-desktop-table w-full overflow-x-auto" style="-webkit-overflow-scrolling: touch; touch-action: pan-x;">
                        <div class="min-w-[920px]">
                            <table class="w-full border-separate border-spacing-0 text-sm">
                                <thead class="bg-gradient-to-r from-gray-100 via-gray-50 to-amber-50/80 dark:from-white/[0.08] dark:via-white/[0.05] dark:to-amber-500/[0.08]">
                                    <tr>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Proker</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Tanggal / Oleh</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Status</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Progress</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Ringkasan</th>
                                        <th class="border-b border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Dokumen</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-transparent">
                                    @forelse ($recentUpdates as $update)
                                        <tr wire:key="recent-update-{{ $update->id }}" class="border-b border-gray-200/90 transition hover:bg-amber-50/30 dark:border-white/10 dark:hover:bg-white/5 even:bg-gray-50/60 dark:even:bg-white/[0.025]">
                                            <td class="border-r border-gray-200/80 px-4 py-4 align-top dark:border-white/10">
                                                <a href="{{ \App\Filament\Resources\ProkerResource::getUrl('edit', ['record' => $update->proker]) }}" class="font-semibold text-gray-950 hover:text-amber-700 dark:text-white dark:hover:text-amber-200">
                                                    {{ $update->proker?->nama ?? 'Proker tidak ditemukan' }}
                                                </a>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $update->proker?->bidang?->nama ?? '-' }}</div>
                                            </td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 text-sm text-gray-600 dark:border-white/10 dark:text-gray-300">
                                                <div>{{ optional($update->tanggal_update)->format('d M Y') }}</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $update->creator?->name ?? 'Sistem' }}</div>
                                            </td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 text-center dark:border-white/10">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $statusBadgeClasses($update->status_snapshot) }}">
                                                    {{ $update->status_snapshot }}
                                                </span>
                                            </td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 text-center font-semibold text-amber-700 dark:border-white/10 dark:text-amber-300">{{ $update->progress_persen ?? '-' }}%</td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 text-sm leading-6 text-gray-600 dark:border-white/10 dark:text-gray-300">
                                                {{ $update->ringkasan ?: 'Belum ada ringkasan update.' }}
                                                @if ($update->tindak_lanjut)
                                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tindak lanjut: {{ \Illuminate\Support\Str::limit($update->tindak_lanjut, 80) }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 text-center font-semibold text-gray-700 dark:text-gray-200">{{ count((array) ($update->dokumentasi ?? [])) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                                Belum ada history monitoring. Tambahkan update pada salah satu proker untuk melihat histori di dashboard ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <section id="prioritas-tindak-lanjut" x-data="{ open: true }" class="rounded-[1.75rem] border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="pd-section-head">
                    <div>
                        <span class="pd-section-kicker">Prioritas tindak lanjut</span>
                        <h3 class="pd-section-title">Proker Prioritas Tindak Lanjut</h3>
                        <p class="pd-section-desc">Urutan ini mempertimbangkan status, keterlambatan update, target, dan kondisi checklist.</p>
                    </div>
                    <button
                        type="button"
                        x-on:click="open = ! open"
                        class="pd-toggle-btn"
                    >
                        <span x-text="open ? 'Minimalkan' : 'Tampilkan'"></span>
                    </button>
                </div>

                <div x-show="open" x-transition.opacity.duration.200ms class="mt-5 overflow-hidden rounded-[1.35rem] border-2 border-gray-300 bg-white shadow-sm shadow-gray-100/80 ring-1 ring-gray-200/70 dark:border-white/15 dark:bg-gray-950/60 dark:shadow-none dark:ring-white/10">
                    <div class="pd-mobile-list p-4">
                        @forelse ($attentionProkers as $proker)
                            <article wire:key="attention-proker-mobile-{{ $proker->id }}" class="pd-mobile-card">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <a href="{{ \App\Filament\Resources\ProkerResource::getUrl('edit', ['record' => $proker]) }}" class="text-sm font-semibold text-gray-950 hover:text-amber-700 dark:text-white dark:hover:text-amber-200">
                                            {{ $proker->nama }}
                                        </a>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $proker->bidang?->nama ?? '-' }} � PIC {{ $proker->penanggung_jawab ?: '-' }}</p>
                                    </div>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $statusBadgeClasses($proker->status) }}">
                                            {{ $proker->status }}
                                        </span>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $attentionLevelClasses($proker->attention_level ?? null) }}">
                                            Prioritas {{ $proker->attention_level ?? 'rendah' }}
                                        </span>
                                    </div>
                                </div>

                                @if ($proker->jadwal_ringkas)
                                    <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($proker->jadwal_ringkas, 110) }}</p>
                                @endif

                                <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $proker->attention_summary ?? 'Perlu review manual.' }}</p>

                                <div class="pd-mobile-metrics mt-4">
                                    <div class="pd-mobile-metric">
                                        <span class="pd-mobile-metric-label">Checklist</span>
                                        <span class="pd-mobile-metric-value">{{ (int) ($proker->checked_indikators_count ?? 0) }}/{{ (int) ($proker->indikators_count ?? 0) }}</span>
                                    </div>
                                    <div class="pd-mobile-metric">
                                        <span class="pd-mobile-metric-label">Update</span>
                                        <span class="pd-mobile-metric-value">{{ (int) ($proker->updates_count ?? 0) }}x</span>
                                    </div>
                                    <div class="pd-mobile-metric">
                                        <span class="pd-mobile-metric-label">Progress</span>
                                        <span class="pd-mobile-metric-value">{{ (int) $proker->progress_persen }}%</span>
                                    </div>
                                </div>

                                <div class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-white/[0.04] dark:text-gray-300">
                                    <div>
                                        <div>Target {{ $proker->target_selesai?->format('d M Y') ?? 'Tanpa target' }}</div>
                                        <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ $proker->last_update_label ?? 'Belum ada update' }}</div>
                                    </div>
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $proker->periode_tahun ?: '-' }}</span>
                                </div>
                            </article>
                        @empty
                            <div class="pd-mobile-card text-sm text-gray-500 dark:text-gray-400">
                                Belum ada proker yang tercatat.
                            </div>
                        @endforelse
                    </div>

                    <div class="pd-desktop-table w-full overflow-x-auto" style="-webkit-overflow-scrolling: touch; touch-action: pan-x;">
                        <div class="min-w-[880px]">
                            <table class="w-full border-separate border-spacing-0 text-sm">
                                <thead class="bg-gradient-to-r from-gray-100 via-gray-50 to-amber-50/80 dark:from-white/[0.08] dark:via-white/[0.05] dark:to-amber-500/[0.08]">
                                    <tr>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Proker</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Alasan / PIC</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Checklist</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Update</th>
                                        <th class="border-b border-r border-gray-300 px-4 py-3 text-center text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Progress</th>
                                        <th class="border-b border-gray-300 px-4 py-3 text-left text-[11px] font-bold uppercase tracking-[0.2em] text-gray-600 dark:border-white/10 dark:text-gray-300">Target</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-transparent">
                                    @forelse ($attentionProkers as $proker)
                                        <tr wire:key="attention-proker-{{ $proker->id }}" class="border-b border-gray-200/90 transition hover:bg-amber-50/30 dark:border-white/10 dark:hover:bg-white/5 even:bg-gray-50/60 dark:even:bg-white/[0.025]">
                                            <td class="border-r border-gray-200/80 px-4 py-4 align-top dark:border-white/10">
                                                <a href="{{ \App\Filament\Resources\ProkerResource::getUrl('edit', ['record' => $proker]) }}" class="font-semibold text-gray-950 hover:text-amber-700 dark:text-white dark:hover:text-amber-200">
                                                    {{ $proker->nama }}
                                                </a>
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $statusBadgeClasses($proker->status) }}">
                                                        {{ $proker->status }}
                                                    </span>
                                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {{ $attentionLevelClasses($proker->attention_level ?? null) }}">
                                                        Prioritas {{ $proker->attention_level ?? 'rendah' }}
                                                    </span>
                                                </div>
                                                @if ($proker->jadwal_ringkas)
                                                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($proker->jadwal_ringkas, 90) }}</div>
                                                @endif
                                            </td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 text-sm text-gray-600 dark:border-white/10 dark:text-gray-300">
                                                <div>{{ $proker->bidang?->nama ?? '-' }}</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">PIC {{ $proker->penanggung_jawab ?: '-' }}</div>
                                                <div class="mt-2 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $proker->attention_summary ?? 'Perlu review manual.' }}</div>
                                            </td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 text-center font-semibold text-gray-700 dark:border-white/10 dark:text-gray-200">{{ (int) ($proker->checked_indikators_count ?? 0) }}/{{ (int) ($proker->indikators_count ?? 0) }}</td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 text-center text-sm text-gray-600 dark:border-white/10 dark:text-gray-300">
                                                <div class="font-semibold text-gray-700 dark:text-gray-200">{{ (int) ($proker->updates_count ?? 0) }}x</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $proker->last_update_label ?? 'Belum ada update' }}</div>
                                            </td>
                                            <td class="border-r border-gray-200/80 px-4 py-4 text-center font-semibold text-amber-700 dark:border-white/10 dark:text-amber-300">{{ (int) $proker->progress_persen }}%</td>
                                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-300">
                                                <div>{{ $proker->target_selesai?->format('d M Y') ?? 'Tanpa target' }}</div>
                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Periode {{ $proker->periode_tahun ?: '-' }}</div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                                Belum ada proker yang tercatat.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>



















