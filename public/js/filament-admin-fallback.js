(function () {
    let livewireSessionExpiredHandled = false;
    let livewireSessionHandlerInstalled = false;
    let livewireSessionHandlerPoll = null;

    function showAdminSessionExpiredNotice() {
        if (!document.body || document.getElementById('admin-livewire-session-expired-notice')) {
            return;
        }

        const notice = document.createElement('div');
        notice.id = 'admin-livewire-session-expired-notice';
        notice.setAttribute('role', 'alert');
        notice.textContent = 'Sesi admin kedaluwarsa. Halaman dimuat ulang untuk mengambil token baru.';
        notice.style.cssText = [
            'position:fixed',
            'z-index:2147483647',
            'inset:auto 1rem 1rem 1rem',
            'max-width:28rem',
            'margin-inline:auto',
            'border-radius:.75rem',
            'background:#0f172a',
            'color:#fff',
            'box-shadow:0 20px 45px rgba(15,23,42,.24)',
            'font:500 .875rem/1.45 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif',
            'padding:.875rem 1rem',
            'text-align:center',
        ].join(';');

        document.body.appendChild(notice);
    }

    function refreshAdminSession() {
        if (livewireSessionExpiredHandled) {
            return;
        }

        livewireSessionExpiredHandled = true;
        showAdminSessionExpiredNotice();

        window.setTimeout(() => {
            window.location.reload();
        }, document.visibilityState === 'hidden' ? 100 : 900);
    }

    function isSessionExpiredPayload(payload) {
        return Number(payload?.status ?? payload?.response?.status) === 419;
    }

    function installLivewireSessionExpiredHandler() {
        if (livewireSessionHandlerInstalled || !window.Livewire) {
            return livewireSessionHandlerInstalled;
        }

        if (typeof window.Livewire.interceptRequest === 'function') {
            window.Livewire.interceptRequest(({ onError }) => {
                onError(({ response, preventDefault }) => {
                    if (Number(response?.status) !== 419) {
                        return;
                    }

                    preventDefault?.();
                    refreshAdminSession();
                });
            });

            livewireSessionHandlerInstalled = true;

            return true;
        }

        if (typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('request', ({ fail }) => {
                if (typeof fail !== 'function') {
                    return;
                }

                fail(({ status, preventDefault }) => {
                    if (Number(status) !== 419) {
                        return;
                    }

                    preventDefault?.();
                    refreshAdminSession();
                });
            });

            livewireSessionHandlerInstalled = true;

            return true;
        }

        return false;
    }

    function stopLivewireSessionHandlerPolling() {
        if (!livewireSessionHandlerPoll) {
            return;
        }

        window.clearInterval(livewireSessionHandlerPoll);
        livewireSessionHandlerPoll = null;
    }

    function startLivewireSessionHandlerPolling() {
        if (installLivewireSessionExpiredHandler() || livewireSessionHandlerPoll) {
            return;
        }

        let attempts = 0;

        livewireSessionHandlerPoll = window.setInterval(() => {
            attempts += 1;

            if (installLivewireSessionExpiredHandler() || attempts >= 40) {
                stopLivewireSessionHandlerPolling();
            }
        }, 250);
    }

    document.addEventListener('livewire:init', () => {
        if (installLivewireSessionExpiredHandler()) {
            stopLivewireSessionHandlerPolling();
        }
    });

    document.addEventListener('livewire:navigated', installLivewireSessionExpiredHandler);

    window.addEventListener('unhandledrejection', (event) => {
        if (!isSessionExpiredPayload(event.reason)) {
            return;
        }

        event.preventDefault();
        refreshAdminSession();
    });

    startLivewireSessionHandlerPolling();

    function table() {
        return {
            checkboxClickController: null,
            collapsedGroups: [],
            isLoading: false,
            selectedRecords: [],
            shouldCheckUniqueSelection: true,
            lastChecked: null,
            livewireId: null,
            init() {
                this.livewireId = this.$root.closest('[wire\\:id]')?.getAttribute('wire:id') ?? null;

                this.$wire?.$on?.('deselectAllTableRecords', () => this.deselectAllRecords());

                this.$watch('selectedRecords', () => {
                    if (!this.shouldCheckUniqueSelection) {
                        this.shouldCheckUniqueSelection = true;

                        return;
                    }

                    this.selectedRecords = [...new Set(this.selectedRecords)];
                    this.shouldCheckUniqueSelection = false;
                });

                this.$nextTick(() => this.watchForCheckboxClicks());

                window.Livewire?.hook?.('element.init', ({ component }) => {
                    if (component.id === this.livewireId) {
                        this.watchForCheckboxClicks();
                    }
                });
            },
            mountAction(name, record = null) {
                this.$wire.set('selectedTableRecords', this.selectedRecords, false);
                this.$wire.mountTableAction(name, record);
            },
            mountBulkAction(name) {
                this.$wire.set('selectedTableRecords', this.selectedRecords, false);
                this.$wire.mountTableBulkAction(name);
            },
            toggleSelectRecordsOnPage() {
                const records = this.getRecordsOnPage();

                if (this.areRecordsSelected(records)) {
                    this.deselectRecords(records);

                    return;
                }

                this.selectRecords(records);
            },
            async toggleSelectRecordsInGroup(group) {
                this.isLoading = true;

                const records = await this.$wire.getGroupedSelectableTableRecordKeys(group);

                if (this.areRecordsSelected(this.getRecordsInGroupOnPage(group))) {
                    this.deselectRecords(records);
                } else {
                    this.selectRecords(records);
                }

                this.isLoading = false;
            },
            getRecordsInGroupOnPage(group) {
                const records = [];

                for (const checkbox of this.$root?.getElementsByClassName('fi-ta-record-checkbox') ?? []) {
                    if (checkbox.dataset.group === group) {
                        records.push(checkbox.value);
                    }
                }

                return records;
            },
            getRecordsOnPage() {
                const records = [];

                for (const checkbox of this.$root?.getElementsByClassName('fi-ta-record-checkbox') ?? []) {
                    records.push(checkbox.value);
                }

                return records;
            },
            selectRecords(records) {
                for (const record of records) {
                    if (!this.isRecordSelected(record)) {
                        this.selectedRecords.push(record);
                    }
                }
            },
            deselectRecords(records) {
                for (const record of records) {
                    const index = this.selectedRecords.indexOf(record);

                    if (index !== -1) {
                        this.selectedRecords.splice(index, 1);
                    }
                }
            },
            async selectAllRecords() {
                this.isLoading = true;
                this.selectedRecords = await this.$wire.getAllSelectableTableRecordKeys();
                this.isLoading = false;
            },
            deselectAllRecords() {
                this.selectedRecords = [];
            },
            isRecordSelected(record) {
                return this.selectedRecords.includes(record);
            },
            areRecordsSelected(records) {
                return records.every((record) => this.isRecordSelected(record));
            },
            toggleCollapseGroup(group) {
                if (this.isGroupCollapsed(group)) {
                    this.collapsedGroups.splice(this.collapsedGroups.indexOf(group), 1);

                    return;
                }

                this.collapsedGroups.push(group);
            },
            isGroupCollapsed(group) {
                return this.collapsedGroups.includes(group);
            },
            resetCollapsedGroups() {
                this.collapsedGroups = [];
            },
            watchForCheckboxClicks() {
                this.checkboxClickController?.abort?.();
                this.checkboxClickController = new AbortController();

                this.$root?.addEventListener(
                    'click',
                    (event) => {
                        if (event.target?.matches('.fi-ta-record-checkbox')) {
                            this.handleCheckboxClick(event, event.target);
                        }
                    },
                    { signal: this.checkboxClickController.signal },
                );
            },
            handleCheckboxClick(event, checkbox) {
                if (!this.lastChecked) {
                    this.lastChecked = checkbox;

                    return;
                }

                if (event.shiftKey) {
                    const checkboxes = Array.from(this.$root?.getElementsByClassName('fi-ta-record-checkbox') ?? []);

                    if (!checkboxes.includes(this.lastChecked)) {
                        this.lastChecked = checkbox;

                        return;
                    }

                    const [start, end] = [checkboxes.indexOf(this.lastChecked), checkboxes.indexOf(checkbox)].sort((left, right) => left - right);
                    const affected = [];

                    for (let index = start; index <= end; index++) {
                        checkboxes[index].checked = checkbox.checked;
                        affected.push(checkboxes[index].value);
                    }

                    if (checkbox.checked) {
                        this.selectRecords(affected);
                    } else {
                        this.deselectRecords(affected);
                    }
                }

                this.lastChecked = checkbox;
            },
        };
    }

    window.table = table;

    document.addEventListener('alpine:init', () => {
        if (window.Alpine) {
            window.Alpine.data('table', table);
        }
    });

    if (window.Alpine) {
        window.Alpine.data('table', table);
    }

    async function copyTextToClipboard(text) {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);

            return;
        }

        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
    }

    document.addEventListener('click', async (event) => {
        const button = event.target?.closest?.('.js-copy-credentials-btn');

        if (!button) {
            return;
        }

        const textBlock = button.closest('.js-copy-credentials-template')?.querySelector('.js-copy-credentials-text')
            ?? button.closest('div')?.querySelector('.js-copy-credentials-text');
        const text = textBlock?.innerText?.trim();

        if (!text) {
            return;
        }

        try {
            await copyTextToClipboard(text);

            const original = button.textContent;
            button.textContent = 'Tersalin';

            window.setTimeout(() => {
                button.textContent = original;
            }, 1600);
        } catch (error) {
            window.prompt('Salin manual teks berikut:', text);
        }
    });
})();
