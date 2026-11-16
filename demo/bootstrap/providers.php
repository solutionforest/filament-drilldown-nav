<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\PlainPanelProvider;
use App\Providers\Filament\PortalPanelProvider;
use App\Providers\Filament\WorkspacePanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    PortalPanelProvider::class,
    PlainPanelProvider::class,
    WorkspacePanelProvider::class,
];
