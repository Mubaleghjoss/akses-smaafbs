<?php

namespace App\Filament\Resources\UksRecordResource\Pages;

use App\Filament\Resources\UksRecordResource;
use App\Filament\Widgets\UksCategoryChart;
use App\Filament\Widgets\UksMeasurementChart;
use App\Filament\Widgets\UksStatsOverview;
use App\Support\Uks\UksAnthropometrySupport;
use App\Support\Uks\UksRecordWorkbookImporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Throwable;

class ListUksRecords extends ListRecords
{
    protected static string $resource = UksRecordResource::class;

    public bool $showSummaryWidgets = true;

    #[Url(as: 'chart_kategori')]
    public ?string $chartKategori = null;

    public function mount(): void
    {
        parent::mount();

        if (filled($this->chartKategori)) {
            $this->tableFilters = [
                'kategori' => ['value' => $this->chartKategori],
            ];
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(route('admin.uks-records.export'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => UksRecordResource::canViewAny()),
            Actions\Action::make('manageAnthropometry')
                ->label('Update Antropometri Murid')
                ->icon('heroicon-o-scale')
                ->color('warning')
                ->badge(fn (): int => UksAnthropometrySupport::activeStudentsCount(auth()->user()))
                ->badgeColor('warning')
                ->url(UksRecordResource::getUrl('anthropometry'))
                ->visible(fn (): bool => UksRecordResource::canViewAny()),
            Actions\Action::make('missingAnthropometryThisMonth')
                ->label('Belum Diukur Bulan Ini')
                ->icon('heroicon-o-exclamation-circle')
                ->color('danger')
                ->badge(fn (): int => UksAnthropometrySupport::unmeasuredThisMonthCount(auth()->user()))
                ->badgeColor('danger')
                ->url(UksRecordResource::getUrl('anthropometry', [
                    'anthropometry_filter' => 'belum_bulan_ini',
                ]))
                ->visible(fn (): bool => UksRecordResource::canViewAny()),
            Actions\Action::make('toggleSummaryWidgets')
                ->label(fn (): string => $this->showSummaryWidgets ? 'Collapse Semua Ringkasan' : 'Tampilkan Semua Ringkasan')
                ->icon(fn (): string => $this->showSummaryWidgets ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                ->color('gray')
                ->action(fn (): bool => $this->showSummaryWidgets = ! $this->showSummaryWidgets),
            $this->importDataAction(),
            Actions\CreateAction::make()
                ->label('Tambah Kunjungan UKS'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        if (! $this->showSummaryWidgets) {
            return [];
        }

        return [
            UksStatsOverview::class,
            UksCategoryChart::class,
            UksMeasurementChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }

    protected function importDataAction(): Actions\Action
    {
        return Actions\Action::make('importDataUks')
            ->label('Import Data UKS')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading('Import Data UKS')
            ->modalSubmitActionLabel('Kirim File')
            ->modalWidth('2xl')
            ->visible(fn (): bool => UksRecordResource::canCreate())
            ->form([
                Forms\Components\Placeholder::make('download_format')
                    ->label('Download Format')
                    ->content(new HtmlString('<a href="'.route('admin.uks-records.import-template').'" target="_blank" class="text-primary-600 font-semibold underline">Download format import Data UKS</a>')),
                Forms\Components\FileUpload::make('berkas')
                    ->label('Kirim File')
                    ->disk('public')
                    ->directory('uks/imports')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])
                    ->required(),
            ])
            ->action(function (array $data): void {
                $disk = Storage::disk('public');
                $path = $data['berkas'] ?? null;

                if (! $path || ! $disk->exists($path)) {
                    Notification::make()
                        ->title('File import tidak ditemukan.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $result = app(UksRecordWorkbookImporter::class)->import($disk->path($path));
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('Import data UKS gagal.')
                        ->body(filled($exception->getMessage()) ? $exception->getMessage() : 'Terjadi kesalahan saat membaca file Excel.')
                        ->danger()
                        ->send();

                    return;
                } finally {
                    if ($disk->exists($path)) {
                        $disk->delete($path);
                    }
                }

                Notification::make()
                    ->title('Import data UKS selesai.')
                    ->body("{$result['created']} data baru, {$result['updated']} data diperbarui, {$result['skipped']} baris dilewati.")
                    ->success()
                    ->send();
            });
    }
}
