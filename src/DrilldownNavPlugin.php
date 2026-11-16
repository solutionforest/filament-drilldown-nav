<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use SolutionForest\DrilldownNav\Tree\NativeNavigation;
use SolutionForest\DrilldownNav\Tree\NavItem;

use function Filament\Support\original_request;

class DrilldownNavPlugin implements Plugin
{
    /** @var array<int, NavItem>|Closure(): array<int, NavItem> */
    protected array|Closure $tree = [];

    protected ChildDisplay $childDisplay = ChildDisplay::Drilldown;

    protected string $panelId = '';

    protected ?Panel $panel = null;

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

    /**
     * Pass a closure (recommended): it is evaluated once per request, so menus can depend on the
     * current user, tenant or database state. Items built outside a closure live as long as the
     * worker does, which is fine for static menus only.
     *
     * @param  array<int, NavItem>|Closure(): array<int, NavItem>  $items
     */
    public function items(array|Closure $items): static
    {
        $this->tree = $items;

        return $this;
    }

    /**
     * Default for items with children: open a new level (drilldown, the default) or expand in place
     * (inline). Override per item with `->inline()` / `->drilldown()`.
     */
    public function childDisplay(ChildDisplay $display): static
    {
        $this->childDisplay = $display;

        return $this;
    }

    public function getChildDisplay(): ChildDisplay
    {
        return $this->childDisplay;
    }

    /** @return array<int, NavItem> evaluated once per request */
    public function getTree(): array
    {
        return RequestState::current()->trees[$this->panelId] ??= $this->tree instanceof Closure ? ($this->tree)() : $this->tree;
    }

    /**
     * The panel's home URL as Filament builds it: includes the domain and, with multi-tenancy,
     * the current tenant (e.g. https://app.test/admin/acme).
     */
    public function getRootUrl(): string
    {
        $panel = $this->panel;

        return $panel?->getUrl(Filament::getTenant()) ?? url($panel?->getPath() ?? '');
    }

    /** Request path including the application's base path (apps installed in a sub-directory). */
    public function getRequestPath(): string
    {
        $request = original_request();

        return $request->getBaseUrl().$request->getPathInfo();
    }

    public function register(Panel $panel): void
    {
        $this->panel = $panel;
        $this->panelId = $panel->getId();

        // Empty the native navigation but keep the sidebar shell (and its render hooks):
        // navigation(false) would remove the sidebar entirely.
        $panel->navigation(function (NavigationBuilder $builder): NavigationBuilder {
            NativeNavigation::capture(); // lets NavItem::fromFilamentNavigation() reuse Filament's items

            return $builder;
        });

        // A panel-scoped hook (not a global one): other panels, with or without this plugin,
        // are never affected, and each panel renders its own tree.
        $panel->renderHook(PanelsRenderHook::SIDEBAR_NAV_START, function (): string {
            // Filament may also boot the default panel while another panel is being served;
            // only ever render the tree of the panel that is actually being shown.
            if (Filament::getCurrentOrDefaultPanel()?->getId() !== $this->panelId) {
                return '';
            }

            /** @var view-string $view */
            $view = 'filament-drilldown-nav::sidebar';

            return view($view, ['plugin' => $this])->render();
        });
    }

    public function boot(Panel $panel): void {}
}
