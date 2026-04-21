@extends('layouts.app')

@section('content')
    <a class="chip" href="{{ route('home') }}"><- Kembali ke beranda</a>

    <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
        <section class="card p-6 md:p-7">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Survei Publik</div>
                    <h1 class="mt-3 text-2xl font-semibold text-balance md:text-3xl">{{ $survei->title }}</h1>
                </div>
                <span class="chip">{{ \App\Models\Survei::audienceLabel($survei->audience_type) }}</span>
            </div>

            @if(filled($survei->description))
                <p class="mt-4 text-sm leading-6 text-slate-600 md:text-base">{{ $survei->description }}</p>
            @endif

            @if (session('status'))
                <div class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($submission)
                <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div class="text-sm font-semibold text-slate-900">Survei sudah diisi.</div>
                    <p class="mt-2 text-sm leading-6 text-slate-600">
                        Jawaban untuk {{ $target->recipientName() }} sudah kami terima pada {{ $submission->submitted_at?->format('d/m/Y H:i') ?? '-' }}.
                    </p>
                </div>
            @elseif(! $survei->is_active || ($survei->opens_at && $survei->opens_at->isFuture()) || ($survei->closes_at && $survei->closes_at->isPast()))
                <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Survei ini sedang tidak tersedia untuk diisi.
                </div>
            @else
                <form class="mt-6 space-y-5" method="POST" action="{{ route('survei.public.submit', $target->access_token) }}">
                    @csrf

                    @foreach($survei->questions as $index => $question)
                        @php
                            $fieldName = 'answers.'.$question->getKey();
                            $oldValue = old('answers.'.$question->getKey());
                        @endphp

                        <div class="rounded-2xl border border-slate-200 bg-white p-4 md:p-5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-600">
                                    Pertanyaan {{ $index + 1 }}
                                </span>
                                @if($question->is_required)
                                    <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-700">
                                        Wajib
                                    </span>
                                @endif
                            </div>

                            <label class="mt-3 block text-sm font-medium leading-6 text-slate-900" for="question-{{ $question->getKey() }}">
                                {{ $question->prompt }}
                            </label>

                            <div class="mt-3">
                                @if($question->question_type === \App\Models\SurveiQuestion::TYPE_LONG_TEXT)
                                    <textarea
                                        id="question-{{ $question->getKey() }}"
                                        name="answers[{{ $question->getKey() }}]"
                                        rows="4"
                                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:bg-white"
                                    >{{ $oldValue }}</textarea>
                                @elseif($question->question_type === \App\Models\SurveiQuestion::TYPE_SINGLE_CHOICE)
                                    <div class="space-y-2">
                                        @foreach($question->normalizedOptions() as $option)
                                            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 transition hover:border-slate-300 hover:bg-white">
                                                <input
                                                    type="radio"
                                                    name="answers[{{ $question->getKey() }}]"
                                                    value="{{ $option }}"
                                                    @checked((string) $oldValue === (string) $option)
                                                    class="mt-1 h-4 w-4 border-slate-300 text-sky-600 focus:ring-sky-500"
                                                >
                                                <span>{{ $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif($question->question_type === \App\Models\SurveiQuestion::TYPE_RATING)
                                    <div class="grid grid-cols-5 gap-2">
                                        @foreach(range(1, 5) as $score)
                                            <label class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 px-3 py-4 text-center text-sm font-semibold text-slate-700 transition hover:border-sky-300 hover:bg-white">
                                                <input
                                                    type="radio"
                                                    name="answers[{{ $question->getKey() }}]"
                                                    value="{{ $score }}"
                                                    @checked((string) $oldValue === (string) $score)
                                                    class="sr-only"
                                                >
                                                <span class="text-lg">{{ $score }}</span>
                                                <span class="mt-1 text-[11px] uppercase tracking-[0.18em] text-slate-400">Skor</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <input
                                        id="question-{{ $question->getKey() }}"
                                        type="text"
                                        name="answers[{{ $question->getKey() }}]"
                                        value="{{ $oldValue }}"
                                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-sky-400 focus:bg-white"
                                    >
                                @endif
                            </div>

                            @error($fieldName)
                                <div class="mt-2 text-sm text-rose-600">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach

                    <button type="submit" class="btn btn-primary w-full sm:w-auto">Kirim Jawaban</button>
                </form>
            @endif
        </section>

        <aside class="space-y-4">
            <div class="card p-5">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Target Pengisian</div>
                <div class="mt-3 text-lg font-semibold text-slate-900">{{ $target->recipientName() }}</div>
                @if(filled($target->recipientContext()))
                    <div class="mt-2 text-sm text-slate-600">{{ $target->recipientContext() }}</div>
                @endif
            </div>

            <div class="card p-5">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-400">Petunjuk</div>
                <ul class="mt-3 space-y-2 text-sm leading-6 text-slate-600">
                    <li>Isi setiap pertanyaan sesuai kondisi sebenarnya.</li>
                    <li>Link ini khusus untuk target di atas dan tidak perlu dibagikan ke pihak lain.</li>
                    <li>Setelah jawaban dikirim, form akan otomatis ditutup.</li>
                </ul>
            </div>
        </aside>
    </div>
@endsection
