<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Widgets\UserCredentialStatsOverview;
use App\Support\Admin\Dashboard\UserCredentialDashboardSupport;
use App\Support\Admin\AdminRoleTemplateSupport;
use App\Support\Security\EndpointProtectionPolicy;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public bool $showSummaryWidgets = true;

    #[Url(as: 'password_status')]
    public ?string $passwordStatus = null;

    #[Url(as: 'linked_status')]
    public ?string $linkedStatus = null;

    public function mount(): void
    {
        parent::mount();

        if (EndpointProtectionPolicy::shouldSkipExpensiveAdminDashboardWidgets()) {
            $this->showSummaryWidgets = false;
        }

        $filters = [];

        if (filled($this->passwordStatus)) {
            $filters['uses_default_password'] = ['value' => $this->passwordStatus];
        }

        if (filled($this->linkedStatus)) {
            $filters['guru_link_status'] = ['value' => $this->linkedStatus];
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
                ->url(UserResource::getUrl('index')),
            Actions\ActionGroup::make([
                Actions\Action::make('presetAllUsers')
                    ->label(fn (): string => 'Semua Akun ('.$this->userSummary()['total'].')')
                    ->icon('heroicon-o-users')
                    ->url(UserResource::getUrl('index')),
                Actions\Action::make('presetDefaultPassword')
                    ->label(fn (): string => 'Akun Password Default ('.$this->userSummary()['default_password'].')')
                    ->icon('heroicon-o-key')
                    ->url(UserResource::getUrl('index', [
                        'password_status' => 'default',
                    ])),
                Actions\Action::make('presetChangedPassword')
                    ->label(fn (): string => 'Akun Sudah Ganti Password ('.$this->userSummary()['changed_password'].')')
                    ->icon('heroicon-o-shield-check')
                    ->url(UserResource::getUrl('index', [
                        'password_status' => 'changed',
                    ])),
                Actions\Action::make('presetLinkedGuru')
                    ->label(fn (): string => 'Akun Tertaut Guru ('.$this->userSummary()['linked_guru'].')')
                    ->icon('heroicon-o-academic-cap')
                    ->url(UserResource::getUrl('index', [
                        'linked_status' => 'linked',
                    ])),
                Actions\Action::make('presetLinkedGuruDefault')
                    ->label(fn (): string => 'Guru Password Default ('.$this->userSummary()['linked_guru_default'].')')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->url(UserResource::getUrl('index', [
                        'linked_status' => 'linked',
                        'password_status' => 'default',
                    ])),
            ])
                ->label('Preset View')
                ->icon('heroicon-o-funnel')
                ->color('gray')
                ->button(),
            Actions\Action::make('toggleSummaryWidgets')
                ->label(fn (): string => $this->showSummaryWidgets ? 'Collapse Ringkasan' : 'Muat Ringkasan')
                ->icon(fn (): string => $this->showSummaryWidgets ? 'heroicon-o-eye-slash' : 'heroicon-o-bolt')
                ->color('gray')
                ->action(fn (): bool => $this->showSummaryWidgets = ! $this->showSummaryWidgets),
            Actions\CreateAction::make()
                ->visible(fn (): bool => UserResource::canCreate()),
            Actions\Action::make('createFromTemplate')
                ->label('Buat Dari Template')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->visible(fn (): bool => UserResource::canCreate())
                ->schema([
                    Forms\Components\Select::make('preset_template')
                        ->label('Template Divisi')
                        ->options(AdminRoleTemplateSupport::options())
                        ->searchable()
                        ->native(false)
                        ->required(),
                ])
                ->modalHeading('Buat akun dari template')
                ->modalSubmitActionLabel('Lanjut isi akun')
                ->action(function (array $data): void {
                    $this->redirect(UserResource::getUrl('create', [
                        'preset_template' => $data['preset_template'],
                    ]));
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        if (! $this->showSummaryWidgets) {
            return [];
        }

        return [
            UserCredentialStatsOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'default' => 1,
            'xl' => 4,
        ];
    }

    protected function hasQuickFilters(): bool
    {
        return filled($this->passwordStatus) || filled($this->linkedStatus);
    }

    /**
     * @return array<string, int>
     */
    protected function userSummary(): array
    {
        return UserCredentialDashboardSupport::snapshot()['user_summary'];
    }
}
