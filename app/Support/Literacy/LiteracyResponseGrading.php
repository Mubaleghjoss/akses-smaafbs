<?php

namespace App\Support\Literacy;

use App\Models\PerpustakaanLiterasiAnswer;
use App\Models\PerpustakaanLiterasiQuestion;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
use Filament\Forms;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class LiteracyResponseGrading
{
    /**
     * @return array<int, Section>
     */
    public function schema(PerpustakaanLiterasiResponse $record): array
    {
        $record->loadMissing('material.questions', 'answers.question');
        $material = $record->material;

        if (! $material) {
            return [
                $this->integritySummarySection($record),
                Section::make('Materi Tidak Tersedia')
                    ->schema([
                        Forms\Components\Placeholder::make('missing_material')
                            ->label('Status')
                            ->content('History tetap dapat dibaca, tetapi nilai tidak dapat diubah karena materi asal sudah tidak tersedia.'),
                    ]),
            ];
        }

        $answers = $record->answers->keyBy('question_id');
        $plagiarismMatchesByAnswer = $record->laterSimilarityMatches()
            ->with(['matchedResponse', 'matchedAnswer', 'reviewedBy'])
            ->get()
            ->groupBy('later_answer_id');

        return collect([$this->integritySummarySection($record)])
            ->merge($material->questions
                ->map(function (PerpustakaanLiterasiQuestion $question, int $index) use ($answers, $plagiarismMatchesByAnswer): Section {
                    $answer = $answers->get($question->getKey());
                    $answerId = (int) $answer?->getKey();
                    $plagiarismMatches = $answerId > 0
                        ? $plagiarismMatchesByAnswer->get($answerId, collect())
                        : collect();
                    $schema = [
                        Forms\Components\Placeholder::make('question_'.$question->getKey())
                            ->label('Pertanyaan')
                            ->content(new HtmlString(
                                '<div class="literasi-grading-question">'.nl2br(e($question->prompt)).'</div>'
                            ))
                            ->columnSpanFull(),
                        Forms\Components\Placeholder::make('answer_'.$answerId)
                            ->label('Jawaban Siswa')
                            ->content(new HtmlString(
                                '<div class="literasi-grading-student-answer">'.
                                nl2br(e((string) ($answer?->answer_text ?: '-'))).
                                '</div>'
                            ))
                            ->columnSpanFull(),
                    ];

                    if ($this->hasDisplayableAnswerKey($question)) {
                        $schema[] = Forms\Components\Placeholder::make('answer_'.$answerId.'_answer_key')
                            ->label('Kunci Jawaban')
                            ->content($this->answerKeyHtml($question))
                            ->columnSpanFull();
                    }

                    if (! $question->plagiarismDetectionEnabled()) {
                        $schema[] = Forms\Components\Placeholder::make('answer_'.$answerId.'_plagiarism_disabled_info')
                            ->label('Status Plagiasi')
                            ->content(new HtmlString(
                                '<div class="literasi-grading-info literasi-grading-info--success">'.
                                '<strong>Tidak dianalisis plagiasi.</strong> Soal objektif atau soal dengan deteksi plagiasi nonaktif tidak masuk Daftar Plagiat Per Kelas.'.
                                '</div>'
                            ))
                            ->columnSpanFull();
                    } elseif ($plagiarismMatches->isNotEmpty()) {
                        $schema[] = Forms\Components\Placeholder::make('answer_'.$answerId.'_plagiarism_info')
                            ->label('Indikasi Plagiasi')
                            ->content($this->plagiarismMatchesHtml($plagiarismMatches))
                            ->columnSpanFull();
                        $schema[] = Forms\Components\Radio::make('answer_'.$answerId.'_plagiarism_status')
                            ->label('Status Plagiasi')
                            ->options(PerpustakaanLiterasiSimilarityMatch::reviewStatusOptions())
                            ->default(PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED)
                            ->inline()
                            ->required()
                            ->columnSpanFull();
                    }

                    if (! $question->isEssay()) {
                        $possible = max(1, (int) ($answer?->score_possible ?? $question->objectiveItemCount()));
                        $schema[] = Forms\Components\Placeholder::make('answer_'.$answerId.'_automatic_score')
                            ->label('Nilai Otomatis')
                            ->content(number_format((int) ($answer?->score_earned ?? 0), 0, ',', '.').'/'.number_format($possible, 0, ',', '.').' poin');
                        $schema[] = Forms\Components\TextInput::make('answer_'.$answerId.'_score')
                            ->label('Koreksi Poin')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue($possible)
                            ->required()
                            ->helperText('Isi 0 sampai '.$possible.'. Penyimpanan dari admin dicatat sebagai koreksi manual.');
                    } else {
                        $schema[] = Forms\Components\Radio::make('answer_'.$answerId.'_status')
                            ->label('Penilaian')
                            ->options([
                                'ungraded' => 'Belum dinilai',
                                'correct' => 'Benar',
                                'wrong' => 'Salah',
                            ])
                            ->default('ungraded')
                            ->inline()
                            ->required();
                    }

                    $schema[] = Forms\Components\Textarea::make('answer_'.$answerId.'_note')
                        ->label('Catatan')
                        ->rows(2)
                        ->maxLength(1000);

                    return Section::make('Pertanyaan '.($index + 1))
                        ->columns(['default' => 1, 'md' => 2])
                        ->schema($schema);
                })
                ->values())
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function data(PerpustakaanLiterasiResponse $record): array
    {
        $record->loadMissing('answers', 'laterSimilarityMatches');
        $data = [];
        $plagiarismMatchesByAnswer = $record->laterSimilarityMatches->groupBy('later_answer_id');

        foreach ($record->answers as $answer) {
            $key = 'answer_'.$answer->getKey();
            $data[$key.'_score'] = $answer->score_earned;
            $data[$key.'_status'] = match ($answer->is_correct) {
                true => 'correct',
                false => 'wrong',
                default => 'ungraded',
            };
            $data[$key.'_note'] = (string) ($answer->grading_note ?? '');

            $plagiarismMatches = $plagiarismMatchesByAnswer->get($answer->getKey(), collect());
            if ($plagiarismMatches->isNotEmpty()) {
                $data[$key.'_plagiarism_status'] = $this->resolvePlagiarismReviewStatus($plagiarismMatches);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(PerpustakaanLiterasiResponse $record, array $data, ?int $userId): void
    {
        $record->loadMissing('answers.question');

        DB::transaction(function () use ($data, $record, $userId): void {
            foreach ($record->answers as $answer) {
                $key = 'answer_'.$answer->getKey();
                $status = (string) ($data[$key.'_status'] ?? 'ungraded');
                $note = trim((string) ($data[$key.'_note'] ?? ''));

                $this->savePlagiarismReview($record, $answer, $data, $userId);

                if ($answer->question && ! $answer->question->isEssay()) {
                    $possible = max(1, (int) ($answer->score_possible ?? $answer->question->objectiveItemCount()));
                    $score = min($possible, max(0, (int) ($data[$key.'_score'] ?? 0)));
                    $answer->forceFill([
                        'score_earned' => $score,
                        'score_possible' => $possible,
                        'grading_source' => 'manual',
                        'is_correct' => $score === $possible,
                        'graded_by' => $userId,
                        'graded_at' => now(),
                        'grading_note' => $note !== '' ? $note : 'Poin dikoreksi manual oleh admin/guru.',
                    ])->save();

                    continue;
                }

                if (! in_array($status, ['correct', 'wrong'], true)) {
                    $answer->forceFill([
                        'is_correct' => null,
                        'score_earned' => null,
                        'score_possible' => 1,
                        'grading_source' => null,
                        'graded_by' => null,
                        'graded_at' => null,
                        'grading_note' => null,
                    ])->save();

                    continue;
                }

                $answer->forceFill([
                    'is_correct' => $status === 'correct',
                    'score_earned' => $status === 'correct' ? 1 : 0,
                    'score_possible' => 1,
                    'grading_source' => 'manual',
                    'graded_by' => $userId,
                    'graded_at' => now(),
                    'grading_note' => $note !== '' ? $note : null,
                ])->save();
            }
        });
    }

    public function isFullyGraded(PerpustakaanLiterasiResponse $record): bool
    {
        $total = (int) ($record->score_possible_total ?? $record->answers()->sum('score_possible'));
        $graded = (int) ($record->graded_points_total ?? $record->answers()->whereNotNull('score_earned')->sum('score_possible'));

        return $total > 0 && $graded >= $total;
    }

    private function integritySummarySection(PerpustakaanLiterasiResponse $record): Section
    {
        return Section::make('Tindakan Keluar Halaman')
            ->columns(['default' => 1, 'md' => 2])
            ->schema([
                Forms\Components\Placeholder::make('app_hidden_count')
                    ->label('Halaman Disembunyikan > 10 Detik')
                    ->content(number_format((int) ($record->app_hidden_count ?? 0), 0, ',', '.').'x'),
                Forms\Components\Placeholder::make('submitted_at')
                    ->label('Submit jawaban')
                    ->content($record->submitted_at?->format('d/m/Y H:i') ?? '-'),
            ]);
    }

    private function hasDisplayableAnswerKey(PerpustakaanLiterasiQuestion $question): bool
    {
        return ! $question->isEssay() || $question->hasAnswerKey();
    }

    private function answerKeyHtml(PerpustakaanLiterasiQuestion $question): HtmlString
    {
        if ($question->isTrueFalse()) {
            $rows = collect($question->trueFalseItems())
                ->map(fn (array $item): string => '<tr><td>'.e($item['statement']).'</td><td><strong>'.
                    ((bool) $item['correct'] ? 'Benar' : 'Salah').
                    '</strong></td></tr>')
                ->implode('');

            return new HtmlString(
                '<div class="literasi-grading-answer-key">'.
                '<table><thead><tr><th>Pernyataan</th><th>Kunci</th></tr></thead><tbody>'.$rows.'</tbody></table>'.
                '</div>'
            );
        }

        if ($question->isMatching()) {
            $rightLabels = collect($question->matchingRightItems())->pluck('label', 'id');
            $rows = collect($question->matchingLeftItems())
                ->map(fn (array $item): string => '<tr><td>'.e($item['label']).'</td><td class="literasi-grading-answer-key__arrow">→</td><td>'.
                    e((string) ($rightLabels[$item['correct_target_id']] ?? '-')).
                    '</td></tr>')
                ->implode('');

            return new HtmlString(
                '<div class="literasi-grading-answer-key">'.
                '<table><thead><tr><th>Soal</th><th></th><th>Pasangan Benar</th></tr></thead><tbody>'.$rows.'</tbody></table>'.
                '</div>'
            );
        }

        return new HtmlString(
            '<div class="literasi-grading-answer-key literasi-grading-answer-key--essay">'.
            nl2br(e((string) ($question->answerKey() ?: '-'))).
            '</div>'
        );
    }

    /**
     * @param  Collection<int, PerpustakaanLiterasiSimilarityMatch>  $matches
     */
    private function plagiarismMatchesHtml(Collection $matches): HtmlString
    {
        $items = $matches
            ->map(function (PerpustakaanLiterasiSimilarityMatch $match): string {
                $student = $match->matchedResponse?->student_name_snapshot ?: 'Pembanding sebelumnya';
                $class = $match->matchedResponse?->student_class_snapshot ?: '-';
                $score = number_format((float) $match->similarity_score, 2, ',', '.').'%';
                $status = PerpustakaanLiterasiSimilarityMatch::reviewStatusLabel($match->review_status);
                $laterSubmitted = $match->later_submitted_at?->format('d/m/Y H:i') ?? '-';
                $matchedSubmitted = $match->matched_submitted_at?->format('d/m/Y H:i') ?? '-';
                $reviewedBy = $match->reviewedBy?->name ? ' oleh '.$match->reviewedBy->name : '';
                $reviewedAt = $match->reviewed_at
                    ? $match->reviewed_at->format('d/m/Y H:i').$reviewedBy
                    : 'Belum diverifikasi guru';

                return '<li>'.
                    '<div><strong>'.e($score).'</strong> mirip dengan '.e($student).' ('.e($class).') - '.e($status).'</div>'.
                    '<div class="literasi-grading-match-meta">Submit pembanding: '.e($matchedSubmitted).' | Submit jawaban ini: '.e($laterSubmitted).' | Verifikasi: '.e($reviewedAt).'</div>'.
                    '</li>';
            })
            ->implode('');

        return new HtmlString(
            '<div class="literasi-plagiarism-review">'.
            '<div class="literasi-plagiarism-review__title">Jawaban ini terdeteksi mirip dengan jawaban sebelumnya.</div>'.
            '<ul>'.$items.'</ul>'.
            '</div>'
        );
    }

    /**
     * @param  Collection<int, PerpustakaanLiterasiSimilarityMatch>  $matches
     */
    private function resolvePlagiarismReviewStatus(Collection $matches): string
    {
        if ($matches->contains(fn (PerpustakaanLiterasiSimilarityMatch $match): bool => $match->review_status === PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED)) {
            return PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED;
        }

        if ($matches->every(fn (PerpustakaanLiterasiSimilarityMatch $match): bool => $match->review_status === PerpustakaanLiterasiSimilarityMatch::REVIEW_CLEARED)) {
            return PerpustakaanLiterasiSimilarityMatch::REVIEW_CLEARED;
        }

        return PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function savePlagiarismReview(
        PerpustakaanLiterasiResponse $record,
        PerpustakaanLiterasiAnswer $answer,
        array $data,
        ?int $userId
    ): void {
        if (! Schema::hasTable('perpustakaan_literasi_similarity_matches')
            || ! Schema::hasColumn('perpustakaan_literasi_similarity_matches', 'review_status')) {
            return;
        }

        $key = 'answer_'.$answer->getKey();
        $status = (string) ($data[$key.'_plagiarism_status'] ?? '');

        if (! array_key_exists($status, PerpustakaanLiterasiSimilarityMatch::reviewStatusOptions())) {
            return;
        }

        PerpustakaanLiterasiSimilarityMatch::query()
            ->where('later_response_id', $record->getKey())
            ->where('later_answer_id', $answer->getKey())
            ->update([
                'review_status' => $status,
                'reviewed_by' => $status === PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED ? null : $userId,
                'reviewed_at' => $status === PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED ? null : now(),
                'review_note' => null,
            ]);
    }
}
