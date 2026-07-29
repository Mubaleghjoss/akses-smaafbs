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
            const answerInputs = Array.from(form.querySelectorAll('[data-literacy-answer-input]'));
            const submitButton = form.querySelector('[data-literacy-submit-button]');
            const submitInitiallyDisabled = submitButton?.disabled === true;
            let queueSubmitLocked = false;

            window.addEventListener('pageshow', (event) => {
                if (!event.persisted) {
                    return;
                }

                const requestId = form.querySelector('[data-literacy-request-id]')?.value || '';

                if (requestId === '') {
                    return;
                }

                try {
                    const markerKey = `literacy.submission.completed.v1:${requestId}`;
                    const rawMarker = window.sessionStorage.getItem(markerKey);

                    if (!rawMarker) {
                        return;
                    }

                    const marker = JSON.parse(rawMarker);
                    window.sessionStorage.removeItem(markerKey);
                    form.reset();
                    window.location.replace(marker?.redirect_url || @js(route('library.literacy.index')));
                } catch (error) {
                    form.reset();
                    window.location.reload();
                }
            });

            const answerLength = (textarea) => Array.from(textarea.value || '').length;
            const answerMaximum = (textarea) => Math.max(
                1,
                Number.parseInt(textarea.dataset.maxCharacters || '1', 10)
            );
            const firstAnswerOverLimit = () => answerInputs.find(
                (textarea) => answerLength(textarea) > answerMaximum(textarea)
            ) || null;
            const syncSubmitAvailability = () => {
                const overLimit = firstAnswerOverLimit() !== null;

                form.dataset.literacyAnswerLimitValid = overLimit ? '0' : '1';

                if (submitButton) {
                    submitButton.disabled = submitInitiallyDisabled || queueSubmitLocked || overLimit;
                    submitButton.setAttribute('aria-disabled', submitButton.disabled ? 'true' : 'false');
                }

                return !overLimit;
            };
            const setQueueSubmitLocked = (locked) => {
                queueSubmitLocked = locked;
                syncSubmitAvailability();
            };
            const focusFirstAnswerOverLimit = () => {
                const textarea = firstAnswerOverLimit();

                if (!textarea) {
                    return false;
                }

                textarea.setAttribute('aria-invalid', 'true');
                textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
                textarea.focus({ preventScroll: true });

                return true;
            };

            form.addEventListener('submit', (event) => {
                rememberSubmitScrollTarget(form.dataset.literacyScrollTarget || '[data-literacy-submit-status]');

                if (!syncSubmitAvailability()) {
                    event.preventDefault();
                    focusFirstAnswerOverLimit();
                }
            });

            if (form.dataset.literacyQueueEnabled === '1' && form.dataset.literacyTicketEndpoint) {
                const ticketInput = form.querySelector('[data-literacy-ticket]');
                const requestIdInput = form.querySelector('[data-literacy-request-id]');
                const studentIdInput = form.querySelector('[data-student-id]');
                const queueWaitedInput = form.querySelector('[data-literacy-queue-waited]');
                const retryStatusesInput = form.querySelector('[data-literacy-retry-statuses]');
                const panel = form.querySelector('[data-literacy-queue-panel]');
                const title = form.querySelector('[data-literacy-queue-title]');
                const message = form.querySelector('[data-literacy-queue-message]');
                const cancelButton = form.querySelector('[data-literacy-queue-cancel]');
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
                let queueAction = 'cancel';
                let lastValidationField = '';
                let clientFailureReported = false;

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
                const recordRetryStatus = (status) => {
                    if (!retryStatusesInput) {
                        return;
                    }

                    const normalizedStatus = Number.isFinite(Number(status)) ? String(Number(status)) : '0';
                    const statuses = retryStatusesInput.value
                        .split(',')
                        .map((value) => value.trim())
                        .filter(Boolean);

                    if (!statuses.includes(normalizedStatus)) {
                        statuses.push(normalizedStatus);
                    }

                    retryStatusesInput.value = statuses.slice(-12).join(',');
                    persistDraft();
                };
                const currentRetryStatuses = () => (retryStatusesInput?.value || '')
                    .split(',')
                    .map((status) => status.trim())
                    .filter(Boolean)
                    .slice(-12);

                const showQueue = (heading, detail) => {
                    panel?.classList.remove('hidden');

                    if (title) {
                        title.textContent = heading;
                    }

                    if (message) {
                        message.textContent = detail;
                    }
                };
                const setQueueAction = (action) => {
                    queueAction = action;

                    if (!cancelButton) {
                        return;
                    }

                    cancelButton.dataset.literacyQueueAction = action;
                    cancelButton.textContent = action === 'repair' ? 'Perbaiki jawaban' : 'Batal menunggu';
                };
                const focusValidationField = (validationField) => {
                    const fieldName = validationField?.startsWith('answers.')
                        ? `answers[${validationField.slice('answers.'.length)}]`
                        : validationField;
                    const field = Array.from(form.elements).find((element) => element.name === fieldName)
                        || (validationField?.startsWith('answers.')
                            ? Array.from(form.elements).find((element) => element.name?.startsWith(`${fieldName}[`))
                            : null);

                    if (!field) {
                        return focusFirstAnswerOverLimit();
                    }

                    const matchingFallback = field.closest?.('[data-literacy-matching-fallback]');

                    if (matchingFallback?.classList.contains('sr-only')) {
                        matchingFallback.classList.remove('sr-only');
                        matchingFallback
                            .closest('[data-literacy-matching-group]')
                            ?.querySelector('[data-literacy-matching-board]')
                            ?.classList.add('hidden');
                    }

                    field.setAttribute('aria-invalid', 'true');
                    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    field.focus({ preventScroll: true });

                    return true;
                };
                const validationContainer = (fieldName) => Array.from(
                    form.querySelectorAll('[data-literacy-validation-for]')
                ).find((element) => element.dataset.literacyValidationFor === fieldName);
                const clearValidationError = (fieldName) => {
                    const container = validationContainer(fieldName);

                    if (container) {
                        container.textContent = '';
                        container.classList.add('hidden');
                    }

                    const inputName = fieldName?.startsWith('answers.')
                        ? `answers[${fieldName.slice('answers.'.length)}]`
                        : fieldName;
                    Array.from(form.elements)
                        .find((element) => element.name === inputName)
                        ?.removeAttribute('aria-invalid');
                };
                const clearValidationErrors = () => {
                    form.querySelectorAll('[data-literacy-validation-for]').forEach((container) => {
                        container.textContent = '';
                        container.classList.add('hidden');
                    });
                    form.querySelectorAll('[aria-invalid="true"]').forEach((field) => field.removeAttribute('aria-invalid'));
                };
                const renderValidationErrors = (errors = {}) => {
                    clearValidationErrors();

                    Object.entries(errors).forEach(([fieldName, messages]) => {
                        const messageText = Array.isArray(messages) ? messages[0] : messages;
                        const container = validationContainer(fieldName);

                        if (!container || !messageText) {
                            return;
                        }

                        container.textContent = String(messageText);
                        container.classList.remove('hidden');
                    });
                };
                const reportClientFailure = (retryStatuses = []) => {
                    if (clientFailureReported || !form.dataset.literacyEventEndpoint) {
                        return;
                    }

                    clientFailureReported = true;
                    const body = new FormData();
                    body.append('_token', csrfToken());
                    body.append('event_code', 'client_retry_exhausted');
                    body.append('submission_ticket', ticketInput?.value || '');
                    body.append('submission_request_id', requestIdInput?.value || '');
                    body.append('retry_statuses', retryStatuses.join(','));

                    fetch(form.dataset.literacyEventEndpoint, {
                        method: 'POST',
                        credentials: 'same-origin',
                        keepalive: true,
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        body,
                    }).catch(() => {});
                };

                const stopQueue = () => {
                    queueRunning = false;
                    form.dataset.literacyQueueAdmitted = '0';
                    setQueueSubmitLocked(false);
                };

                const draftFields = () => Array.from(form.querySelectorAll([
                    '[data-literacy-answer-control]',
                    '[data-student-search]',
                    '[data-student-id]',
                    '[data-student-verification]',
                    '[data-literacy-queue-waited]',
                    '[data-literacy-retry-statuses]',
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
                        if (!field.name) {
                            return;
                        }

                        if ((field.type === 'radio' || field.type === 'checkbox') && !field.checked) {
                            return;
                        }

                        fields[field.name] = field.value;
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
                                const value = String(draft.fields[field.name] ?? '');

                                if (field.type === 'radio' || field.type === 'checkbox') {
                                    field.checked = field.value === value;
                                } else {
                                    field.value = value;
                                }
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
                            const validationEntry = Object.entries(payload.errors || {})[0];
                            const validationMessage = Array.isArray(validationEntry?.[1])
                                ? validationEntry[1][0]
                                : validationEntry?.[1];
                            const error = new Error(validationMessage || payload.message || 'Antrean belum dapat dihubungi.');
                            error.status = response.status;
                            error.retryAfter = Number.parseInt(response.headers.get('Retry-After') || '0', 10);
                            error.retryable = [408, 425, 429, 500, 502, 503, 504].includes(response.status);
                            error.validationField = validationEntry?.[0] || '';
                            error.validationErrors = payload.errors || {};
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

                            recordRetryStatus(error.status);

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
                        if (queueWaitedInput) {
                            queueWaitedInput.value = '1';
                        }

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
                    setQueueSubmitLocked(true);

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
                        window.location.replace(payload.redirect_url);
                    } catch (error) {
                        showQueue(
                            error?.exhausted ? 'Belum berhasil menyimpan jawaban' : 'Jawaban belum dapat disimpan',
                            error?.message || `Silakan periksa jawaban lalu coba lagi. ${draftSafetyText()}`
                        );
                        const isValidationError = error?.status === 422;

                        if (isValidationError) {
                            lastValidationField = error?.validationField || '';
                            renderValidationErrors(error?.validationErrors || {});
                            clearTicketState();
                            setQueueAction('repair');
                        } else {
                            setQueueAction('cancel');
                        }

                        if (error?.exhausted) {
                            reportClientFailure(currentRetryStatuses());
                        }

                        form.dataset.literacyQueueAdmitted = '0';
                        finalSubmissionRunning = false;
                        form.dispatchEvent(new CustomEvent('literacy:final-submit-failed'));
                        setQueueSubmitLocked(false);

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
                    setQueueAction('cancel');
                    persistDraft();
                    setQueueSubmitLocked(true);

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
                        setQueueSubmitLocked(false);

                        if (!syncSubmitAvailability()) {
                            form.dataset.literacyQueueAdmitted = '0';
                            lastValidationField = '';
                            setQueueAction('repair');
                            showQueue(
                                'Jawaban perlu diperbaiki',
                                `Ada jawaban yang melewati batas karakter. ${draftSafetyText()}`
                            );

                            if (cancelUrl) {
                                requestJson(cancelUrl, { method: 'DELETE' }).catch(() => {});
                            }

                            clearTicketState();

                            return;
                        }

                        persistDraft();
                        form.requestSubmit(submitButton || undefined);
                    } catch (error) {
                        showQueue(
                            error?.exhausted ? 'Belum berhasil terhubung ke antrean' : 'Pengiriman belum dapat dilanjutkan',
                            error?.message || `Antrean belum dapat dihubungi. ${draftSafetyText()}`
                        );
                        if (error?.exhausted) {
                            reportClientFailure(currentRetryStatuses());
                        }
                        stopQueue();
                    }
                };

                const restoredDraft = restoreDraft();

                form.addEventListener('input', scheduleDraftPersistence);
                form.addEventListener('input', (event) => {
                    const fieldName = event.target?.name;

                    if (!fieldName) {
                        return;
                    }

                    const validationName = /^answers\[(.+)]$/.test(fieldName)
                        ? `answers.${fieldName.match(/^answers\[(.+)]$/)?.[1]}`
                        : fieldName;
                    clearValidationError(validationName);
                });
                form.addEventListener('change', scheduleDraftPersistence);

                form.addEventListener('submit', (event) => {
                    if (event.defaultPrevented) {
                        return;
                    }

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
                    if (queueAction === 'repair') {
                        panel?.classList.add('hidden');
                        setQueueAction('cancel');
                        focusValidationField(lastValidationField);

                        return;
                    }

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
                    const length = answerLength(textarea);
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
                        textarea.setAttribute('aria-invalid', 'true');
                        setTone('danger');
                        if (status) {
                            status.textContent = `Jawaban berisi ${formatNumber(length)} karakter dan melebihi batas ${formatNumber(max)} karakter. Kurangi ${formatNumber(length - max)} karakter.`;
                        }
                        syncSubmitAvailability();

                        return;
                    }

                    textarea.removeAttribute('aria-invalid');
                    syncSubmitAvailability();

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

            form.querySelectorAll('[data-literacy-matching-group]').forEach((group) => {
                const selects = Array.from(group.querySelectorAll('[data-literacy-matching-select]'));
                const board = group.querySelector('[data-literacy-matching-board]');
                const fallback = group.querySelector('[data-literacy-matching-fallback]');
                const surface = group.querySelector('[data-literacy-matching-surface]');
                const canvas = group.querySelector('[data-literacy-matching-canvas]');
                const lines = group.querySelector('[data-literacy-matching-lines]');
                const status = group.querySelector('[data-literacy-matching-status]');
                const leftButtons = Array.from(group.querySelectorAll('[data-literacy-matching-left]'));
                const targetButtons = Array.from(group.querySelectorAll('[data-literacy-matching-target]'));
                const resetButton = group.querySelector('[data-literacy-matching-reset]');
                const desktopMedia = window.matchMedia('(min-width: 768px)');
                const colors = ['#0284c7', '#7c3aed', '#059669', '#d97706', '#e11d48', '#0891b2'];
                let selectedLeftId = '';
                let forceFallback = false;
                let drawingFrame = null;

                const syncMatchingOptions = () => {
                    const selectedValues = selects
                        .map((select) => select.value)
                        .filter(Boolean);

                    selects.forEach((select) => {
                        Array.from(select.options).forEach((option) => {
                            if (option.value === '' || option.value === select.value) {
                                option.disabled = false;
                                return;
                            }

                            option.disabled = selectedValues.includes(option.value);
                        });
                    });
                };

                const resetButtonStyle = (button) => {
                    button.style.borderColor = '';
                    button.style.backgroundColor = '';
                    button.style.boxShadow = '';
                };

                const colorForLeft = (leftId) => {
                    const leftButton = leftButtons.find((button) => button.dataset.leftId === leftId);
                    const colorIndex = Number.parseInt(leftButton?.dataset.colorIndex || '0', 10);

                    return colors[Math.abs(colorIndex) % colors.length];
                };

                const syncMatchingButtons = () => {
                    leftButtons.forEach((button) => {
                        const select = selects.find((item) => item.dataset.leftId === button.dataset.leftId);
                        const isSelected = selectedLeftId === button.dataset.leftId;

                        resetButtonStyle(button);
                        button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');

                        if (isSelected) {
                            button.style.borderColor = '#0284c7';
                            button.style.backgroundColor = '#e0f2fe';
                            button.style.boxShadow = '0 0 0 3px rgba(14, 165, 233, 0.2)';
                        } else if (select?.value) {
                            const color = colorForLeft(button.dataset.leftId);
                            button.style.borderColor = color;
                            button.style.backgroundColor = `${color}12`;
                        }
                    });

                    targetButtons.forEach((button) => {
                        const connectedSelect = selects.find((select) => select.value === button.dataset.targetId);

                        resetButtonStyle(button);
                        button.setAttribute('aria-pressed', connectedSelect ? 'true' : 'false');

                        if (connectedSelect) {
                            const color = colorForLeft(connectedSelect.dataset.leftId);
                            button.style.borderColor = color;
                            button.style.backgroundColor = `${color}12`;
                        }
                    });
                };

                const drawMatchingLines = () => {
                    drawingFrame = null;

                    if (!surface || !canvas || !lines || board?.classList.contains('hidden')) {
                        return;
                    }

                    const surfaceRect = surface.getBoundingClientRect();
                    const width = Math.max(1, surface.clientWidth);
                    const height = Math.max(1, surface.clientHeight);
                    const markerId = lines.dataset.markerId || '';

                    canvas.setAttribute('viewBox', `0 0 ${width} ${height}`);
                    canvas.setAttribute('width', String(width));
                    canvas.setAttribute('height', String(height));
                    lines.replaceChildren();

                    selects.forEach((select) => {
                        if (!select.value) {
                            return;
                        }

                        const leftButton = leftButtons.find((button) => button.dataset.leftId === select.dataset.leftId);
                        const targetButton = targetButtons.find((button) => button.dataset.targetId === select.value);

                        if (!leftButton || !targetButton) {
                            return;
                        }

                        const leftRect = leftButton.getBoundingClientRect();
                        const targetRect = targetButton.getBoundingClientRect();
                        const startX = leftRect.right - surfaceRect.left;
                        const startY = leftRect.top - surfaceRect.top + (leftRect.height / 2);
                        const endX = targetRect.left - surfaceRect.left - 7;
                        const endY = targetRect.top - surfaceRect.top + (targetRect.height / 2);
                        const bend = Math.max(18, (endX - startX) * 0.45);
                        const color = colorForLeft(select.dataset.leftId);
                        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');

                        path.setAttribute('d', `M ${startX} ${startY} C ${startX + bend} ${startY}, ${endX - bend} ${endY}, ${endX} ${endY}`);
                        path.setAttribute('fill', 'none');
                        path.setAttribute('stroke', color);
                        path.setAttribute('stroke-width', '3');
                        path.setAttribute('stroke-linecap', 'round');
                        path.setAttribute('opacity', '0.9');

                        if (markerId) {
                            path.setAttribute('marker-end', `url(#${markerId})`);
                        }

                        lines.appendChild(path);
                    });
                };

                const scheduleMatchingLines = () => {
                    if (drawingFrame !== null) {
                        window.cancelAnimationFrame(drawingFrame);
                    }

                    drawingFrame = window.requestAnimationFrame(drawMatchingLines);
                };

                const refreshMatching = () => {
                    syncMatchingOptions();
                    syncMatchingButtons();
                    scheduleMatchingLines();
                };

                const syncMatchingMode = () => {
                    const useBoard = desktopMedia.matches && !forceFallback && board && fallback;

                    board?.classList.toggle('hidden', !useBoard);
                    fallback?.classList.toggle('sr-only', Boolean(useBoard));

                    if (!useBoard) {
                        lines?.replaceChildren();
                    }

                    scheduleMatchingLines();
                };

                leftButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        selectedLeftId = selectedLeftId === button.dataset.leftId
                            ? ''
                            : button.dataset.leftId;

                        if (status) {
                            status.textContent = selectedLeftId
                                ? 'Sekarang klik jawaban yang sesuai di Kolom B.'
                                : 'Pilih salah satu item di Kolom A untuk mulai menghubungkan.';
                        }

                        syncMatchingButtons();
                    });
                });

                targetButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        if (!selectedLeftId) {
                            const connectedSelect = selects.find((select) => select.value === button.dataset.targetId);

                            if (connectedSelect) {
                                selectedLeftId = connectedSelect.dataset.leftId;
                                status.textContent = 'Jawaban ini sudah terhubung. Pilih jawaban lain untuk mengubah pasangannya.';
                                syncMatchingButtons();
                            } else if (status) {
                                status.textContent = 'Pilih item di Kolom A terlebih dahulu, lalu pilih jawaban ini.';
                            }

                            return;
                        }

                        const selected = selects.find((select) => select.dataset.leftId === selectedLeftId);
                        const previouslyConnected = selects.find(
                            (select) => select !== selected && select.value === button.dataset.targetId
                        );

                        if (!selected) {
                            return;
                        }

                        if (previouslyConnected) {
                            previouslyConnected.value = '';
                            previouslyConnected.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        selected.value = button.dataset.targetId;
                        selected.dispatchEvent(new Event('change', { bubbles: true }));
                        selectedLeftId = '';

                        if (status) {
                            const completed = selects.filter((select) => select.value !== '').length;
                            status.textContent = `${formatNumber(completed)} dari ${formatNumber(selects.length)} pasangan sudah dihubungkan.`;
                        }

                        refreshMatching();
                    });
                });

                resetButton?.addEventListener('click', () => {
                    selects.forEach((select) => {
                        select.value = '';
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    selectedLeftId = '';

                    if (status) {
                        status.textContent = 'Semua garis dihapus. Pilih item di Kolom A untuk mulai lagi.';
                    }

                    refreshMatching();
                });

                selects.forEach((select) => {
                    select.addEventListener('change', refreshMatching);
                    select.addEventListener('invalid', () => {
                        forceFallback = true;
                        syncMatchingMode();

                        if (status) {
                            status.textContent = 'Lengkapi semua pasangan yang wajib diisi melalui pilihan di bawah.';
                        }
                    });
                });

                if (typeof desktopMedia.addEventListener === 'function') {
                    desktopMedia.addEventListener('change', () => {
                        forceFallback = false;
                        syncMatchingMode();
                    });
                } else {
                    desktopMedia.addListener(() => {
                        forceFallback = false;
                        syncMatchingMode();
                    });
                }

                if (typeof ResizeObserver === 'function' && surface) {
                    new ResizeObserver(scheduleMatchingLines).observe(surface);
                } else {
                    window.addEventListener('resize', scheduleMatchingLines);
                }

                document.fonts?.ready.then(scheduleMatchingLines).catch(() => {});
                syncMatchingMode();
                refreshMatching();
            });

            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

            form.querySelectorAll('[data-literacy-speech]').forEach((container) => {
                const button = container.querySelector('[data-literacy-speech-toggle]');
                const status = container.querySelector('[data-literacy-speech-status]');
                const target = document.getElementById(button?.dataset.speechTarget || '');

                if (!button || !status || !target) {
                    return;
                }

                if (!SpeechRecognition) {
                    button.disabled = true;
                    button.classList.add('opacity-60');
                    status.textContent = 'Browser ini belum mendukung jawaban suara. Silakan ketik jawaban seperti biasa.';
                    return;
                }

                const recognition = new SpeechRecognition();
                recognition.lang = container.dataset.speechLanguage || 'id-ID';
                recognition.continuous = true;
                recognition.interimResults = true;

                let listening = false;
                let stopTimer = null;

                const stopRecognition = () => {
                    if (!listening) {
                        return;
                    }

                    try {
                        recognition.stop();
                    } catch (error) {
                        // The browser can already be stopping the recognizer.
                    }
                };

                const appendTranscript = (transcript) => {
                    const addition = String(transcript || '').trim();

                    if (addition === '') {
                        return;
                    }

                    const current = target.value.trim();
                    target.value = current === '' ? addition : `${current} ${addition}`;
                    target.dispatchEvent(new Event('input', { bubbles: true }));

                    const max = Math.max(1, Number.parseInt(target.dataset.maxCharacters || '1', 10));

                    if (Array.from(target.value).length > max) {
                        status.textContent = 'Teks hasil suara melewati batas karakter. Dikte dihentikan; perbaiki teks sebelum mengirim.';
                        stopRecognition();
                    }
                };

                recognition.onstart = () => {
                    listening = true;
                    button.textContent = 'Berhenti';
                    status.textContent = 'Mendengarkan. Bicara dengan jelas, lalu tekan Berhenti jika selesai.';
                    stopTimer = window.setTimeout(() => {
                        status.textContent = 'Dikte dihentikan otomatis setelah 45 detik. Tekan Jawab dengan Suara untuk melanjutkan.';
                        stopRecognition();
                    }, 45000);
                };

                recognition.onend = () => {
                    listening = false;
                    window.clearTimeout(stopTimer);
                    stopTimer = null;
                    button.textContent = 'Jawab dengan Suara';

                    if (!status.textContent.includes('otomatis') && !status.textContent.includes('batas karakter')) {
                        status.textContent = 'Dikte selesai. Periksa dan edit teks sebelum mengirim.';
                    }
                };

                recognition.onerror = (event) => {
                    status.textContent = event.error === 'not-allowed'
                        ? 'Izin mikrofon ditolak. Aktifkan izin mikrofon di browser atau ketik jawaban.'
                        : 'Layanan suara tidak dapat digunakan saat ini. Jawaban tetap dapat diketik.';
                };

                recognition.onresult = (event) => {
                    let finalTranscript = '';
                    let interimTranscript = '';

                    for (let index = event.resultIndex; index < event.results.length; index += 1) {
                        const transcript = event.results[index][0].transcript;

                        if (event.results[index].isFinal) {
                            finalTranscript += transcript;
                        } else {
                            interimTranscript += transcript;
                        }
                    }

                    if (finalTranscript.trim() !== '') {
                        appendTranscript(finalTranscript);
                    }

                    if (interimTranscript.trim() !== '') {
                        status.textContent = `Mendengarkan: ${interimTranscript.trim()}`;
                    }
                };

                button.addEventListener('click', () => {
                    if (listening) {
                        stopRecognition();
                        return;
                    }

                    try {
                        recognition.start();
                    } catch (error) {
                        status.textContent = 'Dikte sudah berjalan. Tekan Berhenti jika ingin mengakhirinya.';
                    }
                });

                form.addEventListener('literacy:final-submit-start', stopRecognition);
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'hidden') {
                        stopRecognition();
                    }
                });
            });

            const integrityFields = {
                app_hidden_count: form.querySelector('[data-integrity-field="app_hidden_count"]'),
            };

            if (Object.values(integrityFields).some(Boolean) && form.dataset.literacyIntegrityReady !== '1') {
                form.dataset.literacyIntegrityReady = '1';

                const counts = {
                    app_hidden_count: 0,
                };
                let submitting = false;
                let beaconSent = false;
                let leavingPage = false;
                let hiddenTimer = null;

                const clearHiddenTimer = () => {
                    if (hiddenTimer !== null) {
                        window.clearTimeout(hiddenTimer);
                        hiddenTimer = null;
                    }
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
                    clearHiddenTimer();
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
                            clearHiddenTimer();
                        }
                    } catch (error) {
                        // Invalid hrefs are ignored; the browser will handle them.
                    }
                }, true);

                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        clearHiddenTimer();

                        return;
                    }

                    if (!submitting && !leavingPage && document.visibilityState === 'hidden') {
                        clearHiddenTimer();
                        hiddenTimer = window.setTimeout(() => {
                            hiddenTimer = null;

                            if (!submitting && !leavingPage && document.visibilityState === 'hidden') {
                                bumpIntegrity('app_hidden_count');
                            }
                        }, 10000);
                    }
                });

                window.addEventListener('beforeunload', () => {
                    leavingPage = true;
                    clearHiddenTimer();
                });

                window.addEventListener('pagehide', () => {
                    leavingPage = true;
                    clearHiddenTimer();
                    submitIntegrityBeacon();
                });
                syncIntegrityFields();
            }
        });

        const imageTriggers = Array.from(document.querySelectorAll(
            '[data-literacy-image-open], .literacy-reading-content img'
        ));

        if (imageTriggers.length > 0) {
            const dialog = document.createElement('dialog');
            dialog.setAttribute('aria-label', 'Pratinjau gambar');
            dialog.style.cssText = 'width:min(96vw,72rem);max-width:72rem;border:0;border-radius:1rem;padding:0;background:#0f172a;color:#fff;box-shadow:0 24px 80px rgba(15,23,42,.5)';
            dialog.innerHTML = `
                <div style="position:relative;padding:2.75rem .75rem 1rem">
                    <button type="button" data-literacy-image-close aria-label="Tutup pratinjau" style="position:absolute;right:.75rem;top:.65rem;min-width:2.25rem;min-height:2.25rem;border:1px solid rgba(255,255,255,.35);border-radius:999px;background:rgba(15,23,42,.8);color:#fff;font-size:1.25rem;cursor:pointer">×</button>
                    <img data-literacy-image-preview alt="" style="display:block;width:auto;max-width:100%;max-height:82vh;margin:auto;object-fit:contain;border-radius:.65rem">
                    <div data-literacy-image-caption style="margin-top:.75rem;text-align:center;font-size:.875rem;line-height:1.5;color:#e2e8f0"></div>
                </div>
            `;
            document.body.appendChild(dialog);

            const preview = dialog.querySelector('[data-literacy-image-preview]');
            const caption = dialog.querySelector('[data-literacy-image-caption]');
            const closeDialog = () => dialog.open && dialog.close();
            const openDialog = (trigger) => {
                const sourceImage = trigger.matches('img') ? trigger : trigger.querySelector('img');
                const source = sourceImage?.currentSrc || sourceImage?.src;

                if (!source) {
                    return;
                }

                preview.src = source;
                preview.alt = sourceImage?.alt || '';
                caption.textContent = trigger.dataset.literacyImageCaption || sourceImage?.alt || 'Pratinjau gambar';
                dialog.showModal();
            };

            dialog.querySelector('[data-literacy-image-close]')?.addEventListener('click', closeDialog);
            dialog.addEventListener('click', (event) => {
                if (event.target === dialog) {
                    closeDialog();
                }
            });

            imageTriggers.forEach((trigger) => {
                trigger.style.cursor = 'zoom-in';

                if (trigger.matches('img')) {
                    trigger.setAttribute('role', 'button');
                    trigger.setAttribute('tabindex', '0');
                }

                trigger.addEventListener('click', () => openDialog(trigger));
                trigger.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        openDialog(trigger);
                    }
                });
            });
        }

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
