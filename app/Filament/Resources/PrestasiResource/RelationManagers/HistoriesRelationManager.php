<?php

namespace App\Filament\Resources\PrestasiResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = 'History Prestasi';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordTitleAttribute('aksi')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->since(),
                Tables\Columns\TextColumn::make('aksi')
                    ->label('Aksi')
                    ->badge(),
                Tables\Columns\TextColumn::make('judul_ringkas')
                    ->label('Ringkasan')
                    ->wrap(),
                Tables\Columns\TextColumn::make('user_name')
                    ->label('Pengubah')
                    ->placeholder('-'),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
