<?php

namespace App\Filament\Resources\BoardingPencapaianResource\Pages;

use App\Filament\Resources\BoardingPencapaianResource;
use App\Models\BoardingPencapaian;
use Filament\Resources\Pages\ManageRecords;

class ManageBoardingPencapaians extends ManageRecords
{
    protected static string $resource = BoardingPencapaianResource::class;

    public function mount(): void
    {
        parent::mount();

        BoardingPencapaian::ensureRecordsForVisibleStudents(auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
