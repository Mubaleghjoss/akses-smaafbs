<?php

namespace App\Filament\Resources;

use App\Enums\Assessment\AssessmentType;
use App\Filament\Concerns\HasAssessmentPermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\AssessmentReportTemplateResource\Pages;
use App\Models\Assessment\ReportTemplate;
use App\Support\Assessment\AssessmentAuditLogger;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssessmentReportTemplateResource extends Resource
{
    use HasAssessmentPermissions;
    use HasOptimizedAdminTable;

    protected static ?string $model = ReportTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?string $navigationLabel = 'Template Rapor';

    protected static ?string $modelLabel = 'template rapor';

    protected static ?string $pluralModelLabel = 'Template Rapor';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'penilaian/pengaturan/template-rapor';

    protected static string $assessmentManagePermission = 'penilaian.period.manage';

    public static function canEdit(Model $record): bool
    {
        return static::canAccess()
            && parent::canEdit($record)
            && $record instanceof ReportTemplate
            && ! $record->snapshots()->exists()
            && ! $record->classArtifacts()->exists();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function validateTemplateData(array $data): array
    {
        $type = AssessmentType::tryFrom((string) ($data['type'] ?? ''));
        $expectedView = match ($type) {
            AssessmentType::ASTS => 'assessment.reports.asts',
            AssessmentType::ASAS => 'assessment.reports.asas',
            default => null,
        };

        if (! $type || ($data['view_path'] ?? null) !== $expectedView) {
            throw ValidationException::withMessages([
                'data.view_path' => 'Layout rapor harus sesuai dengan jenis ASTS atau ASAS yang dipilih.',
            ]);
        }

        if ((int) ($data['version'] ?? 0) < 1) {
            throw ValidationException::withMessages([
                'data.version' => 'Versi template minimal 1.',
            ]);
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Versi Template Standar')
                ->description('Template hanya memakai layout standar aplikasi. HTML atau Blade bebas tidak dapat dimasukkan dari admin.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Kode Template')
                        ->required()
                        ->maxLength(50),
                    Forms\Components\Select::make('type')
                        ->label('Jenis Rapor')
                        ->options(AssessmentType::options())
                        ->required()
                        ->native(false),
                    Forms\Components\TextInput::make('name')
                        ->label('Nama Template')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\TextInput::make('version')
                        ->label('Versi')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                    Forms\Components\Select::make('view_path')
                        ->label('Layout')
                        ->options([
                            'assessment.reports.asts' => 'Standar ASTS A4',
                            'assessment.reports.asas' => 'Standar ASAS A4',
                        ])
                        ->required()
                        ->native(false),
                    Forms\Components\DatePicker::make('effective_from')
                        ->label('Berlaku Mulai'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true)
                        ->inline(false),
                ]),
            Section::make('Kop dan Judul')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('settings.school_name')
                        ->label('Nama Sekolah')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\TextInput::make('settings.report_title')
                        ->label('Judul Dokumen')
                        ->required()
                        ->maxLength(150),
                    Forms\Components\Textarea::make('settings.school_address')
                        ->label('Alamat Sekolah')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('settings.score_label')
                        ->label('Istilah Nilai')
                        ->default('Nilai Akhir')
                        ->maxLength(50),
                    Forms\Components\TextInput::make('settings.predicate_label')
                        ->label('Istilah Predikat')
                        ->default('Predikat')
                        ->maxLength(50),
                    Forms\Components\Toggle::make('settings.show_predicate')
                        ->label('Tampilkan Kolom Predikat')
                        ->default(true)
                        ->inline(false),
                    Forms\Components\Toggle::make('settings.show_description')
                        ->label('Tampilkan Kolom Capaian')
                        ->default(true)
                        ->inline(false),
                ]),
            Section::make('Tanda Tangan')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Forms\Components\TextInput::make('settings.principal_name')
                        ->label('Nama Kepala Sekolah')
                        ->maxLength(150),
                    Forms\Components\TextInput::make('settings.principal_identifier')
                        ->label('NIP/NIY Kepala Sekolah')
                        ->maxLength(80),
                    Forms\Components\TextInput::make('settings.homeroom_title')
                        ->label('Sebutan Wali Kelas')
                        ->default('Wali Kelas')
                        ->maxLength(80),
                    Forms\Components\TextInput::make('settings.place')
                        ->label('Tempat Terbit')
                        ->maxLength(100),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari template atau kode...',
            emptyStateHeading: 'Belum ada template rapor',
            emptyStateDescription: 'Jalankan assessment:install-defaults untuk memasang template ASTS dan ASAS standar.'
        )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Template')
                    ->description(fn (ReportTemplate $record): string => "{$record->code} · v{$record->version}")
                    ->searchable(['name', 'code'])
                    ->wrap(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => $state instanceof AssessmentType ? $state->label() : strtoupper((string) $state)),
                Tables\Columns\TextColumn::make('version')
                    ->label('Versi')
                    ->numeric(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('effective_from')
                    ->label('Berlaku')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->visibleFrom('md'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(AssessmentType::options()),
            ])
            ->actions([
                EditAction::make()->visible(fn (ReportTemplate $record): bool => static::canEdit($record)),
                Action::make('new_version')
                    ->label('Buat Versi Baru')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->authorize(fn (ReportTemplate $record): bool => static::canManageAssessment()
                        && Gate::allows('view', $record)
                        && Gate::allows('create', ReportTemplate::class))
                    ->visible(fn (): bool => static::canManageAssessment())
                    ->requiresConfirmation()
                    ->action(function (ReportTemplate $record): void {
                        abort_unless(static::canAccess() && static::canManageAssessment(), 403);
                        Gate::authorize('create', ReportTemplate::class);

                        $copy = DB::transaction(function () use ($record): ReportTemplate {
                            $versions = ReportTemplate::query()
                                ->where('code', $record->code)
                                ->lockForUpdate()
                                ->get();
                            $source = $versions->firstWhere('id', $record->getKey());
                            abort_unless($source instanceof ReportTemplate, 404);
                            Gate::authorize('view', $source);

                            $copy = $source->replicate();
                            $copy->version = ((int) $versions->max('version')) + 1;
                            $copy->is_active = false;
                            $copy->save();

                            app(AssessmentAuditLogger::class)->record(
                                actor: auth()->user(),
                                event: 'report_template.version_created',
                                subject: $copy,
                                oldValues: [
                                    'source_template_id' => $source->getKey(),
                                    'source_version' => $source->version,
                                ],
                                newValues: [
                                    'code' => $copy->code,
                                    'version' => $copy->version,
                                    'is_active' => false,
                                ],
                            );

                            return $copy;
                        }, 3);

                        Notification::make()
                            ->title("Versi {$copy->version} dibuat")
                            ->body('Versi baru belum aktif dan dapat disunting tanpa mengubah snapshot lama.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (ReportTemplate $record): bool => static::canDelete($record))
                    ->databaseTransaction()
                    ->before(function (ReportTemplate $record): void {
                        $template = ReportTemplate::query()
                            ->whereKey($record->getKey())
                            ->lockForUpdate()
                            ->firstOrFail();

                        abort_unless(static::canDelete($template), 403);
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentReportTemplates::route('/'),
            'create' => Pages\CreateAssessmentReportTemplate::route('/create'),
            'edit' => Pages\EditAssessmentReportTemplate::route('/{record}/edit'),
        ];
    }
}
