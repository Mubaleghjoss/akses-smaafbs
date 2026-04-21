<?php

namespace App\Filament\Resources\BoardingArsipMtResource\RelationManagers;

use App\Models\BoardingArsipMt;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = 'History Status Arsip';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->since(),
                Tables\Columns\TextColumn::make('status_lama')
                    ->label('Status Lama')
                    ->formatStateUsing(fn (?string $state): string => $state ? BoardingArsipMt::statusLabel($state) : '-'),
                Tables\Columns\TextColumn::make('status_baru')
                    ->label('Status Baru')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BoardingArsipMt::statusLabel($state)),
                Tables\Columns\TextColumn::make('user_name')
                    ->label('Diubah Oleh')
                    ->placeholder('-'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
