<?php

namespace App\Filament\Resources\SurveiResource\RelationManagers;

use App\Models\SurveiTarget;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TargetsRelationManager extends RelationManager
{
    protected static string $relationship = 'targets';

    protected static ?string $title = 'Daftar Pengisian Survei';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('recipient_name_snapshot')
            ->defaultSort('recipient_name_snapshot')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['submission', 'student:id,nama,rombel_saat_ini', 'guruTendik:id,nama,jenis_ptk']))
            ->columns([
                Tables\Columns\TextColumn::make('recipient_name_snapshot')
                    ->label('Target')
                    ->searchable()
                    ->description(fn (SurveiTarget $record): string => collect([
                        $record->recipientContext(),
                        SurveiTarget::statusLabel($record->submission_status),
                        filled($record->whatsapp_number) ? 'WA: '.$record->whatsapp_number : null,
                    ])->filter()->implode(' | '))
                    ->wrap(),
                Tables\Columns\TextColumn::make('submission_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => SurveiTarget::statusLabel($state))
                    ->color(fn (?string $state): string => SurveiTarget::statusColor($state))
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('whatsapp_number')
                    ->label('Nomor WA')
                    ->placeholder('-')
                    ->visibleFrom('lg')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Waktu Isi')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->visibleFrom('lg')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('submission_status')
                    ->label('Status Pengisian')
                    ->options(SurveiTarget::statusOptions()),
            ])
            ->headerActions([])
            ->actions([
                Action::make('lihatHasil')
                    ->label('Hasil')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn (SurveiTarget $record): bool => $record->submission !== null)
                    ->modalHeading(fn (SurveiTarget $record): string => 'Hasil Survei: '.$record->recipientName())
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('4xl')
                    ->modalContent(fn (SurveiTarget $record) => view(
                        'filament.resources.survei-resource.partials.submission-results',
                        ['target' => $record->loadMissing('survei.questions', 'submission')]
                    )),
                ActionGroup::make([
                    Action::make('isiWa')
                        ->label('Isi / Ubah WA')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->color('gray')
                        ->fillForm(fn (SurveiTarget $record): array => [
                            'whatsapp_number' => $record->whatsapp_number,
                        ])
                        ->form([
                            \Filament\Forms\Components\TextInput::make('whatsapp_number')
                                ->label('Nomor WA Tujuan')
                                ->tel()
                                ->maxLength(32)
                                ->helperText('Isi nomor WA ortu untuk survei murid, atau nomor pribadi untuk guru/tendik.'),
                        ])
                        ->action(function (SurveiTarget $record, array $data): void {
                            $record->forceFill([
                                'whatsapp_number' => filled($data['whatsapp_number'] ?? null)
                                    ? trim((string) $data['whatsapp_number'])
                                    : null,
                            ])->save();

                            Notification::make()
                                ->success()
                                ->title('Nomor WA berhasil diperbarui.')
                                ->send();
                        }),
                    Action::make('kirimWa')
                        ->label('Kirim WA')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('primary')
                        ->visible(fn (SurveiTarget $record): bool => filled($record->whatsappUrl()))
                        ->url(fn (SurveiTarget $record): ?string => $record->whatsappUrl())
                        ->openUrlInNewTab(),
                    Action::make('bukaLink')
                        ->label('Buka Link')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('gray')
                        ->url(fn (SurveiTarget $record): string => $record->publicUrl())
                        ->openUrlInNewTab(),
                ])
                    ->label('Aksi')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->bulkActions([]);
    }
}
