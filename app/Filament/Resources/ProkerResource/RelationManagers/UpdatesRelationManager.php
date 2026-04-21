<?php

namespace App\Filament\Resources\ProkerResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'Update, Dokumentasi, dan Evaluasi';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Update Proker')
                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                    ->schema([
                        Forms\Components\DatePicker::make('tanggal_update')
                            ->label('Tanggal Update')
                            ->required()
                            ->default(now()),
                        Forms\Components\Select::make('status_snapshot')
                            ->label('Status Saat Update')
                            ->required()
                            ->options([
                                'draft' => 'Draft',
                                'berjalan' => 'Berjalan',
                                'terkendala' => 'Terkendala',
                                'selesai' => 'Selesai',
                            ])
                            ->default('berjalan'),
                        Forms\Components\TextInput::make('progress_persen')
                            ->label('Progress (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                        Forms\Components\Textarea::make('ringkasan')
                            ->label('Ringkasan Progress')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('evaluasi')
                            ->label('Catatan Evaluasi')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('tindak_lanjut')
                            ->label('Rencana Tindak Lanjut')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('dokumentasi')
                            ->label('Bukti Dokumentasi')
                            ->disk('public')
                            ->directory('proker/updates')
                            ->helperText('Dokumentasi yang sudah ada tetap tampil. Anda bisa menambah, mengurutkan, atau menghapus sebelum disimpan.')
                            ->multiple()
                            ->appendFiles()
                            ->downloadable()
                            ->openable()
                            ->reorderable()
                            ->panelLayout('grid')
                            ->maxFiles(10)
                            ->maxSize(4096)
                            ->columnSpanFull(),
                        Forms\Components\Hidden::make('created_by')
                            ->default(fn () => auth()->id()),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_update', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_update')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status_snapshot')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'berjalan' => 'primary',
                        'terkendala' => 'danger',
                        'selesai' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('progress_persen')
                    ->label('Progress')
                    ->formatStateUsing(fn ($state): string => $state === null ? '-' : ((int) $state.'%'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ringkasan')
                    ->label('Ringkasan')
                    ->limit(40)
                    ->wrap(),
                Tables\Columns\TextColumn::make('dokumentasi_count')
                    ->label('Dokumen')
                    ->state(fn ($record): int => count((array) ($record->dokumentasi ?? [])))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Input Oleh')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dicatat')
                    ->since()
                    ->toggleable(),
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
