<?php

namespace Tests\Feature\Concerns;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;

trait BootstrapsAdminPanel
{
    protected function bootstrapAdminPanel(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }
}
