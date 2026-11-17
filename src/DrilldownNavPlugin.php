<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Livewire\Sidebar;
use Filament\Navigation\NavigationBuilder;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use LogicException;
use SolutionForest\DrilldownNav\Livewire\DrilldownSidebar;
use SolutionForest\DrilldownNav\Tree\NativeNavigation;
use SolutionForest\DrilldownNav\Tree\NavItem;

use function Filament\Support\original_request;

class DrilldownNavPlugin implements Plugin
{
    /** @var array<int, NavItem>|Closure(): array<int, NavItem> */
    protected array|Closure $tree = [];

    protected ChildDisplay $childDisplay = ChildDisplay::Drilldown;

    protected bool $searchable = false;

    protected int $searchMinItems = 0;

    protected int $recentLimit = 0;

    protected bool $pinnable = false;

    protected string $panelId = '';

    protected ?Panel $panel = null;

    public static function make(): static
    {
        $plugin = app(static::class);

        if (! $plugin instanceof static) {
            throw new LogicException(static::class.' was resolved to something else from the container.');
        }

        return $plugin;
    }

    public static function get(): static
    {
        $plugin = filament(static::make()->getId());

        if (! $plugin instanceof static) {
            throw new LogicException(static::class.' is not registered on the current panel.');
        }

        return $plugin;
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

    /**
     * A search box above the menu that filters the items already on screen (the current level and
     * inline sections) as you type. With $minItems, it appears only when at least that many items are listed.
     */
    public function searchable(bool $searchable = true, int $minItems = 0): static
    {
        $this->searchable = $searchable;
        $this->searchMinItems = $minItems;

        return $this;
    }

    /** A "Recent" list of the last $limit pages visited (per browser and user) at the top level. 0 turns it off. */
    public function recent(int $limit = 5): static
    {
        $this->recentLimit = max(0, $limit);

        return $this;
    }

    /** A "Pin this page" toggle, and a "Pinned" list at the top level (per browser and user). */
    public function pinnable(bool $pinnable = true): static
    {
        $this->pinnable = $pinnable;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function getSearchMinItems(): int
    {
        return $this->searchMinItems;
    }

    public function getRecentLimit(): int
    {
        return $this->recentLimit;
    }

    public function isPinnable(): bool
    {
        return $this->pinnable;
    }

    public function getPanelId(): string
    {
        return $this->panelId;
    }

    /** Browser-storage key prefix, so two panels, tenants or users in one browser never share lists. */
    public function getStorageKey(): string
    {
        $tenantKey = Filament::getTenant()?->getKey();
        $tenantPart = is_int($tenantKey) || is_string($tenantKey) ? ':t'.$tenantKey : '';

        return 'fi-drilldown:'.$this->panelId.$tenantPart.':'.(auth()->id() ?? 'guest');
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

        // Filament's own navigation stays intact for the top bar, breadcrumbs and other plugins; it is
        // only empty while the plugin's sidebar renders (see HidesNativeNavigation). Returning true
        // means "no custom builder": Filament then builds its navigation as usual.
        $panel->navigation(function (NavigationBuilder $builder): NavigationBuilder|bool {
            $state = RequestState::current();

            if ($state->capturingNativeNavigation) {
                NativeNavigation::capture(); // lets NavItem::fromFilamentNavigation() reuse Filament's items

                return $builder;
            }

            return $state->hidingNativeNavigation ? $builder : true;
        });

        // Use the plugin's sidebar component unless the panel already has its own
        // (add the HidesNativeNavigation trait to that one).
        if ($panel->getSidebarLivewireComponent() === Sidebar::class) {
            $panel->sidebarLivewireComponent(DrilldownSidebar::class);
        }

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
