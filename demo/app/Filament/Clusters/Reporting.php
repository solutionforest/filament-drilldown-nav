<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

/** A Filament cluster (native sub-navigation beside the content) living next to the drill-down sidebar. */
class Reporting extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $slug = 'cluster-reports';

    protected static ?int $navigationSort = 20;
}
