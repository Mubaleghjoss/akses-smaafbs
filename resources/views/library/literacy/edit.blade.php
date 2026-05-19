@extends('layouts.app')

@section('content')
    @include('library._nav')

    <a class="chip" href="{{ route('library.literacy.index') }}"><- Kembali ke Literacy Habituation Program</a>

    <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <form method="post" action="{{ route('library.literacy.update', $response->shortEditCode()) }}" class="card space-y-5 p-6 md:p-7">
            @csrf

            <div>
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Edit Jawaban</div>
                <h1 class="mt-2 text-2xl font-semibold">{{ $material->title }}</h1>
                <p class="mt-1 text-sm text-slate-500">Perbarui jawaban, lalu simpan kembali. Kode unik tetap sama.</p>
            </div>

            @if(session('success'))
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                    @if(session('edit_code'))
                        <div class="mt-2 font-semibold">Kode unik: {{ session('edit_code') }}</div>
                    @endif
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    Periksa kembali isian yang ditandai.
                </div>
            @endif

            @foreach($material->questions as $index => $question)
                @php
                    $fieldName = 'answers.'.$question->getKey();
                    $maxCharacters = max(1, (int) ($question->max_characters ?: 1000));
                    $minCharacters = min($maxCharacters, max(0, (int) ($question->min_characters ?: 0)));
                    $savedAnswer = $answerMap->get($question->getKey())?->answer_text;
                @endphp
                <section class="rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
                    <div class="flex flex-wrap items-center gap-2">
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
                        @required($question->is_required)
                    >{{ old('answers.'.$question->getKey(), $savedAnswer) }}</textarea>
                    @error($fieldName)
                        <div class="mt-2 text-xs text-rose-600">{{ $message }}</div>
                    @enderror
                </section>
            @endforeach

            <button class="btn btn-primary w-full sm:w-auto" type="submit">Simpan Perubahan</button>
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
        </aside>
    </div>
@endsection

@push('scripts')
    @include('library.literacy._mathjax')
@endpush
