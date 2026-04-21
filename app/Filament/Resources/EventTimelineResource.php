<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\EventTimelineResource\Pages;
use App\Models\Berita;
use App\Models\EventTimeline;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EventTimelineResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;

    protected static ?string $model = EventTimeline::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Agenda';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Timeline Kegiatan';

    protected static ?string $modelLabel = 'timeline kegiatan';

    protected static ?string $pluralModelLabel = 'Timeline Kegiatan';

    protected static ?string $permissionPrefix = 'event_timelines';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('news_id')
                    ->label('Berita')
                    ->required()
                    ->searchable()
                    ->options(fn (): array => Berita::searchOptionLabels(limit: 25))
                    ->getSearchResultsUsing(fn (string $search): array => Berita::searchOptionLabels($search))
                    ->getOptionLabelUsing(fn ($value): ?string => Berita::resolveOptionLabel($value)),
                Forms\Components\TextInput::make('judul')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('deskripsi')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('waktu')
                    ->maxLength(100)
                    ->default(null),
                Forms\Components\Select::make('status')
                    ->required()
                    ->options([
                        'pending' => 'Pending',
                        'active' => 'Active',
                        'completed' => 'Completed',
                    ])
                    ->default('pending'),
                Forms\Components\FileUpload::make('dokumentasi')
                    ->label('Dokumentasi')
                    ->disk('public')
                    ->directory('events/timeline')
                    ->multiple()
                    ->preserveFilenames()
                    ->maxSize(4096)
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                    ])
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('urutan')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('news_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('judul')
                    ->searchable(),
                Tables\Columns\TextColumn::make('waktu')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('urutan')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                static::makeDeleteTableAction('timeline event'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventTimelines::route('/'),
            'create' => Pages\CreateEventTimeline::route('/create'),
            'edit' => Pages\EditEventTimeline::route('/{record}/edit'),
        ];
    }
}
