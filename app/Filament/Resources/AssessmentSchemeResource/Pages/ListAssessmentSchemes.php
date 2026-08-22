<?php

namespace App\Filament\Resources\AssessmentSchemeResource\Pages;

use App\Filament\Resources\AssessmentSchemeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssessmentSchemes extends ListRecords
{
    protected static string $resource = AssessmentSchemeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
