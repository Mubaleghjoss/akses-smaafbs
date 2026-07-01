<x-filament-panels::page>
    <style>
        [x-cloak] { display: none !important; }
        .fc {
            --fc-border-color: rgba(148, 163, 184, 0.2);
            --fc-today-bg-color: rgba(59, 130, 246, 0.08);
        }
        .dark .fc {
            --fc-border-color: rgba(148, 163, 184, 0.15);
            --fc-page-bg-color: transparent;
        }
        .fc .fc-daygrid-day.fc-day-today {
            box-shadow: inset 0 0 0 1px rgba(59, 130, 246, 0.4);
            border-radius: 10px;
        }
        .fc .fc-scrollgrid {
            border-radius: 14px;
            overflow: hidden;
        }
        .fc .fc-daygrid-day-frame {
            min-height: 96px;
        }
        @media (max-width: 640px) {
            .fc .fc-daygrid-day-frame {
                min-height: 72px;
            }
        }
        .fc .fc-col-header-cell {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.08em;
            color: rgba(148, 163, 184, 0.9);
            background: rgba(148, 163, 184, 0.08);
        }
        .dark .fc .fc-col-header-cell {
            color: rgba(226, 232, 240, 0.7);
            background: rgba(148, 163, 184, 0.12);
        }
        .fc .fc-scrollgrid-section-header th {
            padding: 6px 0;
        }
        .fc .fc-daygrid-day-number {
            font-weight: 600;
            color: #e2e8f0;
        }
        .fc .fc-toolbar {
            flex-wrap: wrap;
            gap: 8px;
        }
        .fc .fc-toolbar-chunk {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .fc .fc-button {
            border-radius: 999px;
        }
        .fc .fc-daygrid-body, .fc .fc-daygrid-body table {
            width: 100% !important;
        }
        .fc .fc-scrollgrid-sync-table,
        .fc .fc-scrollgrid-section-header table,
        .fc .fc-scrollgrid-section-body table {
            width: 100% !important;
            table-layout: fixed;
        }
        .fc .fc-scrollgrid-section-body {
            min-height: 420px;
        }
        @media (max-width: 768px) {
            .fc .fc-scrollgrid-section-body {
                min-height: 320px;
            }
        }
        .calendar-page {
            display: grid;
            gap: 1rem;
            width: 100%;
        }
        .calendar-panel {
            border: 1px solid rgba(203, 213, 225, 0.85);
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }
        .dark .calendar-panel {
            border-color: rgba(255, 255, 255, 0.1);
            background: rgb(17, 24, 39);
        }
        .calendar-panel-body,
        .calendar-panel-head {
            padding: 1rem;
        }
        .calendar-panel-head {
            border-bottom: 1px solid rgba(203, 213, 225, 0.85);
        }
        .dark .calendar-panel-head {
            border-bottom-color: rgba(255, 255, 255, 0.1);
        }
        .calendar-header-row {
            display: flex;
            flex-direction: column;
            gap: .75rem;
        }
        @media (min-width: 768px) {
            .calendar-header-row {
                align-items: center;
                flex-direction: row;
                justify-content: space-between;
            }
        }
        .calendar-eyebrow {
            margin: 0;
            color: rgb(100, 116, 139);
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        .calendar-title {
            margin: .25rem 0 0;
            color: rgb(15, 23, 42);
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.35;
        }
        .dark .calendar-title {
            color: #fff;
        }
        .calendar-description {
            margin: .35rem 0 0;
            color: rgb(100, 116, 139);
            font-size: .875rem;
            line-height: 1.55;
        }
        .calendar-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .calendar-chip {
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            padding: .28rem .7rem;
            font-size: .75rem;
            font-weight: 700;
        }
        .calendar-chip-blue {
            background: rgb(239, 246, 255);
            color: rgb(29, 78, 216);
        }
        .calendar-chip-amber {
            background: rgb(255, 251, 235);
            color: rgb(180, 83, 9);
        }
        .quick-menu-grid {
            display: grid;
            gap: .75rem;
        }
        @media (min-width: 640px) {
            .quick-menu-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (min-width: 1280px) {
            .quick-menu-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        .quick-menu-card {
            align-items: flex-start;
            background: rgb(248, 250, 252);
            border: 1px solid rgb(226, 232, 240);
            border-radius: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            color: inherit;
            cursor: pointer;
            display: flex;
            gap: .75rem;
            min-height: 7rem;
            padding: 1rem;
            text-align: left;
            transition: border-color .15s ease, background .15s ease, box-shadow .15s ease, transform .15s ease;
            width: 100%;
        }
        .quick-menu-card:hover {
            background: #fff;
            border-color: rgb(148, 163, 184);
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.1);
            transform: translateY(-2px);
        }
        .dark .quick-menu-card {
            background: rgba(15, 23, 42, 0.45);
            border-color: rgb(31, 41, 55);
        }
        .quick-menu-badge {
            align-items: center;
            background: rgb(240, 253, 244);
            border-radius: 12px;
            color: rgb(21, 128, 61);
            display: flex;
            flex: 0 0 auto;
            font-size: .78rem;
            font-weight: 800;
            height: 2.5rem;
            justify-content: center;
            width: 2.5rem;
        }
        .quick-menu-card:hover .quick-menu-badge {
            background: rgb(21, 128, 61);
            color: #fff;
        }
        .quick-menu-title {
            color: rgb(15, 23, 42);
            display: block;
            font-size: .92rem;
            font-weight: 700;
            line-height: 1.4;
        }
        .dark .quick-menu-title {
            color: #fff;
        }
        .quick-menu-text,
        .calendar-muted {
            color: rgb(100, 116, 139);
            display: block;
            font-size: .78rem;
            line-height: 1.5;
            margin-top: .25rem;
        }
        .agenda-modal-shell {
            align-items: flex-end;
            display: flex;
            inset: 0;
            justify-content: center;
            padding: .75rem;
            position: fixed;
            z-index: 50;
        }
        @media (min-width: 640px) {
            .agenda-modal-shell {
                align-items: center;
                padding: 1rem;
            }
        }
        .agenda-modal-backdrop {
            background: rgba(15, 23, 42, .64);
            inset: 0;
            position: absolute;
        }
        .agenda-modal-dialog {
            background: #fff;
            border: 1px solid rgb(226, 232, 240);
            border-radius: 18px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, .3);
            max-width: 48rem;
            position: relative;
            width: 100%;
        }
        .dark .agenda-modal-dialog {
            background: rgb(17, 24, 39);
            border-color: rgb(31, 41, 55);
        }
        .agenda-modal-content {
            max-height: calc(100vh - 1.5rem);
            overflow-y: auto;
            padding: 1rem;
        }
        @media (min-width: 640px) {
            .agenda-modal-content {
                max-height: calc(100vh - 2rem);
                padding: 1.25rem;
            }
        }
        .agenda-modal-top,
        .agenda-modal-actions,
        .calendar-action-row {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            justify-content: space-between;
        }
        .agenda-modal-close {
            align-items: center;
            background: rgb(248, 250, 252);
            border: 1px solid rgb(226, 232, 240);
            border-radius: 10px;
            color: rgb(71, 85, 105);
            cursor: pointer;
            display: flex;
            font-size: 1.25rem;
            height: 2rem;
            justify-content: center;
            line-height: 1;
            width: 2rem;
        }
        .agenda-summary-grid {
            display: grid;
            gap: .75rem;
            margin-top: 1rem;
        }
        @media (min-width: 640px) {
            .agenda-summary-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        .agenda-summary-card,
        .calendar-inner-card {
            background: rgb(248, 250, 252);
            border: 1px solid rgb(226, 232, 240);
            border-radius: 16px;
            padding: .85rem;
        }
        .dark .agenda-summary-card,
        .dark .calendar-inner-card {
            background: rgba(15, 23, 42, .45);
            border-color: rgb(31, 41, 55);
        }
        .agenda-form {
            display: grid;
            gap: 1rem;
            margin-top: 1rem;
        }
        .agenda-form-grid,
        .calendar-form-grid {
            display: grid;
            gap: 1rem;
        }
        @media (min-width: 1024px) {
            .agenda-form-grid {
                grid-template-columns: minmax(0, 1.35fr) minmax(18rem, .65fr);
            }
            .calendar-form-grid {
                grid-template-columns: minmax(0, 1.25fr) minmax(17rem, .75fr);
            }
        }
        .agenda-field,
        .calendar-field {
            color: rgb(51, 65, 85);
            display: grid;
            font-size: .88rem;
            font-weight: 600;
            gap: .35rem;
        }
        .dark .agenda-field,
        .dark .calendar-field {
            color: rgb(226, 232, 240);
        }
        .agenda-form textarea,
        .agenda-form input[type='text'],
        .agenda-form input[type='date'],
        .agenda-form select,
        .calendar-panel textarea,
        .calendar-panel input[type='number'],
        .calendar-panel select {
            background: #fff;
            border: 1px solid rgb(203, 213, 225);
            border-radius: 12px;
            color: rgb(15, 23, 42);
            font-size: .9rem;
            padding: .65rem .8rem;
            width: 100%;
        }
        .agenda-form textarea,
        .calendar-panel textarea {
            min-height: 9rem;
            resize: vertical;
        }
        .agenda-form input[type='checkbox'] {
            height: 1rem;
            width: 1rem;
        }
        .agenda-side-card {
            background: rgb(248, 250, 252);
            border: 1px solid rgb(226, 232, 240);
            border-radius: 16px;
            display: grid;
            gap: .85rem;
            padding: 1rem;
        }
        .calendar-note-card {
            background: #fff;
            border: 1px dashed rgb(203, 213, 225);
            border-radius: 12px;
            color: rgb(100, 116, 139);
            font-size: .8rem;
            line-height: 1.5;
            padding: .8rem;
        }
        .agenda-modal-actions {
            border-top: 1px solid rgb(226, 232, 240);
            margin-top: .25rem;
            padding-top: 1rem;
        }
        .calendar-button,
        .agenda-button {
            align-items: center;
            border: 1px solid rgb(203, 213, 225);
            border-radius: 12px;
            cursor: pointer;
            display: inline-flex;
            font-size: .88rem;
            font-weight: 700;
            gap: .5rem;
            justify-content: center;
            min-height: 2.5rem;
            padding: .55rem .9rem;
        }
        .calendar-button-secondary,
        .agenda-button-secondary {
            background: #fff;
            color: rgb(51, 65, 85);
        }
        .calendar-button-primary,
        .agenda-button-primary {
            background: rgb(21, 128, 61);
            border-color: rgb(21, 128, 61);
            color: #fff;
        }
        .calendar-button-danger,
        .agenda-button-danger {
            background: rgb(220, 38, 38);
            border-color: rgb(220, 38, 38);
            color: #fff;
        }
        .calendar-danger-panel {
            border-color: rgba(248, 113, 113, .5);
        }
        .calendar-danger-badge {
            background: rgb(254, 242, 242);
            border-radius: 999px;
            color: rgb(185, 28, 28);
            display: inline-flex;
            font-size: .75rem;
            font-weight: 800;
            padding: .28rem .7rem;
        }
        .agenda-summary-label,
        .calendar-small-label {
            color: rgb(100, 116, 139);
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .16em;
            margin: 0;
            text-transform: uppercase;
        }
        .agenda-summary-value {
            color: rgb(15, 23, 42);
            font-size: .88rem;
            font-weight: 700;
            line-height: 1.45;
            margin: .35rem 0 0;
        }
        .dark .agenda-summary-value {
            color: #fff;
        }
        .calendar-form-stack {
            display: grid;
            gap: .75rem;
        }
        .calendar-inline-toggle {
            align-items: center;
            color: rgb(51, 65, 85);
            display: inline-flex;
            font-size: .88rem;
            font-weight: 600;
            gap: .5rem;
        }
        .dark .calendar-inline-toggle {
            color: rgb(226, 232, 240);
        }
        .calendar-button-row {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: flex-end;
        }
        .calendar-button:hover,
        .agenda-button:hover {
            filter: brightness(.98);
        }
        .calendar-button[disabled],
        .agenda-button[disabled] {
            cursor: not-allowed;
            opacity: .65;
        }
        .calendar-import-grid {
            display: grid;
            gap: 1rem;
        }
        @media (min-width: 1024px) {
            .calendar-import-grid {
                grid-template-columns: minmax(0, 1.35fr) minmax(17rem, .65fr);
            }
        }
        .calendar-delete-grid {
            display: grid;
            gap: .85rem;
        }
        @media (min-width: 640px) {
            .calendar-delete-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        .calendar-details-summary {
            align-items: flex-start;
            cursor: pointer;
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            list-style: none;
            padding: 1rem;
        }
        .calendar-details-summary::-webkit-details-marker {
            display: none;
        }
        .calendar-format-list {
            color: rgb(100, 116, 139);
            display: grid;
            font-size: .8rem;
            gap: .35rem;
            line-height: 1.5;
            margin: .5rem 0 0;
            padding-left: 1rem;
        }
        .calendar-danger-panel .calendar-panel-body {
            border-top: 1px solid rgba(248, 113, 113, .25);
        }
    </style>

    <script src="{{ asset('vendor/fullcalendar/index.global.min.js') }}"></script>

    <div class="calendar-page">
        <section class="calendar-panel">
            <div class="calendar-panel-body">
                <div class="calendar-header-row">
                    <div>
                        <p class="calendar-eyebrow">Agenda Sekolah</p>
                        <h2 class="calendar-title">Kalender Agenda</h2>
                        <p class="calendar-description">Kelola agenda harian, rentang kegiatan, import teks WA, dan jadwal yang tampil di publik.</p>
                    </div>

                    <div class="calendar-chip-row">
                        <span class="calendar-chip calendar-chip-blue">Publik</span>
                        <span class="calendar-chip calendar-chip-amber">Internal</span>
                    </div>
                </div>
            </div>
        </section>

        <div
            x-data="calendarScheduler(@js($events))"
            x-init="init()"
            wire:ignore
            class="calendar-page"
        >
            <section class="calendar-panel">
                <div class="calendar-panel-head">
                    <h3 class="calendar-title">Input Cepat</h3>
                    <p class="calendar-description">Pilih preset tanggal atau klik tanggal langsung pada kalender.</p>
                </div>

                <div class="calendar-panel-body">
                    <div class="quick-menu-grid">
                        <button
                            type="button"
                            x-on:click="openPreset(0, 1)"
                            class="quick-menu-card"
                        >
                            <span class="quick-menu-badge">H</span>
                            <span>
                                <span class="quick-menu-title">Hari ini</span>
                                <span class="quick-menu-text">Buka modal untuk agenda tanggal hari ini.</span>
                            </span>
                        </button>

                        <button
                            type="button"
                            x-on:click="openPreset(1, 1)"
                            class="quick-menu-card"
                        >
                            <span class="quick-menu-badge">B</span>
                            <span>
                                <span class="quick-menu-title">Besok</span>
                                <span class="quick-menu-text">Siapkan kegiatan untuk tanggal besok.</span>
                            </span>
                        </button>

                        <button
                            type="button"
                            x-on:click="openWeekRange()"
                            class="quick-menu-card"
                        >
                            <span class="quick-menu-badge">MG</span>
                            <span>
                                <span class="quick-menu-title">Sisa minggu ini</span>
                                <span class="quick-menu-text">Buat agenda rentang hari sampai akhir minggu.</span>
                            </span>
                        </button>

                        <button
                            type="button"
                            x-on:click="openMonthRange()"
                            class="quick-menu-card"
                        >
                            <span class="quick-menu-badge">BL</span>
                            <span>
                                <span class="quick-menu-title">Sampai akhir bulan</span>
                                <span class="quick-menu-text">Buat agenda rentang hari sampai akhir bulan.</span>
                            </span>
                        </button>
                    </div>

                    <p class="calendar-muted">Modal tambah agenda menerima beberapa baris sekaligus. Satu baris dibuat menjadi satu kegiatan.</p>
                </div>
            </section>

            <section class="calendar-panel">
                <div class="calendar-panel-head">
                    <div class="calendar-header-row">
                        <div>
                            <h3 class="calendar-title">Kalender</h3>
                            <p class="calendar-description">Klik agenda untuk edit. Tahan Shift lalu tarik tanggal untuk membuat rentang hari.</p>
                        </div>
                        <span class="calendar-muted">Warna event mengikuti visibilitas.</span>
                    </div>
                </div>

                <div class="calendar-panel-body">
                    <div x-ref="calendar"></div>
                </div>
            </section>

            <div
                x-cloak
                x-show="isOpen"
                x-transition.opacity
                class="agenda-modal-shell"
                aria-modal="true"
                role="dialog"
            >
                <div class="agenda-modal-backdrop" x-on:click="close()"></div>

                <div class="agenda-modal-dialog">
                    <div class="agenda-modal-content">
                        <div class="agenda-modal-top">
                            <div>
                                <h3 class="calendar-title" x-text="mode === 'edit' ? 'Ubah Agenda' : 'Tambah Agenda'"></h3>
                                <p class="calendar-description" x-text="rangeLabel"></p>
                            </div>
                            <button type="button" class="agenda-modal-close" x-on:click="close()">
                                &times;
                            </button>
                        </div>

                        <div class="agenda-summary-grid">
                            <div class="agenda-summary-card">
                                <p class="agenda-summary-label">Mode</p>
                                <p class="agenda-summary-value" x-text="mode === 'edit' ? 'Edit satu agenda' : 'Tambah cepat dari kalender'"></p>
                            </div>
                            <div class="agenda-summary-card">
                                <p class="agenda-summary-label">Tanggal</p>
                                <p class="agenda-summary-value" x-text="rangeLabel || '-'"></p>
                            </div>
                            <div class="agenda-summary-card">
                                <p class="agenda-summary-label">Hasil</p>
                                <p class="agenda-summary-value" x-text="mode === 'edit' ? '1 agenda diperbarui' : summaryLabel()"></p>
                            </div>
                        </div>

                        <form class="agenda-form" x-on:submit.prevent="save()">
                            <div class="calendar-inner-card" x-show="mode === 'create'">
                                <label class="agenda-field">
                                    <span>Daftar kegiatan</span>
                                <textarea
                                    rows="5"
                                    x-model="form.title"
                                    placeholder="Contoh: Apel pagi&#10;KBM sekolah&#10;Rapat guru"
                                    x-on:keydown.ctrl.enter.prevent="save()"
                                    x-on:keydown.meta.enter.prevent="save()"
                                    required
                                ></textarea>
                                </label>
                                <div class="calendar-action-row">
                                    <span>Satu baris = satu kegiatan. Gunakan `Ctrl/Cmd + Enter` untuk simpan cepat.</span>
                                    <span x-text="summaryLabel()"></span>
                                </div>
                            </div>

                            <div class="calendar-inner-card" x-show="mode === 'edit'" x-cloak>
                                <label class="agenda-field">
                                    <span>Nama kegiatan</span>
                                <input
                                    type="text"
                                    x-model="form.title"
                                    placeholder="Contoh: Apel pagi"
                                    x-on:keydown.ctrl.enter.prevent="save()"
                                    x-on:keydown.meta.enter.prevent="save()"
                                    required
                                />
                                </label>
                                <p class="calendar-muted">Gunakan `Ctrl/Cmd + Enter` untuk simpan perubahan.</p>
                            </div>

                            <div class="agenda-form-grid">
                                <label class="agenda-field">
                                    <span>Keterangan (opsional)</span>
                                    <textarea
                                        rows="4"
                                        x-model="form.description"
                                        placeholder="Catatan tambahan jika diperlukan"
                                    ></textarea>
                                </label>

                                <div class="agenda-side-card">
                                    <div class="calendar-form-stack">
                                        <label class="agenda-field">
                                            Visibilitas
                                            <select
                                                x-model="form.visibility"
                                            >
                                                <option value="external">Publik</option>
                                                <option value="internal">Internal</option>
                                            </select>
                                        </label>

                                        <label class="agenda-field">
                                            Tanggal mulai
                                            <input
                                                type="date"
                                                x-model="form.start"
                                                x-on:change="syncDateRange()"
                                                required
                                            />
                                        </label>

                                        <div class="calendar-form-stack">
                                            <label class="calendar-inline-toggle">
                                                <input
                                                    type="checkbox"
                                                    x-model="form.useEndDate"
                                                    x-on:change="toggleEndDate()"
                                                />
                                                Pakai tanggal selesai
                                            </label>

                                            <label class="agenda-field" x-show="form.useEndDate" x-cloak>
                                                Tanggal selesai
                                                <input
                                                    type="date"
                                                    x-model="form.end"
                                                    x-bind:min="form.start || null"
                                                    x-on:change="syncDateRange()"
                                                />
                                            </label>
                                        </div>
                                    </div>

                                    <div class="calendar-note-card">
                                        <div class="calendar-action-row">
                                            <span>Ringkasan tanggal</span>
                                            <span x-text="form.useEndDate && form.end ? 'Rentang hari' : 'Satu hari'"></span>
                                        </div>
                                        <p x-text="rangeLabel || 'Pilih tanggal terlebih dahulu.'"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="agenda-modal-actions">
                                <div class="calendar-muted">
                                    Klik event di kalender untuk edit cepat. Perubahan langsung memperbarui tampilan kalender.
                                </div>
                                <div class="calendar-button-row">
                                    <button type="button" class="agenda-button agenda-button-secondary" x-on:click="close()">
                                        Batal
                                    </button>
                                    <button
                                        type="button"
                                        class="agenda-button agenda-button-danger"
                                        x-on:click="remove()"
                                        x-show="mode === 'edit'"
                                        x-cloak
                                    >
                                        Hapus
                                    </button>
                                    <button type="submit" class="agenda-button agenda-button-primary" x-text="submitLabel()">
                                        Simpan
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function calendarScheduler(initialEvents) {
            return {
                isOpen: false,
                mode: 'create',
                selectedId: null,
                selectedEvent: null,
                rangeLabel: '',
                formatter: null,
                form: {
                    title: '',
                    description: '',
                    start: '',
                    end: '',
                    useEndDate: false,
                    visibility: 'external',
                },
                calendar: null,
                layoutUpdatePending: false,
                init() {
                    const calendarEl = this.$refs.calendar;
                    this.formatter = new Intl.DateTimeFormat('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

                    this.calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'dayGridMonth',
                        height: 'auto',
                        locale: 'id',
                        firstDay: 1,
                        fixedWeekCount: false,
                        selectable: true,
                        selectMirror: true,
                        dayMaxEvents: true,
                        events: Array.isArray(initialEvents) ? initialEvents : [],
                        dateClick: (info) => {
                            this.openCreateModal(info.date, null);
                        },
                        select: (info) => {
                            if (info.jsEvent && !info.jsEvent.shiftKey) {
                                this.calendar.unselect();
                                return;
                            }
                            const endInclusive = this.toEndInclusive(info.end);
                            this.openCreateModal(info.start, endInclusive);
                        },
                        eventClick: (info) => {
                            if (info.jsEvent) {
                                info.jsEvent.preventDefault();
                            }
                            this.openEditModal(info.event);
                        },
                    });

                    this.calendar.render();
                    this.queueLayoutUpdate();

                    window.addEventListener('calendar-event-created', (event) => {
                        const payload = event.detail && event.detail.calendarEvent ? event.detail.calendarEvent : null;
                        this.upsertEvent(payload);
                        this.close();
                        this.queueLayoutUpdate();
                    });

                    window.addEventListener('calendar-event-updated', (event) => {
                        const payload = event.detail && event.detail.calendarEvent ? event.detail.calendarEvent : null;
                        if (!payload) {
                            return;
                        }
                        this.upsertEvent(payload);
                        this.close();
                        this.queueLayoutUpdate();
                    });

                    window.addEventListener('calendar-event-deleted', (event) => {
                        const id = event.detail && event.detail.calendarEventId ? event.detail.calendarEventId : null;
                        if (id === null || id === undefined) {
                            return;
                        }
                        const existing = this.calendar.getEventById(String(id));
                        if (existing) {
                            existing.remove();
                        }
                        this.close();
                        this.queueLayoutUpdate();
                    });

                    window.addEventListener('calendar-events-imported', (event) => {
                        const items = event.detail && event.detail.calendarEvents ? event.detail.calendarEvents : [];
                        if (!Array.isArray(items)) {
                            return;
                        }
                        items.forEach((item) => this.upsertEvent(item));
                        this.close();
                        this.queueLayoutUpdate();
                    });

                    window.addEventListener('calendar-events-replaced', (event) => {
                        const ids = event.detail && event.detail.calendarEventIds ? event.detail.calendarEventIds : [];
                        const items = event.detail && event.detail.calendarEvents ? event.detail.calendarEvents : [];

                        if (Array.isArray(ids)) {
                            ids.forEach((id) => {
                                const existing = this.calendar.getEventById(String(id));
                                if (existing) {
                                    existing.remove();
                                }
                            });
                        }

                        if (Array.isArray(items)) {
                            items.forEach((item) => this.upsertEvent(item));
                        }

                        this.close();
                        this.queueLayoutUpdate();
                    });

                    window.addEventListener('calendar-events-deleted-bulk', (event) => {
                        const ids = event.detail && event.detail.calendarEventIds ? event.detail.calendarEventIds : [];
                        if (!Array.isArray(ids)) {
                            return;
                        }
                        ids.forEach((id) => {
                            const existing = this.calendar.getEventById(String(id));
                            if (existing) {
                                existing.remove();
                            }
                        });
                        this.close();
                        this.queueLayoutUpdate();
                    });

                    window.addEventListener('resize', () => this.queueLayoutUpdate());
                },
                queueLayoutUpdate() {
                    if (this.layoutUpdatePending || !this.calendar) {
                        return;
                    }
                    this.layoutUpdatePending = true;
                    requestAnimationFrame(() => {
                        setTimeout(() => {
                            if (this.calendar) {
                                this.calendar.updateSize();
                            }
                            this.layoutUpdatePending = false;
                        }, 0);
                    });
                },
                upsertEvent(payload) {
                    if (!payload || !this.calendar) {
                        return;
                    }
                    const id = payload.id !== undefined && payload.id !== null ? String(payload.id) : null;
                    if (id) {
                        const existing = this.calendar.getEventById(id);
                        if (existing) {
                            existing.setProp('title', payload.title || '');
                            existing.setAllDay(Boolean(payload.allDay));
                            existing.setStart(payload.start || null);
                            existing.setEnd(payload.end || null);
                            existing.setProp('backgroundColor', payload.backgroundColor || null);
                            existing.setProp('borderColor', payload.borderColor || null);
                            existing.setProp('textColor', payload.textColor || null);
                            existing.setExtendedProp('description', payload.description || '');
                            existing.setExtendedProp('visibility', payload.visibility || 'external');
                            return;
                        }
                    }
                    this.calendar.addEvent(payload);
                },
                today() {
                    const date = new Date();
                    date.setHours(0, 0, 0, 0);

                    return date;
                },
                openPreset(offsetDays, durationDays = 1) {
                    const start = this.today();
                    start.setDate(start.getDate() + offsetDays);

                    let end = null;
                    if (durationDays > 1) {
                        end = new Date(start);
                        end.setDate(end.getDate() + durationDays - 1);
                    }

                    this.openCreateModal(start, end);
                },
                openWeekRange() {
                    const start = this.today();
                    const end = new Date(start);
                    const remainingDays = Math.max(1, 7 - (start.getDay() === 0 ? 7 : start.getDay()));
                    end.setDate(end.getDate() + remainingDays);

                    this.openCreateModal(start, end);
                },
                openMonthRange() {
                    const start = this.today();
                    const end = new Date(start.getFullYear(), start.getMonth() + 1, 0);

                    this.openCreateModal(start, end);
                },
                normalizeTitles(input) {
                    return String(input || '')
                        .split(/\r?\n/)
                        .map((line) => line.trim())
                        .filter(Boolean)
                        .map((line) => line.replace(/^(?:[-*]|\u2022|\d+[.)])\s+/, '').trim())
                        .filter(Boolean);
                },
                summaryLabel() {
                    const count = this.normalizeTitles(this.form.title).length;

                    if (!count) {
                        return 'Belum ada kegiatan';
                    }

                    return count === 1 ? '1 kegiatan akan dibuat' : `${count} kegiatan akan dibuat`;
                },
                submitLabel() {
                    if (this.mode === 'edit') {
                        return 'Simpan Perubahan';
                    }

                    const count = this.normalizeTitles(this.form.title).length;

                    if (count <= 1) {
                        return 'Simpan Agenda';
                    }

                    return `Simpan ${count} Kegiatan`;
                },
                formatRangeLabel(start, end) {
                    if (!end || start.toDateString() === end.toDateString()) {
                        return this.formatter ? this.formatter.format(start) : start.toDateString();
                    }
                    return (this.formatter ? this.formatter.format(start) : start.toDateString()) + ' - ' +
                        (this.formatter ? this.formatter.format(end) : end.toDateString());
                },
                toDateString(date) {
                    return new Date(date.getTime() - (date.getTimezoneOffset() * 60000)).toISOString().slice(0, 10);
                },
                parseDateString(value) {
                    if (!value) {
                        return null;
                    }

                    const [year, month, day] = String(value).split('-').map((part) => parseInt(part, 10));

                    if (!year || !month || !day) {
                        return null;
                    }

                    return new Date(year, month - 1, day);
                },
                syncDateRange() {
                    if (!this.form.start) {
                        this.rangeLabel = '';

                        return;
                    }

                    if (this.form.useEndDate) {
                        if (!this.form.end || this.form.end < this.form.start) {
                            this.form.end = this.form.start;
                        }
                    } else {
                        this.form.end = '';
                    }

                    const start = this.parseDateString(this.form.start);
                    const end = this.form.useEndDate ? this.parseDateString(this.form.end) : null;

                    if (!start) {
                        this.rangeLabel = '';

                        return;
                    }

                    this.rangeLabel = this.formatRangeLabel(start, end || start);
                },
                toggleEndDate() {
                    if (this.form.useEndDate) {
                        if (!this.form.end || this.form.end < this.form.start) {
                            this.form.end = this.form.start;
                        }
                    } else {
                        this.form.end = '';
                    }

                    this.syncDateRange();
                },
                toEndInclusive(endExclusive) {
                    const end = new Date(endExclusive.getTime());
                    end.setDate(end.getDate() - 1);
                    return end;
                },
                openCreateModal(startDate, endDate) {
                    this.mode = 'create';
                    this.selectedId = null;
                    this.selectedEvent = null;
                    this.form.title = '';
                    this.form.description = '';
                    this.form.start = this.toDateString(startDate);
                    this.form.end = endDate ? this.toDateString(endDate) : '';
                    this.form.useEndDate = Boolean(endDate);
                    this.form.visibility = 'external';
                    this.syncDateRange();
                    this.isOpen = true;
                },
                openEditModal(eventApi) {
                    const startDate = eventApi.start ? new Date(eventApi.start) : new Date();
                    const endDate = eventApi.end
                        ? (eventApi.allDay ? this.toEndInclusive(eventApi.end) : new Date(eventApi.end))
                        : null;

                    this.mode = 'edit';
                    this.selectedId = eventApi.id ? parseInt(eventApi.id, 10) : null;
                    this.selectedEvent = eventApi;
                    this.form.title = eventApi.title || '';
                    this.form.description = eventApi.extendedProps && eventApi.extendedProps.description
                        ? eventApi.extendedProps.description
                        : '';
                    this.form.start = this.toDateString(startDate);
                    this.form.end = endDate ? this.toDateString(endDate) : '';
                    this.form.useEndDate = Boolean(endDate);
                    this.form.visibility = eventApi.extendedProps && eventApi.extendedProps.visibility
                        ? eventApi.extendedProps.visibility
                        : 'external';
                    this.syncDateRange();
                    this.isOpen = true;
                },
                close() {
                    this.isOpen = false;
                    this.mode = 'create';
                    this.selectedId = null;
                    this.selectedEvent = null;
                    this.form.useEndDate = false;
                },
                save() {
                    this.syncDateRange();

                    if (!this.form.start) {
                        return;
                    }

                    const payload = {
                        description: this.form.description.trim(),
                        start: this.form.start,
                        end: this.form.useEndDate ? (this.form.end || this.form.start) : null,
                        visibility: this.form.visibility || 'external',
                    };

                    if (this.mode === 'edit') {
                        const title = String(this.form.title || '').trim();
                        if (!title) {
                            return;
                        }

                        if (this.selectedId) {
                            this.$wire.updateEvent(this.selectedId, { ...payload, title });
                        }

                        return;
                    }

                    const titles = this.normalizeTitles(this.form.title);
                    if (!titles.length) {
                        return;
                    }

                    if (titles.length > 1) {
                        this.$wire.createEvents({ ...payload, titles });
                        return;
                    }

                    this.$wire.createEvent({ ...payload, title: titles[0] });
                },
                remove() {
                    if (!this.selectedId) {
                        return;
                    }
                    if (!confirm('Hapus agenda ini?')) {
                        return;
                    }
                    this.$wire.deleteEvent(this.selectedId);
                },
            }
        }
    </script>

    <div class="calendar-page">
        <section class="calendar-panel" x-data="{ sampleText: `Agenda Kegiatan Januari 2026\n1-4 Januari 2026\n- Libur KBM Sekolah\n- Asrama Boarding\n\nSenin, 5 Januari 2026\n1. KBM semester 2\n2. Apel Pagi` }">
            <div class="calendar-panel-head">
                <div class="calendar-header-row">
                    <div>
                        <p class="calendar-eyebrow">Import</p>
                        <h2 class="calendar-title">Import Teks WA</h2>
                        <p class="calendar-description">Tempel agenda dari WhatsApp. Tanggal di satu baris, daftar kegiatan di bawahnya.</p>
                    </div>
                    <div class="calendar-button-row">
                        <button
                            type="button"
                            x-on:click="$wire.set('importText', sampleText)"
                            class="calendar-button calendar-button-secondary"
                        >
                            Isi contoh
                        </button>
                        <button
                            type="button"
                            x-on:click="$wire.set('importText', '')"
                            class="calendar-button calendar-button-secondary"
                        >
                            Kosongkan
                        </button>
                    </div>
                </div>
            </div>

            <div class="calendar-panel-body">
                <div class="calendar-import-grid">
                    <div class="calendar-form-stack">
                        <label class="calendar-field">
                            Teks agenda
                            <textarea
                                wire:model.defer="importText"
                                rows="9"
                                placeholder="Agenda Kegiatan Januari 2026&#10;1-4 Januari 2026&#10;- Libur KBM Sekolah&#10;- Asrama Boarding&#10;&#10;Senin, 5 Januari 2026&#10;1. KBM semester 2&#10;2. Apel Pagi"
                            ></textarea>
                        </label>

                        <div class="calendar-action-row">
                            <span class="calendar-muted">Pastikan ada baris tanggal, lalu daftar kegiatan di bawahnya.</span>
                            <button
                                type="button"
                                wire:click="importFromText"
                                wire:loading.attr="disabled"
                                class="calendar-button calendar-button-primary"
                            >
                                Import ke Kalender
                            </button>
                        </div>
                    </div>

                    <div class="agenda-side-card">
                        <label class="calendar-field">
                            Visibilitas hasil import
                            <select wire:model="importVisibility">
                                <option value="external">Publik</option>
                                <option value="internal">Internal</option>
                            </select>
                        </label>

                        <div class="calendar-note-card">
                            <p class="calendar-small-label">Format</p>
                            <ul class="calendar-format-list">
                                <li>Tanggal tunggal: `5 Januari 2026`</li>
                                <li>Rentang: `1-4 Januari 2026`</li>
                                <li>Kegiatan: `- Kegiatan` atau `1. Kegiatan`</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <details class="calendar-panel calendar-danger-panel">
            <summary class="calendar-details-summary">
                <div>
                    <p class="calendar-eyebrow">Alat Lanjutan</p>
                    <h2 class="calendar-title">Hapus Agenda Massal</h2>
                    <p class="calendar-description">Gunakan hanya bila perlu. Agenda pada periode terpilih akan dihapus permanen.</p>
                </div>
                <span class="calendar-danger-badge">Berisiko</span>
            </summary>

            <div class="calendar-panel-body">
                <div class="calendar-delete-grid" x-data="{ deleteMode: @entangle('deleteMode') }">
                    <label class="calendar-field">
                        Mode
                        <select x-model="deleteMode">
                            <option value="month">Per bulan</option>
                            <option value="year">Per tahun</option>
                        </select>
                    </label>

                    <label class="calendar-field">
                        Bulan
                        <select
                            wire:model="deleteMonth"
                            x-bind:disabled="deleteMode === 'year'"
                        >
                            <option value="1">Januari</option>
                            <option value="2">Februari</option>
                            <option value="3">Maret</option>
                            <option value="4">April</option>
                            <option value="5">Mei</option>
                            <option value="6">Juni</option>
                            <option value="7">Juli</option>
                            <option value="8">Agustus</option>
                            <option value="9">September</option>
                            <option value="10">Oktober</option>
                            <option value="11">November</option>
                            <option value="12">Desember</option>
                        </select>
                    </label>

                    <label class="calendar-field">
                        Tahun
                        <input
                            type="number"
                            min="2000"
                            max="2100"
                            wire:model="deleteYear"
                        />
                    </label>
                </div>

                <div class="calendar-action-row">
                    <span class="calendar-muted">Mode tahun akan menghapus agenda satu tahun penuh.</span>
                    <button
                        type="button"
                        wire:click="deleteSchedule"
                        wire:loading.attr="disabled"
                        x-on:click="if (!confirm('Hapus semua agenda sesuai pilihan ini?')) { $event.preventDefault(); $event.stopImmediatePropagation(); }"
                        class="calendar-button calendar-button-danger"
                    >
                        Hapus Agenda
                    </button>
                </div>
            </div>
        </details>
    </div>
</x-filament-panels::page>
