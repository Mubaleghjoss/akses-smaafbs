<?php

namespace App\Filament\Resources\BoardingHafalanPointResource\Pages;

use App\Filament\Resources\BoardingHafalanPointResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageBoardingHafalanPoints extends ManageRecords
{
    protected static string $resource = BoardingHafalanPointResource::class;

    public function getTabs(): array
    {
        return [
            'boarding' => Tab::make('Materi Boarding')
                ->query(fn (Builder $query): Builder => $query->where('materi_scope', 'boarding')),
            'mt' => Tab::make('Materi MT')
                ->query(fn (Builder $query): Builder => $query->where('materi_scope', 'mt')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'boarding';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
