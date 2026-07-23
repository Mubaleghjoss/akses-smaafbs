@php
    /** @var \App\Models\PerpustakaanLiterasiResponse $response */
    $answers = $response->answers->keyBy('question_id');
    $questions = $response->material?->questions ?? collect();
    $similarityMatches = $response->laterSimilarityMatches;
    $matchesByAnswer = $similarityMatches->groupBy('later_answer_id');

    $tabSwitchCount = (int) ($response->tab_switch_count ?? 0);
    $appHiddenCount = (int) ($response->app_hidden_count ?? 0);
    $pageLeaveCount = (int) ($response->page_leave_attempt_count ?? 0);
    $integrityTotal = $tabSwitchCount + $appHiddenCount + $pageLeaveCount;
    [$integrityLabel, $integrityTone] = match (true) {
        $integrityTotal >= 5 => ['Aktivitas keluar halaman tinggi', 'danger'],
        $integrityTotal > 0 => ['Perlu perhatian', 'warning'],
        default => ['Tidak ada aktivitas keluar halaman', 'success'],
    };

    $confirmedMatches = $similarityMatches->where('review_status', \App\Models\PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED);
    $clearedMatches = $similarityMatches->where('review_status', \App\Models\PerpustakaanLiterasiSimilarityMatch::REVIEW_CLEARED);
    $suspectedMatches = $similarityMatches->filter(fn ($match): bool => blank($match->review_status)
        || $match->review_status === \App\Models\PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED);
    $checkedQuestionCount = $questions->filter(fn ($question): bool => $question->plagiarismDetectionEnabled())->count();

    [$plagiarismLabel, $plagiarismDescription, $plagiarismTone] = match (true) {
        $confirmedMatches->isNotEmpty() => [
            'Plagiasi dikonfirmasi',
            $confirmedMatches->count().' kecocokan telah dikonfirmasi oleh guru atau admin.',
            'danger',
        ],
        $suspectedMatches->isNotEmpty() => [
            'Terindikasi plagiasi',
            $suspectedMatches->count().' kecocokan menunggu peninjauan guru atau admin.',
            'warning',
        ],
        $similarityMatches->isNotEmpty() => [
            'Aman setelah ditinjau',
            $clearedMatches->count().' kecocokan telah dinyatakan bukan plagiasi.',
            'success',
        ],
        $checkedQuestionCount === 0 => [
            'Deteksi plagiasi tidak aktif',
            'Tidak ada pertanyaan pada materi ini yang mengaktifkan pemeriksaan plagiasi.',
            'neutral',
        ],
        default => [
            'Tidak ada indikasi plagiasi',
            'Tidak ditemukan kecocokan pada pertanyaan yang diperiksa.',
            'success',
        ],
    };
@endphp

