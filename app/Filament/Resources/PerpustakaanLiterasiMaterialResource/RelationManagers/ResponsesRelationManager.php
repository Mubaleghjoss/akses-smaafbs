<?php

namespace App\Filament\Resources\PerpustakaanLiterasiMaterialResource\RelationManagers;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Models\PerpustakaanLiterasiAnswer;
use App\Models\PerpustakaanLiterasiResponse;
use App\Models\PerpustakaanLiterasiSimilarityMatch;
use App\Support\Literacy\LiteracyResponseGrading;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                ->withTrashed()
                ->with(['answers.question', 'answers.gradedBy', 'laterSimilarityMatches.matchedResponse', 'laterSimilarityMatches.reviewedBy'])
                ->withCount([
                    'answers' => fn (Builder $query): Builder => $query->withTrashed(),
                    'answers as graded_answers_count' => fn (Builder $query): Builder => $query->withTrashed()->whereNotNull('is_correct'),
                    'answers as correct_answers_count' => fn (Builder $query): Builder => $query->withTrashed()->where('is_correct', true),
                ])
                ->withSum([
                    'answers as score_earned_total' => fn (Builder $query): Builder => $query->withTrashed(),
                ], 'score_earned')
                ->withSum([
                    'answers as score_possible_total' => fn (Builder $query): Builder => $query->withTrashed(),
                ], 'score_possible')
                ->withSum([
                    'answers as graded_points_total' => fn (Builder $query): Builder => $query->withTrashed()->whereNotNull('score_earned'),
                ], 'score_possible'))
            ->columns([
                Tables\Columns\TextColumn::make('student_name_snapshot')
                    ->label('Siswa')
                    ->searchable()
                    ->description(fn (PerpustakaanLiterasiResponse $record): string => collect([
                        $record->student_class_snapshot,
                        'Kode: '.$record->shortEditCode(),
                        'Submit: '.($record->submission_delivery_code ?: 'LEGACY'),
                    ])->filter()->implode(' | '))
                    ->wrap(),
                Tables\Columns\TextColumn::make('student_class_snapshot')
                    ->label('Kelas')
                    ->badge()
                    ->placeholder('-')
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('submission_delivery_code')
                    ->label('Status Submit')
                    ->state(fn (PerpustakaanLiterasiResponse $record): string => $record->submission_delivery_code ?: 'LEGACY')
                    ->description(fn (PerpustakaanLiterasiResponse $record): string => $record->submissionDeliveryDescription())
                    ->badge()
                    ->color(fn (PerpustakaanLiterasiResponse $record): string => $record->submissionDeliveryColor()),
                Tables\Columns\TextColumn::make('answers_count')
                    ->label('Jawaban')
                    ->badge()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('grading_summary')
                    ->label('Nilai')
                    ->state(fn (PerpustakaanLiterasiResponse $record): string => number_format((int) ($record->score_earned_total ?? 0), 0, ',', '.')
                        .'/'.number_format((int) ($record->graded_points_total ?? 0), 0, ',', '.').' poin')
                    ->description(fn (PerpustakaanLiterasiResponse $record): string => number_format((int) ($record->graded_points_total ?? 0), 0, ',', '.')
                        .'/'.number_format((int) ($record->score_possible_total ?? 0), 0, ',', '.').' poin dinilai')
                    ->badge()
                    ->color(fn (PerpustakaanLiterasiResponse $record): string => ((int) ($record->score_possible_total ?? 0) > 0 && (int) ($record->graded_points_total ?? 0) >= (int) ($record->score_possible_total ?? 0)) ? 'success' : 'warning')
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
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Status')
                    ->state(fn (PerpustakaanLiterasiResponse $record): string => $record->trashed() ? 'Sampah' : 'Aktif')
                    ->description(fn (PerpustakaanLiterasiResponse $record): ?string => $record->trashed()
                        ? 'Dihapus '.$record->deleted_at?->format('d/m/Y H:i')
                        : null)
                    ->badge()
                    ->color(fn (PerpustakaanLiterasiResponse $record): string => $record->trashed() ? 'danger' : 'success')
                    ->visibleFrom('md'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('response_status')
                    ->label('Status Jawaban')
                    ->options([
                        'active' => 'Aktif',
                        'trash' => 'Sampah',
                    ])
                    ->default('active')
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? 'active') {
                            'trash' => $query->onlyTrashed(),
                            'active' => $query->withoutTrashed(),
                            default => $query,
                        };
                    }),
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
                Tables\Filters\SelectFilter::make('submission_delivery_code')
                    ->label('Status Submit')
                    ->options([
                        PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_DIRECT => 'OK-LANGSUNG - submit langsung',
                        PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_QUEUED => 'Q-ANTRE - sempat mengantre',
                        PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_RETRY_429 => 'R-429 - pulih setelah 429',
                        PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_RETRY_503 => 'R-503 - pulih setelah 503',
                        PerpustakaanLiterasiResponse::SUBMISSION_DELIVERY_RETRY_OTHER => 'R-RETRY - gangguan lain',
                    ]),
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
            ->headerActions([
                ActionGroup::make([
                    Action::make('showTrashedResponses')
                        ->label('Buka Sampah')
                        ->icon('heroicon-o-archive-box')
                        ->color('warning')
                        ->action(fn () => $this->showResponseStatus('trash')),
                    Action::make('emptyResponseTrash')
                        ->label('Hapus Permanen Semua')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (): bool => $this->canManageResponses() && $this->trashedResponseCount() > 0)
                        ->requiresConfirmation()
                        ->modalHeading('Hapus permanen semua jawaban di Sampah?')
                        ->modalDescription(fn (): string => 'Sebanyak '.$this->trashedResponseCount().' jawaban responden di Sampah akan dihapus permanen beserta jawaban per soal dan data plagiasi terkait. Tindakan ini tidak dapat dibatalkan.')
                        ->modalSubmitActionLabel('Ya, Hapus Permanen Semua')
                        ->action(function (): void {
                            $processed = $this->forceDeleteAllTrashedResponses();

                            $this->showResponseStatus('trash');
                            $this->notifyBulkAction(
                                'Sampah jawaban dikosongkan',
                                "Jawaban responden dihapus permanen: {$processed}.",
                                $processed,
                            );
                        }),
                ])
                    ->label(fn (): string => 'Sampah ('.$this->trashedResponseCount().')')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->button(),
            ])
            ->actions([
                Action::make('nilaiJawaban')
                    ->label(fn (PerpustakaanLiterasiResponse $record): string => $this->isResponseFullyGraded($record) ? 'Edit Nilai' : 'Nilai')
                    ->icon(fn (PerpustakaanLiterasiResponse $record): string => $this->isResponseFullyGraded($record) ? 'heroicon-o-pencil-square' : 'heroicon-o-check-circle')
                    ->color(fn (PerpustakaanLiterasiResponse $record): string => $this->isResponseFullyGraded($record) ? 'warning' : 'success')
                    ->visible(fn (PerpustakaanLiterasiResponse $record): bool => ! $record->trashed()
                        && PerpustakaanLiterasiMaterialResource::canEdit($this->getOwnerRecord()))
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
                    ->visible(fn (PerpustakaanLiterasiResponse $record): bool => ! $record->trashed())
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
                    ->visible(fn (PerpustakaanLiterasiResponse $record): bool => ! $record->trashed())
                    ->url(fn (PerpustakaanLiterasiResponse $record): string => $record->editUrl())
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('gradeSelectedResponses')
                        ->label('Nilai Jawaban Terpilih')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->visible(fn (): bool => $this->canManageResponses())
                        ->modalHeading('Nilai banyak jawaban sekaligus')
                        ->modalDescription('Nilai yang dipilih akan diterapkan pada pertanyaan dan siswa aktif yang dipilih. Jawaban di Sampah akan dilewati.')
                        ->modalSubmitActionLabel('Terapkan Nilai')
                        ->modalWidth('lg')
                        ->form(fn (): array => $this->bulkGradingFormSchema())
                        ->deselectRecordsAfterCompletion()
                        ->action(function (iterable $records, array $data): void {
                            $result = $this->gradeSelectedResponseRecords($records, $data);

                            $this->notifyBulkAction(
                                'Bulk penilaian selesai',
                                "Siswa diproses: {$result['responses']}. Jawaban dinilai: {$result['answers']}.",
                                $result['answers'],
                            );
                        }),
                    BulkAction::make('deleteSelectedResponses')
                        ->label('Pindahkan ke Sampah')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (): bool => $this->canManageResponses())
                        ->requiresConfirmation()
                        ->modalHeading('Pindahkan jawaban terpilih ke Sampah?')
                        ->modalDescription('Jawaban responden, jawaban per soal, dan data plagiasi terkait akan disembunyikan. Data masih dapat direstore dari filter Sampah.')
                        ->modalSubmitActionLabel('Pindahkan ke Sampah')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (iterable $records): void {
                            $processed = $this->deleteResponseRecords($records);
                            $this->notifyBulkAction(
                                'Jawaban dipindahkan ke Sampah',
                                "Total jawaban responden: {$processed}.",
                                $processed,
                            );
                        }),
                    BulkAction::make('restoreSelectedResponses')
                        ->label('Restore dari Sampah')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (): bool => $this->canManageResponses())
                        ->requiresConfirmation()
                        ->modalHeading('Restore jawaban terpilih?')
                        ->modalDescription('Hanya jawaban yang berada di Sampah yang akan diaktifkan kembali beserta jawaban per soal dan data plagiasinya.')
                        ->modalSubmitActionLabel('Restore Jawaban')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (iterable $records): void {
                            $processed = $this->restoreResponseRecords($records);
                            $this->notifyBulkAction(
                                'Restore jawaban selesai',
                                "Jawaban responden aktif kembali: {$processed}.",
                                $processed,
                            );
                        }),
                    BulkAction::make('forceDeleteSelectedResponses')
                        ->label('Hapus Permanen')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (): bool => $this->canManageResponses())
                        ->requiresConfirmation()
                        ->modalHeading('Hapus permanen jawaban terpilih?')
                        ->modalDescription('Hanya jawaban yang sudah berada di Sampah yang akan dihapus permanen. Jawaban per soal dan data plagiasi terkait ikut dihapus dan tidak dapat direstore.')
                        ->modalSubmitActionLabel('Hapus Permanen')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (iterable $records): void {
                            $processed = $this->forceDeleteResponseRecords($records);
                            $this->notifyBulkAction(
                                'Hapus permanen selesai',
                                "Jawaban responden dihapus permanen: {$processed}.",
                                $processed,
                            );
                        }),
                ]),
            ]);
    }

    protected function canManageResponses(): bool
    {
        return PerpustakaanLiterasiMaterialResource::canEdit($this->getOwnerRecord());
    }

    protected function showResponseStatus(string $status): void
    {
        $filters = is_array($this->tableFilters) ? $this->tableFilters : [];
        $filters['response_status']['value'] = $status === 'trash' ? 'trash' : 'active';
        $this->tableFilters = $filters;
        $this->resetPage();
    }

    protected function trashedResponseCount(): int
    {
        return PerpustakaanLiterasiResponse::onlyTrashed()
            ->where('material_id', $this->getOwnerRecord()->getKey())
            ->count();
    }

    protected function forceDeleteAllTrashedResponses(): int
    {
        abort_unless($this->canManageResponses(), 403);

        $processed = 0;
        $materialId = (int) $this->getOwnerRecord()->getKey();

        DB::transaction(function () use ($materialId, &$processed): void {
            PerpustakaanLiterasiResponse::onlyTrashed()
                ->where('material_id', $materialId)
                ->orderBy('id')
                ->get()
                ->each(function (PerpustakaanLiterasiResponse $response) use (&$processed): void {
                    $response->forceDelete();
                    $processed++;
                });
        });

        return $processed;
    }

    protected function bulkGradingFormSchema(): array
    {
        $questionOptions = $this->bulkGradingQuestionOptions();

        return [
            Section::make('Pengaturan Nilai')
                ->columns(['default' => 1])
                ->schema([
                    Forms\Components\CheckboxList::make('question_ids')
                        ->label('Pertanyaan yang Dinilai')
                        ->options($questionOptions)
                        ->searchable()
                        ->bulkToggleable()
                        ->columns(['default' => 1, 'md' => 2])
                        ->required()
                        ->helperText('Pilih pertanyaan tertentu atau gunakan Pilih Semua untuk menilai seluruh pertanyaan.'),
                    Forms\Components\Radio::make('status')
                        ->label('Nilai')
                        ->options([
                            'correct' => 'Benar',
                            'wrong' => 'Salah',
                            'ungraded' => 'Reset menjadi Belum Dinilai',
                        ])
                        ->required(),
                    Forms\Components\Textarea::make('note')
                        ->label('Catatan untuk Semua Jawaban')
                        ->rows(3)
                        ->maxLength(1000)
                        ->helperText('Opsional. Catatan yang sama diterapkan pada jawaban yang dipilih.'),
                ]),
        ];
    }

    protected function bulkGradingQuestionOptions(): array
    {
        return $this->getOwnerRecord()
            ->questions()
            ->orderBy('sort_order')
            ->get(['id', 'prompt', 'sort_order'])
            ->mapWithKeys(fn ($question): array => [
                $question->getKey() => 'Pertanyaan '.$question->sort_order.' - '.Str::limit(trim((string) $question->prompt), 90),
            ])
            ->all();
    }

    /**
     * @param  iterable<PerpustakaanLiterasiResponse>  $records
     * @return array{responses:int,answers:int}
     */
    protected function gradeSelectedResponseRecords(iterable $records, array $data): array
    {
        abort_unless($this->canManageResponses(), 403);

        $materialId = (int) $this->getOwnerRecord()->getKey();
        $questionIds = $this->getOwnerRecord()
            ->questions()
            ->whereKey(collect($data['question_ids'] ?? [])->map(fn ($id): int => (int) $id)->filter()->unique()->all())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $status = (string) ($data['status'] ?? '');
        $note = trim((string) ($data['note'] ?? ''));
        $processedResponses = 0;
        $processedAnswers = 0;

        if ($questionIds === [] || ! in_array($status, ['correct', 'wrong', 'ungraded'], true)) {
            return ['responses' => 0, 'answers' => 0];
        }

        DB::transaction(function () use ($records, $materialId, $questionIds, $status, $note, &$processedResponses, &$processedAnswers): void {
            foreach ($records as $record) {
                if (! $record instanceof PerpustakaanLiterasiResponse) {
                    continue;
                }

                $response = PerpustakaanLiterasiResponse::query()
                    ->where('material_id', $materialId)
                    ->find($record->getKey());

                if (! $response) {
                    continue;
                }

                $answers = $response->answers()
                    ->whereIn('question_id', $questionIds)
                    ->get();

                if ($answers->isEmpty()) {
                    continue;
                }

                foreach ($answers as $answer) {
                    if ($status === 'ungraded') {
                        $answer->forceFill([
                            'is_correct' => null,
                            'score_earned' => null,
                            'grading_source' => null,
                            'graded_by' => null,
                            'graded_at' => null,
                            'grading_note' => null,
                        ])->save();
                    } else {
                        $answer->forceFill([
                            'is_correct' => $status === 'correct',
                            'score_earned' => $status === 'correct'
                                ? max(1, (int) ($answer->score_possible ?? 1))
                                : 0,
                            'score_possible' => max(1, (int) ($answer->score_possible ?? 1)),
                            'grading_source' => 'manual',
                            'graded_by' => auth()->id(),
                            'graded_at' => now(),
                            'grading_note' => $note !== '' ? $note : null,
                        ])->save();
                    }

                    $processedAnswers++;
                }

                $processedResponses++;
            }
        });

        return [
            'responses' => $processedResponses,
            'answers' => $processedAnswers,
        ];
    }

    /**
     * @param  iterable<PerpustakaanLiterasiResponse>  $records
     */
    protected function deleteResponseRecords(iterable $records): int
    {
        abort_unless($this->canManageResponses(), 403);

        return $this->processResponseRecords($records, function (PerpustakaanLiterasiResponse $response): bool {
            if ($response->trashed()) {
                return false;
            }

            $response->delete();

            return true;
        });
    }

    /**
     * @param  iterable<PerpustakaanLiterasiResponse>  $records
     */
    protected function restoreResponseRecords(iterable $records): int
    {
        abort_unless($this->canManageResponses(), 403);

        return $this->processResponseRecords($records, function (PerpustakaanLiterasiResponse $response): bool {
            if (! $response->trashed()) {
                return false;
            }

            $response->restore();

            return true;
        });
    }

    /**
     * @param  iterable<PerpustakaanLiterasiResponse>  $records
     */
    protected function forceDeleteResponseRecords(iterable $records): int
    {
        abort_unless($this->canManageResponses(), 403);

        return $this->processResponseRecords($records, function (PerpustakaanLiterasiResponse $response): bool {
            if (! $response->trashed()) {
                return false;
            }

            $response->forceDelete();

            return true;
        });
    }

    /**
     * @param  iterable<PerpustakaanLiterasiResponse>  $records
     */
    protected function processResponseRecords(iterable $records, callable $callback): int
    {
        $processed = 0;
        $materialId = (int) $this->getOwnerRecord()->getKey();

        DB::transaction(function () use ($records, $callback, $materialId, &$processed): void {
            foreach ($records as $record) {
                if (! $record instanceof PerpustakaanLiterasiResponse) {
                    continue;
                }

                $fresh = PerpustakaanLiterasiResponse::withTrashed()
                    ->where('material_id', $materialId)
                    ->find($record->getKey());

                if ($fresh && $callback($fresh)) {
                    $processed++;
                }
            }
        });

        return $processed;
    }

    protected function notifyBulkAction(string $title, string $body, int $processed): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->{$processed > 0 ? 'success' : 'warning'}()
            ->send();
    }

    protected function gradingFormSchema(PerpustakaanLiterasiResponse $record): array
    {
        return app(LiteracyResponseGrading::class)->schema($record);
    }

    protected function gradingFormData(PerpustakaanLiterasiResponse $record): array
    {
        return app(LiteracyResponseGrading::class)->data($record);
    }

    protected function saveGrades(PerpustakaanLiterasiResponse $record, array $data): void
    {
        app(LiteracyResponseGrading::class)->save($record, $data, auth()->id());

        Notification::make()
            ->title('Nilai jawaban tersimpan')
            ->success()
            ->send();
    }

    protected function isResponseFullyGraded(PerpustakaanLiterasiResponse $record): bool
    {
        return app(LiteracyResponseGrading::class)->isFullyGraded($record);
    }

}
