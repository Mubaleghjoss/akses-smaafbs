{{--
    Proteksi ringan pada teks soal dan form jawaban:
    - klik kanan (context menu) dinonaktifkan di area soal dan form jawaban;
    - copy/cut/drag teks soal diblokir;
    - paste ke kolom jawaban diblokir agar jawaban tidak ditempel dari sumber lain.

    Ini pencegah kebetulan, bukan pengamanan mutlak: siapa pun yang membuka
    DevTools tetap bisa membaca halaman. Karena itu deteksi kemiripan jawaban di
    sisi server tetap menjadi pengaman utama.
--}}
<script>
    (function () {
        'use strict';

        var AREA_SELECTOR = '[data-literacy-question], [data-literacy-answer-form], .literacy-reading-content';
        var PROMPT_SELECTOR = '[data-literacy-question], .literacy-reading-content';
        var INPUT_TAGS = { INPUT: true, TEXTAREA: true, SELECT: true };

        function closestArea(target, selector) {
            if (!target || typeof target.closest !== 'function') {
                return null;
            }

            return target.closest(selector);
        }

        function isEditable(target) {
            if (!target || !target.tagName) {
                return false;
            }

            return INPUT_TAGS[target.tagName] === true || target.isContentEditable === true;
        }

        // Klik kanan: diblokir di seluruh area soal dan form jawaban.
        document.addEventListener('contextmenu', function (event) {
            if (closestArea(event.target, AREA_SELECTOR)) {
                event.preventDefault();
            }
        });

        // Copy / cut / drag teks soal dan bacaan.
        ['copy', 'cut', 'dragstart'].forEach(function (type) {
            document.addEventListener(type, function (event) {
                var area = closestArea(event.target, PROMPT_SELECTOR);

                if (!area) {
                    return;
                }

                // Siswa tetap boleh menyalin dari kolom jawabannya sendiri.
                if (isEditable(event.target)) {
                    return;
                }

                event.preventDefault();
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

        // Seleksi teks soal lewat papan tombol (Ctrl+A / Ctrl+C) pada area soal.
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

            if (closestArea(event.target, PROMPT_SELECTOR)) {
                event.preventDefault();
            }
        });
    })();
</script>
