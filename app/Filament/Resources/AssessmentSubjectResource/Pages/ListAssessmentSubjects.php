<?php

namespace App\Filament\Resources\AssessmentSubjectResource\Pages;

use App\Filament\Resources\AssessmentSubjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssessmentSubjects extends ListRecords
{
    protected static string $resource = AssessmentSubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AssessmentSubjectResource::syncAllActiveAction(),
            CreateAction::make()->label('Tambah Mapel')->color('gray'),
        ];
    }
}
