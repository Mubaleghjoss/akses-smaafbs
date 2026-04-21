<?php

namespace App\Filament\Concerns;

use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

trait HasOptimizedAdminTable
{
    protected static function optimizeAdminTable(
        Table $table,
        string $searchPlaceholder = 'Cari data...',
        string $emptyStateHeading = 'Belum ada data',
        string $emptyStateDescription = 'Data akan tampil di sini setelah ditambahkan atau saat filter yang dipilih cocok.',
    ): Table {
        return $table
            ->striped()
            ->deferLoading()
            ->defaultPaginationPageOption(10)
            ->paginated([10, 25, 50, 100])
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->searchDebounce('600ms')
            ->searchPlaceholder($searchPlaceholder)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->poll(null)
            ->emptyStateHeading($emptyStateHeading)
            ->emptyStateDescription($emptyStateDescription);
    }
}
