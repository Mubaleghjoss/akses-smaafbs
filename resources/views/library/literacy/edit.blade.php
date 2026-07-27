@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap gap-2">
        <a class="chip" href="{{ route('library.literacy.index') }}"><- Kembali ke Literasi Numerasi</a>
    </div>

    <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <form
            method="post"
            action="{{ route('library.literacy.update', $response->shortEditCode()) }}"
            class="card space-y-5 p-6 md:p-7"
            data-literacy-answer-form
            data-literacy-integrity-form
            data-integrity-endpoint="{{ route('library.literacy.integrity', $response->shortEditCode()) }}"
            data-literacy-scroll-target="#status-jawaban"
            data-literacy-queue-enabled="{{ config('literacy.submission_queue.enabled', true) ? '1' : '0' }}"
            data-literacy-ticket-endpoint="{{ route('library.literacy.queue.update', $response->shortEditCode()) }}"
            data-literacy-draft-key="update:{{ $response->getKey() }}"
            data-literacy-submission-success="{{ session('success') ? '1' : '0' }}"
            data-literacy-mass-mode="{{ config('literacy.submission_queue.mass_mode_enabled', true) ? '1' : '0' }}"
            data-literacy-initial-jitter-seconds="{{ config('literacy.submission_queue.initial_jitter_seconds', 30) }}"
            data-literacy-normal-jitter-seconds="{{ config('literacy.submission_queue.normal_initial_jitter_seconds', 2) }}"
            data-literacy-retry-delays="{{ implode(',', config('literacy.submission_queue.retry_delays_seconds', [5, 10, 20, 30])) }}"
            data-literacy-retry-window-seconds="{{ config('literacy.submission_queue.retry_window_seconds', 600) }}"
            data-literacy-draft-ttl-hours="{{ config('literacy.submission_queue.draft_ttl_hours', 12) }}"
        >
            @csrf
            <input type="hidden" name="submission_request_id" value="{{ old('submission_request_id', (string) \Illuminate\Support\Str::uuid()) }}" data-literacy-request-id>
            <input type="hidden" name="submission_ticket" value="{{ old('submission_ticket') }}" data-literacy-ticket>
            <input type="hidden" name="submission_queue_waited" value="{{ old('submission_queue_waited', '0') }}" data-literacy-queue-waited>
            <input type="hidden" name="submission_retry_statuses" value="{{ old('submission_retry_statuses') }}" data-literacy-retry-statuses>
            <input type="hidden" name="integrity[tab_switch_count]" value="0" data-integrity-field="tab_switch_count">
            <input type="hidden" name="integrity[app_hidden_count]" value="0" data-integrity-field="app_hidden_count">
            <input type="hidden" name="integrity[page_leave_attempt_count]" value="0" data-integrity-field="page_leave_attempt_count">

            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Edit Jawaban</div>
                <h1 class="mt-2 text-2xl font-semibold">{{ $material->title }}</h1>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="chip {{ $material->programCategoryBadgeClasses() }}">{{ $material->programCategoryLabel() }}</span>
                </div>
                <p class="mt-3 text-sm text-slate-500">Perbarui jawaban, lalu simpan kembali. Kode unik tetap sama.</p>
            </div>

            <div id="status-jawaban" class="scroll-mt-28 space-y-3 outline-none" tabindex="-1" data-literacy-submit-status>
                @if(session('success'))
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                        {{ session('success') }}
                        @if(session('edit_code'))
                            <div class="mt-2 font-semibold">Kode unik: {{ session('edit_code') }}</div>
                        @endif
                    </div>
                @endif

                @if($errors->any())
                    <div class="rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                        Periksa kembali isian yang ditandai.
                    </div>
                @endif
            </div>

            <div class="hidden rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900" role="status" aria-live="polite" data-literacy-queue-panel>
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 inline-block h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-sky-200 border-t-sky-600" aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold" data-literacy-queue-title>Menyiapkan antrean pengiriman...</div>
                        <div class="mt-1 leading-6 text-sky-800" data-literacy-queue-message>Perubahan jawaban tetap tersimpan di halaman ini. Jangan tutup halaman.</div>
                    </div>
                </div>
                <button class="mt-3 rounded-xl border border-sky-300 px-3 py-2 text-xs font-semibold text-sky-800" type="button" data-literacy-queue-cancel>Batal menunggu</button>
            </div>

            @foreach($material->questions as $index => $question)
                @php
                    $fieldName = 'answers.'.$question->getKey();
                    $maxCharacters = max(1, (int) ($question->max_characters ?: 1000));
                    $minCharacters = min($maxCharacters, max(0, (int) ($question->min_characters ?: 0)));
                    $savedAnswer = $answerMap->get($question->getKey())?->answer_text;
                @endphp
                <section class="rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="chip {{ $material->programCategoryBadgeClasses() }}">{{ $material->programCategoryLabel() }}</span>
                        <span class="chip">Pertanyaan {{ $index + 1 }}</span>
                        <span class="chip">Min. {{ number_format($minCharacters, 0, ',', '.') }} karakter</span>
                        <span class="chip">Maks. {{ number_format($maxCharacters, 0, ',', '.') }} karakter</span>
                    </div>
                    <label class="mt-3 block text-sm font-semibold leading-6 text-slate-900" for="question-{{ $question->getKey() }}">
                        {{ $question->prompt }}
                    </label>
                    @if($question->imageUrl())
                        <img
                            src="{{ $question->imageUrl() }}"
                            alt=""
                            class="mt-3 max-h-80 w-full rounded-2xl border border-slate-200 object-contain"
                            width="1280"
                            height="1280"
                            loading="lazy"
                            decoding="async"
                        >
                    @endif
                    @if($question->google_drive_url)
                        <a class="chip mt-3 inline-flex" href="{{ $question->google_drive_url }}" target="_blank" rel="noopener">Buka Lampiran</a>
                    @endif
                    <textarea
                        id="question-{{ $question->getKey() }}"
                        class="input mt-3 min-h-40"
                        name="answers[{{ $question->getKey() }}]"
                        minlength="{{ $minCharacters }}"
                        maxlength="{{ $maxCharacters }}"
                        data-literacy-answer-input
                        data-min-characters="{{ $minCharacters }}"
                        data-max-characters="{{ $maxCharacters }}"
                        @required($question->is_required)
                    >{{ old('answers.'.$question->getKey(), $savedAnswer) }}</textarea>
                    <div class="mt-2 flex flex-col gap-1 text-xs sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-slate-500" data-literacy-answer-status></div>
                        <div class="font-semibold text-slate-700" data-literacy-answer-count></div>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-slate-400 transition-all" style="width: 0%" data-literacy-answer-bar></div>
                    </div>
                    @error($fieldName)
                        <div class="mt-2 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </section>
            @endforeach

            <button class="btn btn-primary w-full sm:w-auto" type="submit" data-literacy-submit-button>Simpan Perubahan</button>
        </form>

        <aside class="card h-fit p-5">
            <h2 class="text-lg font-semibold">Detail Pengiriman</h2>
            <div class="mt-4 space-y-3 text-sm">
                <div>
                    <div class="text-xs uppercase tracking-wide text-slate-400">Kode Unik</div>
                    <div class="mt-1 break-words font-semibold">{{ $response->edit_code }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-slate-400">Siswa</div>
                    <div class="mt-1 font-semibold">{{ $response->student_name_snapshot }}</div>
                    @if($response->student_class_snapshot)
                        <div class="text-slate-500">{{ $response->student_class_snapshot }}</div>
                    @endif
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-slate-400">Dikirim</div>
                    <div class="mt-1">{{ $response->submitted_at?->format('d/m/Y H:i') ?? '-' }}</div>
                </div>
                @if($response->last_edited_at)
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-400">Edit Terakhir</div>
                        <div class="mt-1">{{ $response->last_edited_at->format('d/m/Y H:i') }}</div>
                    </div>
                @endif
            </div>

            <div class="mt-5 border-t border-slate-200 pt-5">
                <a class="btn btn-secondary w-full" href="{{ route('library.literacy.show', $material->slug) }}">
                    Isi Jawaban Murid Baru
                </a>
            </div>
        </aside>
    </div>
@endsection

@push('scripts')
    @if($hasLatex ?? false)
        @include('library.literacy._mathjax')
    @endif
    @include('library.literacy._answer_tools')
@endpush
