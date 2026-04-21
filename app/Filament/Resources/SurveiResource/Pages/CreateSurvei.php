<?php

namespace App\Filament\Resources\SurveiResource\Pages;

use App\Filament\Resources\SurveiResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSurvei extends CreateRecord
{
    protected static string $resource = SurveiResource::class;

    protected array $targetSelectionData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->targetSelectionData = [
            'audience_type' => $data['audience_type'] ?? null,
            'selected_student_ids' => $data['selected_student_ids'] ?? [],
            'selected_guru_tendik_ids' => $data['selected_guru_tendik_ids'] ?? [],
        ];

        unset($data['selected_student_ids'], $data['selected_guru_tendik_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        SurveiResource::syncTargets($this->getRecord(), $this->targetSelectionData);
    }
}
