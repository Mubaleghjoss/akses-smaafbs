<script>
    (() => {
        const formatNumber = (value) => new Intl.NumberFormat('id-ID').format(value);
        const forms = document.querySelectorAll('[data-literacy-answer-form]');

        forms.forEach((form) => {
            if (form.dataset.literacyAnswerReady === '1') {
                return;
            }

            form.dataset.literacyAnswerReady = '1';

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

                form.addEventListener('submit', () => {
                    submitting = true;
                    syncIntegrityFields();
                });

                window.addEventListener('blur', () => {
                    if (!submitting) {
                        bumpIntegrity('tab_switch_count');
                    }
                });

                document.addEventListener('visibilitychange', () => {
                    if (!submitting && document.visibilityState === 'hidden') {
                        bumpIntegrity('app_hidden_count');
                    }
                });

                window.addEventListener('beforeunload', (event) => {
                    if (submitting) {
                        return;
                    }

                    bumpIntegrity('page_leave_attempt_count');
                    event.preventDefault();
                    event.returnValue = 'Jawaban belum tentu tersimpan. Tetap keluar dari halaman pengerjaan?';

                    return event.returnValue;
                });

                window.addEventListener('pagehide', submitIntegrityBeacon);
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

        const selectStudent = (student) => {
            hiddenInput.value = String(student.id);
            input.value = student.label;
            input.setCustomValidity('');
            updateSelectedNotice(student);
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
            event.preventDefault();
        });
        document.addEventListener('click', (event) => {
            if (!combobox.contains(event.target)) {
                closeResults();
            }
        });

        const initialStudent = students.find((student) => String(student.id) === String(hiddenInput.value || ''));
        updateSelectedNotice(initialStudent || null);
    })();
</script>
