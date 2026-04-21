<?php

namespace App\Filament\Resources\SurveiResource\Pages;

use App\Filament\Resources\SurveiResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSurvei extends EditRecord
{
    protected static string $resource = SurveiResource::class;

    protected array $targetSelectionData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return array_merge($data, SurveiResource::initialTargetFormData($this->getRecord()));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->targetSelectionData = [
            'audience_type' => $data['audience_type'] ?? $this->getRecord()->audience_type,
            'selected_student_ids' => $data['selected_student_ids'] ?? [],
            'selected_guru_tendik_ids' => $data['selected_guru_tendik_ids'] ?? [],
        ];

        unset($data['selected_student_ids'], $data['selected_guru_tendik_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        SurveiResource::syncTargets($this->getRecord(), $this->targetSelectionData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}
