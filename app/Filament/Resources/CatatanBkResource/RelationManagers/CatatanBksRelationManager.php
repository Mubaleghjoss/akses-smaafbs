<?php

namespace App\Filament\Resources\CatatanBkResource\RelationManagers;

use App\Filament\Resources\CatatanBkResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class CatatanBksRelationManager extends RelationManager
{
    protected static string $relationship = 'catatanBks';

    protected static ?string $title = 'Riwayat Konseling BK';

    protected static bool $isLazy = false;

    #[On('refresh-catatan-bk-relation-manager')]
    public function refreshRelationManager(): void
    {
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema(CatatanBkResource::noteFormSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_konseling', 'desc')
            ->recordTitleAttribute('topik_pembahasan')
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_konseling')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('topik_pembahasan')
                    ->label('Topik Pembahasan')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('hasil_konseling')
                    ->label('Hasil Konseling')
                    ->limit(100)
                    ->wrap(),
                Tables\Columns\TextColumn::make('petugas.name')
                    ->label('Petugas')
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
                    ->visible(fn (): bool => CatatanBkResource::userCanManageEntries()),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (): bool => CatatanBkResource::userCanManageEntries()),
                DeleteAction::make()
                    ->visible(fn (): bool => CatatanBkResource::userCanManageEntries()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => CatatanBkResource::userCanManageEntries()),
                ]),
            ]);
    }
}
