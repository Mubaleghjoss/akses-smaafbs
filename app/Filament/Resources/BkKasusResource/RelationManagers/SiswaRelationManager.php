<?php

namespace App\Filament\Resources\BkKasusResource\RelationManagers;

use App\Filament\Resources\BkKasusResource;
use App\Models\BkKasus;
use App\Models\DataSiswa;
use App\Support\Bk\BkKasusSiswaSync;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SiswaRelationManager extends RelationManager
{
    protected static string $relationship = 'siswa';

    protected static ?string $title = 'Siswa Terlibat';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->defaultSort('nama')
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('rombel_snapshot')
                    ->label('Kelas Saat Kasus')
                    ->state(fn (DataSiswa $record): string => (string) (
                        $record->pivot?->rombel_snapshot ?: ($record->rombel_saat_ini ?: '-')
                    ))
                    ->badge(),
                Tables\Columns\TextColumn::make('rombel_saat_ini')
                    ->label('Kelas Sekarang')
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('nisn')
                    ->label('NISN')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status Siswa')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Tambah Siswa')
                    ->modalHeading('Tambah siswa ke laporan ini')
                    ->recordSelectSearchColumns(['nama', 'nisn', 'rombel_saat_ini'])
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('status', 'aktif'))
                    ->preloadRecordSelect(false)
                    ->using(function (array $data): void {
                        /** @var BkKasus $kasus */
                        $kasus = $this->getOwnerRecord();

                        BkKasusSiswaSync::attach($kasus, (int) $data['recordId']);
                    })
                    ->visible(fn (): bool => BkKasusResource::canEdit($this->getOwnerRecord())),
            ])
            ->actions([
                DetachAction::make()
                    ->label('Keluarkan')
                    ->requiresConfirmation()
                    ->modalHeading('Keluarkan siswa dari laporan?')
                    ->modalDescription('Siswa hanya dilepas dari laporan ini; data siswa tidak dihapus.')
                    ->visible(fn (): bool => BkKasusResource::canEdit($this->getOwnerRecord())),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Keluarkan Terpilih')
                        ->requiresConfirmation(),
                ]),
            ])
            ->emptyStateHeading('Belum ada siswa pada laporan ini')
            ->emptyStateDescription('Tambahkan siswa agar laporan SIGAP terhitung pada rekap kelas.');
    }
}
