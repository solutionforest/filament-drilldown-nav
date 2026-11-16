<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Pages\PageConfiguration;
use Filament\Panel;
use Illuminate\Support\Facades\Route;

/** Catch-all demo page: renders whatever sub-path was requested. */
class DemoPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'demo';

    protected string $view = 'filament.pages.demo';

    public string $path = '';

    public static function getRoutePath(Panel $panel): string
    {
        return '/demo/{path?}';
    }

    public static function routes(Panel $panel, ?PageConfiguration $configuration = null): void
    {
        Route::get(static::getRoutePath($panel), static::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name(static::getRelativeRouteName($panel))
            ->where('path', '.*');
    }

    public function mount(?string $path = null): void
    {
        $this->path = $path ?? '';
    }

    public function getTitle(): string
    {
        return $this->path === '' ? 'Demo' : ucwords(str_replace(['/', '-'], [' / ', ' '], $this->path));
    }
}
