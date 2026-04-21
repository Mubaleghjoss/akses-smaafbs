<?php

namespace App\Filament\Resources\CalendarEventResource\Pages;

use App\Filament\Resources\CalendarEventResource;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class ListCalendarEvents extends ListRecords
{
    protected static string $resource = CalendarEventResource::class;

    protected function getHeaderActions(): array
    {
        $monthOptions = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $updateMessage = function (Set $set, Get $get): void {
            $month = (int) ($get('month') ?: now()->month);
            $year = (int) ($get('year') ?: now()->year);
            $visibility = (string) ($get('visibility') ?: 'external');
            $includeDescription = (bool) $get('include_description');

            $set('message', CalendarEventResource::buildAgendaText($month, $year, $visibility, $includeDescription));
        };

        return [
            Actions\CreateAction::make(),
            Actions\Action::make('exportText')
                ->label('Export Teks WA')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->modalHeading('Export Teks Agenda')
                ->modalSubmitActionLabel('Salin & Buka WA')
                ->modalCancelActionLabel('Tutup')
                ->modalSubmitAction(function (Actions\Action $action) {
                    return $action->extraAttributes([
                        'x-on:click' => "const el = document.getElementById('agenda-export-text'); if (el) { const text = el.value || el.textContent || ''; if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text); } else { if (el.select) { el.select(); } try { document.execCommand('copy'); } catch (e) {} if (window.getSelection) { window.getSelection().removeAllRanges(); } } }",
                    ]);
                })
                ->form([
                    Select::make('month')
                        ->label('Bulan')
                        ->options($monthOptions)
                        ->default((int) now()->format('n'))
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => $updateMessage($set, $get)),
                    TextInput::make('year')
                        ->label('Tahun')
                        ->numeric()
                        ->default((int) now()->format('Y'))
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => $updateMessage($set, $get)),
                    Select::make('visibility')
                        ->label('Visibilitas')
                        ->options([
                            'external' => 'Publik',
                            'internal' => 'Internal',
                            'all' => 'Semua',
                        ])
                        ->default('external')
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => $updateMessage($set, $get)),
                    Toggle::make('include_description')
                        ->label('Sertakan keterangan')
                        ->default(false)
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => $updateMessage($set, $get)),
                    Textarea::make('message')
                        ->label('Teks agenda')
                        ->rows(12)
                        ->readOnly()
                        ->dehydrated(false)
                        ->extraInputAttributes(['id' => 'agenda-export-text'])
                        ->afterStateHydrated(fn ($state, Set $set, Get $get) => $updateMessage($set, $get))
                        ->helperText('Salin teks di bawah ini untuk dibagikan ke WhatsApp.'),
                ])
                ->action(static function (array $data) {
                    $month = (int) ($data['month'] ?? now()->month);
                    $year = (int) ($data['year'] ?? now()->year);
                    $visibility = (string) ($data['visibility'] ?? 'external');
                    $includeDescription = (bool) ($data['include_description'] ?? false);

                    $message = CalendarEventResource::buildAgendaText($month, $year, $visibility, $includeDescription);
                    $url = 'https://wa.me/?text='.urlencode($message);

                    return redirect()->away($url);
                }),
        ];
    }
}
