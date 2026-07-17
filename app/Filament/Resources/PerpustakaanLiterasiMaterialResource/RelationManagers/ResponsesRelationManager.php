<?php

namespace App\Filament\Resources\PerpustakaanLiterasiMaterialResource\RelationManagers;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Models\PerpustakaanLiterasiAnswer;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class ResponsesRelationManager extends RelationManager
{
    protected static string $relationship = 'responses';

    protected static ?string $title = 'Detail Jawaban Responden';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('student_name_snapshot')
            ->defaultSort('submitted_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['answers.question', 'answers.gradedBy', 'laterSimilarityMatches.matchedResponse', 'laterSimilarityMatches.reviewedBy'])
                ->withCount([
                    'answers',
                    'answers as graded_answers_count' => fn (Builder $query): Builder => $query->whereNotNull('is_correct'),
                    'answers as correct_answers_count' => fn (Builder $query): Builder => $query->where('is_correct', true),
                ]))
            ->columns([
                Tables\Columns\TextColumn::make('student_name_snapshot')
                    ->label('Siswa')
                    ->searchable()
                    ->description(fn (PerpustakaanLiterasiResponse $record): string => collect([
                        $record->student_class_snapshot,
                        'Kode: '.$record->shortEditCode(),
                    ])->filter()->implode(' | '))
                    ->wrap(),
                Tables\Columns\TextColumn::make('student_class_snapshot')
                    ->label('Kelas')
                    ->badge()
                    ->placeholder('-')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('answers_count')
                    ->label('Jawaban')
                    ->badge()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('grading_summary')
                    ->label('Nilai')
                    ->state(fn (PerpustakaanLiterasiResponse $record): string => number_format((int) ($record->correct_answers_count ?? 0), 0, ',', '.')
                        .'/'.number_format((int) ($record->graded_answers_count ?? 0), 0, ',', '.').' benar')
                    ->description(fn (PerpustakaanLiterasiResponse $record): string => number_format((int) ($record->graded_answers_count ?? 0), 0, ',', '.')
                        .'/'.number_format((int) ($record->answers_count ?? 0), 0, ',', '.').' dinilai')
                    ->badge()
                    ->color(fn (PerpustakaanLiterasiResponse $record): string => ((int) ($record->answers_count ?? 0) > 0 && (int) ($record->graded_answers_count ?? 0) >= (int) ($record->answers_count ?? 0)) ? 'success' : 'warning')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Dikirim')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('last_edited_at')
                    ->label('Edit')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->visibleFrom('lg')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('student_class_snapshot')
                    ->label('Kelas')
                    ->options(fn (): array => $this->getOwnerRecord()
                        ->responses()
                        ->whereNotNull('student_class_snapshot')
                        ->where('student_class_snapshot', '!=', '')
                        ->distinct()
                        ->orderBy('student_class_snapshot')
                        ->pluck('student_class_snapshot', 'student_class_snapshot')
                        ->all()),
                Tables\Filters\Filter::make('submitted_today')
                    ->label('Dikirim Hari Ini')
                    ->query(fn (Builder $query): Builder => $query->whereDate('submitted_at', now()->toDateString())),
                Tables\Filters\TernaryFilter::make('grading_complete')
                    ->label('Status Penilaian')
                    ->trueLabel('Sudah dinilai')
                    ->falseLabel('Belum lengkap')
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->whereHas('answers')
                            ->whereDoesntHave('answers', fn (Builder $answerQuery): Builder => $answerQuery->whereNull('is_correct')),
                        false: fn (Builder $query): Builder => $query
                            ->whereHas('answers', fn (Builder $answerQuery): Builder => $answerQuery->whereNull('is_correct')),
                    ),
            ])
            ->actions([
                Action::make('nilaiJawaban')
                    ->label(fn (PerpustakaanLiterasiResponse $record): string => $this->isResponseFullyGraded($record) ? 'Edit Nilai' : 'Nilai')
                    ->icon(fn (PerpustakaanLiterasiResponse $record): string => $this->isResponseFullyGraded($record) ? 'heroicon-o-pencil-square' : 'heroicon-o-check-circle')
                    ->color(fn (PerpustakaanLiterasiResponse $record): string => $this->isResponseFullyGraded($record) ? 'warning' : 'success')
                    ->visible(fn (): bool => PerpustakaanLiterasiMaterialResource::canEdit($this->getOwnerRecord()))
                    ->modalHeading(fn (PerpustakaanLiterasiResponse $record): string => 'Nilai Jawaban: '.$record->student_name_snapshot)
                    ->modalSubmitActionLabel('Simpan Nilai')
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('6xl')
                    ->form(fn (PerpustakaanLiterasiResponse $record): array => $this->gradingFormSchema($record))
                    ->fillForm(fn (PerpustakaanLiterasiResponse $record): array => $this->gradingFormData($record))
                    ->action(function (PerpustakaanLiterasiResponse $record, array $data): void {
                        $this->saveGrades($record, $data);
                    }),
                Action::make('lihatJawaban')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (PerpustakaanLiterasiResponse $record): string => 'Jawaban: '.$record->student_name_snapshot)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('6xl')
                    ->modalContent(fn (PerpustakaanLiterasiResponse $record) => view(
                        'filament.resources.perpustakaan-literasi-material-resource.partials.response-detail',
                        ['response' => $record->loadMissing(
                            'material.questions',
                            'answers.question',
                            'answers.gradedBy',
                            'laterSimilarityMatches.matchedResponse',
                            'laterSimilarityMatches.reviewedBy',
                        )]
                    )),
                Action::make('bukaEditPublik')
                    ->label('Link Edit')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (PerpustakaanLiterasiResponse $record): string => $record->editUrl())
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    protected function gradingFormSchema(PerpustakaanLiterasiResponse $record): array
    {
        $record->loadMissing('material.questions', 'answers.question');
        $answers = $record->answers->keyBy('question_id');
        $plagiarismMatchesByAnswer = $record->laterSimilarityMatches()
            ->with(['matchedResponse', 'matchedAnswer', 'reviewedBy'])
            ->get()
            ->groupBy('later_answer_id');

        return collect([$this->integritySummarySection($record)])
            ->merge($record->material->questions
            ->map(function ($question, int $index) use ($answers, $plagiarismMatchesByAnswer): Section {
                $answer = $answers->get($question->getKey());
                $answerId = (int) $answer?->getKey();
                $plagiarismMatches = $answerId > 0
                    ? $plagiarismMatchesByAnswer->get($answerId, collect())
                    : collect();
                $schema = [
                    Forms\Components\Placeholder::make('question_'.$question->getKey())
                        ->label('Pertanyaan')
                        ->content(new HtmlString('<div class="whitespace-pre-line text-sm leading-6">'.e($question->prompt).'</div>'))
                        ->columnSpanFull(),
                    Forms\Components\Placeholder::make('answer_'.$answerId)
                        ->label('Jawaban Siswa')
                        ->content(new HtmlString('<div class="rounded-lg border border-dashed border-gray-200 bg-gray-50 p-3 text-sm leading-6 dark:border-white/10 dark:bg-white/5">'.nl2br(e((string) ($answer?->answer_text ?: '-'))).'</div>'))
                        ->columnSpanFull(),
                ];

                if ($question->hasAnswerKey()) {
                    $schema[] = Forms\Components\Placeholder::make('answer_'.$answerId.'_answer_key')
                        ->label('Kunci Jawaban')
                        ->content(new HtmlString(
                            '<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm leading-6 text-emerald-950 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100">'.
                            nl2br(e((string) $question->answerKey())).
                            '</div>'
                        ))
                        ->columnSpanFull();
                }

                if (! $question->plagiarismDetectionEnabled()) {
                    $schema[] = Forms\Components\Placeholder::make('answer_'.$answerId.'_plagiarism_disabled_info')
                        ->label('Status Plagiasi')
                        ->content(new HtmlString(
                            '<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm leading-6 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-100">'.
                            '<strong>Tidak plagiasi.</strong> Soal ini tidak dianalisa plagiasi karena deteksi plagiasi dinonaktifkan, sehingga jawaban tidak masuk Daftar Plagiat Per Kelas.'.
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

    protected function integritySummarySection(PerpustakaanLiterasiResponse $record): Section
    {
        return Section::make('Tindakan Keluar Halaman')
            ->columns(['default' => 1, 'md' => 4])
            ->schema([
                Forms\Components\Placeholder::make('tab_switch_count')
                    ->label('Pindah Tab')
                    ->content(number_format((int) ($record->tab_switch_count ?? 0), 0, ',', '.').'x'),
                Forms\Components\Placeholder::make('app_hidden_count')
                    ->label('Keluar Aplikasi')
                    ->content(number_format((int) ($record->app_hidden_count ?? 0), 0, ',', '.').'x'),
                Forms\Components\Placeholder::make('page_leave_attempt_count')
                    ->label('Percobaan Keluar Halaman')
                    ->content(number_format((int) ($record->page_leave_attempt_count ?? 0), 0, ',', '.').'x'),
                Forms\Components\Placeholder::make('submitted_at')
                    ->label('Submit jawaban')
                    ->content($record->submitted_at?->format('d/m/Y H:i') ?? '-'),
            ]);
    }

    protected function gradingFormData(PerpustakaanLiterasiResponse $record): array
    {
        $record->loadMissing('answers', 'laterSimilarityMatches');
        $data = [];
        $plagiarismMatchesByAnswer = $record->laterSimilarityMatches->groupBy('later_answer_id');

        foreach ($record->answers as $answer) {
            $key = 'answer_'.$answer->getKey();
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

    protected function saveGrades(PerpustakaanLiterasiResponse $record, array $data): void
    {
        $record->loadMissing('answers');
        $userId = auth()->id();

        DB::transaction(function () use ($data, $record, $userId): void {
            foreach ($record->answers as $answer) {
                $key = 'answer_'.$answer->getKey();
                $status = (string) ($data[$key.'_status'] ?? 'ungraded');
                $note = trim((string) ($data[$key.'_note'] ?? ''));

                $this->savePlagiarismReview($record, $answer, $data, $userId);

                if (! in_array($status, ['correct', 'wrong'], true)) {
                    $answer->forceFill([
                        'is_correct' => null,
                        'graded_by' => null,
                        'graded_at' => null,
                        'grading_note' => null,
                    ])->save();

                    continue;
                }

                $answer->forceFill([
                    'is_correct' => $status === 'correct',
                    'graded_by' => $userId,
                    'graded_at' => now(),
                    'grading_note' => $note !== '' ? $note : null,
                ])->save();
            }
        });

        Notification::make()
            ->title('Nilai jawaban tersimpan')
            ->success()
            ->send();
    }

    protected function isResponseFullyGraded(PerpustakaanLiterasiResponse $record): bool
    {
        $total = (int) ($record->answers_count ?? $record->answers()->count());
        $graded = (int) ($record->graded_answers_count ?? $record->answers()->whereNotNull('is_correct')->count());

        return $total > 0 && $graded >= $total;
    }

    protected function plagiarismMatchesHtml($matches): HtmlString
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
                    '<div class="mt-1 text-xs opacity-80">Submit pembanding: '.e($matchedSubmitted).' | Submit jawaban ini: '.e($laterSubmitted).' | Verifikasi: '.e($reviewedAt).'</div>'.
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

    protected function resolvePlagiarismReviewStatus($matches): string
    {
        if ($matches->contains(fn (PerpustakaanLiterasiSimilarityMatch $match): bool => $match->review_status === PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED)) {
            return PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED;
        }

        if ($matches->every(fn (PerpustakaanLiterasiSimilarityMatch $match): bool => $match->review_status === PerpustakaanLiterasiSimilarityMatch::REVIEW_CLEARED)) {
            return PerpustakaanLiterasiSimilarityMatch::REVIEW_CLEARED;
        }

        return PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED;
    }

    protected function savePlagiarismReview(
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

        $attributes = [
            'review_status' => $status,
            'reviewed_by' => $status === PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED ? null : $userId,
            'reviewed_at' => $status === PerpustakaanLiterasiSimilarityMatch::REVIEW_SUSPECTED ? null : now(),
            'review_note' => null,
        ];

        PerpustakaanLiterasiSimilarityMatch::query()
            ->where('later_response_id', $record->getKey())
            ->where('later_answer_id', $answer->getKey())
            ->update($attributes);
    }
}
