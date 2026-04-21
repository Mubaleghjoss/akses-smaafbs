<?php

namespace App\Filament\Resources\GuruTendikResource\Pages;

use App\Filament\Resources\GuruTendikResource;
use App\Filament\Widgets\GuruTendikAccountStatsOverview;
use App\Filament\Widgets\GuruTendikGenderChart;
use App\Filament\Widgets\GuruTendikJenisPtkChart;
use App\Filament\Widgets\GuruTendikStatsOverview;
use App\Support\Admin\Dashboard\UserCredentialDashboardSupport;
use App\Support\GuruTendik\GuruTendikWorkbookImporter;
use App\Support\Security\EndpointProtectionPolicy;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Throwable;

class ListGuruTendiks extends ListRecords
{
    protected static string $resource = GuruTendikResource::class;

    public bool $showSummaryWidgets = true;

    #[Url(as: 'chart_jenis_ptk')]
    public ?string $chartJenisPtk = null;

    #[Url(as: 'chart_jk')]
    public ?string $chartJk = null;

    #[Url(as: 'password_status')]
    public ?string $passwordStatus = null;

    #[Url(as: 'account_status')]
    public ?string $accountStatus = null;

    public function mount(): void
    {
        parent::mount();

        if (EndpointProtectionPolicy::shouldSkipExpensiveAdminDashboardWidgets()) {
            $this->showSummaryWidgets = false;
        }

        $filters = [];

        if (filled($this->chartJenisPtk)) {
            $filters['jenis_ptk'] = ['value' => $this->chartJenisPtk];
        }

        if (filled($this->chartJk)) {
            $filters['jk'] = ['value' => $this->chartJk];
        }

        if (filled($this->passwordStatus)) {
            $filters['user_password_status'] = ['value' => $this->passwordStatus];
        }

        if (filled($this->accountStatus)) {
            $filters['user_account_status'] = ['value' => $this->accountStatus];
        }

        if ($filters !== []) {
            $this->tableFilters = $filters;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('clearQuickFilters')
                ->label('Reset Filter Cepat')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (): bool => $this->hasQuickFilters())
                ->url(GuruTendikResource::getUrl('index')),
            Actions\ActionGroup::make([
                Actions\Action::make('presetAllGuruTendik')
                    ->label(fn (): string => 'Semua Guru/Tendik ('.$this->guruAccountSummary()['total_guru_tendik'].')')
                    ->icon('heroicon-o-users')
                    ->url(GuruTendikResource::getUrl('index')),
                Actions\Action::make('presetHasAccount')
                    ->label(fn (): string => 'Sudah Punya Akun ('.$this->guruAccountSummary()['punya_akun'].')')
                    ->icon('heroicon-o-user-circle')
                    ->url(GuruTendikResource::getUrl('index', [
                        'account_status' => 'has_account',
                    ])),
                Actions\Action::make('presetDefaultPassword')
                    ->label(fn (): string => 'Password Default ('.$this->guruAccountSummary()['default_password'].')')
                    ->icon('heroicon-o-key')
                    ->url(GuruTendikResource::getUrl('index', [
                        'account_status' => 'has_account',
                        'password_status' => 'default',
                    ])),
                Actions\Action::make('presetChangedPassword')
                    ->label(fn (): string => 'Sudah Ganti Password ('.$this->guruAccountSummary()['changed_password'].')')
                    ->icon('heroicon-o-shield-check')
                    ->url(GuruTendikResource::getUrl('index', [
                        'account_status' => 'has_account',
                        'password_status' => 'changed',
                    ])),
                Actions\Action::make('presetNoAccount')
                    ->label(fn (): string => 'Belum Ada Akun ('.$this->guruAccountSummary()['belum_punya_akun'].')')
                    ->icon('heroicon-o-user-plus')
                    ->url(GuruTendikResource::getUrl('index', [
                        'account_status' => 'no_account',
                    ])),
            ])
                ->label('Preset View')
                ->icon('heroicon-o-funnel')
                ->color('gray')
                ->button(),
            Actions\Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->url(route('admin.guru-tendiks.export'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => GuruTendikResource::canViewAny()),
            Actions\Action::make('toggleSummaryWidgets')
                ->label(fn (): string => $this->showSummaryWidgets ? 'Collapse Semua Ringkasan' : 'Muat Semua Ringkasan')
                ->icon(fn (): string => $this->showSummaryWidgets ? 'heroicon-o-eye-slash' : 'heroicon-o-bolt')
                ->color('gray')
                ->action(fn (): bool => $this->showSummaryWidgets = ! $this->showSummaryWidgets),
            Actions\Action::make('importGuruTendik')
                ->label('Import Data Guru / Tendik')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('Import Data Guru / Tendik')
                ->modalSubmitActionLabel('Kirim File')
                ->modalWidth('2xl')
                ->visible(fn (): bool => GuruTendikResource::canCreate())
                ->form([
                    Forms\Components\Placeholder::make('download_format')
                        ->label('Download Format')
                        ->content(new HtmlString('<a href="'.route('admin.guru-tendiks.import-template').'" target="_blank" class="text-primary-600 font-semibold underline">Download format import Guru / Tendik</a>')),
                    Forms\Components\FileUpload::make('berkas')
                        ->label('Kirim File')
                        ->disk('public')
                        ->directory('guru-tendik/imports')
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
                        Notification::make()->title('File import tidak ditemukan.')->danger()->send();

                        return;
                    }

                    try {
                        $result = app(GuruTendikWorkbookImporter::class)->import($disk->path($path));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Import guru / tendik gagal.')
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
                        ->title('Import guru / tendik selesai.')
                        ->body("{$result['created']} data baru, {$result['updated']} data diperbarui, {$result['skipped']} baris dilewati.")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make()
                ->visible(fn (): bool => GuruTendikResource::canCreate()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        if (! $this->showSummaryWidgets) {
            return [];
        }

        return [
            GuruTendikAccountStatsOverview::class,
            GuruTendikStatsOverview::class,
            GuruTendikJenisPtkChart::class,
            GuruTendikGenderChart::class,
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

    protected function hasQuickFilters(): bool
    {
        return filled($this->chartJenisPtk)
            || filled($this->chartJk)
            || filled($this->passwordStatus)
            || filled($this->accountStatus);
    }

    /**
     * @return array<string, int>
     */
    protected function guruAccountSummary(): array
    {
        return UserCredentialDashboardSupport::snapshot()['guru_summary'];
    }
}
