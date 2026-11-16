<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav;

use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DrilldownNavServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-drilldown-nav';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)->hasViews(static::$name);
    }

    public function packageBooted(): void
    {
        Blade::component(static::$name.'::sidebar', 'filament-drilldown-nav::sidebar');
    }
}
