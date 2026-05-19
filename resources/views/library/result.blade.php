@extends('layouts.app')

@section('content')
    @include('library._nav')

    <div class="card p-6 reveal">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Perpustakaan</div>
                <h1 class="mt-2 text-2xl font-semibold">Input hasil literasi</h1>
                <p class="mt-1 text-sm text-slate-500">Gunakan kode singkat dari aktivitas untuk menyimpan atau mengedit hasil bacaan.</p>
            </div>
            <a class="btn btn-secondary w-full sm:w-auto" href="{{ route('library.activities.create') }}">Form Aktivitas</a>
        </div>

        @if(session('success'))
            <div class="mt-4 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <form method="post" action="{{ route('library.activities.result.store') }}" class="card space-y-4 p-6" data-literacy-result-form>
            @csrf

            @if($errors->any())
                <div class="rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    Periksa kembali isian yang ditandai.
                </div>
            @endif

            <div>
                <label class="text-sm font-semibold text-slate-700" for="activity_code">Kode Singkat / Kode Lengkap *</label>
                <input class="input mt-2 uppercase" id="activity_code" name="activity_code" value="{{ old('activity_code', $code) }}" placeholder="Contoh: I8EVPG" required>
                @error('activity_code')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
                <div class="mt-2 text-xs text-slate-500" data-code-lookup-status>
                    Ketik 6 karakter kode singkat. Jika sudah pernah diisi, hasil bacaan akan muncul otomatis.
                </div>
            </div>

            <div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label class="text-sm font-semibold text-slate-700" for="result_text">Hasil Bacaan *</label>
                    <button class="btn btn-secondary w-full sm:w-auto" type="button" data-speech-toggle>Mulai Suara</button>
                </div>
                <textarea class="input mt-2 min-h-52" id="result_text" name="result_text" required data-speech-target>{{ old('result_text', $activity?->result_text) }}</textarea>
                <div class="mt-2 text-xs text-slate-500" data-speech-status>Input suara tersedia di Chrome/Edge dengan izin mikrofon.</div>
                @error('result_text')
                    <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                @enderror
            </div>

            <div class="grid gap-2 sm:flex sm:flex-wrap">
                <button class="btn btn-primary w-full sm:w-auto" type="submit">Simpan Hasil Literasi</button>
                <a class="btn btn-secondary w-full sm:w-auto" href="{{ route('library.activities.result') }}">Reset</a>
            </div>
        </form>

        <aside class="card h-fit p-5">
            <h2 class="text-lg font-semibold">Detail aktivitas</h2>

            @if($activity)
                @php
                    $shortCode = \Illuminate\Support\Str::afterLast($activity->activity_code, '-');
                @endphp
                <div class="mt-4 space-y-3 text-sm">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-400">Kode Singkat</div>
                        <div class="mt-1 inline-flex rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-semibold tracking-[0.18em] text-slate-900">{{ $shortCode }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-400">Kode Lengkap</div>
                        <div class="break-words font-semibold">{{ $activity->activity_code }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-400">Nama</div>
                        <div class="font-semibold">{{ $activity->participant_name }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-400">Buku</div>
                        <div class="font-semibold">{{ $activity->book_title_snapshot }}</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <span class="chip">{{ \App\Models\PerpustakaanLiterasiActivity::purposeLabel($activity->purpose) }}</span>
                        <span class="chip">{{ \App\Models\PerpustakaanLiterasiActivity::resultStatusLabel($activity->result_status) }}</span>
                    </div>
                    @if($activity->result_submitted_at)
                        <div class="text-xs text-slate-500">Dikirim {{ $activity->result_submitted_at->format('d M Y H:i') }}</div>
                    @endif
                </div>
            @elseif($code !== '')
                <div class="mt-4 text-sm text-slate-500">{{ $lookupError ?? 'Kode literasi tidak ditemukan.' }}</div>
            @else
                <div class="mt-4 text-sm text-slate-500">Masukkan kode literasi untuk menampilkan detail aktivitas.</div>
            @endif
        </aside>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.querySelector('[data-literacy-result-form]');
            if (!form) {
                return;
            }

            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const lookupUrl = @json(route('library.activities.result.lookup'));
            const codeInput = form.querySelector('#activity_code');
            const button = form.querySelector('[data-speech-toggle]');
            const status = form.querySelector('[data-speech-status]');
            const lookupStatus = form.querySelector('[data-code-lookup-status]');
            const target = form.querySelector('[data-speech-target]');
            let lookupTimer = null;
            let lastLoadedCode = '';

            const normalizeCode = (value) => value.trim().toUpperCase().replace(/\s+/g, '');

            const shouldLookup = (code) => code.length === 6 || (code.startsWith('LIT-') && code.length >= 15);

            const loadExistingResult = async () => {
                const code = normalizeCode(codeInput.value);
                codeInput.value = code;

                if (code === '' || !shouldLookup(code)) {
                    lookupStatus.textContent = 'Ketik 6 karakter kode singkat. Jika sudah pernah diisi, hasil bacaan akan muncul otomatis.';
                    return;
                }

                if (code === lastLoadedCode) {
                    return;
                }

                lookupStatus.textContent = 'Mengecek kode literasi...';

                try {
                    const response = await fetch(`${lookupUrl}?code=${encodeURIComponent(code)}`, {
                        headers: {
                            Accept: 'application/json',
                        },
                    });

                    const payload = await response.json();

                    if (!response.ok || !payload.found) {
                        lookupStatus.textContent = payload.message || 'Kode literasi tidak ditemukan.';
                        return;
                    }

                    lastLoadedCode = code;
                    target.value = payload.result_text || '';
                    target.dispatchEvent(new Event('input', { bubbles: true }));

                    const suffix = payload.result_text
                        ? 'Hasil bacaan lama sudah dimuat dan bisa diedit.'
                        : 'Belum ada hasil bacaan tersimpan untuk kode ini.';

                    lookupStatus.textContent = `Data ditemukan: ${payload.participant_name} - ${payload.book_title}. ${suffix}`;
                } catch (error) {
                    lookupStatus.textContent = 'Tidak bisa mengecek kode saat ini. Coba tekan Enter atau muat ulang halaman dengan kode tersebut.';
                }
            };

            codeInput.addEventListener('input', () => {
                window.clearTimeout(lookupTimer);
                lookupTimer = window.setTimeout(loadExistingResult, 450);
            });

            codeInput.addEventListener('blur', loadExistingResult);

            if (shouldLookup(normalizeCode(codeInput.value))) {
                window.setTimeout(loadExistingResult, 250);
            }

            if (!SpeechRecognition) {
                button.disabled = true;
                button.classList.add('opacity-60');
                status.textContent = 'Browser ini belum mendukung input suara. Gunakan Chrome atau Edge terbaru.';
                return;
            }

            const recognition = new SpeechRecognition();
            recognition.lang = 'id-ID';
            recognition.continuous = true;
            recognition.interimResults = true;

            let listening = false;

            const appendText = (text) => {
                const current = target.value.trim();
                const next = text.trim();

                if (next === '') {
                    return;
                }

                target.value = current === '' ? next : `${current} ${next}`;
                target.dispatchEvent(new Event('input', { bubbles: true }));
            };

            recognition.onstart = () => {
                listening = true;
                button.textContent = 'Stop Suara';
                status.textContent = 'Mendengarkan. Bicara dengan jelas, lalu tekan Stop Suara jika selesai.';
            };

            recognition.onend = () => {
                listening = false;
                button.textContent = 'Mulai Suara';
                status.textContent = 'Input suara berhenti. Anda masih bisa mengedit teks sebelum menyimpan.';
            };

            recognition.onerror = (event) => {
                status.textContent = event.error === 'not-allowed'
                    ? 'Izin mikrofon ditolak. Aktifkan izin mikrofon di browser.'
                    : 'Input suara berhenti karena browser tidak bisa membaca audio.';
            };

            recognition.onresult = (event) => {
                let finalTranscript = '';
                let interimTranscript = '';

                for (let i = event.resultIndex; i < event.results.length; i += 1) {
                    const transcript = event.results[i][0].transcript;

                    if (event.results[i].isFinal) {
                        finalTranscript += transcript;
                    } else {
                        interimTranscript += transcript;
                    }
                }

                if (finalTranscript.trim() !== '') {
                    appendText(finalTranscript);
                }

                if (interimTranscript.trim() !== '') {
                    status.textContent = `Mendengarkan: ${interimTranscript.trim()}`;
                }
            };

            button.addEventListener('click', () => {
                if (listening) {
                    recognition.stop();
                    return;
                }

                try {
                    recognition.start();
                } catch (error) {
                    status.textContent = 'Input suara sudah berjalan. Tekan Stop Suara jika ingin berhenti.';
                }
            });
        })();
    </script>
@endpush
