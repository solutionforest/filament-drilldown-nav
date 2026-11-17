<?php

namespace App\Filament\Clusters\Reporting\Pages;

use App\Filament\Clusters\Reporting;
use Filament\Pages\Page;

class Overview extends Page
{
    protected static ?string $cluster = Reporting::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'overview';

    protected string $view = 'filament.pages.cluster-page';
}
