<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationBuilder;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use SolutionForest\DrilldownNav\Tree\NavNode;

class DrilldownNavPlugin implements Plugin
{
    protected array|Closure $tree = [];

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

    /** @param array<int, NavNode>|Closure $tree */
    public function tree(array|Closure $tree): static
    {
        $this->tree = $tree;

        return $this;
    }

    /** @return array<int, NavNode> */
    public function getTree(): array
    {
        return $this->tree instanceof Closure ? ($this->tree)() : $this->tree;
    }

    public function register(Panel $panel): void
    {
        // Empty the native navigation but keep the sidebar shell (and its render hooks):
        // navigation(false) would remove the sidebar entirely.
        $panel->navigation(fn (NavigationBuilder $builder): NavigationBuilder => $builder);
    }

    public function boot(Panel $panel): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_NAV_START,
            fn (): string => view('filament-drilldown-nav::sidebar')->render(),
        );
    }
}
