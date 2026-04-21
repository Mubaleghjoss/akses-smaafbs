<?php

namespace App\Filament\Resources\BoardingPerizinanSiswaResource\RelationManagers;

use App\Filament\Resources\BoardingPerizinanSiswaResource;
use App\Models\BoardingPerizinanSiswa;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class BoardingPerizinanSiswasRelationManager extends RelationManager
{
    protected static string $relationship = 'boardingPerizinanSiswas';

    protected static ?string $title = 'Riwayat Perizinan Boarding';

    protected static bool $isLazy = true;

    public function form(Schema $schema): Schema
    {
        return $schema->schema(BoardingPerizinanSiswaResource::permitFormSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->visibleToUser(auth()->user())
                ->select(BoardingPerizinanSiswaResource::historySelectColumns())
                ->with([
                    'diizinkanOlehUser:id,name',
                    'siswa:id,nama',
                ]))
            ->defaultSort('tanggal_izin', 'desc')
            ->recordTitleAttribute('judul_izin')
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_izin')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('judul_izin')
                    ->label('Perizinan')
                    ->description(fn (BoardingPerizinanSiswa $record): ?string => BoardingPerizinanSiswaResource::leaveSummaryDescription($record))
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('status_perizinan')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BoardingPerizinanSiswaResource::statusLabel($state))
                    ->color(fn (?string $state): string => ($state ?? 'pending') === 'selesai' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('diizinkan_oleh_ringkas')
                    ->label('Diizinkan Oleh')
                    ->state(fn (BoardingPerizinanSiswa $record): string => BoardingPerizinanSiswaResource::approvalLabel($record))
                    ->description(fn (BoardingPerizinanSiswa $record): ?string => BoardingPerizinanSiswaResource::approvalSourceLabel($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => BoardingPerizinanSiswaResource::applyApprovalSearch($query, $search))
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('kepulangan_ringkas')
                    ->label('Kepulangan')
                    ->state(fn (BoardingPerizinanSiswa $record): string => BoardingPerizinanSiswaResource::returnPrimaryLabel($record))
                    ->description(fn (BoardingPerizinanSiswa $record): ?string => BoardingPerizinanSiswaResource::returnDetailLabel($record))
                    ->wrap()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Perizinan')
                    ->visible(fn (): bool => BoardingPerizinanSiswaResource::userCanManageEntries()),
            ])
            ->actions([
                Action::make('ubahPengizin')
                    ->label('Ubah Pengizin')
                    ->icon('heroicon-o-user-circle')
                    ->color('warning')
                    ->modalWidth('2xl')
                    ->modalHeading('Ubah Yang Mengizinkan')
                    ->modalSubmitActionLabel('Simpan Pengizin')
                    ->fillForm(fn (BoardingPerizinanSiswa $record): array => [
                        'approval_mode' => filled($record->diizinkan_oleh_nama) && blank($record->diizinkan_oleh_user_id) ? 'manual' : 'akun',
                        'diizinkan_oleh_user_id' => $record->diizinkan_oleh_user_id,
                        'diizinkan_oleh_nama' => $record->diizinkan_oleh_nama,
                    ])
                    ->schema(BoardingPerizinanSiswaResource::approvalActionSchema())
                    ->action(function (BoardingPerizinanSiswa $record, array $data): void {
                        BoardingPerizinanSiswaResource::updateApproval($record, $data);

                        Notification::make()
                            ->title('Data pengizin diperbarui.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => BoardingPerizinanSiswaResource::userCanManageEntries()
                        && (BoardingPerizinanSiswa::approvalUserColumnAvailable() || BoardingPerizinanSiswa::approvalNameColumnAvailable())),
                Action::make('lengkapiKepulangan')
                    ->label(fn (BoardingPerizinanSiswa $record): string => filled($record->tanggal_kembali) ? 'Ubah Kepulangan' : 'Lengkapi Kepulangan')
                    ->icon('heroicon-o-check-circle')
                    ->color(fn (BoardingPerizinanSiswa $record): string => filled($record->tanggal_kembali) ? 'gray' : 'success')
                    ->modalHeading('Lengkapi Data Kedatangan Kembali')
                    ->modalSubmitActionLabel('Simpan Data Kembali')
                    ->modalWidth('2xl')
                    ->fillForm(fn (BoardingPerizinanSiswa $record): array => [
                        'tanggal_kembali' => $record->tanggal_kembali,
                        'waktu_kembali' => $record->waktu_kembali,
                        'detail_kembali' => $record->detail_kembali,
                        'kafaroh_keterlambatan' => $record->kafaroh_keterlambatan,
                    ])
                    ->schema(array_merge([
                        Placeholder::make('info_izin')
                            ->label('Ringkasan Izin')
                            ->content(fn (BoardingPerizinanSiswa $record): string => trim($record->judul_izin.' | '.($record->tanggal_izin?->format('d M Y') ?: '-').(filled($record->waktu_izin) ? ' '.Carbon::parse($record->waktu_izin)->format('H:i') : ''))),
                    ], BoardingPerizinanSiswaResource::returnFormSchema()))
                    ->action(function (BoardingPerizinanSiswa $record, array $data): void {
                        $record->update(BoardingPerizinanSiswa::buildReturnCompletionPayload($data));

                        Notification::make()
                            ->title('Data kepulangan diperbarui.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (): bool => BoardingPerizinanSiswaResource::userCanManageEntries()),
                EditAction::make()
                    ->visible(fn (): bool => BoardingPerizinanSiswaResource::userCanManageEntries()),
                DeleteAction::make()
                    ->visible(fn (): bool => BoardingPerizinanSiswaResource::userCanManageEntries()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => BoardingPerizinanSiswaResource::userCanManageEntries()),
                ]),
            ]);
    }
}
