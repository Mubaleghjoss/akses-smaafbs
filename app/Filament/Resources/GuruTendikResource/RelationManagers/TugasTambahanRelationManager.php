<?php

namespace App\Filament\Resources\GuruTendikResource\RelationManagers;

use App\Models\GuruTendikTugasTambahan;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class TugasTambahanRelationManager extends RelationManager
{
    protected static string $relationship = 'tugasTambahan';

    protected static ?string $title = 'History Tugas Tambahan';

    protected static bool $isLazy = true;

    #[On('refresh-tugas-tambahan-relation-manager')]
    public function refreshRelationManager(): void
    {
        if (! isset($this->table)) {
            return;
        }

        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('tmt', 'desc')
            ->modifyQueryUsing(fn ($query) => $query
                ->select([
                    'id',
                    'guru_tendik_id',
                    'tugas_tambahan',
                    'no_sk',
                    'tmt',
                    'tst',
                    'sk_file_path',
                    'berkas_guru_id',
                ])
                ->with('berkasGuru:id'))
            ->columns([
                Tables\Columns\TextColumn::make('tugas_tambahan')
                    ->label('Tugas')
                    ->wrap(),
                Tables\Columns\TextColumn::make('no_sk')
                    ->label('No. SK')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tmt')
                    ->label('TMT')
                    ->date(),
                Tables\Columns\TextColumn::make('tst')
                    ->label('TST')
                    ->date()
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('sk_file_path')
                    ->label('File SK')
                    ->boolean(fn (GuruTendikTugasTambahan $record): bool => filled($record->sk_file_path)),
            ])
            ->headerActions([])
            ->actions([
                Action::make('viewSk')
                    ->label('View SK')
                    ->icon('heroicon-o-eye')
                    ->url(fn (GuruTendikTugasTambahan $record): ?string => $record->berkasGuru?->id ? route('admin.berkas-gurus.preview', $record->berkasGuru) : $record->resolvedSkUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (GuruTendikTugasTambahan $record): bool => filled($record->sk_file_path)),
            ])
            ->bulkActions([]);
    }
}
