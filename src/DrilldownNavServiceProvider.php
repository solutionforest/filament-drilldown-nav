<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Cookie\Middleware\EncryptCookies;
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
        // Written by the sidebar's JavaScript (open/closed inline sections), so it can't be encrypted.
        // It only holds section keys and 0/1, and is validated when read.
        EncryptCookies::except(DrilldownNavPlugin::OPEN_STATE_COOKIE);

        // A stylesheet instead of an inline <style> block: it works under a strict
        // Content-Security-Policy, is cached by the browser, and can be overridden by a theme.
        // Published to public/ by `php artisan filament:assets` (also run by `filament:upgrade`).
        FilamentAsset::register([
            Css::make(static::$name, __DIR__.'/../resources/dist/filament-drilldown-nav.css'),
        ], 'solutionforest/filament-drilldown-nav');
    }
}