<div class="literasi-response-detail">
    <section class="literasi-response-detail__identity">
        <div class="literasi-response-detail__identity-main">
            <span class="literasi-response-detail__eyebrow">Detail responden</span>
            <h3>{{ $response->student_name_snapshot }}</h3>
            <p>{{ $response->student_class_snapshot ?: 'Kelas belum tercatat' }}</p>
        </div>
        <dl class="literasi-response-detail__identity-grid">
            <div>
                <dt>Kode Edit</dt>
                <dd>{{ $response->edit_code }}</dd>
            </div>
            <div>
                <dt>Submit Jawaban</dt>
                <dd>{{ $response->submitted_at?->format('d/m/Y H:i') ?? '-' }}</dd>
            </div>
            <div>
                <dt>Status Submit</dt>
                <dd>{{ $response->submission_delivery_code ?: 'LEGACY' }} - {{ $response->submissionDeliveryDescription() }}</dd>
            </div>
            <div>
                <dt>Terakhir Diedit</dt>
                <dd>{{ $response->last_edited_at?->format('d/m/Y H:i') ?? '-' }}</dd>
            </div>
            <div>
                <dt>Total Jawaban</dt>
                <dd>{{ number_format($response->answers->count(), 0, ',', '.') }}</dd>
            </div>
        </dl>
    </section>

    <section class="literasi-response-detail__section">
        <div class="literasi-response-detail__section-head">
            <div>
                <span class="literasi-response-detail__eyebrow">Integritas pengerjaan</span>
                <h4>Tindakan keluar halaman</h4>
            </div>
            <span class="literasi-response-detail__status literasi-response-detail__status--{{ $integrityTone }}">
                {{ $integrityLabel }}
            </span>
        </div>

        <div class="literasi-response-detail__metric-grid">
            <article class="literasi-response-detail__metric">
                <span>Pindah Tab</span>
                <strong>{{ number_format($tabSwitchCount, 0, ',', '.') }}x</strong>
                <small>Tab browser kehilangan fokus.</small>
            </article>
            <article class="literasi-response-detail__metric">
                <span>Keluar Aplikasi</span>
                <strong>{{ number_format($appHiddenCount, 0, ',', '.') }}x</strong>
                <small>Browser atau aplikasi disembunyikan.</small>
            </article>
            <article class="literasi-response-detail__metric">
                <span>Percobaan Keluar</span>
                <strong>{{ number_format($pageLeaveCount, 0, ',', '.') }}x</strong>
                <small>Navigasi keluar dari halaman soal.</small>
            </article>
            <article class="literasi-response-detail__metric literasi-response-detail__metric--total">
                <span>Total Indikator</span>
                <strong>{{ number_format($integrityTotal, 0, ',', '.') }}x</strong>
                <small>Indikator teknis, bukan keputusan akhir.</small>
            </article>
        </div>
    </section>

    <section class="literasi-response-detail__section literasi-response-detail__section--{{ $plagiarismTone }}">
        <div class="literasi-response-detail__section-head">
            <div>
                <span class="literasi-response-detail__eyebrow">Pemeriksaan kemiripan</span>
                <h4>Status plagiasi</h4>
            </div>
            <span class="literasi-response-detail__status literasi-response-detail__status--{{ $plagiarismTone }}">
                {{ $plagiarismLabel }}
            </span>
        </div>
        <p class="literasi-response-detail__description">{{ $plagiarismDescription }}</p>
        <div class="literasi-response-detail__plagiarism-counts">
            <span><strong>{{ $suspectedMatches->count() }}</strong> belum ditinjau</span>
            <span><strong>{{ $confirmedMatches->count() }}</strong> dikonfirmasi</span>
            <span><strong>{{ $clearedMatches->count() }}</strong> dinyatakan aman</span>
        </div>
    </section>

    <section class="literasi-response-detail__answers">
        <div class="literasi-response-detail__answers-head">
            <span class="literasi-response-detail__eyebrow">Rincian jawaban</span>
            <h4>{{ number_format($questions->count(), 0, ',', '.') }} pertanyaan</h4>
        </div>

        @forelse($questions as $index => $question)
            @php
                $answer = $answers->get($question->getKey());
                $answerMatches = $answer ? $matchesByAnswer->get($answer->getKey(), collect()) : collect();
                $statusLabel = match ($answer?->is_correct) {
                    true => 'Benar',
                    false => 'Salah',
                    default => 'Belum dinilai',
                };
                $statusTone = match ($answer?->is_correct) {
                    true => 'success',
                    false => 'danger',
                    default => 'warning',
                };
            @endphp
            <article class="literasi-response-detail__answer">
                <header class="literasi-response-detail__answer-head">
                    <div>
                        <span>Pertanyaan {{ $index + 1 }}</span>
                        <strong>{{ number_format((int) ($answer?->character_count ?? 0), 0, ',', '.') }} karakter</strong>
                    </div>
                    <span class="literasi-response-detail__status literasi-response-detail__status--{{ $statusTone }}">
                        {{ $statusLabel }}
                    </span>
                </header>

                <div class="literasi-response-detail__prompt">{{ $question->prompt }}</div>
                <div class="literasi-response-detail__answer-text">
                    <span>{{ filled($answer?->answer_text) ? $answer->answer_text : '-' }}</span>
                </div>

                <div class="literasi-response-detail__answer-meta">
                    @if(! $question->plagiarismDetectionEnabled())
                        <span class="literasi-response-detail__status literasi-response-detail__status--neutral">Plagiasi tidak diperiksa</span>
                    @elseif($answerMatches->isEmpty())
                        <span class="literasi-response-detail__status literasi-response-detail__status--success">Tidak ada indikasi plagiasi</span>
                    @else
                        <span class="literasi-response-detail__status literasi-response-detail__status--warning">
                            {{ $answerMatches->count() }} kecocokan plagiasi
                        </span>
                    @endif
                </div>

                @if($answerMatches->isNotEmpty())
                    <div class="literasi-response-detail__matches">
                        @foreach($answerMatches as $match)
                            <div class="literasi-response-detail__match">
                                <div>
                                    <strong>{{ number_format((float) $match->similarity_score, 2, ',', '.') }}% mirip</strong>
                                    <span>
                                        {{ $match->matchedResponse?->student_name_snapshot ?: 'Responden pembanding' }}
                                        ({{ $match->matchedResponse?->student_class_snapshot ?: '-' }})
                                    </span>
                                </div>
                                <div>
                                    <span class="literasi-response-detail__status literasi-response-detail__status--{{ \App\Models\PerpustakaanLiterasiSimilarityMatch::reviewStatusColor($match->review_status) }}">
                                        {{ \App\Models\PerpustakaanLiterasiSimilarityMatch::reviewStatusLabel($match->review_status) }}
                                    </span>
                                    <small>
                                        {{ $match->reviewed_at ? 'Ditinjau '.$match->reviewed_at->format('d/m/Y H:i') : 'Belum diverifikasi' }}
                                        {{ $match->reviewedBy?->name ? ' oleh '.$match->reviewedBy->name : '' }}
                                    </small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($answer?->graded_at || filled($answer?->grading_note))
                    <div class="literasi-response-detail__grading">
                        @if($answer?->graded_at)
                            <div>Dinilai {{ $answer->graded_at->format('d/m/Y H:i') }}{{ $answer->gradedBy?->name ? ' oleh '.$answer->gradedBy->name : '' }}</div>
                        @endif
                        @if(filled($answer?->grading_note))
                            <div>Catatan: <span>{{ $answer->grading_note }}</span></div>
                        @endif
                    </div>
                @endif
            </article>
        @empty
            <div class="literasi-response-detail__empty">Belum ada pertanyaan pada materi ini.</div>
        @endforelse
    </section>
</div>
