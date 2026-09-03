<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Support\Admin\AdminModuleAccess;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->record->loadMissing('roles');
        $data['module_access_levels'] = AdminModuleAccess::effectiveLevels($record);
        $data['allowed_navigation_items'] = $record->hasExplicitNavigationSelection()
            ? $record->resolvedNavigationItems()
            : AdminModuleAccess::deriveNavigationItems(
                $data['module_access_levels'],
                (array) ($record->allowed_navigation_items ?? []),
            );
        $data['navigation_selection_explicit'] = true;
        $data['access_template_addons'] = UserResource::defaultAccessAddonState();

        if ($data['access_template_addons'] !== []) {
            $data['module_access_levels'] = UserResource::mergeAddonTemplatesIntoLevels(
                $data['module_access_levels'],
                $data['access_template_addons'],
            );
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Hapus akun admin?')
                ->modalDescription('Tindakan ini tidak bisa dibatalkan. Pastikan akun yang dipilih memang ingin dihapus.')
                ->modalSubmitActionLabel('Ya, hapus data')
                ->visible(fn (): bool => UserResource::canDelete($this->record)),
        ];
    }

    protected function afterSave(): void
    {
        UserResource::syncScopedModuleConfiguration($this->record, $this->data);
    }
}
