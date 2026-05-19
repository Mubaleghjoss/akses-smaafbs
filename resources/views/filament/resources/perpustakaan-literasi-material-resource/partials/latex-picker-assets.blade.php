@once
    <script>
        window.MathJax = window.MathJax || {
            tex: {
                inlineMath: [['\\(', '\\)']],
                displayMath: [['\\[', '\\]']],
                processEscapes: true,
            },
            options: {
                skipHtmlTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code'],
            },
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-chtml.js"></script>
    <script>
        (() => {
            if (window.__literacyLatexPickerReady) {
                return;
            }

            window.__literacyLatexPickerReady = true;

            let lastTarget = null;
            let previewTimer = null;

            const targetSelector = 'textarea[data-literacy-latex-target]';

            document.addEventListener('focusin', (event) => {
                if (event.target instanceof HTMLTextAreaElement && event.target.matches(targetSelector)) {
                    lastTarget = event.target;
                }
            });

            const visibleTargets = () => Array
                .from(document.querySelectorAll(targetSelector))
                .filter((target) => !target.disabled && target.offsetParent !== null);

            const findNearestTargetBefore = (picker) => {
                const targets = visibleTargets();

                if (!picker) {
                    return targets[0] || null;
                }

                const pickerTop = picker.getBoundingClientRect().top + window.scrollY;
                let nearest = null;

                for (const target of targets) {
                    const targetBottom = target.getBoundingClientRect().bottom + window.scrollY;

                    if (targetBottom <= pickerTop + 8) {
                        nearest = target;
                    }
                }

                return nearest || targets[0] || null;
            };

            const findNearestTarget = (button) => {
                const nearestTarget = findNearestTargetBefore(button.closest('[data-literacy-latex-picker-root]'));

                if (nearestTarget) {
                    return nearestTarget;
                }

                if (lastTarget && document.contains(lastTarget) && !lastTarget.disabled) {
                    return lastTarget;
                }

                return null;
            };

            const typesetPreview = (preview) => {
                if (!window.MathJax || typeof window.MathJax.typesetPromise !== 'function') {
                    return;
                }

                if (typeof window.MathJax.typesetClear === 'function') {
                    window.MathJax.typesetClear([preview]);
                }

                window.MathJax.typesetPromise([preview]).catch(() => {});
            };

            const renderPreview = (picker) => {
                const preview = picker.querySelector('[data-literacy-latex-preview]');

                if (!preview) {
                    return;
                }

                const target = findNearestTargetBefore(picker);
                const value = target?.value?.trim() || '';

                preview.textContent = value || 'Belum ada isi untuk preview.';
                preview.dataset.empty = value ? '0' : '1';
                typesetPreview(preview);
            };

            const refreshPreviews = () => {
                document
                    .querySelectorAll('[data-literacy-latex-picker-root]')
                    .forEach(renderPreview);
            };

            const schedulePreviewRefresh = () => {
                window.clearTimeout(previewTimer);
                previewTimer = window.setTimeout(refreshPreviews, 100);
            };

            const selectPlaceholder = (target, insertedStart, template, placeholder) => {
                if (!placeholder) {
                    const cursorPosition = insertedStart + template.length;
                    target.setSelectionRange(cursorPosition, cursorPosition);

                    return;
                }

                const placeholderIndex = template.indexOf(placeholder);

                if (placeholderIndex === -1) {
                    const cursorPosition = insertedStart + template.length;
                    target.setSelectionRange(cursorPosition, cursorPosition);

                    return;
                }

                const start = insertedStart + placeholderIndex;
                target.setSelectionRange(start, start + placeholder.length);
            };

            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-literacy-latex-template]');

                if (!button) {
                    return;
                }

                const target = findNearestTarget(button);

                if (!target) {
                    return;
                }

                const template = button.dataset.literacyLatexTemplate || '';
                const placeholder = button.dataset.literacyLatexPlaceholder || '';
                const start = target.selectionStart ?? target.value.length;
                const end = target.selectionEnd ?? start;
                const before = target.value.slice(0, start);
                const after = target.value.slice(end);
                const prefix = before && !before.endsWith(' ') && !before.endsWith('\n') ? ' ' : '';
                const suffix = after && !after.startsWith(' ') && !after.startsWith('\n') ? ' ' : '';
                const inserted = `${prefix}${template}${suffix}`;
                const insertedStart = start + prefix.length;

                target.value = `${before}${inserted}${after}`;
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new Event('change', { bubbles: true }));
                target.focus();
                selectPlaceholder(target, insertedStart, template, placeholder);
                lastTarget = target;
                schedulePreviewRefresh();
            });

            document.addEventListener('input', (event) => {
                if (event.target instanceof HTMLTextAreaElement && event.target.matches(targetSelector)) {
                    schedulePreviewRefresh();
                }
            });

            const ensureDomObserver = () => {
                if (!document.body || window.__literacyLatexPickerObserver) {
                    return;
                }

                window.__literacyLatexPickerObserver = new MutationObserver((mutations) => {
                    for (const mutation of mutations) {
                        for (const node of mutation.addedNodes) {
                            if (!(node instanceof Element)) {
                                continue;
                            }

                            if (
                                node.matches('[data-literacy-latex-picker-root], ' + targetSelector) ||
                                node.querySelector('[data-literacy-latex-picker-root], ' + targetSelector)
                            ) {
                                schedulePreviewRefresh();

                                return;
                            }
                        }
                    }
                });

                window.__literacyLatexPickerObserver.observe(document.body, {
                    childList: true,
                    subtree: true,
                });
            };

            document.addEventListener('DOMContentLoaded', () => {
                ensureDomObserver();
                schedulePreviewRefresh();
            });
            document.addEventListener('livewire:navigated', () => {
                ensureDomObserver();
                schedulePreviewRefresh();
            });
            window.addEventListener('load', schedulePreviewRefresh);
            ensureDomObserver();
            schedulePreviewRefresh();
        })();
    </script>
@endonce
