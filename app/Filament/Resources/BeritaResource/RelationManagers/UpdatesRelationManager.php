<?php

namespace App\Filament\Resources\BeritaResource\RelationManagers;

use App\Models\BeritaUpdate;
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

    protected static ?string $title = 'Timeline perkembangan kegiatan';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Update tahap kegiatan')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\DateTimePicker::make('tanggal_update')
                            ->label('Waktu update')
                            ->seconds(false)
                            ->default(now())
                            ->required(),
                        Forms\Components\Select::make('phase')
                            ->label('Tahap kegiatan')
                            ->options(BeritaUpdate::PHASES)
                            ->required(),
                        Forms\Components\TextInput::make('progress_percent')
                            ->label('Progres saat ini')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->nullable(),
                        Forms\Components\TextInput::make('live_url')
                            ->label('URL siaran langsung')
                            ->url()
                            ->maxLength(2048)
                            ->nullable(),
                        Forms\Components\Textarea::make('update_text')
                            ->label('Keterangan update')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('documentation_media')
                            ->label('Dokumentasi tahap ini')
                            ->disk('public')
                            ->directory('news/documentation')
                            ->image()
                            ->multiple()
                            ->appendFiles()
                            ->downloadable()
                            ->openable()
                            ->reorderable()
                            ->panelLayout('grid')
                            ->maxFiles(20)
                            ->maxSize(4096)
                            ->nullable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_update', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tanggal_update')
                    ->label('Waktu update')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('phase_label')
                    ->label('Tahap')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'persiapan' => 'warning',
                        'acara' => 'info',
                        'selesai' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('progress_percent')
                    ->label('Progres')
                    ->formatStateUsing(fn ($state): string => $state === null ? '-' : ((int) $state.'%')),
                Tables\Columns\TextColumn::make('update_text')
                    ->label('Keterangan')
                    ->limit(60)
                    ->wrap(),
                Tables\Columns\TextColumn::make('documentation_media_count')
                    ->label('Dokumentasi')
                    ->state(fn (BeritaUpdate $record): int => count((array) ($record->documentation_media ?? []))),
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
