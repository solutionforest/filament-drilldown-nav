<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DrilldownNavServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-drilldown-nav';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)->hasViews(static::$name)->hasTranslations();
    }

    public function packageBooted(): void
    {
        // A stylesheet instead of an inline <style> block: it works under a strict
        // Content-Security-Policy, is cached by the browser, and can be overridden by a theme.
        // Published to public/ by `php artisan filament:assets` (also run by `filament:upgrade`).
        FilamentAsset::register([
            Css::make(static::$name, __DIR__.'/../resources/dist/filament-drilldown-nav.css'),
        ], 'solutionforest/filament-drilldown-nav');
    }
}
