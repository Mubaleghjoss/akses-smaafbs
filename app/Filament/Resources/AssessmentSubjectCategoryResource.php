<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasAssessmentPermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\AssessmentSubjectCategoryResource\Pages;
use App\Models\Assessment\SubjectCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AssessmentSubjectCategoryResource extends Resource
{
    use HasAssessmentPermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = SubjectCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Kategori Mapel';

    protected static ?string $modelLabel = 'kategori mapel';

    protected static ?string $pluralModelLabel = 'Kategori Mata Pelajaran';

    protected static ?string $slug = 'penilaian/pengaturan/kategori-mapel';

    protected static string $assessmentManagePermission = 'penilaian.period.manage';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Kategori Rapor')
                ->description('Nama dan urutan kategori ini akan dibekukan ke snapshot rapor periode berikutnya.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Kode Kategori')
                        ->required()
                        ->maxLength(40)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama di Rapor')
                        ->required()
                        ->maxLength(120),
                    Forms\Components\Select::make('type')
                        ->label('Jenis')
                        ->options(SubjectCategory::TYPES)
                        ->native(false)
                        ->required(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Urutan di Rapor')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(999)
                        ->default(0)
                        ->required(),
                    Forms\Components\Textarea::make('description')
                        ->label('Keterangan')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Kategori Aktif')
                        ->default(true)
                        ->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari kode atau nama kategori...',
            emptyStateHeading: 'Belum ada kategori mapel',
            emptyStateDescription: 'Tambahkan kategori yang akan menjadi kelompok mata pelajaran pada rapor.'
        )
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Kategori')
                    ->description(fn (SubjectCategory $record): string => $record->code)
                    ->searchable(['name', 'code'])
                    ->wrap(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->formatStateUsing(fn (string $state): string => SubjectCategory::TYPES[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => $state === 'wajib' ? 'warning' : 'info'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan')
                    ->numeric(),
                Tables\Columns\TextColumn::make('teaching_assignments_count')
                    ->label('Dipakai')
                    ->counts('teachingAssignments')
                    ->suffix(' plotting')
                    ->visibleFrom('md'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (SubjectCategory $record): bool => ! $record->teachingAssignments()->exists()),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentSubjectCategories::route('/'),
            'create' => Pages\CreateAssessmentSubjectCategory::route('/create'),
            'edit' => Pages\EditAssessmentSubjectCategory::route('/{record}/edit'),
        ];
    }
}
