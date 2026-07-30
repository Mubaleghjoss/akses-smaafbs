<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasAssessmentPermissions;
use App\Filament\Concerns\HasOptimizedAdminTable;
use App\Filament\Resources\AssessmentAuditLogResource\Pages;
use App\Models\Assessment\AuditLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class AssessmentAuditLogResource extends Resource
{
    use HasAssessmentPermissions {
        canAccess as protected assessmentModuleCanAccess;
    }
    use HasOptimizedAdminTable;

    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?string $navigationLabel = 'Log Perubahan';

    protected static ?string $modelLabel = 'log perubahan';

    protected static ?string $pluralModelLabel = 'Log Perubahan';

    protected static ?int $navigationSort = 13;

    protected static ?string $slug = 'penilaian/pengaturan/log-perubahan';

    protected static string $assessmentManagePermission = 'penilaian.audit.view';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return static::assessmentModuleCanAccess()
            && $user instanceof User
            && ($user->hasFullAdminAccess() || $user->can('penilaian.audit.view'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return static::optimizeAdminTable(
            $table,
            searchPlaceholder: 'Cari event, subjek, atau alasan...',
            emptyStateHeading: 'Belum ada log perubahan',
            emptyStateDescription: 'Aktivitas sensitif Penilaian akan dicatat secara otomatis.'
        )
            ->modifyQueryUsing(fn ($query) => $query->with(['actor', 'period']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('event')
                    ->label('Peristiwa')
                    ->badge()
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('actor.name')
                    ->label('Pelaku')
                    ->placeholder('Sistem')
                    ->searchable()
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('period.name')
                    ->label('Periode')
                    ->placeholder('-')
                    ->visibleFrom('lg')
                    ->wrap(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subjek')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->description(fn (AuditLog $record): string => '#'.$record->subject_id)
                    ->visibleFrom('md'),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Alasan')
                    ->placeholder('-')
                    ->limit(80)
                    ->wrap(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('assessment_period_id')
                    ->label('Periode')
                    ->relationship('period', 'name'),
                Tables\Filters\SelectFilter::make('event')
                    ->label('Peristiwa')
                    ->options(fn (): array => AuditLog::query()
                        ->distinct()
                        ->orderBy('event')
                        ->pluck('event', 'event')
                        ->all()),
            ])
            ->actions([
                Action::make('details')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->authorize(fn (AuditLog $record): bool => static::canAccess()
                        && Gate::allows('view', $record))
                    ->modalHeading(fn (AuditLog $record): string => 'Detail Log - '.$record->event)
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(function (AuditLog $record) {
                        Gate::authorize('view', $record);

                        return view(
                            'filament.resources.assessment-audit-log-resource.audit-detail',
                            ['record' => $record->loadMissing(['actor', 'period'])],
                        );
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssessmentAuditLogs::route('/'),
        ];
    }
}
