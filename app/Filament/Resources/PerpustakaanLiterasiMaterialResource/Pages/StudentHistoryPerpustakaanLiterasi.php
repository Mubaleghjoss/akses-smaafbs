<?php

namespace App\Filament\Resources\PerpustakaanLiterasiMaterialResource\Pages;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Models\PerpustakaanLiterasiAnswer;
use App\Models\PerpustakaanLiterasiMaterial;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class StudentHistoryPerpustakaanLiterasi extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = PerpustakaanLiterasiMaterialResource::class;

    protected static ?string $title = 'History Pengerjaan Siswa';

    protected static ?string $breadcrumb = 'History Siswa';

    protected string $view = 'filament.resources.perpustakaan-literasi-material-resource.pages.student-history';

    public function mount(): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restoreDeletedMaterialAction')
                ->label('Restore Materi Terhapus')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => static::getResource()::canCreate()
                    && PerpustakaanLiterasiMaterial::onlyTrashed()->exists())
                ->modalHeading('Restore materi dan responden?')
                ->modalDescription('Pilih materi yang ingin dikembalikan. Semua history siswa, jawaban, dan data plagiasi pada materi tersebut ikut dikembalikan.')
                ->modalSubmitActionLabel('Restore Materi')
                ->schema([
                    Forms\Components\Select::make('material_id')
                        ->label('Materi yang dikembalikan')
                        ->options(fn (): array => $this->deletedMaterialOptions())
                        ->searchable()
                        ->native(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->restoreDeletedMaterial((int) ($data['material_id'] ?? 0));
                }),
        ];
    }

    public function getViewData(): array
    {
        $responses = PerpustakaanLiterasiResponse::query();
        $deletedResponses = PerpustakaanLiterasiResponse::onlyTrashed();

        return [
            'summaryMetrics' => [
                'students' => (clone $responses)->distinct('data_siswa_id')->count('data_siswa_id'),
                'responses' => (clone $responses)->count(),
                'deleted_responses' => (clone $deletedResponses)->count(),
                'deleted_materials' => PerpustakaanLiterasiMaterial::onlyTrashed()->count(),
                'graded_responses' => (clone $responses)
                    ->whereHas('answers')
                    ->whereDoesntHave('answers', fn (Builder $query): Builder => $query->whereNull('is_correct'))
                    ->count(),
                'graders' => PerpustakaanLiterasiAnswer::query()
                    ->whereNotNull('graded_by')
                    ->distinct('graded_by')
                    ->count('graded_by'),
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PerpustakaanLiterasiResponse::withTrashed()
                ->with([
                    'material:id,title,deleted_at',
                    'answers' => fn ($query) => $query
                        ->withTrashed()
                        ->with([
                            'question' => fn ($questionQuery) => $questionQuery
                                ->withTrashed()
                                ->select('id', 'material_id', 'sort_order', 'prompt'),
                            'gradedBy:id,name',
                        ]),
                    'laterSimilarityMatches' => fn ($query) => $query->withTrashed(),
                ])
                ->withCount([
                    'answers' => fn (Builder $query): Builder => $query->withTrashed(),
                    'answers as graded_answers_count' => fn (Builder $query): Builder => $query->withTrashed()->whereNotNull('is_correct'),
                    'answers as correct_answers_count' => fn (Builder $query): Builder => $query->withTrashed()->where('is_correct', true),
                    'laterSimilarityMatches as confirmed_plagiarism_count' => fn (Builder $query): Builder => $query
                        ->withTrashed()
                        ->where('review_status', PerpustakaanLiterasiSimilarityMatch::REVIEW_CONFIRMED),
                ]))
            ->defaultSort('submitted_at', 'desc')
            ->striped()
            ->deferLoading()
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50, 100])
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->searchDebounce('600ms')
            ->searchPlaceholder('Cari siswa, kelas, atau materi...')
            ->emptyStateHeading('Belum ada history pengerjaan')
            ->emptyStateDescription('History akan muncul setelah siswa mengirim jawaban Literacy Habituation Program.')
            ->columns([
                Tables\Columns\TextColumn::make('student_name_snapshot')
                    ->label('Siswa')
                    ->searchable()
                    ->description(fn (PerpustakaanLiterasiResponse $record): string => $record->student_class_snapshot ?: 'Tanpa kelas')
                    ->wrap(),
                Tables\Columns\TextColumn::make('material.title')
                    ->label('Materi')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Status')
                    ->state(fn (PerpustakaanLiterasiResponse $record): string => $record->trashed() ? 'Terhapus' : 'Aktif')
                    ->description(fn (PerpustakaanLiterasiResponse $record): ?string => $record->trashed()
                        ? 'Dihapus '.$record->deleted_at?->format('d/m/Y H:i')
                        : null)
                    ->badge()
                    ->color(fn (PerpustakaanLiterasiResponse $record): string => $record->trashed() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Dikirim')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('grading_summary')
                    ->label('Nilai')
                    ->state(fn (PerpustakaanLiterasiResponse $record): string => number_format((int) ($record->correct_answers_count ?? 0), 0, ',', '.')
                        .'/'.number_format((int) ($record->graded_answers_count ?? 0), 0, ',', '.').' benar')
                    ->description(fn (PerpustakaanLiterasiResponse $record): string => number_format((int) ($record->graded_answers_count ?? 0), 0, ',', '.')
                        .'/'.number_format((int) ($record->answers_count ?? 0), 0, ',', '.').' dinilai')
                    ->badge()
                    ->color(fn (PerpustakaanLiterasiResponse $record): string => $this->isResponseFullyGraded($record) ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('graders')
                    ->label('Penilai')
                    ->state(fn (PerpustakaanLiterasiResponse $record): string => $this->graderNames($record))
                    ->wrap()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('confirmed_plagiarism_count')
                    ->label('Konfirmasi Plagiat')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->visibleFrom('lg'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('deleted_at')
                    ->label('Status History')
                    ->placeholder('Semua')
                    ->trueLabel('Terhapus')
                    ->falseLabel('Aktif')
                    ->nullable(),
                Tables\Filters\SelectFilter::make('material_id')
                    ->label('Materi')
                    ->options(fn (): array => PerpustakaanLiterasiMaterial::query()
                        ->withTrashed()
                        ->orderBy('title')
                        ->pluck('title', 'id')
                        ->all()),
                Tables\Filters\SelectFilter::make('student_class_snapshot')
                    ->label('Kelas')
                    ->options(fn (): array => PerpustakaanLiterasiResponse::withTrashed()
                        ->whereNotNull('student_class_snapshot')
                        ->where('student_class_snapshot', '!=', '')
                        ->distinct()
                        ->orderBy('student_class_snapshot')
                        ->pluck('student_class_snapshot', 'student_class_snapshot')
                        ->all()),
                Tables\Filters\TernaryFilter::make('grading_complete')
                    ->label('Status Penilaian')
                    ->trueLabel('Sudah dinilai')
                    ->falseLabel('Belum lengkap')
                    ->queries(
                        true: fn (Builder $query): Builder => $query
                            ->whereHas('answers', fn (Builder $answerQuery): Builder => $answerQuery->withTrashed())
                            ->whereDoesntHave('answers', fn (Builder $answerQuery): Builder => $answerQuery->withTrashed()->whereNull('is_correct')),
                        false: fn (Builder $query): Builder => $query
                            ->whereHas('answers', fn (Builder $answerQuery): Builder => $answerQuery->withTrashed()->whereNull('is_correct')),
                    ),
            ])
            ->actions([
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (PerpustakaanLiterasiResponse $record): bool => ! $this->canManageResponse($record))
                    ->modalHeading(fn (PerpustakaanLiterasiResponse $record): string => 'History: '.$record->student_name_snapshot)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('6xl')
                    ->modalContent(fn (PerpustakaanLiterasiResponse $record) => $this->studentHistoryDetailView($record)),
                Action::make('detailEdit')
                    ->label(fn (PerpustakaanLiterasiResponse $record): string => $this->isResponseFullyGraded($record) ? 'Detail / Edit Nilai' : 'Detail / Nilai')
                    ->icon(fn (PerpustakaanLiterasiResponse $record): string => $this->isResponseFullyGraded($record) ? 'heroicon-o-pencil-square' : 'heroicon-o-check-circle')
                    ->color(fn (PerpustakaanLiterasiResponse $record): string => $this->isResponseFullyGraded($record) ? 'warning' : 'success')
                    ->visible(fn (PerpustakaanLiterasiResponse $record): bool => $this->canManageResponse($record))
                    ->modalHeading(fn (PerpustakaanLiterasiResponse $record): string => 'History dan Nilai: '.$record->student_name_snapshot)
                    ->modalDescription('Admin atau guru dengan akses manage dapat mengubah penilaian dan status plagiasi dari halaman history ini.')
                    ->modalSubmitActionLabel('Simpan Nilai')
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('6xl')
                    ->modalContent(fn (PerpustakaanLiterasiResponse $record) => $this->studentHistoryDetailView($record))
                    ->form(fn (PerpustakaanLiterasiResponse $record): array => $this->gradingFormSchema($record))
                    ->fillForm(fn (PerpustakaanLiterasiResponse $record): array => $this->gradingFormData($record))
                    ->action(function (PerpustakaanLiterasiResponse $record, array $data): void {
                        abort_unless($this->canManageResponse($record), 403);

                        $this->saveGrades($record, $data);
                    }),
            ]);
    }

    protected function canManageResponse(PerpustakaanLiterasiResponse $record): bool
    {
        $record->loadMissing('material');

        if ($record->trashed() || $record->material?->trashed()) {
            return false;
        }

        return $record->material instanceof PerpustakaanLiterasiMaterial
            && static::getResource()::canEdit($record->material);
    }

    protected function studentHistoryDetailView(PerpustakaanLiterasiResponse $record)
    {
        return view(
            'filament.resources.perpustakaan-literasi-material-resource.partials.student-history-detail',
            [
                'response' => $record->loadMissing([
                    'material' => fn ($query) => $query->withTrashed(),
                    'answers' => fn ($query) => $query
                        ->withTrashed()
                        ->with([
                            'question' => fn ($questionQuery) => $questionQuery->withTrashed(),
                            'gradedBy',
                        ]),
                    'laterSimilarityMatches' => fn ($query) => $query
                        ->withTrashed()
                        ->with([
                            'question' => fn ($questionQuery) => $questionQuery->withTrashed(),
                            'matchedResponse' => fn ($responseQuery) => $responseQuery->withTrashed(),
                            'matchedAnswer' => fn ($answerQuery) => $answerQuery->withTrashed(),
                        ]),
                ]),
            ]
        );
    }

    protected function isResponseFullyGraded(PerpustakaanLiterasiResponse $record): bool
    {
        $total = (int) ($record->answers_count ?? 0);
        $graded = (int) ($record->graded_answers_count ?? 0);

        return $total > 0 && $graded >= $total;
    }

    protected function gradingFormSchema(PerpustakaanLiterasiResponse $record): array
    {
        $record->loadMissing('material.questions', 'answers.question');
        $answers = $record->answers->keyBy('question_id');
        $plagiarismMatchesByAnswer = $record->laterSimilarityMatches()
            ->with(['matchedResponse', 'matchedAnswer'])
            ->get()
            ->groupBy('later_answer_id');

        return $record->material->questions
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

                if ($plagiarismMatches->isNotEmpty()) {
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
            ->values()
            ->all();
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
            ->title('Nilai history siswa tersimpan')
            ->success()
            ->send();
    }

    protected function graderNames(PerpustakaanLiterasiResponse $record): string
    {
        $names = $record->answers
            ->map(fn (PerpustakaanLiterasiAnswer $answer): ?string => $answer->gradedBy?->name)
            ->filter()
            ->unique()
            ->values();

        return $names->isNotEmpty() ? $names->implode(', ') : 'Belum dinilai';
    }

    protected function plagiarismMatchesHtml($matches): HtmlString
    {
        $items = $matches
            ->map(function (PerpustakaanLiterasiSimilarityMatch $match): string {
                $student = $match->matchedResponse?->student_name_snapshot ?: 'Pembanding sebelumnya';
                $class = $match->matchedResponse?->student_class_snapshot ?: '-';
                $score = number_format((float) $match->similarity_score, 2, ',', '.').'%';
                $status = PerpustakaanLiterasiSimilarityMatch::reviewStatusLabel($match->review_status);

                return '<li><strong>'.e($score).'</strong> mirip dengan '.e($student).' ('.e($class).') - '.e($status).'</li>';
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

    /**
     * @return array<int, string>
     */
    protected function deletedMaterialOptions(): array
    {
        return PerpustakaanLiterasiMaterial::onlyTrashed()
            ->withCount([
                'responses as deleted_responses_count' => fn (Builder $query): Builder => $query->withTrashed(),
            ])
            ->orderByDesc('deleted_at')
            ->orderBy('title')
            ->get()
            ->mapWithKeys(function (PerpustakaanLiterasiMaterial $material): array {
                $deletedAt = $material->deleted_at?->format('d/m/Y H:i') ?? '-';
                $responses = number_format((int) ($material->deleted_responses_count ?? 0), 0, ',', '.');

                return [
                    $material->getKey() => "{$material->title} - {$responses} responden - dihapus {$deletedAt}",
                ];
            })
            ->all();
    }

    public function restoreDeletedMaterial(int $materialId): void
    {
        abort_unless(static::getResource()::canCreate(), 403);

        $material = PerpustakaanLiterasiMaterial::onlyTrashed()->findOrFail($materialId);

        DB::transaction(function () use ($material): void {
            $material->restore();
        });

        Notification::make()
            ->title('Materi berhasil direstore')
            ->body('Materi, history responden, jawaban, dan data plagiasi terkait sudah aktif kembali.')
            ->success()
            ->send();
    }
}
