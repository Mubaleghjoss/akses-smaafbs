<?php

namespace App\Filament\Resources\AssessmentReportTemplateResource\Pages;

use App\Filament\Resources\AssessmentReportTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssessmentReportTemplate extends CreateRecord
{
    protected static string $resource = AssessmentReportTemplateResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_active'] = false;

        return AssessmentReportTemplateResource::validateTemplateData($data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Template disimpan sebagai draf';
    }
}
