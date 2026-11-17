<?php

namespace App\Filament\Clusters\Reporting\Pages;

use App\Filament\Clusters\Reporting;
use Filament\Pages\Page;

class Exports extends Page
{
    protected static ?string $cluster = Reporting::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'exports';

    protected string $view = 'filament.pages.cluster-page';
}
