@php
    /** @var \App\Models\PerpustakaanLiterasiResponse $response */
    $answers = $response->answers->keyBy('question_id');
@endphp

<div class="space-y-4 text-sm">
    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">Responden</div>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Siswa</div>
                <div class="font-medium text-gray-900 dark:text-white">{{ $response->student_name_snapshot }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Kelas</div>
                <div class="font-medium text-gray-900 dark:text-white">{{ $response->student_class_snapshot ?: '-' }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Kode Edit</div>
                <div class="font-medium text-gray-900 dark:text-white">{{ $response->edit_code }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Waktu Kirim</div>
                <div class="font-medium text-gray-900 dark:text-white">{{ $response->submitted_at?->format('d/m/Y H:i') ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="space-y-3">
        @foreach($response->material->questions as $index => $question)
            @php
                $answer = $answers->get($question->getKey());
                $statusLabel = match ($answer?->is_correct) {
                    true => 'Benar',
                    false => 'Salah',
                    default => 'Belum dinilai',
                };
                $statusClass = match ($answer?->is_correct) {
                    true => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                    false => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
                    default => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
                };
            @endphp
            <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-600 dark:bg-white/10 dark:text-gray-300">
                        Pertanyaan {{ $index + 1 }}
                    </span>
                    <span class="inline-flex rounded-full bg-primary-50 px-2.5 py-1 text-[11px] font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                        {{ number_format((int) ($answer?->character_count ?? 0), 0, ',', '.') }} karakter
                    </span>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusClass }}">
                        {{ $statusLabel }}
                    </span>
                </div>
                <div class="mt-3 text-sm font-medium leading-6 text-gray-900 dark:text-white">{{ $question->prompt }}</div>
                <div class="mt-3 rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-sm leading-6 text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
                    <span class="whitespace-pre-line">{{ filled($answer?->answer_text) ? $answer->answer_text : '-' }}</span>
                </div>
                @if($answer?->graded_at || filled($answer?->grading_note))
                    <div class="mt-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs leading-5 text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
                        @if($answer?->graded_at)
                            <div>Dinilai: {{ $answer->graded_at->format('d/m/Y H:i') }}{{ $answer->gradedBy?->name ? ' oleh '.$answer->gradedBy->name : '' }}</div>
                        @endif
                        @if(filled($answer?->grading_note))
                            <div class="mt-1 whitespace-pre-line">Catatan: {{ $answer->grading_note }}</div>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
