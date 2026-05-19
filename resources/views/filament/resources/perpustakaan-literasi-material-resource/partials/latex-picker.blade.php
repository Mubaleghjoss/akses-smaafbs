@php
    $latexGroups = [
        [
            'title' => '1. Pangkat dan Akar',
            'items' => [
                ['label' => 'Pangkat biasa', 'template' => '\(x^{2}\)', 'placeholder' => 'x'],
                ['label' => 'Pangkat kompleks', 'template' => '\(x^{2n+1}\)', 'placeholder' => 'x'],
                ['label' => 'Akar kuadrat', 'template' => '\(\sqrt{x}\)', 'placeholder' => 'x'],
                ['label' => 'Akar pangkat n', 'template' => '\(\sqrt[n]{x}\)', 'placeholder' => 'n'],
            ],
        ],
        [
            'title' => '2. Pecahan dan Pembagian',
            'items' => [
                ['label' => 'Pecahan biasa', 'template' => '\(\frac{atas}{bawah}\)', 'placeholder' => 'atas'],
                ['label' => 'Pecahan bertingkat', 'template' => '\(\frac{x}{\frac{y}{z}}\)', 'placeholder' => 'x'],
                ['label' => 'Pembagian', 'template' => '\(a \div b\)', 'placeholder' => 'a'],
                ['label' => 'Rasio', 'template' => '\(a : b\)', 'placeholder' => 'a'],
            ],
        ],
        [
            'title' => '3. Simbol Operasi & Relasi',
            'items' => [
                ['label' => 'Perkalian titik', 'template' => '\(a \cdot b\)', 'placeholder' => 'a'],
                ['label' => 'Perkalian silang', 'template' => '\(a \times b\)', 'placeholder' => 'a'],
                ['label' => 'Kurang lebih', 'template' => '\(\pm\)', 'placeholder' => ''],
                ['label' => 'Tidak sama dengan', 'template' => '\(\neq\)', 'placeholder' => ''],
                ['label' => 'Kurang dari sama dengan', 'template' => '\(\leq\)', 'placeholder' => ''],
                ['label' => 'Lebih dari sama dengan', 'template' => '\(\geq\)', 'placeholder' => ''],
            ],
        ],
        [
            'title' => '4. Matriks (Paling Sering Dicari)',
            'items' => [
                ['label' => 'Matriks 2 x 2', 'template' => '\(\begin{pmatrix} a & b \\\\ c & d \end{pmatrix}\)', 'placeholder' => 'a'],
                ['label' => 'Matriks 3 x 3', 'template' => '\(\begin{pmatrix} a & b & c \\\\ d & e & f \\\\ g & h & i \end{pmatrix}\)', 'placeholder' => 'a'],
                ['label' => 'Determinan 2 x 2', 'template' => '\(\begin{vmatrix} a & b \\\\ c & d \end{vmatrix}\)', 'placeholder' => 'a'],
                ['label' => 'Sistem persamaan', 'template' => '\(\begin{cases} ax + by = c \\\\ dx + ey = f \end{cases}\)', 'placeholder' => 'a'],
            ],
        ],
    ];
@endphp

<div
    data-literacy-latex-picker-root
    class="rounded-xl border border-gray-200 bg-white/80 p-3 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
>
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="font-medium text-gray-950 dark:text-white">Template Rumus Cepat</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Klik textarea materi/pertanyaan, lalu pilih rumus.</div>
        </div>
        <div class="text-xs text-gray-500 dark:text-gray-400">LaTeX otomatis masuk ke posisi kursor.</div>
    </div>

    <div class="mt-3 space-y-2">
        @foreach($latexGroups as $group)
            <details @class([
                'rounded-lg border border-gray-200 bg-gray-50/70 p-2 dark:border-white/10 dark:bg-white/5',
            ]) @if($loop->first) open @endif>
                <summary class="cursor-pointer text-sm font-semibold text-gray-900 dark:text-white">
                    {{ $group['title'] }}
                </summary>
                <div class="mt-3 grid gap-2 md:grid-cols-2">
                    @foreach($group['items'] as $item)
                        <button
                            type="button"
                            data-literacy-latex-template="{{ $item['template'] }}"
                            data-literacy-latex-placeholder="{{ $item['placeholder'] }}"
                            class="group flex min-h-16 w-full flex-col gap-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-left transition hover:border-primary-400 hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-400 dark:hover:bg-primary-500/10"
                        >
                            <span class="font-medium text-gray-900 dark:text-white">{{ $item['label'] }}</span>
                            <span class="break-all rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $item['template'] }}</span>
                            <span class="text-sm text-gray-700 dark:text-gray-200">{{ $item['template'] }}</span>
                        </button>
                    @endforeach
                </div>
            </details>
        @endforeach
    </div>

    <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50/80 p-3 dark:border-white/10 dark:bg-gray-950/40">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div class="font-medium text-gray-950 dark:text-white">Preview Tampilan</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Preview mengikuti textarea materi/pertanyaan di atas.</div>
        </div>
        <div
            data-literacy-latex-preview
            class="mt-3 min-h-20 max-w-full overflow-x-auto whitespace-pre-wrap rounded-lg border border-dashed border-gray-300 bg-white p-3 text-sm leading-6 text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-gray-100"
        >Belum ada isi untuk preview.</div>
    </div>
</div>
