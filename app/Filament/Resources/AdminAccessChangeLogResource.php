<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\AdminAccessChangeLogResource\Pages;
use App\Models\AdminAccessChangeLog;
use App\Support\Admin\AdminAccessChangeLogSupport;
use App\Support\Admin\AdminRoleTemplateSupport;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminAccessChangeLogResource extends Resource
{
    use HasModulePermissions;

    protected static ?string $model = AdminAccessChangeLog::class;

    protected static ?string $permissionPrefix = 'riwayat_akses_divisi';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Riwayat Akses Divisi';

    protected static ?string $modelLabel = 'riwayat akses divisi';

    protected static ?string $pluralModelLabel = 'Riwayat Akses Divisi';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'actorUser:id,name,username',
                'targetUser:id,name,username',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->label('Aksi')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'remove' ? 'danger' : 'success')
                    ->formatStateUsing(fn (string $state): string => $state === 'remove' ? 'Cabut Divisi' : 'Tambah Divisi'),
                Tables\Columns\TextColumn::make('actorUser.name')
                    ->label('Dilakukan Oleh')
                    ->description(fn (AdminAccessChangeLog $record): ?string => $record->actorUser?->username),
                Tables\Columns\TextColumn::make('targetUser.name')
                    ->label('Akun Tujuan')
                    ->description(fn (AdminAccessChangeLog $record): ?string => $record->targetUser?->username),
                Tables\Columns\TextColumn::make('template_keys')
                    ->label('Divisi')
                    ->state(fn (AdminAccessChangeLog $record): string => implode(', ', AdminAccessChangeLogSupport::templateLabels($record->template_keys ?? [])) ?: '-')
                    ->badge()
                    ->separator(',')
                    ->wrap(),
                Tables\Columns\TextColumn::make('changed_prefixes')
                    ->label('Modul Terdampak')
                    ->state(fn (AdminAccessChangeLog $record): string => implode(', ', AdminAccessChangeLogSupport::changedModuleLabels($record->changed_prefixes ?? [])) ?: '-')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Aksi')
                    ->options([
                        'add' => 'Tambah Divisi',
                        'remove' => 'Cabut Divisi',
                    ]),
                Tables\Filters\SelectFilter::make('template_keys')
                    ->label('Divisi')
                    ->options(AdminRoleTemplateSupport::options())
                    ->multiple()
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $values = AdminRoleTemplateSupport::normalizeTemplateKeys($data['values'] ?? []);

                        if ($values === []) {
                            return $query;
                        }

                        return $query->where(function (Builder $innerQuery) use ($values): void {
                            foreach ($values as $value) {
                                $innerQuery->orWhereJsonContains('template_keys', $value);
                            }
                        });
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema([
                        Placeholder::make('actor')
                            ->label('Dilakukan Oleh')
                            ->content(fn (AdminAccessChangeLog $record): string => $record->actorUser?->name
                                ? $record->actorUser->name.' ('.$record->actorUser->username.')'
                                : '-'),
                        Placeholder::make('target')
                            ->label('Akun Tujuan')
                            ->content(fn (AdminAccessChangeLog $record): string => $record->targetUser?->name
                                ? $record->targetUser->name.' ('.$record->targetUser->username.')'
                                : '-'),
                        Placeholder::make('division_templates')
                            ->label('Divisi')
                            ->content(fn (AdminAccessChangeLog $record): string => implode(', ', AdminAccessChangeLogSupport::templateLabels($record->template_keys ?? [])) ?: '-'),
                        Placeholder::make('affected_modules')
                            ->label('Modul Terdampak')
                            ->content(fn (AdminAccessChangeLog $record): string => implode(', ', AdminAccessChangeLogSupport::changedModuleLabels($record->changed_prefixes ?? [])) ?: '-'),
                        TextEntry::make('note')
                            ->label('Catatan')
                            ->placeholder('-'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminAccessChangeLogs::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::userCanModule('view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
