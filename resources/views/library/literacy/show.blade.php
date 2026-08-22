@extends('layouts.app')

@section('content')
    @php
        $videoEmbedUrl = $material->videoEmbedUrl();
        $materialImageUrl = $material->imageUrl();
        $materialThumbnailUrl = $material->imageUrl('thumbnail');
    @endphp

    <div class="flex flex-wrap gap-2">
        <a class="chip" href="{{ route('library.literacy.index') }}"><- Kembali ke Literasi Numerasi</a>
    </div>

    @if($isDirectLinkOnly ?? false)
        <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm leading-6 text-amber-900" role="status">
            <strong>Materi dibuka melalui direct link.</strong>
            Materi ini tidak sedang tampil di daftar umum, tetapi jawaban tetap dapat dikirim melalui halaman ini.
        </div>
    @endif

    <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <article class="card overflow-hidden">
                @if($materialImageUrl)
                    <img
                        src="{{ $materialImageUrl }}"
                        srcset="{{ $materialThumbnailUrl }} 640w, {{ $materialImageUrl }} 1280w"
                        sizes="(min-width: 1024px) calc(100vw - 26rem), 100vw"
                        alt=""
                        class="max-h-[28rem] w-full object-cover"
                        width="1280"
                        height="720"
                        decoding="async"
                        fetchpriority="high"
                        data-literacy-image-open
                        data-literacy-image-caption="{{ $material->title }}"
                    >
                @endif
                <div class="p-6 md:p-7">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="chip {{ $material->programCategoryBadgeClasses() }}">{{ $material->programCategoryLabel() }}</span>
                        <span class="chip">Materi Bacaan</span>
                    </div>
                    <h1 class="mt-3 text-2xl font-semibold md:text-3xl">{{ $material->title }}</h1>
                    @if($material->google_drive_url)
                        <a class="chip mt-4 inline-flex" href="{{ $material->google_drive_url }}" target="_blank" rel="noopener">Buka Google Drive</a>
                    @endif
                    @if($videoEmbedUrl)
                        <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-slate-950">
                            <iframe
                                class="aspect-video w-full"
                                src="{{ $videoEmbedUrl }}"
                                title="Video pendukung {{ $material->title }}"
                                loading="lazy"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen
                            ></iframe>
                        </div>
                    @elseif($material->video_url)
                        <a class="chip mt-4 inline-flex" href="{{ $material->video_url }}" target="_blank" rel="noopener">Buka Video</a>
                    @endif
                    @if(filled($material->reading_content))
                        <div class="literacy-reading-content">
                            {!! $material->readingContentHtml() !!}
                        </div>
                    @endif
                </div>
            </article>

            <details class="card p-5" open>
                <summary class="cursor-pointer text-sm font-semibold text-slate-900">Arahan dan tata tertib pengerjaan</summary>
                <div class="mt-3 space-y-2 text-sm leading-6 text-slate-600">
                    {!! $material->instructionsHtml() !!}
                </div>
            </details>

            <form
                method="post"
                action="{{ route('library.literacy.store', $material->slug) }}"
                class="card space-y-5 p-6 md:p-7"
                data-literacy-answer-form
                data-literacy-integrity-form
                data-literacy-scroll-target="#status-jawaban"
                data-literacy-queue-enabled="{{ config('literacy.submission_queue.enabled', true) ? '1' : '0' }}"
                data-literacy-ticket-endpoint="{{ route('library.literacy.queue.store', $material->slug) }}"
                data-literacy-draft-key="create:{{ $material->getKey() }}"
                data-literacy-submission-success="0"
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
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Jawaban</div>
                    <h2 class="mt-2 text-xl font-semibold">Isi Pertanyaan Literasi</h2>
                    <p class="mt-1 text-sm text-slate-500">Pilih nama siswa dari data master aktif. Setelah terkirim, sistem akan memberi kode unik untuk edit.</p>
                </div>

                <div id="status-jawaban" class="scroll-mt-28 outline-none" tabindex="-1" data-literacy-submit-status>
                    @if($errors->any())
                        <div class="rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                            Periksa kembali isian yang ditandai.
                        </div>
                    @endif
                </div>

                @include('library.literacy._submission_status_panel', ['draftLabel' => 'Jawaban'])

                @if($students === [])
                    <div class="rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Data siswa aktif belum tersedia. Hubungi admin untuk melengkapi data master siswa.
                    </div>
                @else
                    @php
                        $oldStudentId = (string) old('student_id', '');
                        $selectedStudent = collect($students)->first(fn (array $student): bool => (string) $student['id'] === $oldStudentId);
                    @endphp
                    <div>
                        <label class="text-sm font-semibold text-slate-700" for="student_search">Nama Siswa *</label>
                        <div class="relative" data-literacy-student-combobox>
                            <input
                                class="input mt-2"
                                id="student_search"
                                name="student_search"
                                type="search"
                                value="{{ old('student_search', $selectedStudent['label'] ?? '') }}"
                                placeholder="Ketik nama atau kelas siswa"
                                autocomplete="off"
                                role="combobox"
                                aria-expanded="false"
                                aria-controls="student-search-results"
                                aria-autocomplete="list"
                                data-student-search
                                required
                            >
                            <input type="hidden" id="student_id" name="student_id" value="{{ $oldStudentId }}" data-student-id>
                            <div
                                id="student-search-results"
                                class="absolute left-0 right-0 z-20 mt-2 hidden max-h-72 overflow-y-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-xl"
                                role="listbox"
                                data-student-results
                            ></div>
                        </div>
                        <div class="mt-1 text-xs text-slate-500">Ketik nama atau kelas, lalu pilih siswa dari daftar yang muncul.</div>
                        <div @class([
                            'mt-2 rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700',
                            'hidden' => ! $selectedStudent,
                        ]) data-student-selected>
                            @if($selectedStudent)
                                Terpilih: {{ $selectedStudent['label'] }}
                            @endif
                        </div>
                        <div
                            @class(['mt-1 text-xs text-rose-600', 'hidden' => ! $errors->has('student_id')])
                            role="alert"
                            data-literacy-validation-for="student_id"
                        >{{ $errors->first('student_id') }}</div>
                    </div>

                    @if($material->student_verification_enabled ?? true)
                        <div>
                            <label class="text-sm font-semibold text-slate-700" for="student_verification">Verifikasi siswa *</label>
                            <input
                                class="input mt-2"
                                id="student_verification"
                                name="student_verification"
                                value="{{ old('student_verification') }}"
                                placeholder="NISN atau tanggal lahir, contoh 15/01/2010"
                                autocomplete="off"
                                data-student-verification
                            >
                            <div class="mt-1 text-xs text-slate-500" data-student-verification-help>
                                Isi NISN atau tanggal lahir siswa yang dipilih. Ini membantu mencegah nama dipakai oleh siswa lain.
                            </div>
                            <div
                                @class(['mt-1 text-xs text-rose-600', 'hidden' => ! $errors->has('student_verification')])
                                role="alert"
                                data-literacy-validation-for="student_verification"
                            >{{ $errors->first('student_verification') }}</div>
                        </div>
                    @endif
                @endif

                @forelse($material->questions as $index => $question)
                    @include('library.literacy._question_field', ['savedAnswer' => null])
                @empty
                    <div class="rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Materi ini belum memiliki pertanyaan.
                    </div>
                @endforelse

                <button class="btn btn-primary w-full sm:w-auto" type="submit" data-literacy-submit-button @disabled($students === [] || $material->questions->isEmpty())>Kirim Jawaban</button>
            </form>
        </section>

        <aside class="card h-fit space-y-5 p-5">
            <div>
                <h2 class="text-lg font-semibold">Edit Jawaban</h2>
                <form method="get" action="{{ route('library.literacy.edit.lookup') }}" class="mt-3 space-y-3">
                    <label class="text-sm font-semibold text-slate-700" for="code">Kode edit jawaban</label>
                    <input id="code" name="code" value="{{ old('code') }}" class="input uppercase" placeholder="Contoh: ABC123">
                    @error('code')
                        <div class="text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                    <button class="btn btn-secondary w-full" type="submit">Buka Edit</button>
                </form>
            </div>

            <div class="border-t border-slate-200 pt-5">
                <h2 class="text-lg font-semibold">Catatan</h2>
                <div class="mt-3 space-y-3 text-sm leading-6 text-slate-600">
                    <p>Jawaban hanya bisa dikirim satu kali untuk setiap siswa dan materi.</p>
                    <p>Simpan kode unik setelah mengirim. Kode itu dipakai untuk membuka kembali halaman edit.</p>
                    <p>Jika nama sudah mengisi dan lupa kode editnya, segera hubungi <strong class="font-semibold text-slate-900">Guru / Tim Literasi Numerasi</strong> agar kode edit dicek.</p>
                </div>
            </div>
        </aside>
    </div>
@endsection

@push('scripts')
    @if($hasLatex ?? false)
        @include('library.literacy._mathjax')
    @endif
    @include('library.literacy._answer_tools', ['students' => $students])
@endpush
