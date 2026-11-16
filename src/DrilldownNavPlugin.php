<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
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
        // Suppress Filament's native sidebar navigation; ours replaces it.
        $panel->navigation(false);
    }

    public function boot(Panel $panel): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_NAV_START,
            fn (): string => Blade::render('<x-filament-drilldown-nav::sidebar />'),
        );
    }
}
