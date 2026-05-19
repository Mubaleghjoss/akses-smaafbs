<?php

namespace App\Filament\Resources\PerpustakaanLiterasiMaterialResource\RelationManagers;

use App\Models\PerpustakaanLiterasiSimilarityMatch;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SimilarityMatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'similarityMatches';

    protected static ?string $title = 'Daftar Plagiat Per Kelas';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('student_class_snapshot')
            ->defaultSort('similarity_score', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'question',
                'laterResponse',
                'matchedResponse',
                'laterAnswer',
                'matchedAnswer',
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
                        ['match' => $record->loadMissing('question', 'laterResponse', 'matchedResponse', 'laterAnswer', 'matchedAnswer')]
                    )),
            ])
            ->bulkActions([]);
    }
}
