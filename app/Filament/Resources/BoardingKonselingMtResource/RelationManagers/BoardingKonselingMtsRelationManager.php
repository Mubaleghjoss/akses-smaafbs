<?php

namespace App\Filament\Resources\BoardingKonselingMtResource\RelationManagers;

use App\Filament\Resources\BoardingKonselingMtResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BoardingKonselingMtsRelationManager extends RelationManager
{
    protected static string $relationship = 'boardingKonselingMts';

    protected static ?string $title = 'Riwayat Konseling Boarding';

    protected static bool $isLazy = true;

    public function form(Schema $schema): Schema
    {
        return $schema->schema(BoardingKonselingMtResource::noteFormSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->visibleToUser(auth()->user())
                ->select([
                    'id',
                    'siswa_id',
                    'pamong_user_id',
                    'tanggal_konseling',
                    'ringkasan_masalah',
                    'tindak_lanjut',
                    'created_at',
                ])
                ->with('pamongUser:id,name'))
            ->defaultSort('tanggal_konseling', 'desc')
            ->recordTitleAttribute('ringkasan_masalah')
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_konseling')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ringkasan_masalah')
                    ->label('Topik Pembahasan')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('tindak_lanjut')
                    ->label('Hasil Konseling')
                    ->limit(100)
                    ->wrap(),
                Tables\Columns\TextColumn::make('pamongUser.name')
                    ->label('Pamong')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dicatat')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Konseling')
                    ->visible(fn (): bool => BoardingKonselingMtResource::userCanManageEntries()),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => BoardingKonselingMtResource::userCanManageEntries()),
                DeleteAction::make()
                    ->visible(fn (): bool => BoardingKonselingMtResource::userCanManageEntries()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => BoardingKonselingMtResource::userCanManageEntries()),
                ]),
            ]);
    }
}
