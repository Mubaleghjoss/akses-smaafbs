<?php

namespace App\Filament\Resources\PerpustakaanLiterasiMaterialResource\RelationManagers;

use App\Models\PerpustakaanLiterasiSimilarityMatch;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class SimilarityMatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'similarityMatches';

    protected static ?string $title = 'Daftar Plagiat Per Kelas';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('student_class_snapshot')
            ->emptyStateHeading('Tidak ada indikasi plagiasi')
            ->emptyStateDescription('Jawaban yang muncul di sini hanya dari soal dengan deteksi plagiasi aktif. Soal yang deteksinya dinonaktifkan tidak dianalisa plagiasi dan dianggap tidak plagiasi karena pengaturan soal.')
            ->defaultSort('similarity_score', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'question',
                'laterResponse',
                'matchedResponse',
                'laterAnswer',
                'matchedAnswer',
                'reviewedBy',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('student_class_snapshot')
                    ->label('Kelas')
                    ->badge()
                    ->placeholder('Tanpa kelas')
                    ->searchable(),
                Tables\Columns\TextColumn::make('laterResponse.student_name_snapshot')
                    ->label('Pengirim Belakangan')
                    ->description(fn (PerpustakaanLiterasiSimilarityMatch $record): string => 'Pembanding: '.$record->matchedResponse?->student_name_snapshot)
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('similarity_score')
                    ->label('Kemiripan')
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state, 2, ',', '.').'%')
                    ->badge()
                    ->color('danger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('review_status')
                    ->label('Review')
                    ->formatStateUsing(fn (?string $state): string => PerpustakaanLiterasiSimilarityMatch::reviewStatusLabel($state))
                    ->badge()
                    ->color(fn (?string $state): string => PerpustakaanLiterasiSimilarityMatch::reviewStatusColor($state))
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('question.prompt')
                    ->label('Pertanyaan')
                    ->limit(60)
                    ->wrap()
                    ->visibleFrom('lg'),
                Tables\Columns\TextColumn::make('matched_submitted_at')
                    ->label('Submit Pembanding')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->visibleFrom('xl')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('later_submitted_at')
                    ->label('Submit Belakangan')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->visibleFrom('lg')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Diverifikasi')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->visibleFrom('xl')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Terdeteksi')
                    ->since()
                    ->visibleFrom('lg'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('student_class_snapshot')
                    ->label('Kelas')
                    ->options(fn (): array => $this->getOwnerRecord()
                        ->similarityMatches()
                        ->whereNotNull('student_class_snapshot')
                        ->where('student_class_snapshot', '!=', '')
                        ->distinct()
                        ->orderBy('student_class_snapshot')
                        ->pluck('student_class_snapshot', 'student_class_snapshot')
                        ->all()),
                Tables\Filters\SelectFilter::make('review_status')
                    ->label('Status Review')
                    ->options(PerpustakaanLiterasiSimilarityMatch::reviewStatusOptions()),
            ])
            ->headerActions([
                Action::make('disabledPlagiarismQuestionsInfo')
                    ->label('Soal Nonaktif Plagiasi')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->modalHeading('Soal yang tidak dianalisa plagiasi')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (): HtmlString => $this->disabledPlagiarismQuestionsHtml())
                    ->visible(fn (): bool => $this->disabledPlagiarismQuestions()->isNotEmpty()),
            ])
            ->actions([
                Action::make('lihatDetail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (PerpustakaanLiterasiSimilarityMatch $record): string => 'Detail Plagiat '.$record->student_class_snapshot)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('5xl')
                    ->modalContent(fn (PerpustakaanLiterasiSimilarityMatch $record) => view(
                        'filament.resources.perpustakaan-literasi-material-resource.partials.similarity-detail',
                        ['match' => $record->loadMissing('question', 'laterResponse', 'matchedResponse', 'laterAnswer', 'matchedAnswer', 'reviewedBy')]
                    )),
            ])
            ->bulkActions([]);
    }

    protected function disabledPlagiarismQuestions()
    {
        return $this->getOwnerRecord()
            ->questions()
            ->where('plagiarism_detection_enabled', false)
            ->orderBy('sort_order')
            ->get();
    }

    protected function disabledPlagiarismQuestionsHtml(): HtmlString
    {
        $questions = $this->disabledPlagiarismQuestions();

        if ($questions->isEmpty()) {
            return new HtmlString('<div class="text-sm text-gray-600 dark:text-gray-300">Semua soal pada materi ini masih mengaktifkan deteksi plagiasi.</div>');
        }

        $items = $questions
            ->map(fn ($question): string => '<li><strong>Pertanyaan '.number_format((int) $question->sort_order, 0, ',', '.').':</strong> '.e($question->prompt).'</li>')
            ->implode('');

        return new HtmlString(
            '<div class="space-y-3 text-sm leading-6 text-gray-700 dark:text-gray-200">'.
            '<p><strong>Tidak plagiasi karena soal dinonaktifkan plagiasi.</strong> Jawaban untuk soal berikut tidak dibuatkan indikasi plagiasi oleh sistem.</p>'.
            '<ul class="list-disc space-y-2 pl-5">'.$items.'</ul>'.
            '</div>'
        );
    }
}
