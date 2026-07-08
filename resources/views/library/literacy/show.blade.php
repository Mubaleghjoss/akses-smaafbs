@extends('layouts.app')

@section('content')
    @php
        $videoEmbedUrl = $material->videoEmbedUrl();
    @endphp

    <div class="flex flex-wrap gap-2">
        <a class="chip" href="{{ route('library.literacy.index') }}"><- Kembali ke Literasi Numerasi</a>
    </div>

    <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="space-y-6">
            <article class="card overflow-hidden">
                @if($material->imageUrl())
                    <img src="{{ $material->imageUrl() }}" alt="" class="max-h-[28rem] w-full object-cover">
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
            >
                @csrf
                <input type="hidden" name="integrity[tab_switch_count]" value="0" data-integrity-field="tab_switch_count">
                <input type="hidden" name="integrity[app_hidden_count]" value="0" data-integrity-field="app_hidden_count">
                <input type="hidden" name="integrity[page_leave_attempt_count]" value="0" data-integrity-field="page_leave_attempt_count">

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
                        @error('student_id')
                            <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                        @enderror
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
                            @error('student_verification')
                                <div class="mt-1 text-xs text-rose-600">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif
                @endif

                @forelse($material->questions as $index => $question)
                    @php
                        $fieldName = 'answers.'.$question->getKey();
                        $maxCharacters = max(1, (int) ($question->max_characters ?: 1000));
                        $minCharacters = min($maxCharacters, max(0, (int) ($question->min_characters ?: 0)));
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
                            <img src="{{ $question->imageUrl() }}" alt="" class="mt-3 max-h-80 w-full rounded-2xl border border-slate-200 object-contain">
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
                        >{{ old('answers.'.$question->getKey()) }}</textarea>
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
                @empty
                    <div class="rounded-2xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Materi ini belum memiliki pertanyaan.
                    </div>
                @endforelse

                <button class="btn btn-primary w-full sm:w-auto" type="submit" @disabled($students === [] || $material->questions->isEmpty())>Kirim Jawaban</button>
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
    @include('library.literacy._mathjax')
    @include('library.literacy._answer_tools', ['students' => $students])
@endpush
