<?php

namespace App\Filament\Resources\AssessmentReportTemplateResource\Pages;

use App\Filament\Resources\AssessmentReportTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssessmentReportTemplates extends ListRecords
{
    protected static string $resource = AssessmentReportTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
