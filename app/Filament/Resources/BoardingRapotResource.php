<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\BoardingRapotResource\Pages;
use App\Models\BoardingRapot;
use App\Models\DataSiswa;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class BoardingRapotResource extends Resource
{
    use HasModulePermissions;

    protected static ?string $model = BoardingRapot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Boarding';

    protected static ?string $navigationLabel = 'Rapot';

    protected static ?string $modelLabel = 'rapot boarding';

    protected static ?string $pluralModelLabel = 'Rapot Boarding';

    protected static ?int $navigationSort = 10;

    protected static ?string $permissionPrefix = 'boarding_rapot';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Data Rapot Boarding')
                    ->description('Rapot boarding dapat disinkron otomatis dari data siswa, pencapaian target, konseling, dan keuangan kas.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Select::make('siswa_id')
                            ->label('Murid')
                            ->relationship(
                                name: 'siswa',
                                titleAttribute: 'nama',
                                modifyQueryUsing: fn (Builder $query) => DataSiswa::applyVisibleScope($query, auth()->user())->orderBy('nama')
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (DataSiswa $record): string => trim($record->nama.' - '.($record->rombel_saat_ini ?: 'Tanpa rombel'))
                            )
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('pamong_user_id')
                            ->label('Pamong Penanggung Jawab')
                            ->relationship(
                                name: 'pamongUser',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query) => User::boardingPamongQuery()->orderBy('name')
                            )
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => User::searchBoardingPamongOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => User::resolveNameOptionLabel($value))
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('wali_pamong_nama', User::query()->whereKey($state)->value('name'));
                            })
                            ->default(fn (): ?int => auth()->user()?->isBoardingPamong() ? auth()->id() : null)
                            ->disabled(fn (): bool => (bool) auth()->user()?->isBoardingPamong())
                            ->dehydrated()
                            ->required(),
                        Forms\Components\TextInput::make('periode_tahun')
                            ->label('Periode Tahun')
                            ->required()
                            ->maxLength(20)
                            ->default(fn (): string => now()->format('Y').'/'.now()->addYear()->format('Y')),
                        Forms\Components\Select::make('semester')
                            ->required()
                            ->options([
                                'ganjil' => 'Ganjil',
                                'genap' => 'Genap',
                            ])
                            ->rules([
                                fn (Get $get, ?BoardingRapot $record) => Rule::unique('boarding_rapots')
                                    ->where(fn (Builder $query) => $query
                                        ->where('siswa_id', $get('siswa_id'))
                                        ->where('periode_tahun', $get('periode_tahun')))
                                    ->ignore($record),
                            ]),
                        Forms\Components\DatePicker::make('tanggal_rapot')
                            ->label('Tanggal Rapot')
                            ->default(now()),
                        Forms\Components\Select::make('status_rapot')
                            ->label('Status Rapot')
                            ->required()
                            ->default('draft')
                            ->options(BoardingRapot::statusOptions()),
                        Forms\Components\TextInput::make('nomor_dokumen')
                            ->label('Nomor Dokumen')
                            ->maxLength(50)
                            ->placeholder('Contoh: RB/BOARDING/2026/001'),
                        Forms\Components\Select::make('predikat_boarding')
                            ->label('Predikat Boarding')
                            ->options(BoardingRapot::predikatOptions()),
                        Forms\Components\Placeholder::make('generated_at_view')
                            ->label('Sinkron Terakhir')
                            ->content(fn (?BoardingRapot $record): string => $record?->generated_at?->translatedFormat('d M Y H:i') ?? 'Belum pernah disinkronkan'),
                    ]),
                Section::make('Tanda Tangan dan Legalisasi')
                    ->description('Gunakan data ini untuk template rapot final siap cetak sekolah.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('wali_pamong_nama')
                            ->label('Wali Pamong')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('kepala_boarding_nama')
                            ->label('Kepala Boarding')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('mudir_asrama_nama')
                            ->label('Mudir Asrama')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('tempat_cetak')
                            ->label('Tempat Cetak')
                            ->maxLength(100),
                    ]),
                Section::make('Ringkasan Naratif Rapot')
                    ->description('Konten ini akan ikut muncul pada preview dan hasil cetak rapot.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\Textarea::make('ringkasan_pencapaian')
                            ->label('Ringkasan Pencapaian')
                            ->rows(5)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('catatan_pamong')
                            ->label('Catatan Pamong')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('rekomendasi_tindak_lanjut')
                            ->label('Rekomendasi Tindak Lanjut')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('siswa.nama')
                    ->label('Murid')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('siswa.rombel_saat_ini')
                    ->label('Rombel')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pamongUser.name')
                    ->label('Pamong')
                    ->searchable(),
                Tables\Columns\TextColumn::make('periode_tahun')
                    ->label('Periode')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('semester')
                    ->label('Semester')
                    ->badge(),
                Tables\Columns\TextColumn::make('nomor_dokumen')
                    ->label('Nomor')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('predikat_boarding')
                    ->label('Predikat')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BoardingRapot::predikatOptions()[$state] ?? ($state ?: '-')),
                Tables\Columns\TextColumn::make('status_rapot')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => BoardingRapot::statusOptions()[$state] ?? ($state ?: '-')),
                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Sinkron')
                    ->since(),
                Tables\Columns\TextColumn::make('tanggal_rapot')
                    ->label('Tanggal')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diupdate')
                    ->since()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('periode_tahun')
                    ->label('Periode')
                    ->options(BoardingRapot::periodeTahunOptions()),
                Tables\Filters\SelectFilter::make('semester')
                    ->options([
                        'ganjil' => 'Ganjil',
                        'genap' => 'Genap',
                    ]),
                Tables\Filters\SelectFilter::make('status_rapot')
                    ->label('Status')
                    ->options(BoardingRapot::statusOptions()),
                Tables\Filters\SelectFilter::make('pamong_user_id')
                    ->label('Pamong')
                    ->relationship(
                        name: 'pamongUser',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query) => User::boardingPamongQuery()->orderBy('name')
                    )
                    ->visible(fn (): bool => ! auth()->user()?->isBoardingPamong()),
            ])
            ->actions([
                Action::make('sinkronkan')
                    ->label('Sinkronkan')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function (BoardingRapot $record): void {
                        $record->syncFromSources(overwriteNarratives: true);
                    }),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (BoardingRapot $record): string => route('admin.boarding-rapots.preview', $record))
                    ->openUrlInNewTab(),
                Action::make('cetak')
                    ->label('Cetak / PDF')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->url(fn (BoardingRapot $record): string => route('admin.boarding-rapots.print', $record))
                    ->openUrlInNewTab(),
                Action::make('export_excel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('warning')
                    ->url(fn (BoardingRapot $record): string => route('admin.boarding-rapots.export', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->select([
                'id',
                'siswa_id',
                'pamong_user_id',
                'periode_tahun',
                'semester',
                'nomor_dokumen',
                'predikat_boarding',
                'status_rapot',
                'generated_at',
                'tanggal_rapot',
                'updated_at',
            ])
            ->with([
                'siswa:id,nama,rombel_saat_ini',
                'pamongUser:id,name',
            ])
            ->visibleToUser(auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBoardingRapots::route('/'),
        ];
    }
}
