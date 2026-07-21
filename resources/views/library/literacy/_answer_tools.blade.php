<script>
    (() => {
        const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(value);
        const forms = document.querySelectorAll('[data-literacy-answer-form]');
        const submitScrollStorageKey = 'literacy.answer.submitScrollTarget';

        const storedSubmitScrollTarget = () => {
            try {
                return window.sessionStorage.getItem(submitScrollStorageKey) || '';
            } catch (error) {
                return '';
            }
        };

        const clearSubmitScrollTarget = () => {
            try {
                window.sessionStorage.removeItem(submitScrollStorageKey);
            } catch (error) {
                // Storage can be unavailable in strict private modes.
            }
        };

        const rememberSubmitScrollTarget = (selector) => {
            try {
                window.sessionStorage.setItem(submitScrollStorageKey, selector);
            } catch (error) {
                // Storage can be unavailable in strict private modes.
            }
        };

        const restoreSubmitScrollTarget = () => {
            const selector = storedSubmitScrollTarget()
                || (window.location.hash === '#status-jawaban' ? '#status-jawaban' : '');

            if (selector === '') {
                return;
            }

            clearSubmitScrollTarget();

            const target = document.querySelector(selector);

            if (!target) {
                return;
            }

            window.setTimeout(() => {
                target.scrollIntoView({
                    block: 'center',
                    behavior: 'auto',
                });

                target.focus?.({ preventScroll: true });
            }, 80);
        };

        restoreSubmitScrollTarget();

        forms.forEach((form) => {
            if (form.dataset.literacyAnswerReady === '1') {
                return;
            }

            form.dataset.literacyAnswerReady = '1';
            form.addEventListener('submit', () => {
                rememberSubmitScrollTarget(form.dataset.literacyScrollTarget || '[data-literacy-submit-status]');
            });

            if (form.dataset.literacyQueueEnabled === '1' && form.dataset.literacyTicketEndpoint) {
                const ticketInput = form.querySelector('[data-literacy-ticket]');
                const requestIdInput = form.querySelector('[data-literacy-request-id]');
                const studentIdInput = form.querySelector('[data-student-id]');
                const panel = form.querySelector('[data-literacy-queue-panel]');
                const title = form.querySelector('[data-literacy-queue-title]');
                const message = form.querySelector('[data-literacy-queue-message]');
                const cancelButton = form.querySelector('[data-literacy-queue-cancel]');
                const submitButton = form.querySelector('[data-literacy-submit-button]');
                let queueRunning = false;
                let cancelled = false;
                let cancelUrl = '';

                const wait = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));
                const csrfToken = () => form.querySelector('input[name="_token"]')?.value || '';
                const waitLabel = (seconds) => {
                    const safeSeconds = Math.max(1, Number.parseInt(seconds || '1', 10));

                    if (safeSeconds < 60) {
                        return `${safeSeconds} detik`;
                    }

                    const minutes = Math.floor(safeSeconds / 60);
                    const remainder = safeSeconds % 60;

                    return remainder > 0 ? `${minutes} menit ${remainder} detik` : `${minutes} menit`;
                };

                const showQueue = (heading, detail) => {
                    panel?.classList.remove('hidden');

                    if (title) {
                        title.textContent = heading;
                    }

                    if (message) {
                        message.textContent = detail;
                    }
                };

                const resetQueue = () => {
                    queueRunning = false;
                    cancelled = false;
                    cancelUrl = '';
                    form.dataset.literacyQueueAdmitted = '0';

                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                };

                const requestJson = async (url, options = {}) => {
                    const response = await fetch(url, {
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            ...(options.headers || {}),
                        },
                        ...options,
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const validationMessage = Object.values(payload.errors || {}).flat()[0];
                        throw new Error(validationMessage || payload.message || 'Antrean belum dapat dihubungi.');
                    }

                    return payload;
                };

                const renderQueuePayload = (payload) => {
                    if (ticketInput && payload.ticket) {
                        ticketInput.value = payload.ticket;
                    }

                    cancelUrl = payload.cancel_url || cancelUrl;

                    if (payload.status === 'waiting') {
                        showQueue(
                            `Harap tunggu - antrean ke-${Math.max(1, payload.position || 1)}`,
                            `Perkiraan ${waitLabel(payload.estimated_wait_seconds)}. Jawaban tetap tersimpan di halaman ini.`
                        );

                        return false;
                    }

                    if (['admitted', 'processing', 'completed'].includes(payload.status)) {
                        showQueue('Giliran Anda sudah tersedia', 'Jawaban sedang dikirim. Mohon jangan menutup halaman.');

                        return true;
                    }

                    throw new Error('Tiket antrean berakhir. Tekan tombol Kirim untuk mengambil antrean baru.');
                };

                const runQueue = async () => {
                    queueRunning = true;
                    cancelled = false;

                    if (submitButton) {
                        submitButton.disabled = true;
                    }

                    showQueue('Menyiapkan antrean pengiriman...', 'Jawaban tetap tersimpan di halaman ini. Jangan tutup halaman.');
                    panel?.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    try {
                        await wait(Math.floor(Math.random() * 1500));

                        if (cancelled) {
                            return;
                        }

                        const body = new FormData();
                        body.append('_token', csrfToken());
                        body.append('submission_request_id', requestIdInput?.value || '');

                        if (studentIdInput) {
                            body.append('student_id', studentIdInput.value || '');
                        }

                        let payload = await requestJson(form.dataset.literacyTicketEndpoint, {
                            method: 'POST',
                            body,
                        });

                        while (!cancelled && !renderQueuePayload(payload)) {
                            await wait(Math.max(2, payload.retry_after_seconds || 5) * 1000);

                            if (cancelled) {
                                return;
                            }

                            payload = await requestJson(payload.status_url, { method: 'GET' });
                        }

                        if (cancelled) {
                            return;
                        }

                        form.dataset.literacyQueueAdmitted = '1';
                        queueRunning = false;

                        if (submitButton) {
                            submitButton.disabled = false;
                        }

                        form.requestSubmit(submitButton || undefined);
                    } catch (error) {
                        showQueue('Pengiriman belum dapat dilanjutkan', error?.message || 'Antrean belum dapat dihubungi. Silakan coba lagi.');
                        resetQueue();
                    }
                };

                form.addEventListener('submit', (event) => {
                    if (form.dataset.literacyQueueAdmitted === '1') {
                        return;
                    }

                    event.preventDefault();

                    if (queueRunning || !form.reportValidity()) {
                        return;
                    }

                    runQueue();
                });

                cancelButton?.addEventListener('click', async () => {
                    cancelled = true;

                    if (cancelUrl) {
                        requestJson(cancelUrl, { method: 'DELETE' }).catch(() => {});
                    }

                    if (ticketInput) {
                        ticketInput.value = '';
                    }

                    panel?.classList.add('hidden');
                    resetQueue();
                });
            }

            form.querySelectorAll('[data-literacy-answer-input]').forEach((textarea) => {
                const wrapper = textarea.closest('section') || form;
                const min = Number.parseInt(textarea.dataset.minCharacters || '0', 10);
                const max = Math.max(1, Number.parseInt(textarea.dataset.maxCharacters || '1', 10));
                const counter = wrapper.querySelector('[data-literacy-answer-count]');
                const status = wrapper.querySelector('[data-literacy-answer-status]');
                const bar = wrapper.querySelector('[data-literacy-answer-bar]');

                const setTone = (tone) => {
                    status?.classList.remove('text-slate-500', 'text-amber-600', 'text-emerald-600', 'text-rose-600');
                    counter?.classList.remove('text-slate-700', 'text-amber-700', 'text-emerald-700', 'text-rose-700');
                    bar?.classList.remove('bg-slate-400', 'bg-amber-500', 'bg-emerald-500', 'bg-rose-500');

                    const statusClass = {
                        neutral: 'text-slate-500',
                        warning: 'text-amber-600',
                        success: 'text-emerald-600',
                        danger: 'text-rose-600',
                    }[tone] || 'text-slate-500';
                    const counterClass = {
                        neutral: 'text-slate-700',
                        warning: 'text-amber-700',
                        success: 'text-emerald-700',
                        danger: 'text-rose-700',
                    }[tone] || 'text-slate-700';
                    const barClass = {
                        neutral: 'bg-slate-400',
                        warning: 'bg-amber-500',
                        success: 'bg-emerald-500',
                        danger: 'bg-rose-500',
                    }[tone] || 'bg-slate-400';

                    status?.classList.add(statusClass);
                    counter?.classList.add(counterClass);
                    bar?.classList.add(barClass);
                };

                const refresh = () => {
                    const length = Array.from(textarea.value || '').length;
                    const remainingMin = Math.max(0, min - length);
                    const remainingMax = max - length;
                    const percent = Math.min(100, Math.max(0, (length / max) * 100));
                    const nearLimit = remainingMax <= Math.max(20, Math.ceil(max * 0.1));

                    if (counter) {
                        counter.textContent = `${formatNumber(length)}/${formatNumber(max)} karakter`;
                    }

                    if (bar) {
                        bar.style.width = `${percent}%`;
                    }

                    if (length > max) {
                        setTone('danger');
                        if (status) {
                            status.textContent = `Melebihi ${formatNumber(length - max)} karakter dari batas maksimal.`;
                        }

                        return;
                    }

                    if (remainingMin > 0) {
                        setTone('warning');
                        if (status) {
                            status.textContent = `Kurang ${formatNumber(remainingMin)} karakter dari minimal ${formatNumber(min)}.`;
                        }

                        return;
                    }

                    if (nearLimit) {
                        setTone('warning');
                        if (status) {
                            status.textContent = `Sudah memenuhi minimal. Sisa ${formatNumber(Math.max(0, remainingMax))} karakter.`;
                        }

                        return;
                    }

                    setTone('success');
                    if (status) {
                        status.textContent = `Sudah memenuhi minimal. Sisa ${formatNumber(remainingMax)} karakter.`;
                    }
                };

                textarea.addEventListener('input', refresh);
                refresh();
            });

            const integrityFields = {
                tab_switch_count: form.querySelector('[data-integrity-field="tab_switch_count"]'),
                app_hidden_count: form.querySelector('[data-integrity-field="app_hidden_count"]'),
                page_leave_attempt_count: form.querySelector('[data-integrity-field="page_leave_attempt_count"]'),
            };

            if (Object.values(integrityFields).some(Boolean) && form.dataset.literacyIntegrityReady !== '1') {
                form.dataset.literacyIntegrityReady = '1';

                const counts = {
                    tab_switch_count: 0,
                    app_hidden_count: 0,
                    page_leave_attempt_count: 0,
                };
                let submitting = false;
                let beaconSent = false;
                let leavingPage = false;
                let pendingPopupMessage = '';
                const pendingTimers = new Set();

                const clearPendingIntegrityTimers = () => {
                    pendingTimers.forEach((timer) => window.clearTimeout(timer));
                    pendingTimers.clear();
                };

                const createIntegrityPopup = () => {
                    let popup = document.querySelector('[data-literacy-integrity-popup]');

                    if (popup) {
                        return popup;
                    }

                    popup = document.createElement('div');
                    popup.setAttribute('data-literacy-integrity-popup', '1');
                    popup.setAttribute('role', 'alertdialog');
                    popup.setAttribute('aria-modal', 'true');
                    popup.style.cssText = [
                        'position:fixed',
                        'inset:0',
                        'z-index:9999',
                        'display:none',
                        'align-items:center',
                        'justify-content:center',
                        'padding:1rem',
                        'background:rgba(15,23,42,.48)',
                    ].join(';');

                    popup.innerHTML = `
                        <div style="max-width:28rem;border-radius:1rem;background:#fff;padding:1.25rem;box-shadow:0 24px 70px rgba(15,23,42,.28);color:#0f172a">
                            <div style="font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em;color:#dc2626">Peringatan Integritas</div>
                            <div data-literacy-integrity-popup-message style="margin-top:.55rem;font-size:1rem;font-weight:800;line-height:1.45"></div>
                            <p style="margin-top:.65rem;font-size:.9rem;line-height:1.6;color:#475569">Tetap kerjakan soal secara mandiri. Aktivitas keluar tab atau aplikasi akan tercatat pada laporan guru/admin.</p>
                            <button type="button" data-literacy-integrity-popup-close style="margin-top:1rem;min-height:2.5rem;border:0;border-radius:999px;background:#0f172a;padding:.65rem 1rem;color:#fff;font-weight:800;cursor:pointer">Saya Mengerti</button>
                        </div>
                    `;

                    popup.querySelector('[data-literacy-integrity-popup-close]')?.addEventListener('click', () => {
                        popup.style.display = 'none';
                    });

                    document.body.appendChild(popup);

                    return popup;
                };

                const showIntegrityPopup = (message) => {
                    pendingPopupMessage = message;

                    if (document.visibilityState === 'hidden') {
                        return;
                    }

                    const popup = createIntegrityPopup();
                    const messageElement = popup.querySelector('[data-literacy-integrity-popup-message]');

                    if (messageElement) {
                        messageElement.textContent = message;
                    }

                    popup.style.display = 'flex';
                    pendingPopupMessage = '';
                };

                const scheduleIntegrityBump = (key, message) => {
                    const timer = window.setTimeout(() => {
                        pendingTimers.delete(timer);

                        if (submitting || leavingPage) {
                            return;
                        }

                        bumpIntegrity(key);
                        showIntegrityPopup(message);
                    }, 800);

                    pendingTimers.add(timer);
                };

                const syncIntegrityFields = () => {
                    Object.entries(integrityFields).forEach(([key, input]) => {
                        if (input) {
                            input.value = String(counts[key] || 0);
                        }
                    });
                };

                const bumpIntegrity = (key) => {
                    counts[key] = (counts[key] || 0) + 1;
                    syncIntegrityFields();
                };

                const submitIntegrityBeacon = () => {
                    const endpoint = form.dataset.integrityEndpoint || '';

                    if (!endpoint || submitting || beaconSent) {
                        return;
                    }

                    syncIntegrityFields();
                    beaconSent = true;

                    const payload = new FormData();
                    const token = form.querySelector('input[name="_token"]')?.value || '';

                    if (token) {
                        payload.append('_token', token);
                    }

                    Object.entries(counts).forEach(([key, value]) => {
                        payload.append(`integrity[${key}]`, String(value || 0));
                    });

                    if (navigator.sendBeacon) {
                        navigator.sendBeacon(endpoint, payload);

                        return;
                    }

                    fetch(endpoint, {
                        method: 'POST',
                        body: payload,
                        credentials: 'same-origin',
                        keepalive: true,
                    }).catch(() => {});
                };

                form.addEventListener('submit', (event) => {
                    if (event.defaultPrevented) {
                        return;
                    }

                    submitting = true;
                    leavingPage = true;
                    clearPendingIntegrityTimers();
                    syncIntegrityFields();
                });

                document.addEventListener('click', (event) => {
                    const link = event.target.closest?.('a[href]');

                    if (!link) {
                        return;
                    }

                    const href = link.getAttribute('href') || '';

                    if (href.startsWith('#') || link.target === '_blank' || link.hasAttribute('download')) {
                        return;
                    }

                    try {
                        const destination = new URL(link.href, window.location.href);

                        if (destination.origin === window.location.origin) {
                            leavingPage = true;
                            clearPendingIntegrityTimers();
                        }
                    } catch (error) {
                        // Invalid hrefs are ignored; the browser will handle them.
                    }
                }, true);

                window.addEventListener('blur', () => {
                    if (!submitting && !leavingPage) {
                        scheduleIntegrityBump(
                            'tab_switch_count',
                            'Terdeteksi pindah tab atau fokus keluar dari halaman pengerjaan.'
                        );
                    }
                });

                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible' && pendingPopupMessage !== '') {
                        showIntegrityPopup(pendingPopupMessage);

                        return;
                    }

                    if (!submitting && !leavingPage && document.visibilityState === 'hidden') {
                        scheduleIntegrityBump(
                            'app_hidden_count',
                            'Terdeteksi keluar aplikasi atau menyembunyikan halaman pengerjaan.'
                        );
                    }
                });

                window.addEventListener('beforeunload', () => {
                    leavingPage = true;
                    clearPendingIntegrityTimers();
                });

                window.addEventListener('pagehide', () => {
                    leavingPage = true;
                    clearPendingIntegrityTimers();
                    submitIntegrityBeacon();
                });
                syncIntegrityFields();
            }
        });

        const combobox = document.querySelector('[data-literacy-student-combobox]');

        if (!combobox || combobox.dataset.ready === '1') {
            return;
        }

        combobox.dataset.ready = '1';

        const students = @json($students ?? []);
        const input = combobox.querySelector('[data-student-search]');
        const hiddenInput = combobox.querySelector('[data-student-id]');
        const results = combobox.querySelector('[data-student-results]');
        const selectedNotice = document.querySelector('[data-student-selected]');
        const verificationInput = document.querySelector('[data-student-verification]');
        const verificationHelp = document.querySelector('[data-student-verification-help]');
        let activeIndex = -1;
        let currentMatches = [];

        const normalize = (value) => String(value || '').toLocaleLowerCase('id-ID').trim();
        const studentText = (student) => normalize(`${student.label || ''} ${student.name || ''} ${student.class || ''}`);

        const closeResults = () => {
            results.classList.add('hidden');
            input.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
        };

        const updateSelectedNotice = (student) => {
            if (!selectedNotice) {
                return;
            }

            if (!student) {
                selectedNotice.textContent = '';
                selectedNotice.classList.add('hidden');

                return;
            }

            selectedNotice.textContent = `Terpilih: ${student.label}`;
            selectedNotice.classList.remove('hidden');
        };

        const updateVerificationRequirement = (student) => {
            if (!verificationInput) {
                return;
            }

            const isRequired = Boolean(student?.verification_required);
            verificationInput.required = isRequired;

            if (verificationHelp) {
                verificationHelp.textContent = isRequired
                    ? 'Wajib isi NISN atau tanggal lahir siswa yang dipilih. Format tanggal bisa DD/MM/YYYY atau YYYY-MM-DD.'
                    : 'Data NISN/tanggal lahir siswa ini belum lengkap di master. Admin tetap bisa mengecek history jika terjadi salah input.';
            }
        };

        const selectStudent = (student) => {
            hiddenInput.value = String(student.id);
            input.value = student.label;
            input.setCustomValidity('');
            updateSelectedNotice(student);
            updateVerificationRequirement(student);
            closeResults();
        };

        const setActiveOption = (index) => {
            activeIndex = index;

            results.querySelectorAll('[data-student-option]').forEach((option, optionIndex) => {
                option.classList.toggle('bg-slate-100', optionIndex === activeIndex);
                option.setAttribute('aria-selected', optionIndex === activeIndex ? 'true' : 'false');
            });
        };

        const renderResults = () => {
            const query = normalize(input.value);
            currentMatches = (query === ''
                ? students
                : students.filter((student) => studentText(student).includes(query)))
                .slice(0, 30);

            results.replaceChildren();

            if (currentMatches.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'px-3 py-3 text-sm text-slate-500';
                empty.textContent = 'Siswa tidak ditemukan.';
                results.appendChild(empty);
                results.classList.remove('hidden');
                input.setAttribute('aria-expanded', 'true');

                return;
            }

            currentMatches.forEach((student, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'block w-full rounded-xl px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 focus:bg-slate-100 focus:outline-none';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.dataset.studentOption = String(index);

                const name = document.createElement('span');
                name.className = 'block font-semibold text-slate-900';
                name.textContent = student.name || student.label;
                option.appendChild(name);

                if (student.class) {
                    const studentClass = document.createElement('span');
                    studentClass.className = 'mt-0.5 block text-xs text-slate-500';
                    studentClass.textContent = student.class;
                    option.appendChild(studentClass);
                }

                option.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    selectStudent(student);
                });
                option.addEventListener('mouseenter', () => setActiveOption(index));

                results.appendChild(option);
            });

            results.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
            setActiveOption(-1);
        };

        const openResults = () => {
            renderResults();
        };

        input.addEventListener('focus', openResults);
        input.addEventListener('input', () => {
            hiddenInput.value = '';
            input.setCustomValidity('');
            updateSelectedNotice(null);
            updateVerificationRequirement(null);
            renderResults();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (results.classList.contains('hidden')) {
                    renderResults();
                }
                setActiveOption(Math.min(currentMatches.length - 1, activeIndex + 1));

                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActiveOption(Math.max(0, activeIndex - 1));

                return;
            }

            if (event.key === 'Enter' && activeIndex >= 0 && currentMatches[activeIndex]) {
                event.preventDefault();
                selectStudent(currentMatches[activeIndex]);

                return;
            }

            if (event.key === 'Escape') {
                closeResults();
            }
        });
        input.form?.addEventListener('submit', (event) => {
            if (hiddenInput.value !== '') {
                return;
            }

            const typed = normalize(input.value);
            const exactMatch = students.find((student) => normalize(student.label) === typed);

            if (exactMatch) {
                selectStudent(exactMatch);

                return;
            }

            input.setCustomValidity('Pilih siswa dari daftar yang muncul.');
            input.reportValidity();
            clearSubmitScrollTarget();
            event.preventDefault();
        });
        updateVerificationRequirement(students.find((student) => String(student.id) === hiddenInput.value) || null);
        document.addEventListener('click', (event) => {
            if (!combobox.contains(event.target)) {
                closeResults();
            }
        });

        const initialStudent = students.find((student) => String(student.id) === String(hiddenInput.value || ''));
        updateSelectedNotice(initialStudent || null);
    })();
</script>
