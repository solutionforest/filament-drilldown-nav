<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DrilldownNavServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-drilldown-nav';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)->hasViews(static::$name);
    }
}
