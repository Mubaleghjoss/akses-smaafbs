<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PerpustakaanLiterasiMaterialResource;
use App\Support\Perpustakaan\LiterasiAnalytics;
use Filament\Widgets\Widget;

class PerpustakaanLiterasiGlobalAnalytics extends Widget
{
    protected string $view = 'filament.widgets.perpustakaan-literasi-global-analytics';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return PerpustakaanLiterasiMaterialResource::canViewAny();
    }

    protected function getViewData(): array
    {
        return [
            'analytics' => LiterasiAnalytics::global(),
            'categoryAnalytics' => LiterasiAnalytics::categoryAnalytics(),
        ];
    }
}
