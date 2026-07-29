@php
    $fieldName = 'answers.'.$question->getKey();
    $savedPayload = $savedAnswer?->answer_payload ?? [];
    $maxCharacters = max(1, (int) ($question->max_characters ?: 1000));
    $minCharacters = min($maxCharacters, max(0, (int) ($question->min_characters ?: 0)));
@endphp

<section
    class="rounded-2xl border border-slate-200 bg-white p-4 md:p-5"
    data-literacy-question
    data-question-type="{{ $question->question_type ?: \App\Models\PerpustakaanLiterasiQuestion::TYPE_ESSAY }}"
>
    <div class="flex flex-wrap items-center gap-2">
        <span class="chip {{ $material->programCategoryBadgeClasses() }}">{{ $material->programCategoryLabel() }}</span>
        <span class="chip">Pertanyaan {{ $index + 1 }}</span>
        <span class="chip">{{ \App\Models\PerpustakaanLiterasiQuestion::typeLabel($question->question_type) }}</span>
        @if($question->isEssay())
            <span class="chip">Min. {{ number_format($minCharacters, 0, ',', '.') }} karakter</span>
            <span class="chip">Maks. {{ number_format($maxCharacters, 0, ',', '.') }} karakter</span>
        @else
            <span class="chip">{{ number_format($question->objectiveItemCount(), 0, ',', '.') }} poin</span>
        @endif
    </div>

    <div class="mt-3 whitespace-pre-line break-words text-sm font-semibold leading-6 text-slate-900">
        {{ $question->prompt }}
    </div>

    @if($question->imageUrl())
        <button type="button" class="mt-3 block w-full cursor-zoom-in rounded-2xl text-left focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2" data-literacy-image-open data-literacy-image-caption="Gambar pertanyaan {{ $index + 1 }}">
            <img
                src="{{ $question->imageUrl() }}"
                alt="Gambar pendukung pertanyaan {{ $index + 1 }}"
                class="max-h-80 w-full rounded-2xl border border-slate-200 object-contain"
                width="1280"
                height="1280"
                loading="lazy"
                decoding="async"
            >
            <span class="mt-1 block text-center text-xs font-semibold text-sky-700">Ketuk gambar untuk memperbesar</span>
        </button>
    @endif

    @if($question->google_drive_url)
        <a class="chip mt-3 inline-flex" href="{{ $question->google_drive_url }}" target="_blank" rel="noopener">Buka Lampiran</a>
    @endif

    @if($question->isTrueFalse())
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">
            Baca setiap pernyataan dan pasangan dengan teliti. Jangan memilih secara acak; jawaban tiap butir diperiksa otomatis.
        </div>
        <div class="mt-3 overflow-hidden rounded-2xl border border-slate-200" role="group" aria-label="Jawaban Benar atau Salah pertanyaan {{ $index + 1 }}">
            <div class="hidden grid-cols-[minmax(0,1fr)_6rem_6rem] bg-slate-100 px-4 py-2 text-xs font-bold uppercase tracking-wide text-slate-600 md:grid">
                <span>Pernyataan</span>
                <span class="text-center">Benar</span>
                <span class="text-center">Salah</span>
            </div>
            @foreach($question->trueFalseItems() as $itemIndex => $item)
                @php
                    $savedValue = data_get($savedPayload, 'items.'.$item['id']);
                    $selectedValue = old(
                        'answers.'.$question->getKey().'.items.'.$item['id'],
                        $savedValue === null ? null : ($savedValue ? '1' : '0'),
                    );
                @endphp
                <div class="border-t border-slate-200 p-4 first:border-t-0 md:grid md:grid-cols-[minmax(0,1fr)_6rem_6rem] md:items-center md:gap-0 md:py-3">
                    <div class="whitespace-pre-line break-words text-sm font-medium leading-6 text-slate-800">
                        <span class="mr-1 font-bold text-slate-500">{{ $itemIndex + 1 }}.</span>{{ $item['statement'] }}
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 md:contents">
                        @foreach(['1' => 'Benar', '0' => 'Salah'] as $value => $label)
                            <label class="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50 has-[:checked]:text-sky-800 md:mx-2">
                                <input
                                    type="radio"
                                    name="answers[{{ $question->getKey() }}][items][{{ $item['id'] }}]"
                                    value="{{ $value }}"
                                    data-literacy-answer-control
                                    @checked((string) $selectedValue === $value)
                                    @required($question->is_required)
                                >
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @elseif($question->isMatching())
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900">
            Baca setiap pernyataan dan pasangan dengan teliti. Jangan memilih secara acak; jawaban tiap butir diperiksa otomatis.
        </div>
        <div class="mt-3 space-y-3" data-literacy-matching-group>
            @foreach($question->matchingLeftItems() as $itemIndex => $item)
                @php
                    $selectedTarget = old(
                        'answers.'.$question->getKey().'.pairs.'.$item['id'],
                        data_get($savedPayload, 'pairs.'.$item['id']),
                    );
                @endphp
                <div class="rounded-2xl border border-slate-200 p-3 md:grid md:grid-cols-[minmax(0,1fr)_2rem_minmax(12rem,0.8fr)] md:items-center md:gap-3">
                    <div class="whitespace-pre-line break-words text-sm font-medium leading-6 text-slate-800">
                        <span class="mr-1 font-bold text-slate-500">{{ $itemIndex + 1 }}.</span>{{ $item['label'] }}
                    </div>
                    <div class="hidden text-center text-xl font-bold text-sky-600 md:block" aria-hidden="true">→</div>
                    <label class="mt-2 block md:mt-0">
                        <span class="mb-1 block text-xs font-semibold text-slate-500 md:hidden">Pilih pasangan</span>
                        <select
                            class="input"
                            name="answers[{{ $question->getKey() }}][pairs][{{ $item['id'] }}]"
                            data-literacy-answer-control
                            data-literacy-matching-select
                            @required($question->is_required)
                        >
                            <option value="">Pilih pasangan...</option>
                            @foreach($question->matchingRightItems() as $targetIndex => $target)
                                <option value="{{ $target['id'] }}" @selected((string) $selectedTarget === $target['id'])>
                                    {{ chr(65 + $targetIndex) }}. {{ $target['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
            @endforeach
        </div>
    @else
        <textarea
            id="question-{{ $question->getKey() }}"
            class="input mt-3 min-h-40"
            name="answers[{{ $question->getKey() }}]"
            minlength="{{ $minCharacters }}"
            maxlength="{{ $maxCharacters }}"
            data-literacy-answer-input
            data-literacy-answer-control
            data-min-characters="{{ $minCharacters }}"
            data-max-characters="{{ $maxCharacters }}"
            aria-describedby="question-{{ $question->getKey() }}-status question-{{ $question->getKey() }}-count"
            @required($question->is_required)
        >{{ old('answers.'.$question->getKey(), $savedAnswer?->answer_text) }}</textarea>

        @if($question->speech_input_enabled)
            <div class="mt-3 rounded-2xl border border-sky-200 bg-sky-50 p-3" data-literacy-speech data-speech-language="id-ID">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <button type="button" class="btn btn-secondary w-full sm:w-auto" data-literacy-speech-toggle data-speech-target="question-{{ $question->getKey() }}">
                        Jawab dengan Suara
                    </button>
                    <span class="text-xs font-medium leading-5 text-sky-900" aria-live="polite" data-literacy-speech-status>
                        Tekan tombol, izinkan mikrofon, lalu bicara dengan jelas.
                    </span>
                </div>
                <p class="mt-2 text-xs leading-5 text-sky-800">
                    Suara diproses oleh layanan pengenal suara browser dan tidak disimpan oleh aplikasi. Periksa kembali teks sebelum mengirim.
                </p>
            </div>
        @endif

        <div class="mt-2 flex flex-col gap-1 text-xs sm:flex-row sm:items-center sm:justify-between">
            <div id="question-{{ $question->getKey() }}-status" class="text-slate-500" aria-live="polite" data-literacy-answer-status></div>
            <div id="question-{{ $question->getKey() }}-count" class="font-semibold text-slate-700" data-literacy-answer-count></div>
        </div>
        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div class="h-full rounded-full bg-slate-400 transition-all" style="width: 0%" data-literacy-answer-bar></div>
        </div>
    @endif

    <div
        @class(['mt-2 text-xs text-rose-600', 'hidden' => ! $errors->has($fieldName)])
        role="alert"
        data-literacy-validation-for="{{ $fieldName }}"
    >{{ $errors->first($fieldName) }}</div>
</section>
