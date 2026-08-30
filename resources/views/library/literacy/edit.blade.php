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
            data-literacy-event-endpoint="{{ route('library.literacy.submission-event', $material->slug) }}"
        >
            @csrf
            <input type="hidden" name="submission_request_id" value="{{ old('submission_request_id', (string) \Illuminate\Support\Str::uuid()) }}" data-literacy-request-id>
            <input type="hidden" name="submission_ticket" value="{{ old('submission_ticket') }}" data-literacy-ticket>
            <input type="hidden" name="submission_queue_waited" value="{{ old('submission_queue_waited', '0') }}" data-literacy-queue-waited>
            <input type="hidden" name="submission_retry_statuses" value="{{ old('submission_retry_statuses') }}" data-literacy-retry-statuses>
            <input type="hidden" name="integrity[app_hidden_count]" value="0" data-integrity-field="app_hidden_count">

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

            @include('library.literacy._submission_status_panel', ['draftLabel' => 'Perubahan jawaban'])

            @foreach($material->questions as $index => $question)
                @include('library.literacy._question_field', [
                    'savedAnswer' => $answerMap->get($question->getKey()),
                ])
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
    @include('library.literacy._copy_guard')
@endpush
