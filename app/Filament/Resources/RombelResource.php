<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\RombelResource\Pages;
use App\Models\DataSiswa;
use App\Models\Rombel;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RombelResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = Rombel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Siswa';

    protected static ?string $navigationLabel = 'Rombel';

    protected static ?string $modelLabel = 'rombel';

    protected static ?string $pluralModelLabel = 'Rombel';

    protected static ?int $navigationSort = 11;

    protected static ?string $permissionPrefix = 'rombel';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Rombel')
                    ->description('Master rombel dipakai sebagai pilihan pada Data Siswa dan filter siswa.')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\TextInput::make('nama')
                            ->label('Nama Rombel')
                            ->required()
                            ->dehydrateStateUsing(fn ($state): string => Rombel::normalizeName($state))
                            ->maxLength(50)
                            ->unique(table: 'rombels', column: 'nama', ignoreRecord: true)
                            ->helperText('Jika nama rombel diubah, siswa yang memakai nama lama akan ikut dipindahkan ke nama baru.'),
                        Forms\Components\TextInput::make('angkatan')
                            ->label('Angkatan')
                            ->maxLength(20)
                            ->helperText('Opsional. Jika kosong, sistem mencoba membaca tahun ajaran dari nama rombel.'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->inline(false),
                        Forms\Components\Textarea::make('catatan')
                            ->label('Catatan')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari nama rombel atau angkatan...',
            emptyStateHeading: 'Belum ada rombel',
            emptyStateDescription: 'Tambahkan master rombel agar pilihan kelas pada data siswa lebih rapi.'
        )
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'students',
                'activeStudents as active_students_count',
            ]))
            ->defaultSort('nama')
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Rombel')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('angkatan')
                    ->label('Angkatan')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('students_count')
                    ->label('Total Siswa')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('active_students_count')
                    ->label('Siswa Aktif')
                    ->numeric()
                    ->sortable()
                    ->visibleFrom('md'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('catatan')
                    ->label('Catatan')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Aktif',
                        '0' => 'Nonaktif',
                    ]),
                Tables\Filters\SelectFilter::make('angkatan')
                    ->label('Angkatan')
                    ->options(fn (): array => Rombel::query()
                        ->whereNotNull('angkatan')
                        ->where('angkatan', '!=', '')
                        ->orderBy('angkatan')
                        ->pluck('angkatan', 'angkatan')
                        ->all()),
            ])
            ->actions([
                Action::make('lihat_siswa')
                    ->label('Lihat Siswa')
                    ->icon('heroicon-o-users')
                    ->color('gray')
                    ->url(fn (Rombel $record): string => DataSiswaResource::getUrl('index', [
                        'filters' => [
                            'rombel_saat_ini' => [
                                'value' => $record->nama,
                            ],
                        ],
                    ])),
                EditAction::make(),
                static::makeDeleteTableAction('rombel')
                    ->visible(fn (Rombel $record): bool => ! $record->students()->exists()),
                static::deleteWithStudentsAction(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::deleteEmptyRombelsBulkAction(),
                ]),
            ]);
    }

    protected static function deleteWithStudentsAction(): Action
    {
        return Action::make('delete_with_students')
            ->label('Hapus Rombel + Siswa')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (Rombel $record): string => 'Hapus rombel '.$record->nama.' beserta semua siswa?')
            ->modalDescription(fn (Rombel $record): string => 'Aksi ini akan menghapus '.$record->students()->count().' data siswa di rombel ini. Sistem akan memblokir aksi jika ada siswa yang masih punya berkas, prestasi, rapot, konseling, keuangan, arsip, atau data boarding lain.')
            ->modalSubmitActionLabel('Validasi dan hapus')
            ->visible(fn (Rombel $record): bool => $record->students()->exists())
            ->form(fn (Rombel $record): array => [
                Forms\Components\TextInput::make('confirmation')
                    ->label('Ketik nama rombel untuk validasi')
                    ->helperText('Ketik persis: '.$record->nama)
                    ->required(),
            ])
            ->action(function (Rombel $record, array $data): void {
                if (Rombel::normalizeName($data['confirmation'] ?? null) !== $record->nama) {
                    Notification::make()
                        ->title('Validasi nama rombel tidak sesuai.')
                        ->body('Ketik nama rombel persis seperti yang diminta sebelum menghapus siswa.')
                        ->danger()
                        ->send();

                    return;
                }

                $students = static::studentsForDeletionValidation($record);
                $blockedStudents = $students->reject(fn (DataSiswa $student): bool => DataSiswaResource::canDelete($student));

                if ($blockedStudents->isNotEmpty()) {
                    Notification::make()
                        ->title('Rombel belum bisa dihapus.')
                        ->body(static::blockedStudentsMessage($blockedStudents))
                        ->warning()
                        ->duration(15000)
                        ->send();

                    return;
                }

                DB::transaction(function () use ($record, $students): void {
                    $students->each(fn (DataSiswa $student): ?bool => $student->delete());
                    $record->delete();
                });

                Notification::make()
                    ->title('Rombel dan siswa berhasil dihapus.')
                    ->body($students->count().' data siswa ikut dihapus.')
                    ->success()
                    ->send();
            });
    }

    protected static function deleteEmptyRombelsBulkAction(): BulkAction
    {
        return BulkAction::make('delete_empty_rombels')
            ->label('Hapus Rombel Kosong')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Hapus rombel kosong terpilih?')
            ->modalDescription('Hanya rombel yang tidak memiliki siswa yang akan dihapus. Rombel berisi siswa akan dilewati.')
            ->modalSubmitActionLabel('Hapus rombel kosong')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $deleted = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    if (! $record instanceof Rombel || $record->students()->exists()) {
                        $skipped++;

                        continue;
                    }

                    $record->delete();
                    $deleted++;
                }

                Notification::make()
                    ->title('Hapus rombel kosong selesai.')
                    ->body("Rombel dihapus: {$deleted}. Dilewati karena masih berisi siswa: {$skipped}.")
                    ->{$skipped > 0 ? 'warning' : 'success'}()
                    ->send();
            });
    }

    /**
     * @return Collection<int, DataSiswa>
     */
    protected static function studentsForDeletionValidation(Rombel $record): Collection
    {
        return $record->students()
            ->withExists([
                'boardingRapots',
                'boardingPencapaian',
                'boardingArsipMt',
                'boardingKonselingMts',
                'boardingKeuanganSiswa',
                'prestasis',
                'berkasSiswas',
            ])
            ->orderBy('nama')
            ->get();
    }

    /**
     * @param  Collection<int, DataSiswa>  $students
     */
    protected static function blockedStudentsMessage(Collection $students): string
    {
        $names = $students
            ->take(5)
            ->map(fn (DataSiswa $student): string => $student->nama)
            ->implode(', ');

        $remaining = max(0, $students->count() - 5);
        $suffix = $remaining > 0 ? " dan {$remaining} siswa lain" : '';

        return "Ada {$students->count()} siswa yang masih punya data terkait: {$names}{$suffix}. Hapus atau pindahkan data terkait lebih dulu.";
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRombels::route('/'),
            'create' => Pages\CreateRombel::route('/create'),
            'edit' => Pages\EditRombel::route('/{record}/edit'),
        ];
    }
}
