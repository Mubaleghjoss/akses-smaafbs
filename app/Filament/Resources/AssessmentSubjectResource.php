<?php

namespace App\Filament\Resources;

use App\Enums\Assessment\AssessmentPeriodStatus;
use App\Filament\Concerns\HasAssessmentPermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\AssessmentSubjectResource\Pages;
use App\Models\Assessment\Subject;
use App\Models\User;
use App\Support\Assessment\AssessmentAuditLogger;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AssessmentSubjectResource extends Resource
{
    use HasAssessmentPermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = Subject::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Mapel Penilaian';

    protected static ?string $modelLabel = 'mata pelajaran';

    protected static ?string $pluralModelLabel = 'Mata Pelajaran Penilaian';

    protected static ?string $slug = 'penilaian/pengaturan/mapel';

    protected static string $assessmentManagePermission = 'penilaian.period.manage';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Identitas Mata Pelajaran')
                ->description('Kode dipakai pada workbook dan penugasan guru. Kelompok serta urutan menentukan susunan rapor.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Kode Mapel')
                        ->required()
                        ->maxLength(40)
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Mapel')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),
            Section::make('Kelompok dan Urutan Rapor')
                ->description('Contoh: A · Kelompok A (Umum). Jangan biarkan kelompok BELUM pada rapor resmi.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('report_group_code')
                        ->label('Kode Kelompok')
                        ->required()
                        ->maxLength(40)
                        ->default('BELUM'),
                    Forms\Components\TextInput::make('report_group_name')
                        ->label('Nama Kelompok')
                        ->required()
                        ->maxLength(120)
                        ->default('Belum Dikelompokkan'),
                    Forms\Components\TextInput::make('report_group_sort_order')
                        ->label('Urutan Kelompok')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(999)
                        ->default(999)
                        ->required(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Urutan Mapel')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->maxValue(999)
                        ->default(0)
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Mapel Aktif')
                        ->default(true)
                        ->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari kode, mapel, atau kelompok...',
            emptyStateHeading: 'Belum ada mata pelajaran',
            emptyStateDescription: 'Tambah melalui halaman ini atau gunakan Impor Master Excel.'
        )
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Mata Pelajaran')
                    ->description(fn (Subject $record): string => $record->code)
                    ->searchable(['name', 'code'])
                    ->wrap(),
                Tables\Columns\TextColumn::make('report_group_name')
                    ->label('Kelompok Rapor')
                    ->description(fn (Subject $record): string => $record->report_group_code)
                    ->badge()
                    ->color(fn (Subject $record): string => $record->report_group_code === 'BELUM' ? 'warning' : 'info')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('report_group_sort_order')
                    ->label('Urutan Kelompok')
                    ->numeric()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Urutan Mapel')
                    ->numeric()
                    ->visibleFrom('md'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Subject $record): bool => ! $record->teachingAssignments()->exists()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('syncUnlockedPeriodMetadata')
                        ->label('Terapkan Kelompok ke Periode Berjalan')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn (): bool => static::canManageAssessment())
                        ->requiresConfirmation()
                        ->modalHeading('Terapkan metadata rapor ke periode yang belum dikunci?')
                        ->modalDescription('Kelompok dan urutan mapel terpilih disalin hanya ke assignment periode yang belum Dikunci/Diterbitkan. Nilai siswa tidak diubah dan snapshot/PDF lama tidak disentuh.')
                        ->modalSubmitActionLabel('Ya, Terapkan')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records): void {
                            abort_unless(static::canManageAssessment(), 403);
                            $actor = auth()->user();
                            abort_unless($actor instanceof User, 403);
                            $updated = 0;

                            DB::transaction(function () use ($records, $actor, &$updated): void {
                                foreach ($records as $subject) {
                                    abort_unless($subject instanceof Subject, 422);
                                    $assignments = $subject->periodAssignments()
                                        ->whereHas('period', fn ($query) => $query->whereNotIn('status', [
                                            AssessmentPeriodStatus::LOCKED->value,
                                            AssessmentPeriodStatus::PUBLISHED->value,
                                        ]))
                                        ->lockForUpdate()
                                        ->get();

                                    foreach ($assignments as $assignment) {
                                        $old = [
                                            'group_code' => $assignment->subject_group_code_snapshot,
                                            'group_name' => $assignment->subject_group_name_snapshot,
                                            'group_sort_order' => $assignment->subject_group_sort_order_snapshot,
                                            'subject_sort_order' => $assignment->subject_sort_order_snapshot,
                                        ];
                                        $new = [
                                            'group_code' => $subject->report_group_code ?: 'BELUM',
                                            'group_name' => $subject->report_group_name ?: 'Belum Dikelompokkan',
                                            'group_sort_order' => (int) ($subject->report_group_sort_order ?? 999),
                                            'subject_sort_order' => (int) ($subject->sort_order ?? 0),
                                        ];

                                        if ($old === $new) {
                                            continue;
                                        }

                                        $assignment->forceFill([
                                            'subject_group_code_snapshot' => $new['group_code'],
                                            'subject_group_name_snapshot' => $new['group_name'],
                                            'subject_group_sort_order_snapshot' => $new['group_sort_order'],
                                            'subject_sort_order_snapshot' => $new['subject_sort_order'],
                                        ])->save();
                                        app(AssessmentAuditLogger::class)->record(
                                            actor: $actor,
                                            event: 'assignment.report_metadata_synchronized',
                                            subject: $assignment,
                                            oldValues: $old,
                                            newValues: $new,
                                            reason: 'Sinkronisasi eksplisit dari Mapel Penilaian sebelum periode dikunci.',
                                        );
                                        $updated++;
                                    }
                                }
                            }, 3);

                            Notification::make()
                                ->title('Metadata rapor diterapkan')
                                ->body($updated.' assignment periode berjalan diperbarui. Periode terkunci/diterbitkan dan PDF lama tidak berubah.')
                                ->{$updated > 0 ? 'success' : 'warning'}()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentSubjects::route('/'),
            'create' => Pages\CreateAssessmentSubject::route('/create'),
            'edit' => Pages\EditAssessmentSubject::route('/{record}/edit'),
        ];
    }
}
