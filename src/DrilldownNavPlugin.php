<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationBuilder;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use SolutionForest\DrilldownNav\Tree\NativeNavigation;
use SolutionForest\DrilldownNav\Tree\NavItem;

class DrilldownNavPlugin implements Plugin
{
    /** @var array<int, NavItem>|Closure(): array<int, NavItem> */
    protected array|Closure $tree = [];

    /** @var array<int, NavItem>|null */
    protected ?array $resolvedTree = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'drilldown-nav';
    }

    /** @param array<int, NavItem>|Closure(): array<int, NavItem> $items */
    public function items(array|Closure $items): static
    {
        $this->tree = $items;
        $this->resolvedTree = null;

        return $this;
    }

    /** @return array<int, NavItem> evaluated once per request */
    public function getTree(): array
    {
        return $this->resolvedTree ??= $this->tree instanceof Closure ? ($this->tree)() : $this->tree;
    }

    public function register(Panel $panel): void
    {
        // Empty the native navigation but keep the sidebar shell (and its render hooks):
        // navigation(false) would remove the sidebar entirely.
        $panel->navigation(function (NavigationBuilder $builder): NavigationBuilder {
            NativeNavigation::capture(); // lets NavItem::fromFilamentNavigation() reuse Filament's items

            return $builder;
        });
    }

    public function boot(Panel $panel): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_NAV_START,
            function (): string {
                /** @var view-string $view */
                $view = 'filament-drilldown-nav::sidebar';

                return view($view)->render();
            },
        );
    }
}
