@php
    /** @var \App\Models\PerpustakaanLiterasiSimilarityMatch $match */
    $reviewStatus = \App\Models\PerpustakaanLiterasiSimilarityMatch::reviewStatusLabel($match->review_status);
    $reviewedBy = $match->reviewedBy?->name ? ' oleh '.$match->reviewedBy->name : '';
@endphp

<div class="space-y-4 text-sm">
    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-950 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100">
        <div class="text-xs font-semibold uppercase tracking-[0.2em]">Ringkasan Plagiat</div>
        <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <div class="text-xs opacity-80">Kelas</div>
                <div class="font-semibold">{{ $match->student_class_snapshot ?: '-' }}</div>
            </div>
            <div>
                <div class="text-xs opacity-80">Kemiripan</div>
                <div class="font-semibold">{{ number_format((float) $match->similarity_score, 2, ',', '.') }}%</div>
            </div>
            <div>
                <div class="text-xs opacity-80">Pengirim Belakangan</div>
                <div class="font-semibold">{{ $match->laterResponse?->student_name_snapshot ?? '-' }}</div>
            </div>
            <div>
                <div class="text-xs opacity-80">Submit Pembanding</div>
                <div class="font-semibold">{{ $match->matched_submitted_at?->format('d/m/Y H:i') ?? '-' }}</div>
            </div>
            <div>
                <div class="text-xs opacity-80">Submit Belakangan</div>
                <div class="font-semibold">{{ $match->later_submitted_at?->format('d/m/Y H:i') ?? '-' }}</div>
            </div>
            <div>
                <div class="text-xs opacity-80">Status Review</div>
                <div class="font-semibold">{{ $reviewStatus }}</div>
                <div class="mt-0.5 text-xs opacity-80">
                    {{ $match->reviewed_at ? 'Diverifikasi '.$match->reviewed_at->format('d/m/Y H:i').$reviewedBy : 'Belum diverifikasi guru' }}
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Pertanyaan</div>
        <div class="mt-2 text-sm font-medium leading-6 text-gray-900 dark:text-white">{{ $match->question?->prompt ?? '-' }}</div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-rose-200 bg-white p-4 dark:border-rose-500/30 dark:bg-gray-900">
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-rose-600 dark:text-rose-300">Jawaban Pengirim Belakangan</div>
            <div class="mt-2 font-medium text-gray-900 dark:text-white">
                {{ $match->laterResponse?->student_name_snapshot ?? '-' }}
                @if($match->laterResponse?->student_class_snapshot)
                    <span class="text-gray-500 dark:text-gray-400">({{ $match->laterResponse->student_class_snapshot }})</span>
                @endif
            </div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Submit: {{ $match->later_submitted_at?->format('d/m/Y H:i') ?? '-' }}
            </div>
            <div class="mt-3 rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-2 leading-6 text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                <span class="whitespace-pre-line">{{ $match->laterAnswer?->answer_text ?: '-' }}</span>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Jawaban Pembanding Sebelumnya</div>
            <div class="mt-2 font-medium text-gray-900 dark:text-white">
                {{ $match->matchedResponse?->student_name_snapshot ?? '-' }}
                @if($match->matchedResponse?->student_class_snapshot)
                    <span class="text-gray-500 dark:text-gray-400">({{ $match->matchedResponse->student_class_snapshot }})</span>
                @endif
            </div>
            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Submit: {{ $match->matched_submitted_at?->format('d/m/Y H:i') ?? '-' }}
            </div>
            <div class="mt-3 rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-2 leading-6 text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                <span class="whitespace-pre-line">{{ $match->matchedAnswer?->answer_text ?: '-' }}</span>
            </div>
        </div>
    </div>
</div>
