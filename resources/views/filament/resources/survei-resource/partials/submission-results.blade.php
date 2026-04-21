@php
    /** @var \App\Models\SurveiTarget $target */
    $survei = $target->survei;
    $submission = $target->submission;
    $questions = $survei?->questions ?? collect();
@endphp

<div class="space-y-4 text-sm">
    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Ringkasan</div>
        <div class="mt-2 grid gap-3 md:grid-cols-2">
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Target</div>
                <div class="font-medium text-gray-900 dark:text-white">{{ $target->recipientName() }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Waktu Isi</div>
                <div class="font-medium text-gray-900 dark:text-white">{{ $submission?->submitted_at?->format('d/m/Y H:i') ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        @foreach($questions as $index => $question)
            @php
                $answer = $submission?->answerForQuestion($question);
                $formattedAnswer = match ($question->question_type) {
                    \App\Models\SurveiQuestion::TYPE_RATING => filled($answer) ? $answer.' / 5' : '-',
                    default => filled($answer) ? (string) $answer : '-',
                };
            @endphp

            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-600 dark:bg-white/10 dark:text-gray-300">
                        Pertanyaan {{ $index + 1 }}
                    </span>
                    <span class="inline-flex rounded-full bg-primary-50 px-2.5 py-1 text-[11px] font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                        {{ \App\Models\SurveiQuestion::typeLabel($question->question_type) }}
                    </span>
                </div>
                <div class="mt-3 text-sm font-medium leading-6 text-gray-900 dark:text-white">{{ $question->prompt }}</div>
                <div class="mt-3 rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-sm leading-6 text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                    <span class="whitespace-pre-line">{{ $formattedAnswer }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
