<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\SarprasMonthlyAgendaResource\Pages;
use App\Models\SarprasMonthlyAgenda;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class SarprasMonthlyAgendaResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = SarprasMonthlyAgenda::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Sekolah';

    protected static ?string $navigationLabel = 'Agenda Bulanan Sarpras';

    protected static ?string $modelLabel = 'agenda bulanan sarpras';

    protected static ?string $pluralModelLabel = 'Agenda Bulanan Sarpras';

    protected static ?int $navigationSort = 40;

    protected static ?string $permissionPrefix = 'sarpras_monthly_agenda';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        return SchemaFacade::hasTable('sarpras_monthly_agendas') && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Agenda Bulanan')
                    ->description('Simpan agenda sarpras bulanan berikut tindak lanjut dan penanggung jawabnya.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\DatePicker::make('bulan_agenda')
                            ->label('Bulan Agenda')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->helperText('Pilih tanggal pada bulan yang sama. Sistem akan menampilkan bulan dan tahun agenda.')
                            ->default(now()->startOfMonth()),
                        Forms\Components\TextInput::make('urutan')
                            ->label('No')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->minValue(1),
                        Forms\Components\TextInput::make('jenis_kegiatan')
                            ->label('Jenis Kegiatan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('tindak_lanjut_status')
                            ->label('Tindak Lanjut')
                            ->options(SarprasMonthlyAgenda::statusOptions())
                            ->default(SarprasMonthlyAgenda::STATUS_BELUM)
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('penanggung_jawab')
                            ->label('PJ')
                            ->maxLength(150),
                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari jenis kegiatan atau penanggung jawab...',
            emptyStateHeading: 'Belum ada agenda sarpras',
            emptyStateDescription: 'Tambahkan agenda bulanan sarpras untuk memantau pekerjaan yang sudah dan belum ditindaklanjuti.'
        )
            ->defaultSort('bulan_agenda', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->orderByDesc('bulan_agenda')
                ->orderBy('urutan')
                ->orderByDesc('id'))
            ->columns([
                Tables\Columns\TextColumn::make('urutan')
                    ->label('No')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('jenis_kegiatan')
                    ->label('Jenis Kegiatan')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SarprasMonthlyAgenda $record): string => collect([
                        $record->bulan_agenda?->translatedFormat('F Y'),
                        filled($record->penanggung_jawab) ? 'PJ: '.$record->penanggung_jawab : null,
                    ])->filter()->implode(' | '))
                    ->wrap(),
                Tables\Columns\TextColumn::make('tindak_lanjut_status')
                    ->label('Tindak Lanjut')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => SarprasMonthlyAgenda::statusLabel($state))
                    ->color(fn (?string $state): string => $state === SarprasMonthlyAgenda::STATUS_SUDAH ? 'success' : 'warning')
                    ->sortable(),
                Tables\Columns\TextColumn::make('bulan_agenda')
                    ->label('Bulan')
                    ->date('F Y')
                    ->placeholder('-')
                    ->visibleFrom('md')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tindak_lanjut_status')
                    ->label('Tindak Lanjut')
                    ->options(SarprasMonthlyAgenda::statusOptions()),
            ])
            ->actions([
                EditAction::make(),
                static::makeDeleteTableAction('agenda sarpras'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSarprasMonthlyAgendas::route('/'),
            'create' => Pages\CreateSarprasMonthlyAgenda::route('/create'),
            'edit' => Pages\EditSarprasMonthlyAgenda::route('/{record}/edit'),
        ];
    }
}
