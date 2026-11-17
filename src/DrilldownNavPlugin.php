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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use SolutionForest\DrilldownNav\Livewire\DrilldownSidebar;
use SolutionForest\DrilldownNav\Models\DrilldownPin;
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

    protected bool $searchAllLevels = false;

    protected ?string $searchShortcut = '/';

    protected int $recentLimit = 0;

    protected bool $pinnable = false;

    protected bool $pinsInDatabase = false;

    /** @var class-string<DrilldownPin> */
    protected string $pinModel = DrilldownPin::class;

    /** Most pins kept per user, panel and tenant. */
    public const MAX_PINS = 20;

    protected ?string $pollInterval = null;

    protected bool $accordion = false;

    /** Cookie holding which inline sections the user opened or closed (read on the server). */
    public const OPEN_STATE_COOKIE = 'fi-drilldown-open';

    protected string $panelId = '';

    protected ?Panel $panel = null;

    /** Create the plugin to register on a panel: `->plugin(DrilldownNavPlugin::make()->items(...))`. */
    public static function make(): static
    {
        $plugin = app(static::class);

        if (! $plugin instanceof static) {
            throw new LogicException(static::class.' was resolved to something else from the container.');
        }

        return $plugin;
    }

    /** The plugin instance registered on the current panel (throws if the panel does not use it). */
    public static function get(): static
    {
        $plugin = filament(static::make()->getId());

        if (! $plugin instanceof static) {
            throw new LogicException(static::class.' is not registered on the current panel.');
        }

        return $plugin;
    }

    public const ID = 'drilldown-nav';

    public function getId(): string
    {
        return self::ID;
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
     *
     * With $allLevels, it also finds pages on levels that are not open ("In other menus"), from an index of
     * the whole menu (see flatten()) loaded on first use. $shortcut is the key that focuses the box from
     * anywhere on the page ('/' by default, null for none).
     */
    public function searchable(bool $searchable = true, int $minItems = 0, bool $allLevels = false, ?string $shortcut = '/'): static
    {
        if ($shortcut !== null && mb_strlen($shortcut) !== 1) {
            throw new \InvalidArgumentException('searchable() expects a single-character shortcut (or null).');
        }

        $this->searchable = $searchable;
        $this->searchMinItems = $minItems;
        $this->searchAllLevels = $allLevels;
        $this->searchShortcut = $shortcut;

        return $this;
    }

    /** @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md). */
    public function searchesAllLevels(): bool
    {
        return $this->searchable && $this->searchAllLevels;
    }

    /** @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md). */
    public function getSearchShortcut(): ?string
    {
        return $this->searchShortcut;
    }

    /**
     * The whole menu as a flat list of pages the current user may see: label, URL, the labels of the
     * levels above it (trail) and its depth. For "search all levels", command palettes, a mobile bottom
     * bar (filter `depth === 0`) or tests.
     *
     * Runs children closures (once per request each), so it stops after $limit pages or $maxDepth levels.
     * Per-record menus are not included: they depend on a record, and records are not enumerated.
     *
     * @return list<array{label: string, url: string, trail: list<string>, depth: int}>
     */
    public function flatten(int $limit = 300, int $maxDepth = 6): array
    {
        $entries = [];
        $seen = [];
        $this->flattenInto($this->getTree(), [], 0, $limit, $maxDepth, $entries, $seen);

        return $entries;
    }

    /**
     * @param  array<int, NavItem>  $items
     * @param  list<string>  $trail
     * @param  list<array{label: string, url: string, trail: list<string>, depth: int}>  $entries
     * @param  array<string, true>  $seen
     */
    private function flattenInto(array $items, array $trail, int $depth, int $limit, int $maxDepth, array &$entries, array &$seen): void
    {
        foreach ($items as $item) {
            if (count($entries) >= $limit) {
                return;
            }

            if (! $item->isVisible() || $item->isHeading()) {
                continue;
            }

            $label = $item->getLabelText();
            $url = $item->getUrl();

            if ($url !== null && ! isset($seen[$url])) {
                $seen[$url] = true;
                $entries[] = ['label' => $label, 'url' => $url, 'trail' => $trail, 'depth' => $depth];
            }

            if ($depth + 1 < $maxDepth) {
                $this->flattenInto($item->getChildren(), [...$trail, $label], $depth + 1, $limit, $maxDepth, $entries, $seen);
            }
        }
    }

    /** The plugin of the panel being served, if that panel uses it. */
    public static function current(): ?static
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($panel === null || ! $panel->hasPlugin(self::ID)) {
            return null;
        }

        $plugin = $panel->getPlugin(self::ID);

        return $plugin instanceof static ? $plugin : null;
    }

    /** A "Recent" list of the last $limit pages visited (per browser and user) at the top level. 0 turns it off. */
    public function recent(int $limit = 5): static
    {
        $this->recentLimit = max(0, $limit);

        return $this;
    }

    /**
     * A "Pin this page" toggle, and a "Pinned" list at the top of the top level.
     *
     * By default pins live in the browser (per panel, tenant and user). With $database they are stored in
     * the `drilldown_nav_pins` table (publish and run the migration: `php artisan vendor:publish
     * --tag=filament-drilldown-nav-migrations && php artisan migrate`): they follow the user across
     * devices and are rendered by the server. $model lets you use your own model on the same table.
     *
     * @param  string|null  $model  a subclass of DrilldownPin
     */
    public function pinnable(bool $pinnable = true, bool $database = false, ?string $model = null): static
    {
        $this->pinnable = $pinnable;
        $this->pinsInDatabase = $database;

        if ($model !== null) {
            if (! is_a($model, DrilldownPin::class, true)) {
                throw new \InvalidArgumentException('pinnable(model:) expects a subclass of '.DrilldownPin::class.'.');
            }

            $this->pinModel = $model;
        }

        return $this;
    }

    /**
     * Pins are stored in the database (and a user is signed in).
     *
     * @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md).
     */
    public function storesPinsInDatabase(): bool
    {
        return $this->pinnable && $this->pinsInDatabase && auth()->user() !== null;
    }

    /**
     * The signed-in user's pins for this panel and tenant, newest first.
     *
     * @return list<array{url: string, label: string}>
     *
     * @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md).
     */
    public function getPins(): array
    {
        $query = $this->pinQuery();

        if ($query === null) {
            return [];
        }

        return array_values($query->latest('id')->limit(self::MAX_PINS)->get()
            ->map(fn (DrilldownPin $pin): array => ['url' => $pin->url, 'label' => $pin->label])
            ->all());
    }

    /**
     * Pin or unpin a page. The URL must be a path under this panel (and tenant), like the pages the
     * sidebar links to; anything else is ignored.
     *
     * @return list<array{url: string, label: string}> the pins afterwards
     *
     * @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md).
     */
    public function togglePin(string $url, string $label): array
    {
        $query = $this->pinQuery();

        if ($query === null || ! $this->isPinnableUrl($url)) {
            return $this->getPins();
        }

        $existing = (clone $query)->where('url_hash', sha1($url))->first();

        if ($existing !== null) {
            $existing->delete();
        } else {
            $query->create([...$this->pinOwner(), 'url' => $url, 'url_hash' => sha1($url), 'label' => mb_substr(trim($label), 0, 120)]);
            // keep the newest MAX_PINS
            $keep = (clone $query)->latest('id')->limit(self::MAX_PINS)->pluck('id')->all();
            (clone $query)->whereNotIn('id', $keep)->delete();
        }

        return $this->getPins();
    }

    /**
     * @return list<array{url: string, label: string}> the pins afterwards
     *
     * @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md).
     */
    public function forgetPin(string $url): array
    {
        $this->pinQuery()?->where('url_hash', sha1($url))->delete();

        return $this->getPins();
    }

    /**
     * Keep a pinned page's label current while the user is on it.
     *
     * @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md).
     */
    public function refreshPinLabel(string $url, string $label): void
    {
        $label = mb_substr(trim($label), 0, 120);

        if ($label !== '') {
            $this->pinQuery()?->where('url_hash', sha1($url))->where('label', '!=', $label)->update(['label' => $label]);
        }
    }

    protected function isPinnableUrl(string $url): bool
    {
        if (! str_starts_with($url, '/') || str_starts_with($url, '//') || strlen($url) > 2000 || preg_match('/[\x00-\x1F\\\\]/', $url) === 1) {
            return false;
        }

        $root = Tree\ActivePathResolver::normalise($this->getRootUrl());
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

        return $root === '/' || $path === $root || str_starts_with($path, $root.'/');
    }

    /** @return array{user_type: string, user_id: string, panel: string, tenant: string} */
    protected function pinOwner(): array
    {
        $user = auth()->user();
        $id = $user?->getAuthIdentifier();
        $tenantKey = Filament::getTenant()?->getKey();

        return [
            'user_type' => $user instanceof Model ? $user->getMorphClass() : '',
            'user_id' => is_int($id) || is_string($id) ? (string) $id : '',
            'panel' => $this->panelId,
            'tenant' => is_int($tenantKey) || is_string($tenantKey) ? (string) $tenantKey : '',
        ];
    }

    /** @return Builder<DrilldownPin>|null */
    protected function pinQuery(): ?Builder
    {
        if (! $this->storesPinsInDatabase()) {
            return null;
        }

        $owner = $this->pinOwner();

        if ($owner['user_id'] === '') {
            return null;
        }

        return $this->pinModel::query()->where($owner);
    }

    /**
     * Re-render the sidebar every $interval (e.g. '30s', '2m'), so badges and record titles stay current
     * without a page load. Livewire pauses polling in background tabs. Null turns it off. For changes you
     * know about, dispatch Filament's `refresh-sidebar` event instead (e.g. `$this->dispatch('refresh-sidebar')`).
     */
    public function pollBadges(?string $interval = '30s'): static
    {
        if ($interval !== null && preg_match('/^\d+(ms|s|m)$/', $interval) !== 1) {
            throw new \InvalidArgumentException("pollBadges() expects an interval like '30s', '2m' or '500ms', got [{$interval}].");
        }

        $this->pollInterval = $interval;

        return $this;
    }

    /** Only one inline section open at a time within the same parent (opening one closes its siblings). */
    public function accordion(bool $accordion = true): static
    {
        $this->accordion = $accordion;

        return $this;
    }

    /** @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md). */
    public function isAccordion(): bool
    {
        return $this->accordion;
    }

    /**
     * Which inline sections the user opened (1) or closed (0), by section key, from the cookie the
     * sidebar writes. Read on the server so a closed section is not painted open first.
     *
     * @return array<string, bool>
     *
     * @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md).
     */
    public function getOpenState(): array
    {
        $raw = original_request()->cookie(self::OPEN_STATE_COOKIE);

        if (! is_string($raw) || $raw === '' || strlen($raw) > 4096) {
            return [];
        }

        $decoded = json_decode($raw, true);
        $state = [];

        if (is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                if (is_string($key) && preg_match('/^[a-f0-9]{10}$/', $key) === 1) {
                    $state[$key] = (bool) $value;
                }
            }
        }

        return $state;
    }

    /** @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md). */
    public function getPollInterval(): ?string
    {
        return $this->pollInterval;
    }

    /** @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md). */
    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /** @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md). */
    public function getSearchMinItems(): int
    {
        return $this->searchMinItems;
    }

    /** @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md). */
    public function getRecentLimit(): int
    {
        return $this->recentLimit;
    }

    /** @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md). */
    public function isPinnable(): bool
    {
        return $this->pinnable;
    }

    /** @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md). */
    public function getPanelId(): string
    {
        return $this->panelId;
    }

    /**
     * Browser-storage key prefix, so two panels, tenants or users in one browser never share lists.
     *
     * @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md).
     */
    public function getStorageKey(): string
    {
        $tenantKey = Filament::getTenant()?->getKey();
        $tenantPart = is_int($tenantKey) || is_string($tenantKey) ? ':t'.$tenantKey : '';

        return 'fi-drilldown:'.$this->panelId.$tenantPart.':'.(auth()->id() ?? 'guest');
    }

    /** @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md). */
    public function getChildDisplay(): ChildDisplay
    {
        return $this->childDisplay;
    }

    /**
     * @return array<int, NavItem> evaluated once per request
     *
     * @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md).
     */
    public function getTree(): array
    {
        return RequestState::current()->trees[$this->panelId] ??= $this->tree instanceof Closure ? ($this->tree)() : $this->tree;
    }

    /**
     * The panel's home URL as Filament builds it: includes the domain and, with multi-tenancy,
     * the current tenant (e.g. https://app.test/admin/acme).
     *
     * @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md).
     */
    public function getRootUrl(): string
    {
        $panel = $this->panel;

        return $panel?->getUrl(Filament::getTenant()) ?? url($panel?->getPath() ?? '');
    }

    /**
     * Request path including the application's base path (apps installed in a sub-directory).
     *
     * @internal Used by the sidebar view; not part of the public API (see docs/api-stability.md).
     */
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
