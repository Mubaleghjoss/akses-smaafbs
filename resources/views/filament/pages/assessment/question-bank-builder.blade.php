<x-filament-panels::page>
    <div class="space-y-6">
        <section class="overflow-hidden rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-50 via-white to-amber-50 p-5 shadow-sm dark:border-primary-500/20 dark:from-primary-950/30 dark:via-gray-900 dark:to-gray-900 sm:p-7">
            <div class="max-w-3xl">
                <span class="inline-flex items-center gap-2 rounded-full bg-primary-100 px-3 py-1 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                    <x-filament::icon icon="heroicon-o-document-text" class="h-4 w-4" />
                    Fondasi ujian online
                </span>
                <h2 class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">Susun naskah soal, lalu siapkan untuk dikerjakan secara online.</h2>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-gray-700 dark:text-gray-300">
                    Halaman tahap 1 ini menerjemahkan struktur dokumen Word menjadi alur kerja yang konsisten. Belum ada soal atau kunci jawaban yang disimpan, sehingga setiap guru tetap hanya melihat ruang kerja pribadinya dan tidak ada data soal bersama yang terbuka.
                </p>
            </div>
        </section>

        <section aria-labelledby="question-builder-flow-title">
            <div class="mb-3">
                <h2 id="question-builder-flow-title" class="text-lg font-bold">Alur Penyusunan Soal</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Ikuti urutan ini saat mengubah template Word menjadi paket ujian online.</p>
            </div>
            <ol class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['step' => '01', 'title' => 'Identitas ujian', 'copy' => 'Tentukan judul, mapel, kelas, periode, durasi, dan pengampu.'],
                    ['step' => '02', 'title' => 'Petunjuk peserta', 'copy' => 'Salin dan rapikan aturan pengerjaan dari dokumen menjadi petunjuk yang mudah dibaca.'],
                    ['step' => '03', 'title' => 'Susun tipe soal', 'copy' => 'Kelompokkan PG kompleks, PG, benar/salah, dan essay sesuai naskah.'],
                    ['step' => '04', 'title' => 'Kunci dan bobot', 'copy' => 'Tetapkan jawaban benar, rubrik essay, serta bobot tiap butir sebelum ditinjau.'],
                    ['step' => '05', 'title' => 'Preview naskah', 'copy' => 'Periksa urutan, opsi jawaban, media pendukung, dan total bobot seperti tampilan siswa.'],
                    ['step' => '06', 'title' => 'Validasi', 'copy' => 'Pastikan semua soal lengkap, kunci tersedia, dan bobot memenuhi aturan ujian.'],
                    ['step' => '07', 'title' => 'Siapkan publish', 'copy' => 'Setelah valid, paket dapat diterbitkan ke ujian online pada tahap berikutnya.'],
                ] as $item)
                    <li class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <span class="text-xs font-black tracking-[0.2em] text-primary-600 dark:text-primary-300">{{ $item['step'] }}</span>
                        <h3 class="mt-2 text-base font-bold">{{ $item['title'] }}</h3>
                        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $item['copy'] }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.6fr)]">
            <article class="rounded-2xl border border-dashed border-primary-300 bg-primary-50/50 p-5 dark:border-primary-500/40 dark:bg-primary-950/20">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                        <x-filament::icon icon="heroicon-o-pencil-square" class="h-6 w-6" />
                    </span>
                    <div>
                        <h2 class="text-lg font-bold">Ruang kerja soal akan hadir di tahap berikutnya</h2>
                        <p class="mt-1 text-sm leading-6 text-gray-700 dark:text-gray-300">Tahap berikutnya menambahkan penyimpanan bank soal per guru, editor butir, import Word terarah, preview, validasi, dan alur publish tanpa mengganggu modul nilai yang sudah berjalan.</p>
                    </div>
                </div>
            </article>

            <aside class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h2 class="text-base font-bold">Batas akses tahap 1</h2>
                @if ($this->canPrepareAdvancedFeatures())
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">Admin dan Kurikulum akan menjadi pihak yang dapat menyiapkan import dan publish setelah fitur tersebut tersedia.</p>
                @else
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">Guru dapat melihat alur penyusunan untuk pekerjaannya sendiri. Import dan publish akan dibatasi untuk Admin atau Kurikulum.</p>
                @endif
                <span class="mt-4 inline-flex cursor-not-allowed rounded-lg bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">Import dan publish belum tersedia</span>
            </aside>
        </section>
    </div>
</x-filament-panels::page>
