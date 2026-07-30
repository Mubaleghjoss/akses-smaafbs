<?php

namespace App\Filament\Resources\AssessmentPeriodResource\Pages;

use App\Filament\Pages\Assessment\AssessmentMasterImport;
use App\Filament\Resources\AssessmentPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssessmentPeriods extends ListRecords
{
    protected static string $resource = AssessmentPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('master')
                ->label('Impor Master Resmi')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(AssessmentMasterImport::getUrl()),
            Actions\CreateAction::make(),
        ];
    }
}
