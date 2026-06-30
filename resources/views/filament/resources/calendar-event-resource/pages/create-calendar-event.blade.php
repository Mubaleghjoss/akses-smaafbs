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
    </style>

    <script src="{{ asset('vendor/fullcalendar/index.global.min.js') }}"></script>

    <div class="mx-auto w-full space-y-4">
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Agenda Sekolah</p>
                    <h2 class="mt-1 text-base font-semibold text-gray-900 dark:text-white">Kalender Agenda</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola agenda harian, rentang kegiatan, import teks WA, dan jadwal yang tampil di publik.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 font-semibold text-blue-700 dark:bg-blue-500/15 dark:text-blue-200">Publik</span>
                    <span class="inline-flex items-center rounded-full bg-amber-50 px-3 py-1 font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-200">Internal</span>
                </div>
            </div>
        </section>

        <div
            x-data="calendarScheduler(@js($events))"
            x-init="init()"
            wire:ignore
            class="space-y-4"
        >
            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-4 dark:border-white/10">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Input Cepat</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih preset tanggal atau klik tanggal langsung pada kalender.</p>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <button
                            type="button"
                            x-on:click="openPreset(0, 1)"
                            class="group flex min-h-28 w-full items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:bg-white hover:shadow-md dark:border-gray-800 dark:bg-gray-950/40 dark:hover:border-primary-500/50 dark:hover:bg-gray-900"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-sm font-bold text-primary-700 group-hover:bg-primary-600 group-hover:text-white dark:bg-primary-500/15 dark:text-primary-200">
                                H
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">Hari ini</span>
                                <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-gray-400">Buka modal untuk agenda tanggal hari ini.</span>
                            </span>
                        </button>

                        <button
                            type="button"
                            x-on:click="openPreset(1, 1)"
                            class="group flex min-h-28 w-full items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:bg-white hover:shadow-md dark:border-gray-800 dark:bg-gray-950/40 dark:hover:border-primary-500/50 dark:hover:bg-gray-900"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-sm font-bold text-primary-700 group-hover:bg-primary-600 group-hover:text-white dark:bg-primary-500/15 dark:text-primary-200">
                                B
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">Besok</span>
                                <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-gray-400">Siapkan kegiatan untuk tanggal besok.</span>
                            </span>
                        </button>

                        <button
                            type="button"
                            x-on:click="openWeekRange()"
                            class="group flex min-h-28 w-full items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:bg-white hover:shadow-md dark:border-gray-800 dark:bg-gray-950/40 dark:hover:border-primary-500/50 dark:hover:bg-gray-900"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-xs font-bold text-primary-700 group-hover:bg-primary-600 group-hover:text-white dark:bg-primary-500/15 dark:text-primary-200">
                                MG
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">Sisa minggu ini</span>
                                <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-gray-400">Buat agenda rentang hari sampai akhir minggu.</span>
                            </span>
                        </button>

                        <button
                            type="button"
                            x-on:click="openMonthRange()"
                            class="group flex min-h-28 w-full items-start gap-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:bg-white hover:shadow-md dark:border-gray-800 dark:bg-gray-950/40 dark:hover:border-primary-500/50 dark:hover:bg-gray-900"
                        >
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-xs font-bold text-primary-700 group-hover:bg-primary-600 group-hover:text-white dark:bg-primary-500/15 dark:text-primary-200">
                                BL
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-white">Sampai akhir bulan</span>
                                <span class="mt-1 block text-xs leading-5 text-gray-500 dark:text-gray-400">Buat agenda rentang hari sampai akhir bulan.</span>
                            </span>
                        </button>
                    </div>

                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Modal tambah agenda menerima beberapa baris sekaligus. Satu baris dibuat menjadi satu kegiatan.</p>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-4 dark:border-white/10">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">Kalender</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Klik agenda untuk edit. Tahan Shift lalu tarik tanggal untuk membuat rentang hari.</p>
                        </div>
                        <span class="text-xs text-gray-400">Warna event mengikuti visibilitas.</span>
                    </div>
                </div>

                <div class="p-4">
                    <div x-ref="calendar"></div>
                </div>
            </section>

            <div
                x-cloak
                x-show="isOpen"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-end justify-center p-3 sm:items-center sm:p-4"
                aria-modal="true"
                role="dialog"
            >
                <div class="absolute inset-0 bg-gray-950/60" x-on:click="close()"></div>

                <div class="relative w-full max-w-2xl rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-800 dark:bg-gray-900">
                    <div class="max-h-[calc(100vh-1.5rem)] overflow-y-auto p-4 sm:max-h-[calc(100vh-2rem)] sm:p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="mode === 'edit' ? 'Ubah Agenda' : 'Tambah Agenda'"></h3>
                                <p class="mt-1 text-sm text-gray-500" x-text="rangeLabel"></p>
                            </div>
                            <button type="button" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200" x-on:click="close()">
                                &times;
                            </button>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-950/40">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500">Mode</p>
                                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="mode === 'edit' ? 'Edit satu agenda' : 'Tambah cepat dari kalender'"></p>
                            </div>
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-950/40">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500">Tanggal</p>
                                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="rangeLabel || '-'"></p>
                            </div>
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-950/40">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500">Hasil</p>
                                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="mode === 'edit' ? '1 agenda diperbarui' : summaryLabel()"></p>
                            </div>
                        </div>

                        <form class="mt-5 space-y-5" x-on:submit.prevent="save()">
                            <div x-show="mode === 'create'">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Daftar kegiatan</label>
                                <textarea
                                    rows="5"
                                    class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                    x-model="form.title"
                                    placeholder="Contoh: Apel pagi&#10;KBM sekolah&#10;Rapat guru"
                                    x-on:keydown.ctrl.enter.prevent="save()"
                                    x-on:keydown.meta.enter.prevent="save()"
                                    required
                                ></textarea>
                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                                    <span>Satu baris = satu kegiatan. Gunakan `Ctrl/Cmd + Enter` untuk simpan cepat.</span>
                                    <span x-text="summaryLabel()"></span>
                                </div>
                            </div>

                            <div x-show="mode === 'edit'" x-cloak>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Nama kegiatan</label>
                                <input
                                    type="text"
                                    class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                    x-model="form.title"
                                    placeholder="Contoh: Apel pagi"
                                    x-on:keydown.ctrl.enter.prevent="save()"
                                    x-on:keydown.meta.enter.prevent="save()"
                                    required
                                />
                                <p class="mt-2 text-xs text-gray-500">Gunakan `Ctrl/Cmd + Enter` untuk simpan perubahan.</p>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                                <div>
                                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Keterangan (opsional)</label>
                                    <textarea
                                        rows="4"
                                        class="mt-1 w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                        x-model="form.description"
                                        placeholder="Catatan tambahan jika diperlukan"
                                    ></textarea>
                                </div>

                                <div class="space-y-4 rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950/40">
                                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                                        <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                                            Visibilitas
                                            <select
                                                x-model="form.visibility"
                                                class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                            >
                                                <option value="external">Publik</option>
                                                <option value="internal">Internal</option>
                                            </select>
                                        </label>

                                        <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                                            Tanggal mulai
                                            <input
                                                type="date"
                                                x-model="form.start"
                                                x-on:change="syncDateRange()"
                                                class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                                required
                                            />
                                        </label>

                                        <div class="grid gap-3">
                                            <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                                                <input
                                                    type="checkbox"
                                                    x-model="form.useEndDate"
                                                    x-on:change="toggleEndDate()"
                                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                />
                                                Pakai tanggal selesai
                                            </label>

                                            <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200" x-show="form.useEndDate" x-cloak>
                                                Tanggal selesai
                                                <input
                                                    type="date"
                                                    x-model="form.end"
                                                    x-bind:min="form.start || null"
                                                    x-on:change="syncDateRange()"
                                                    class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                                                />
                                            </label>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-dashed border-gray-300 bg-white px-3 py-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="font-medium text-gray-700 dark:text-gray-200">Ringkasan tanggal</span>
                                            <span x-text="form.useEndDate && form.end ? 'Rentang hari' : 'Satu hari'"></span>
                                        </div>
                                        <p class="mt-2" x-text="rangeLabel || 'Pilih tanggal terlebih dahulu.'"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-4 dark:border-gray-800">
                                <div class="text-xs text-gray-500">
                                    Klik event di kalender untuk edit cepat. Perubahan langsung memperbarui tampilan kalender.
                                </div>
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <button type="button" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800" x-on:click="close()">
                                        Batal
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50 dark:border-red-500/30 dark:text-red-200 dark:hover:bg-red-500/20"
                                        x-on:click="remove()"
                                        x-show="mode === 'edit'"
                                        x-cloak
                                    >
                                        Hapus
                                    </button>
                                    <button type="submit" class="rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500" x-text="submitLabel()">
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

        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900" x-data="{ sampleText: `Agenda Kegiatan Januari 2026\n1-4 Januari 2026\n- Libur KBM Sekolah\n- Asrama Boarding\n\nSenin, 5 Januari 2026\n1. KBM semester 2\n2. Apel Pagi` }">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Import Teks WA</h2>
                    <p class="text-sm text-gray-500">
                        Tempel teks agenda dari WhatsApp atau catatan rapat. Format dengan bullet `-` atau nomor `1.` sama-sama bisa dibaca.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        x-on:click="$wire.set('importText', sampleText)"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                    >
                        Isi contoh
                    </button>
                    <button
                        type="button"
                        x-on:click="$wire.set('importText', '')"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                    >
                        Kosongkan
                    </button>
                </div>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(260px,0.7fr)]">
                <div class="grid gap-3">
                    <textarea
                        wire:model.defer="importText"
                        rows="9"
                        class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                        placeholder="Agenda Kegiatan Januari 2026&#10;1-4 Januari 2026&#10;- Libur KBM Sekolah&#10;- Asrama Boarding&#10;&#10;Senin, 5 Januari 2026&#10;1. KBM semester 2&#10;2. Apel Pagi"
                    ></textarea>
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                        <span>Tip: Pastikan ada baris tanggal, lalu daftar kegiatan di bawahnya.</span>
                        <button
                            type="button"
                            wire:click="importFromText"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 disabled:opacity-60"
                        >
                            Import ke Kalender
                        </button>
                    </div>
                </div>

                <div class="space-y-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950/40">
                    <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                        Visibilitas hasil import
                        <select
                            wire:model="importVisibility"
                            class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                        >
                            <option value="external">Publik</option>
                            <option value="internal">Internal</option>
                        </select>
                    </label>

                    <div class="rounded-xl border border-dashed border-gray-300 bg-white px-3 py-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900">
                        <p class="font-semibold text-gray-700 dark:text-gray-200">Format yang didukung</p>
                        <ul class="mt-2 space-y-1">
                            <li>Tanggal tunggal: `5 Januari 2026`</li>
                            <li>Rentang: `1-4 Januari 2026`</li>
                            <li>Daftar kegiatan: `- Kegiatan` atau `1. Kegiatan`</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <details class="rounded-2xl border border-red-200 bg-white p-4 shadow-sm dark:border-red-500/30 dark:bg-gray-900">
            <summary class="flex cursor-pointer list-none items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Alat Lanjutan: Hapus Agenda Massal</h2>
                    <p class="text-sm text-gray-500">
                        Gunakan hanya bila perlu. Semua agenda pada periode terpilih akan dihapus permanen.
                    </p>
                </div>
                <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 dark:bg-red-500/15 dark:text-red-200">
                    Berisiko
                </span>
            </summary>

            <div class="mt-4 grid gap-3 sm:grid-cols-3" x-data="{ deleteMode: @entangle('deleteMode') }">
                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    Mode
                    <select
                        x-model="deleteMode"
                        class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    >
                        <option value="month">Per bulan</option>
                        <option value="year">Per tahun</option>
                    </select>
                </label>

                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    Bulan
                    <select
                        wire:model="deleteMonth"
                        x-bind:disabled="deleteMode === 'year'"
                        x-bind:class="deleteMode === 'year' ? 'opacity-60' : ''"
                        class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 disabled:cursor-not-allowed dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
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

                <label class="grid gap-1 text-sm text-gray-700 dark:text-gray-200">
                    Tahun
                    <input
                        type="number"
                        min="2000"
                        max="2100"
                        wire:model="deleteYear"
                        class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                    />
                </label>
            </div>

            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                <span>Tip: Gunakan mode tahun untuk hapus agenda setahun penuh.</span>
                <button
                    type="button"
                    wire:click="deleteSchedule"
                    wire:loading.attr="disabled"
                    x-on:click="if (!confirm('Hapus semua agenda sesuai pilihan ini?')) { $event.preventDefault(); $event.stopImmediatePropagation(); }"
                    class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-red-500 disabled:opacity-60"
                >
                    Hapus Agenda
                </button>
            </div>
        </details>
</x-filament-panels::page>
