<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Concerns\HasModulePermissions;
use App\Filament\Resources\CalendarEventResource\Pages;
use App\Models\CalendarEvent;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class CalendarEventResource extends Resource
{
    use HasConfirmedDeleteActions;
    use HasModulePermissions;

    protected static ?string $model = CalendarEvent::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Agenda';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Kalender Agenda';

    protected static ?string $modelLabel = 'agenda kalender';

    protected static ?string $pluralModelLabel = 'Kalender Agenda';

    protected static ?string $permissionPrefix = 'calendar_events';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Detail Agenda')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Judul kegiatan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Opsional, bisa diisi detail singkat.')
                            ->columnSpanFull(),
                    ]),
                Section::make('Waktu & Publikasi')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        Forms\Components\DatePicker::make('start')
                            ->label('Tanggal mulai')
                            ->required()
                            ->native(false),
                        Forms\Components\DatePicker::make('end')
                            ->label('Tanggal selesai')
                            ->native(false)
                            ->helperText('Kosongkan jika hanya satu hari.')
                            ->minDate(fn ($get) => $get('start')),
                        Forms\Components\Toggle::make('all_day')
                            ->label('Seharian')
                            ->default(true),
                        Forms\Components\Select::make('visibility')
                            ->label('Visibilitas')
                            ->required()
                            ->options([
                                'external' => 'Publik',
                                'internal' => 'Internal',
                            ])
                            ->helperText('Publik akan tampil di beranda.')
                            ->default('external'),
                    ]),
                Section::make('Opsional')
                    ->columns(['default' => 1, 'md' => 2])
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('color')
                            ->label('Warna')
                            ->maxLength(20)
                            ->default(null),
                        Forms\Components\TextInput::make('display')
                            ->label('Tampilan')
                            ->maxLength(20)
                            ->default(null),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Kegiatan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start')
                    ->label('Tanggal')
                    ->formatStateUsing(function ($state, CalendarEvent $record): string {
                        return self::formatDateRange($record->start, $record->end);
                    })
                    ->sortable(),
                Tables\Columns\IconColumn::make('all_day')
                    ->label('Seharian')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('visibility')
                    ->label('Visibilitas')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'internal' ? 'Internal' : 'Publik'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('visibility')
                    ->label('Visibilitas')
                    ->options([
                        'external' => 'Publik',
                        'internal' => 'Internal',
                    ]),
            ])
            ->defaultSort('start', 'desc')
            ->actions([
                EditAction::make(),
                static::makeDeleteTableAction('agenda'),
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

    public static function buildAgendaText(int $month, int $year, string $visibility = 'external', bool $includeDescription = false): string
    {
        $startRange = Carbon::create($year, $month, 1)->startOfDay();
        $endRange = $startRange->copy()->endOfMonth()->endOfDay();

        $query = CalendarEvent::query()
            ->where(function ($query) use ($startRange, $endRange) {
                $query->whereBetween('start', [$startRange, $endRange])
                    ->orWhereBetween('end', [$startRange, $endRange])
                    ->orWhere(function ($query) use ($startRange, $endRange) {
                        $query->where('start', '<=', $startRange)
                            ->where('end', '>=', $endRange);
                    });
            });

        if ($visibility !== 'all') {
            $query->where('visibility', $visibility);
        }

        $events = $query
            ->orderBy('start')
            ->orderBy('title')
            ->get();

        $header = 'Agenda Kegiatan '.$startRange->locale('id')->translatedFormat('F Y');

        if ($events->isEmpty()) {
            return $header."\nBelum ada agenda.";
        }

        $groups = $events->groupBy(function (CalendarEvent $event): string {
            $startDate = $event->start ? $event->start->copy()->startOfDay() : null;
            $endDate = $event->end ? $event->end->copy()->startOfDay() : $startDate;

            $startKey = $startDate?->toDateString() ?? 'unknown';
            $endKey = $endDate?->toDateString() ?? $startKey;

            return $startKey.'|'.$endKey;
        });

        $lines = [$header];

        foreach ($groups as $groupKey => $groupEvents) {
            [$startKey, $endKey] = explode('|', $groupKey);

            $startDate = Carbon::parse($startKey)->locale('id');
            $endDate = Carbon::parse($endKey)->locale('id');

            $lines[] = '';
            $lines[] = self::formatAgendaGroupLabel($startDate, $endDate);

            foreach ($groupEvents as $event) {
                $lines[] = self::formatAgendaLine($event, $includeDescription);
            }
        }

        return implode("\n", $lines);
    }

    protected static function formatAgendaGroupLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->translatedFormat('l, j F Y');
        }

        if ($start->isSameMonth($end)) {
            return $start->format('j').'-'.$end->format('j').' '.$start->translatedFormat('F Y');
        }

        if ($start->isSameYear($end)) {
            return $start->translatedFormat('j F').' - '.$end->translatedFormat('j F Y');
        }

        return $start->translatedFormat('j F Y').' - '.$end->translatedFormat('j F Y');
    }

    protected static function formatAgendaLine(CalendarEvent $event, bool $includeDescription): string
    {
        $title = trim((string) $event->title);
        $timeLabel = self::formatAgendaTime($event);

        $line = '- '.trim($timeLabel.' '.$title);

        $description = trim((string) ($event->description ?? ''));
        if ($includeDescription && $description !== '') {
            $line .= ' ('.$description.')';
        }

        return $line;
    }

    protected static function formatAgendaTime(CalendarEvent $event): string
    {
        if ($event->all_day || $event->all_day === null) {
            return '';
        }

        $start = $event->start;
        if (! $start) {
            return '';
        }

        $startTime = $start->format('H:i');
        $end = $event->end;
        $endTime = $end?->format('H:i');

        if ($end && $end->isSameDay($start) && $endTime) {
            if ($startTime === '00:00' && $endTime === '00:00') {
                return '';
            }

            return $startTime.'-'.$endTime;
        }

        return $startTime === '00:00' ? '' : $startTime;
    }

    protected static function formatDateRange(?Carbon $start, ?Carbon $end): string
    {
        if (! $start) {
            return '-';
        }

        $start = $start->copy()->locale('id');
        $end = $end?->copy()->locale('id');

        if (! $end || $start->isSameDay($end)) {
            return $start->translatedFormat('d M Y');
        }

        if ($start->isSameMonth($end)) {
            return $start->format('d').'-'.$end->format('d').' '.$start->translatedFormat('M Y');
        }

        if ($start->isSameYear($end)) {
            return $start->translatedFormat('d M').' - '.$end->translatedFormat('d M Y');
        }

        return $start->translatedFormat('d M Y').' - '.$end->translatedFormat('d M Y');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCalendarEvents::route('/'),
            'create' => Pages\CreateCalendarEvent::route('/create'),
            'edit' => Pages\EditCalendarEvent::route('/{record}/edit'),
        ];
    }
}
