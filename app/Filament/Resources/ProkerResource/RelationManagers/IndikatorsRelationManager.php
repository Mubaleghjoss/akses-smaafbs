<?php

namespace App\Filament\Resources\ProkerResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class IndikatorsRelationManager extends RelationManager
{
    protected static string $relationship = 'indikators';

    protected static ?string $title = 'Checklist Indikator';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\TextInput::make('indikator')
                    ->label('Indikator')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('target')
                    ->label('Target / Ukuran Keberhasilan')
                    ->rows(3),
                Forms\Components\TextInput::make('bobot')
                    ->label('Bobot')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(1)
                    ->required(),
                Forms\Components\Toggle::make('is_checked')
                    ->label('Sudah Berjalan')
                    ->default(false),
                Forms\Components\DateTimePicker::make('checked_at')
                    ->label('Tanggal Ceklis'),
                Forms\Components\Textarea::make('catatan')
                    ->label('Catatan')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('urutan')
            ->columns([
                Tables\Columns\TextColumn::make('urutan')
                    ->label('Urutan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('indikator')
                    ->label('Indikator')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('bobot')
                    ->label('Bobot')
                    ->alignCenter(),
                Tables\Columns\IconColumn::make('is_checked')
                    ->label('Ceklis')
                    ->boolean(),
                Tables\Columns\TextColumn::make('checked_at')
                    ->label('Tercatat')
                    ->since()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('catatan')
                    ->label('Catatan')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_checked')
                    ->label('Status Ceklis'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
