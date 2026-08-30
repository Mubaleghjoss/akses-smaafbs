{{--
    Proteksi teks soal dan bahan bacaan pada halaman publik literasi.

    Dua lapis:
    1. CSS  : mematikan seleksi teks (drag mouse, long-press HP) dan menu
              "Copy/Salin" bawaan iOS/Android di area soal + bacaan.
    2. JS   : memblokir klik kanan, copy/cut/drag, seleksi via papan tombol,
              dan paste ke kolom jawaban.

    CSS wajib ada: event `contextmenu` TIDAK dipicu oleh long-press di banyak
    browser mobile, sehingga tanpa `user-select:none` + `-webkit-touch-callout:none`
    siswa tetap bisa menahan jari lalu menyalin teks soal.

    Ini pencegah kebetulan, bukan pengamanan mutlak: siapa pun yang membuka
    DevTools atau melihat sumber halaman tetap bisa membaca teks. Karena itu
    deteksi kemiripan jawaban di sisi server tetap pengaman utama.
--}}
<style>
    /* Area yang teksnya tidak boleh diseleksi/disalin. */
    [data-literacy-question],
    .literacy-reading-content,
    [data-literacy-noselect] {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
        -webkit-touch-callout: none;
        -webkit-tap-highlight-color: transparent;
    }

    /* Siswa tetap harus bisa mengetik, memilih, dan mengoreksi jawabannya. */
    [data-literacy-question] input,
    [data-literacy-question] textarea,
    [data-literacy-question] select,
    [data-literacy-question] [contenteditable="true"],
    [data-literacy-answer-form] input,
    [data-literacy-answer-form] textarea,
    [data-literacy-answer-form] select,
    [data-literacy-answer-form] [contenteditable="true"] {
        -webkit-user-select: text;
        -moz-user-select: text;
        -ms-user-select: text;
        user-select: text;
        -webkit-touch-callout: default;
    }

    /* Label pilihan jawaban tetap bisa diketuk tanpa memunculkan seleksi. */
    [data-literacy-question] label {
        -webkit-touch-callout: none;
    }

    /* Gambar bacaan/soal tidak bisa ditarik keluar halaman. */
    [data-literacy-question] img,
    .literacy-reading-content img {
        -webkit-user-drag: none;
        user-select: none;
        pointer-events: auto;
    }
</style>
<script>
    (function () {
        'use strict';

        var AREA_SELECTOR = '[data-literacy-question], [data-literacy-answer-form], .literacy-reading-content';
        var PROMPT_SELECTOR = '[data-literacy-question], .literacy-reading-content';
        var INPUT_TAGS = { INPUT: true, TEXTAREA: true, SELECT: true };

        function closestArea(target, selector) {
            if (!target) {
                return null;
            }

            // Node teks tidak punya closest(); naik dulu ke elemen induknya.
            var element = target.nodeType === 3 ? target.parentElement : target;

            if (!element || typeof element.closest !== 'function') {
                return null;
            }

            return element.closest(selector);
        }

        function isEditable(target) {
            var element = target && target.nodeType === 3 ? target.parentElement : target;

            if (!element || !element.tagName) {
                return false;
            }

            return INPUT_TAGS[element.tagName] === true || element.isContentEditable === true;
        }

        // Klik kanan: diblokir di seluruh area soal dan form jawaban.
        document.addEventListener('contextmenu', function (event) {
            if (isEditable(event.target)) {
                return;
            }

            if (closestArea(event.target, AREA_SELECTOR)) {
                event.preventDefault();
            }
        });

        // Mulai menyeleksi teks soal/bacaan langsung dibatalkan (drag mouse).
        document.addEventListener('selectstart', function (event) {
            if (isEditable(event.target)) {
                return;
            }

            if (closestArea(event.target, PROMPT_SELECTOR)) {
                event.preventDefault();
            }
        });

        // Copy / cut / drag teks soal dan bacaan.
        ['copy', 'cut', 'dragstart'].forEach(function (type) {
            document.addEventListener(type, function (event) {
                // Siswa tetap boleh menyalin dari kolom jawabannya sendiri.
                if (isEditable(event.target)) {
                    return;
                }

                if (closestArea(event.target, PROMPT_SELECTOR)) {
                    event.preventDefault();

                    return;
                }

                // Long-press pada HP dapat menargetkan body, bukan elemen soal.
                // Jika hasil seleksi berada di dalam area soal, tetap blokir.
                var selection = window.getSelection ? window.getSelection() : null;

                if (!selection || selection.isCollapsed || selection.rangeCount === 0) {
                    return;
                }

                if (closestArea(selection.getRangeAt(0).commonAncestorContainer, PROMPT_SELECTOR)) {
                    event.preventDefault();
                }
            });
        });

        // Tempel ke kolom jawaban diblokir.
        document.addEventListener('paste', function (event) {
            if (!isEditable(event.target)) {
                return;
            }

            if (closestArea(event.target, '[data-literacy-answer-form]')) {
                event.preventDefault();
            }
        });

        // Seleksi teks soal lewat papan tombol (Ctrl+A / Ctrl+C / Ctrl+X).
        document.addEventListener('keydown', function (event) {
            if (!(event.ctrlKey || event.metaKey)) {
                return;
            }

            var key = (event.key || '').toLowerCase();

            if (key !== 'a' && key !== 'c' && key !== 'x') {
                return;
            }

            if (isEditable(event.target)) {
                return;
            }

            event.preventDefault();
        });
    })();
</script>
