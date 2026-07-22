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
                const massModeEnabled = form.dataset.literacyMassMode === '1';
                const initialJitterSeconds = Math.max(0, Number.parseInt(
                    massModeEnabled
                        ? (form.dataset.literacyInitialJitterSeconds || '30')
                        : (form.dataset.literacyNormalJitterSeconds || '2'),
                    10
                ));
                const retryDelays = (form.dataset.literacyRetryDelays || '5,10,20,30')
                    .split(',')
                    .map((seconds) => Number.parseInt(seconds, 10))
                    .filter((seconds) => Number.isFinite(seconds) && seconds > 0);
                const retryWindowMs = Math.max(60, Number.parseInt(form.dataset.literacyRetryWindowSeconds || '600', 10)) * 1000;
                const draftTtlMs = Math.max(1, Number.parseInt(form.dataset.literacyDraftTtlHours || '12', 10)) * 60 * 60 * 1000;
                const draftStorageKey = `literacy.submission.draft.v2:${form.dataset.literacyDraftKey || window.location.pathname}`;
                let queueRunning = false;
                let cancelled = false;
                let cancelUrl = '';
                let statusUrl = '';
                let queueSnapshot = null;
                let retryStartedAt = 0;
                let persistTimer = null;
                let storageAvailable = true;
                let finalSubmissionRunning = false;

                const wait = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));
                const csrfToken = () => form.querySelector('input[name="_token"]')?.value || '';
                const draftSafetyText = () => storageAvailable
                    ? 'Jawaban tersimpan sebagai draf di tab ini.'
                    : 'Jawaban tetap tersimpan selama halaman ini tidak ditutup.';
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

                const stopQueue = () => {
                    queueRunning = false;
                    form.dataset.literacyQueueAdmitted = '0';

                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                };

                const draftFields = () => Array.from(form.querySelectorAll([
                    '[data-literacy-answer-input]',
                    '[data-student-search]',
                    '[data-student-id]',
                    '[data-student-verification]',
                ].join(',')));

                const removeDraft = () => {
                    try {
                        window.sessionStorage.removeItem(draftStorageKey);
                    } catch (error) {
                        storageAvailable = false;
                    }
                };

                const persistDraft = () => {
                    const fields = {};

                    draftFields().forEach((field) => {
                        if (field.name) {
                            fields[field.name] = field.value;
                        }
                    });

                    try {
                        window.sessionStorage.setItem(draftStorageKey, JSON.stringify({
                            saved_at: Date.now(),
                            request_id: requestIdInput?.value || '',
                            ticket: ticketInput?.value || '',
                            fields,
                            queue: queueSnapshot,
                        }));
                        storageAvailable = true;
                    } catch (error) {
                        storageAvailable = false;
                    }
                };

                const scheduleDraftPersistence = () => {
                    if (persistTimer !== null) {
                        window.clearTimeout(persistTimer);
                    }

                    persistTimer = window.setTimeout(() => {
                        persistTimer = null;
                        persistDraft();
                    }, 250);
                };

                const restoreDraft = () => {
                    if (form.dataset.literacySubmissionSuccess === '1') {
                        removeDraft();

                        return false;
                    }

                    try {
                        const rawDraft = window.sessionStorage.getItem(draftStorageKey);

                        if (!rawDraft) {
                            return false;
                        }

                        const draft = JSON.parse(rawDraft);

                        if (!draft?.saved_at || Date.now() - draft.saved_at > draftTtlMs) {
                            removeDraft();

                            return false;
                        }

                        draftFields().forEach((field) => {
                            if (field.name && Object.hasOwn(draft.fields || {}, field.name)) {
                                field.value = String(draft.fields[field.name] ?? '');
                            }
                        });

                        if (requestIdInput && draft.request_id) {
                            requestIdInput.value = draft.request_id;
                        }

                        if (ticketInput && draft.ticket) {
                            ticketInput.value = draft.ticket;
                        }

                        queueSnapshot = draft.queue || null;
                        cancelUrl = queueSnapshot?.cancel_url || '';
                        statusUrl = queueSnapshot?.status_url || '';

                        return true;
                    } catch (error) {
                        removeDraft();

                        return false;
                    }
                };

                const clearTicketState = () => {
                    queueSnapshot = null;
                    cancelUrl = '';
                    statusUrl = '';

                    if (ticketInput) {
                        ticketInput.value = '';
                    }

                    persistDraft();
                };

                const countdownWait = async (seconds, onTick = null) => {
                    for (let remaining = Math.max(0, Math.ceil(seconds)); remaining > 0; remaining -= 1) {
                        if (cancelled) {
                            return;
                        }

                        onTick?.(remaining);
                        await wait(1000);
                    }
                };

                const requestJson = async (url, options = {}) => {
                    try {
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
                            const error = new Error(validationMessage || payload.message || 'Antrean belum dapat dihubungi.');
                            error.status = response.status;
                            error.retryAfter = Number.parseInt(response.headers.get('Retry-After') || '0', 10);
                            error.retryable = [408, 425, 429, 500, 502, 503, 504].includes(response.status);
                            throw error;
                        }

                        return payload;
                    } catch (error) {
                        if (typeof error?.retryable === 'boolean') {
                            throw error;
                        }

                        const networkError = new Error('Koneksi ke antrean terputus sementara.');
                        networkError.status = 0;
                        networkError.retryAfter = 0;
                        networkError.retryable = true;
                        throw networkError;
                    }
                };

                const requestWithRetry = async (url, options = {}) => {
                    let failureCount = 0;

                    while (!cancelled) {
                        try {
                            return await requestJson(url, options);
                        } catch (error) {
                            if (!error?.retryable) {
                                throw error;
                            }

                            if (Date.now() - retryStartedAt >= retryWindowMs) {
                                const exhaustedError = new Error(`Belum berhasil terhubung setelah ${waitLabel(Math.ceil(retryWindowMs / 1000))}. ${draftSafetyText()}`);
                                exhaustedError.exhausted = true;
                                throw exhaustedError;
                            }

                            const configuredDelay = retryDelays[Math.min(failureCount, Math.max(0, retryDelays.length - 1))] || 30;
                            const baseDelay = Math.max(2, error.retryAfter || configuredDelay);
                            const delaySeconds = Math.ceil(baseDelay * (1 + (Math.random() * 0.3)));
                            failureCount += 1;

                            await countdownWait(delaySeconds, (remaining) => {
                                showQueue(
                                    'Server sedang ramai - tidak perlu menekan Kirim lagi',
                                    `Percobaan otomatis ke-${failureCount + 1} dilakukan dalam ${remaining} detik. ${draftSafetyText()}`
                                );
                            });
                        }
                    }

                    return null;
                };

                const renderQueuePayload = (payload) => {
                    if (ticketInput && payload.ticket) {
                        ticketInput.value = payload.ticket;
                    }

                    cancelUrl = payload.cancel_url || cancelUrl;
                    statusUrl = payload.status_url || statusUrl;
                    queueSnapshot = {
                        ...payload,
                        cancel_url: cancelUrl,
                        status_url: statusUrl,
                    };
                    persistDraft();

                    if (payload.status === 'waiting') {
                        showQueue(
                            `Anda sudah masuk antrean - urutan ke-${Math.max(1, payload.position || 1)}`,
                            `Perkiraan ${waitLabel(payload.estimated_wait_seconds)}. ${draftSafetyText()}`
                        );

                        return false;
                    }

                    if (['admitted', 'processing', 'completed'].includes(payload.status)) {
                        showQueue('Giliran Anda sudah tersedia', 'Jawaban sedang dikirim. Mohon jangan menutup halaman.');

                        return true;
                    }

                    if (['cancelled', 'expired'].includes(payload.status)) {
                        clearTicketState();

                        return null;
                    }

                    const statusError = new Error('Status tiket antrean tidak dikenali.');
                    statusError.retryable = true;
                    throw statusError;
                };

                const createTicket = async () => {
                    const body = new FormData();
                    body.append('_token', csrfToken());
                    body.append('submission_request_id', requestIdInput?.value || '');

                    if (studentIdInput) {
                        body.append('student_id', studentIdInput.value || '');
                    }

                    return requestWithRetry(form.dataset.literacyTicketEndpoint, {
                        method: 'POST',
                        body,
                    });
                };

                const submitFinal = async () => {
                    if (finalSubmissionRunning) {
                        return;
                    }

                    finalSubmissionRunning = true;
                    retryStartedAt = Date.now();

                    if (submitButton) {
                        submitButton.disabled = true;
                    }

                    if (cancelButton) {
                        cancelButton.disabled = true;
                    }

                    showQueue('Menyimpan jawaban...', `Mohon tunggu. ${draftSafetyText()}`);
                    form.dispatchEvent(new CustomEvent('literacy:final-submit-start'));

                    try {
                        const payload = await requestWithRetry(form.action, {
                            method: (form.method || 'POST').toUpperCase(),
                            body: new FormData(form),
                            headers: {
                                Accept: 'application/json',
                            },
                        });

                        if (!payload?.redirect_url) {
                            throw new Error('Server tidak mengirim tujuan halaman hasil.');
                        }

                        removeDraft();
                        showQueue('Jawaban berhasil disimpan', 'Halaman hasil sedang dibuka...');
                        window.location.assign(payload.redirect_url);
                    } catch (error) {
                        showQueue(
                            error?.exhausted ? 'Belum berhasil menyimpan jawaban' : 'Jawaban belum dapat disimpan',
                            error?.message || `Silakan periksa jawaban lalu coba lagi. ${draftSafetyText()}`
                        );
                        form.dataset.literacyQueueAdmitted = '0';
                        finalSubmissionRunning = false;
                        form.dispatchEvent(new CustomEvent('literacy:final-submit-failed'));

                        if (submitButton) {
                            submitButton.disabled = false;
                        }

                        if (cancelButton) {
                            cancelButton.disabled = false;
                        }
                    }
                };

                const runQueue = async ({ resume = false } = {}) => {
                    if (queueRunning) {
                        return;
                    }

                    queueRunning = true;
                    cancelled = false;
                    retryStartedAt = Date.now();
                    persistDraft();

                    if (submitButton) {
                        submitButton.disabled = true;
                    }

                    showQueue('Menyiapkan jalur antrean...', draftSafetyText());
                    panel?.scrollIntoView({ behavior: 'smooth', block: 'center' });

                    try {
                        let payload = null;

                        if (resume && statusUrl) {
                            showQueue('Melanjutkan antrean sebelumnya...', `${draftSafetyText()} Posisi sedang diperbarui.`);

                            try {
                                payload = await requestWithRetry(statusUrl, { method: 'GET' });
                            } catch (error) {
                                if (![404, 410, 422].includes(error?.status)) {
                                    throw error;
                                }

                                clearTicketState();
                            }
                        }

                        if (!payload) {
                            const gateSeconds = Math.floor(Math.random() * (initialJitterSeconds + 1));

                            await countdownWait(gateSeconds, (remaining) => {
                                showQueue(
                                    'Menyiapkan jalur antrean',
                                    `Permintaan akan dikirim dalam ${remaining} detik. ${draftSafetyText()}`
                                );
                            });

                            if (cancelled) {
                                return;
                            }

                            payload = await createTicket();
                        }

                        if (cancelled) {
                            return;
                        }

                        let admitted = renderQueuePayload(payload);

                        if (admitted === null) {
                            payload = await createTicket();
                            admitted = renderQueuePayload(payload);
                        }

                        while (!cancelled && admitted === false) {
                            const basePollSeconds = Math.max(2, Number.parseInt(payload.retry_after_seconds || '5', 10));
                            const pollSeconds = Math.ceil(basePollSeconds * (1 + (Math.random() * 0.25)));

                            await countdownWait(pollSeconds, (remaining) => {
                                showQueue(
                                    `Anda sudah masuk antrean - urutan ke-${Math.max(1, payload.position || 1)}`,
                                    `Perkiraan ${waitLabel(payload.estimated_wait_seconds)}. Pemeriksaan berikutnya dalam ${remaining} detik. ${draftSafetyText()}`
                                );
                            });

                            if (cancelled) {
                                return;
                            }

                            payload = await requestWithRetry(payload.status_url || statusUrl, { method: 'GET' });
                            admitted = renderQueuePayload(payload);

                            if (admitted === null) {
                                payload = await createTicket();
                                admitted = renderQueuePayload(payload);
                            }
                        }

                        if (cancelled) {
                            return;
                        }

                        form.dataset.literacyQueueAdmitted = '1';
                        queueRunning = false;

                        if (submitButton) {
                            submitButton.disabled = false;
                        }

                        persistDraft();
                        form.requestSubmit(submitButton || undefined);
                    } catch (error) {
                        showQueue(
                            error?.exhausted ? 'Belum berhasil terhubung ke antrean' : 'Pengiriman belum dapat dilanjutkan',
                            error?.message || `Antrean belum dapat dihubungi. ${draftSafetyText()}`
                        );
                        stopQueue();
                    }
                };

                const restoredDraft = restoreDraft();

                form.addEventListener('input', scheduleDraftPersistence);
                form.addEventListener('change', scheduleDraftPersistence);

                form.addEventListener('submit', (event) => {
                    if (form.dataset.literacyQueueAdmitted === '1') {
                        event.preventDefault();
                        submitFinal();

                        return;
                    }

                    event.preventDefault();

                    if (queueRunning || (studentIdInput && studentIdInput.value === '') || !form.reportValidity()) {
                        return;
                    }

                    const canResume = Boolean(queueSnapshot?.status_url && ticketInput?.value);
                    runQueue({ resume: canResume });
                });

                cancelButton?.addEventListener('click', async () => {
                    cancelled = true;

                    if (cancelUrl) {
                        requestJson(cancelUrl, { method: 'DELETE' }).catch(() => {});
                    }

                    clearTicketState();
                    panel?.classList.add('hidden');
                    stopQueue();
                });

                if (restoredDraft) {
                    if (queueSnapshot?.status_url && ticketInput?.value) {
                        window.setTimeout(() => runQueue({ resume: true }), 0);
                    } else {
                        showQueue('Draf sebelumnya dipulihkan', 'Jawaban dari tab ini sudah dikembalikan. Silakan periksa sebelum mengirim.');
                        window.setTimeout(() => {
                            if (!queueRunning) {
                                panel?.classList.add('hidden');
                            }
                        }, 5000);
                    }
                }
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

                const beginFinalSubmission = () => {
                    submitting = true;
                    leavingPage = true;
                    clearPendingIntegrityTimers();
                    syncIntegrityFields();
                };

                form.addEventListener('literacy:final-submit-start', beginFinalSubmission);
                form.addEventListener('literacy:final-submit-failed', () => {
                    submitting = false;
                    leavingPage = false;
                });
                form.addEventListener('submit', (event) => {
                    if (event.defaultPrevented) {
                        return;
                    }

                    beginFinalSubmission();
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
