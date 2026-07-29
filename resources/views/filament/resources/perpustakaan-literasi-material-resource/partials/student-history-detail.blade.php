@php
    /** @var \App\Models\PerpustakaanLiterasiResponse $response */
    $answers = $response->answers
        ->sortBy(fn ($answer) => $answer->question?->sort_order ?? $answer->question_id)
        ->values();
    $matchesByAnswer = $response->laterSimilarityMatches->groupBy('later_answer_id');
@endphp

<div class="literasi-history-detail">
    <section class="literasi-history-detail__summary">
        <div>
            <span>Siswa</span>
            <strong>{{ $response->student_name_snapshot }}</strong>
        </div>
        <div>
            <span>Kelas</span>
            <strong>{{ $response->student_class_snapshot ?: '-' }}</strong>
        </div>
        <div>
            <span>Materi</span>
            <strong>{{ $response->material?->title ?: 'Materi tidak ditemukan' }}</strong>
        </div>
        <div>
            <span>Dikirim</span>
            <strong>{{ $response->submitted_at?->format('d/m/Y H:i') ?? '-' }}</strong>
        </div>
        <div>
            <span>Kode Edit</span>
            <strong>{{ $response->shortEditCode() }}</strong>
        </div>
    </section>

    <div class="literasi-history-detail__answers">
        @forelse ($answers as $index => $answer)
            @php
                $matches = $matchesByAnswer->get($answer->getKey(), collect());
                $statusLabel = match ($answer->is_correct) {
                    true => 'Benar',
                    false => 'Salah',
                    default => 'Belum dinilai',
                };
                if ($answer->question && ! $answer->question->isEssay() && $answer->score_earned !== null) {
                    $statusLabel = number_format((int) $answer->score_earned, 0, ',', '.')
                        .'/'.number_format((int) ($answer->score_possible ?: $answer->question->objectiveItemCount()), 0, ',', '.')
                        .' poin';
                }
                $statusClass = match ($answer->is_correct) {
                    true => 'is-correct',
                    false => 'is-wrong',
                    default => 'is-ungraded',
                };
            @endphp

            <article class="literasi-history-answer-card">
                <header>
                    <div>
                        <span>Pertanyaan {{ $index + 1 }}</span>
                        <h3>{{ $answer->question?->prompt ?: '-' }}</h3>
                    </div>
                    <span class="literasi-history-answer-card__status {{ $statusClass }}">{{ $statusLabel }}</span>
                </header>

                <div class="literasi-history-answer-card__body">
                    <div>
                        <span>Jawaban Siswa</span>
                        <p>{{ $answer->answer_text ?: '-' }}</p>
                    </div>

                    <dl>
                        <div>
                            <dt>{{ ($answer->question?->isEssay() ?? true) ? 'Jumlah Karakter' : 'Jenis / Poin' }}</dt>
                            <dd>
                                @if($answer->question && ! $answer->question->isEssay())
                                    {{ \App\Models\PerpustakaanLiterasiQuestion::typeLabel($answer->question->question_type) }}
                                    · {{ number_format((int) ($answer->score_earned ?? 0), 0, ',', '.') }}/{{ number_format((int) ($answer->score_possible ?? 0), 0, ',', '.') }}
                                @else
                                    {{ number_format((int) $answer->character_count, 0, ',', '.') }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Dinilai Oleh</dt>
                            <dd>{{ $answer->gradedBy?->name ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt>Waktu Penilaian</dt>
                            <dd>{{ $answer->graded_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt>Catatan Nilai</dt>
                            <dd>{{ $answer->grading_note ?: '-' }}</dd>
                        </div>
                    </dl>
                </div>

                @if ($matches->isNotEmpty())
                    <div class="literasi-history-answer-card__plagiarism">
                        <strong>Indikasi Plagiasi</strong>
                        <ul>
                            @foreach ($matches as $match)
                                <li>
                                    <span>{{ number_format((float) $match->similarity_score, 2, ',', '.') }}%</span>
                                    mirip dengan {{ $match->matchedResponse?->student_name_snapshot ?: 'Pembanding sebelumnya' }}
                                    ({{ $match->matchedResponse?->student_class_snapshot ?: '-' }}),
                                    status {{ \App\Models\PerpustakaanLiterasiSimilarityMatch::reviewStatusLabel($match->review_status) }}.
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </article>
        @empty
            <p class="literasi-empty-state">Belum ada jawaban pada history ini.</p>
        @endforelse
    </div>
</div>
