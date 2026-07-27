@php
    $calendarId = $calendarId ?? 'agenda-calendar';
    $modalId = $modalId ?? 'agenda-modal';
    $titleId = $titleId ?? 'agenda-modal-title';
    $dateId = $dateId ?? 'agenda-modal-date';
    $descId = $descId ?? 'agenda-modal-desc';
    $closeId = $closeId ?? 'agenda-modal-close';
@endphp

<div
    class="w-full overflow-x-auto"
    id="{{ $calendarId }}"
    data-public-agenda-calendar
    data-modal-id="{{ $modalId }}"
    data-title-id="{{ $titleId }}"
    data-date-id="{{ $dateId }}"
    data-desc-id="{{ $descId }}"
    data-close-id="{{ $closeId }}"
></div>

<div id="{{ $modalId }}" class="fixed inset-0 z-50 hidden items-center justify-center p-4" aria-hidden="true" role="dialog">
    <div class="absolute inset-0 bg-slate-900/60"></div>
    <div class="relative w-full max-w-lg max-h-[85vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h3 id="{{ $titleId }}" class="text-lg font-semibold text-slate-900">Rincian agenda</h3>
                <p id="{{ $dateId }}" class="mt-1 text-sm text-slate-500"></p>
            </div>
            <button id="{{ $closeId }}" type="button" class="text-slate-400 hover:text-slate-700">&times;</button>
        </div>
        <div id="{{ $descId }}" class="mt-4 whitespace-pre-line text-sm text-slate-600"></div>
    </div>
</div>

@once
    @push('scripts')
        <style>
            .fc {
                --fc-border-color: rgba(148, 163, 184, 0.3);
                --fc-page-bg-color: transparent;
                --fc-today-bg-color: rgba(59, 130, 246, 0.12);
            }
            .fc .fc-scrollgrid {
                border-radius: 16px;
                overflow: hidden;
                background: #fff;
            }
            .fc .fc-col-header-cell {
                font-weight: 600;
                text-transform: uppercase;
                font-size: 0.72rem;
                letter-spacing: 0.08em;
                color: #475569;
                background: rgba(148, 163, 184, 0.12);
            }
            .fc .fc-daygrid-day-frame {
                min-height: 110px;
            }
            .fc .fc-daygrid-body,
            .fc .fc-daygrid-body table,
            .fc .fc-scrollgrid-sync-table,
            .fc .fc-scrollgrid-section-header table,
            .fc .fc-scrollgrid-section-body table {
                width: 100% !important;
                table-layout: fixed;
            }
            .fc .fc-toolbar {
                flex-wrap: wrap;
                gap: 8px;
            }
            .fc .fc-button {
                border-radius: 999px;
                border: 1px solid #e2e8f0;
                background: #fff;
                color: #0f172a;
            }
            .fc .fc-button-primary:not(:disabled).fc-button-active,
            .fc .fc-button-primary:not(:disabled):active {
                background: #0f172a;
                border-color: #0f172a;
            }
            .fc .fc-daygrid-day-number {
                font-weight: 600;
                color: #0f172a;
            }
            @media (max-width: 640px) {
                .fc .fc-daygrid-day-frame {
                    min-height: 88px;
                }
            }
        </style>
        <script>
            const initializePublicAgendaCalendars = () => {
                document.querySelectorAll('[data-public-agenda-calendar]').forEach((calendarEl) => {
                    if (!window.FullCalendar || calendarEl.dataset.calendarReady === 'true') {
                        return;
                    }

                    calendarEl.dataset.calendarReady = 'true';

                    const modalId = calendarEl.dataset.modalId;
                    const titleId = calendarEl.dataset.titleId;
                    const dateId = calendarEl.dataset.dateId;
                    const descId = calendarEl.dataset.descId;
                    const closeId = calendarEl.dataset.closeId;
                    const modalEl = document.getElementById(modalId);
                    const modalTitle = document.getElementById(titleId);
                    const modalDate = document.getElementById(dateId);
                    const modalDesc = document.getElementById(descId);
                    const modalClose = document.getElementById(closeId);

                    const formatter = new Intl.DateTimeFormat('id-ID', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                    });

                    const openModal = (eventApi) => {
                        if (!modalEl || !modalTitle || !modalDate || !modalDesc) {
                            return;
                        }

                        const startDate = eventApi.start ? new Date(eventApi.start) : null;
                        let endDate = eventApi.end ? new Date(eventApi.end) : null;

                        if (eventApi.allDay && endDate) {
                            endDate.setDate(endDate.getDate() - 1);
                        }

                        modalTitle.textContent = eventApi.title || 'Rincian agenda';

                        if (startDate) {
                            const startLabel = formatter.format(startDate);
                            const endLabel = endDate ? formatter.format(endDate) : '';
                            modalDate.textContent = endLabel && endLabel !== startLabel ? `${startLabel} - ${endLabel}` : startLabel;
                        } else {
                            modalDate.textContent = '';
                        }

                        modalDesc.textContent = eventApi.extendedProps?.description || 'Keterangan kegiatan belum ditambahkan.';
                        modalEl.classList.remove('hidden');
                        modalEl.classList.add('flex');
                        modalEl.setAttribute('aria-hidden', 'false');
                    };

                    const closeModal = () => {
                        if (!modalEl) {
                            return;
                        }

                        modalEl.classList.add('hidden');
                        modalEl.classList.remove('flex');
                        modalEl.setAttribute('aria-hidden', 'true');
                    };

                    modalClose?.addEventListener('click', closeModal);

                    modalEl?.addEventListener('click', (event) => {
                        if (event.target === modalEl || event.target.classList.contains('bg-slate-900/60')) {
                            closeModal();
                        }
                    });

                    document.addEventListener('keydown', (event) => {
                        if (event.key === 'Escape') {
                            closeModal();
                        }
                    });

                    const calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'dayGridMonth',
                        height: 'auto',
                        locale: 'id',
                        firstDay: 1,
                        fixedWeekCount: false,
                        dayMaxEvents: true,
                        eventClick: (info) => {
                            info.jsEvent?.preventDefault();
                            openModal(info.event);
                        },
                        events: {
                            url: '{{ route('agenda.events') }}',
                            method: 'GET',
                            failure: () => {
                                calendarEl.innerHTML = '<div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">Agenda kegiatan tidak dapat dimuat saat ini.</div>';
                            },
                        },
                    });

                    calendar.render();
                });
            };

            const loadPublicAgendaCalendarAsset = () => {
                if (window.FullCalendar) {
                    initializePublicAgendaCalendars();

                    return;
                }

                if (document.querySelector('script[data-public-agenda-asset]')) {
                    return;
                }

                const script = document.createElement('script');
                script.src = @js(asset('vendor/fullcalendar/index.global.min.js'));
                script.async = true;
                script.dataset.publicAgendaAsset = '1';
                script.addEventListener('load', initializePublicAgendaCalendars, { once: true });
                document.head.appendChild(script);
            };

            document.addEventListener('DOMContentLoaded', () => {
                const calendars = document.querySelectorAll('[data-public-agenda-calendar]');

                if (!calendars.length) {
                    return;
                }

                if (!('IntersectionObserver' in window)) {
                    loadPublicAgendaCalendarAsset();

                    return;
                }

                const observer = new IntersectionObserver((entries) => {
                    if (!entries.some((entry) => entry.isIntersecting)) {
                        return;
                    }

                    observer.disconnect();
                    loadPublicAgendaCalendarAsset();
                }, { rootMargin: '400px 0px' });

                calendars.forEach((calendar) => observer.observe(calendar));
            }, { once: true });
        </script>
    @endpush
@endonce
