<?php

namespace App\Filament\Resources\AssessmentSubjectCategoryResource\Pages;

use App\Filament\Resources\AssessmentSubjectCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssessmentSubjectCategories extends ListRecords
{
    protected static string $resource = AssessmentSubjectCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Kategori')];
    }
}
