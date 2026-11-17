<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Tree;

use BackedEnum;
use Closure;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use LogicException;
use SolutionForest\DrilldownNav\Contracts\HasDrilldownRecordMenu;
use SolutionForest\DrilldownNav\RequestState;

class NavItem
{
    /** @var array<int, NavItem>|(Closure(): array<int, NavItem>)|null */
    protected array|Closure|null $children = null;

    protected string|BackedEnum|Htmlable|null $icon = null;

    protected ?string $url = null;

    protected string|BackedEnum|Htmlable|null $activeIcon = null;

    /** null = follow the plugin's default child display */
    protected ?bool $inline = null;

    protected bool $defaultExpanded = false;

    protected bool $heading = false;

    protected ?int $limit = null;

    protected ?string $moreUrl = null;

    protected bool $dividerBefore = false;

    /** @var string|(Closure(): ?string)|null */
    protected string|Closure|null $badge = null;

    /** @var string|array<mixed>|(Closure(): (string|array<mixed>|null))|null */
    protected string|array|Closure|null $badgeColor = null;

    protected bool|Closure $visible = true;

    /** Closures from the app: their results are validated where they are called (see itemsFrom(), plainText()). */
    protected ?Closure $recordMenu = null;

    /** @var class-string<\Filament\Resources\Resource>|null set by resource() */
    protected ?string $resourceClass = null;

    protected ?Closure $recordResolver = null;

    protected ?Closure $recordTitle = null;

    /** @var array<int, string>|(Closure(): array<int, string>) */
    protected array|Closure $ignoredRecordKeys = ['create'];

    /** @var array<int, string>|(Closure(): array<int, string>) */
    protected array|Closure $activeUrls = [];

    /** @var (Closure(): bool)|null */
    protected ?Closure $activeWhen = null;

    /** @var Htmlable|string|(Closure(): (Htmlable|string|null))|null */
    protected Htmlable|string|Closure|null $header = null;

    protected ?Closure $recordHeader = null;

    final public function __construct(protected string|Htmlable $label) {}

    public static function make(string|Htmlable $label): static
    {
        return new static($label);
    }

    /**
     * Everything Filament would show in its own sidebar (`$navigationGroup`, `$navigationParentItem`,
     * `$navigationSort`, badges, `shouldRegisterNavigation()`, access checks), as drill-down items.
     * Mix it with hand-written items; use $filter to leave items out (return false).
     *
     * @param  (Closure(NavigationItem): bool)|null  $filter
     * @return array<int, NavItem>
     */
    public static function fromFilamentNavigation(?Closure $filter = null): array
    {
        return NativeNavigation::items($filter);
    }

    /**
     * A non-clickable section heading ("Manage", "Reports" ...), like Filament's group labels.
     * Combine with ->divider() for a line above it.
     */
    public static function heading(string|Htmlable $label): static
    {
        $item = static::make($label);
        $item->heading = true;

        return $item;
    }

    /**
     * A Filament Page: label, icon, URL and access come from the page class.
     *
     * @param  class-string<Page>  $page
     */
    public static function page(string $page): static
    {
        return static::make($page::getNavigationLabel())
            ->icon($page::getNavigationIcon())
            ->url($page::getUrl())
            ->visible(fn (): bool => $page::canAccess());
    }

    /**
     * A Filament Resource: label, icon, list URL and access come from the resource class.
     * Pass URL parameters for a nested resource, e.g. `['project' => $project]`.
     *
     * @param  class-string<resource>  $resource
     * @param  array<string, mixed>  $parameters
     */
    public static function resource(string $resource, array $parameters = []): static
    {
        $url = $resource::getIndexUrl($parameters);

        $item = static::make($resource::getNavigationLabel())
            ->icon($resource::getNavigationIcon())
            ->url($url)
            ->visible(fn (): bool => $resource::canViewAny())
            ->ignoreRecordKeys(fn (): array => ['create', ...static::staticRouteSegments($url)])
            ->resolveRecordUsing(fn (string $key): ?Model => $resource::resolveRecordRouteBinding($key))
            ->recordTitleUsing(fn (Model $record): string => static::plainText($resource::getRecordTitle($record)));
        $item->resourceClass = $resource;

        // A resource that declares its own record menu needs no ->record() here.
        if (is_subclass_of($resource, HasDrilldownRecordMenu::class)) {
            $item->record(fn (mixed $record): array => $record instanceof Model ? $resource::drilldownRecordMenu($record) : []);
        }

        return $item;
    }

    /**
     * The record menu, built from the resource's own record pages: View, Edit, pages managing related
     * records, custom pages on `/{record}/…`. Labels, icons, badges and access come from each page
     * (`getNavigationLabel()`, `getNavigationIcon()`, `canAccess()` …), exactly as Filament builds a
     * record's sub-navigation. Pass page classes to choose and order them; by default every page whose
     * route needs only the record, in the order of `getPages()`. Only for items made with resource().
     *
     * @param  array<int, class-string<\Filament\Resources\Pages\Page>>|null  $pages
     */
    public function recordPages(?array $pages = null): static
    {
        $resource = $this->resourceClass ?? throw new LogicException('recordPages() only works on items made with NavItem::resource().');

        return $this->record(fn (mixed $record): array => $record instanceof Model ? RecordPages::items($resource, $record, $pages) : []);
    }

    /**
     * Fixed URL segments registered right below $url (e.g. "import" for /admin/projects/import),
     * so such list-level pages are never mistaken for a record key.
     *
     * @return array<int, string>
     */
    protected static function staticRouteSegments(string $url): array
    {
        $base = ActivePathResolver::normalise($url);
        $segments = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = ActivePathResolver::normalise('/'.$route->uri());

            if (! str_starts_with($uri, $base.'/')) {
                continue;
            }

            $segment = explode('/', substr($uri, strlen($base) + 1))[0];

            if ($segment !== '' && ! str_starts_with($segment, '{')) {
                $segments[] = $segment;
            }
        }

        return array_values(array_unique($segments));
    }

    public function url(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    /** Show the children expanded in place under this item (click to expand/collapse) instead of opening a new level. */
    public function inline(bool $inline = true): static
    {
        $this->inline = $inline;

        return $this;
    }

    /** Open a new level for the children (the default), whatever the plugin's default display is. */
    public function drilldown(): static
    {
        return $this->inline(false);
    }

    /** For inline items: start expanded even when the current page is not inside it. */
    public function defaultExpanded(bool $expanded = true): static
    {
        $this->defaultExpanded = $expanded;

        return $this;
    }

    /**
     * Show at most $max children (the one containing the current page is always kept), followed by a
     * "View all (N)" link to $moreUrl, or a "+N more" note without one. For menus with hundreds of entries.
     * All children still take part in URL matching; only the display is shortened.
     */
    public function limit(int $max, ?string $moreUrl = null): static
    {
        $this->limit = max(1, $max);
        $this->moreUrl = $moreUrl;

        return $this;
    }

    /**
     * More URLs where this item counts as the current page, matched like its own URL (by prefix).
     * For pages that belong to the item but live elsewhere, e.g. a list at /agents/1/sources whose
     * records open at /agents/1/knowledge/{id}. Only items with a URL use them.
     *
     * @param  array<int, string>|Closure(): array<int, string>  $urls
     */
    public function activeOn(array|Closure $urls): static
    {
        $this->activeUrls = $urls;

        return $this;
    }

    /**
     * Marks the item as the current page whenever $condition returns true, whatever the URL:
     * for pages the URL cannot place, e.g. `fn () => request()->routeIs('reports.*')` or a tab in
     * the query string. It counts as an exact URL match; if another item matches the URL exactly,
     * the deeper of the two wins. Evaluated at most once per request; only items with a URL use it.
     *
     * @param  Closure(): bool  $condition
     */
    public function activeWhen(Closure $condition): static
    {
        $this->activeWhen = $condition;

        return $this;
    }

    /**
     * Replaces the default header (Back link and title) above the level this item opens, e.g. with
     * a card and a switcher. Rendered as given: an Htmlable (a view) as HTML, a string as escaped text.
     * The header then owns the way back, so include a link to the parent level yourself.
     *
     * @param  Htmlable|string|(Closure(): (Htmlable|string|null))|null  $header
     */
    public function header(Htmlable|string|Closure|null $header): static
    {
        $this->header = $header;

        return $this;
    }

    /**
     * Like header(), for the level of ONE record of this item (see record()): the closure gets the record.
     *
     * @param  Closure(never): (Htmlable|string|null)  $header
     */
    public function recordHeader(Closure $header): static
    {
        $this->recordHeader = $header;

        return $this;
    }

    /** Draw a separator line above this item. */
    public function divider(bool $divider = true): static
    {
        $this->dividerBefore = $divider;

        return $this;
    }

    /** Icon shown while the item is the current page. */
    public function activeIcon(string|BackedEnum|Htmlable|null $icon): static
    {
        $this->activeIcon = $icon;

        return $this;
    }

    public function icon(string|BackedEnum|Htmlable|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /** @param array<int, NavItem>|Closure $children */
    public function children(array|Closure $children): static
    {
        $this->children = $children;

        return $this;
    }

    public function visible(bool|Closure $condition = true): static
    {
        $this->visible = $condition;

        return $this;
    }

    /**
     * The menu shown while the visitor is inside ONE record of this item:
     * the URL segment right after this item's URL is treated as the record key.
     *
     * @param  Closure(Model|mixed): array<int, NavItem>  $menu
     */
    public function record(Closure $menu): static
    {
        $this->recordMenu = $menu;

        return $this;
    }

    /**
     * URL segments after this item's URL that are pages, not record keys ("create" by default).
     *
     * @param  array<int, string>|Closure(): array<int, string>  $keys
     */
    public function ignoreRecordKeys(array|Closure $keys): static
    {
        $this->ignoredRecordKeys = $keys;

        return $this;
    }

    /** @param Closure(never): mixed $resolver turns the URL key into a record (null = not found) */
    public function resolveRecordUsing(Closure $resolver): static
    {
        $this->recordResolver = $resolver;

        return $this;
    }

    /** @param Closure(never): string $title label of the record, shown above its menu */
    public function recordTitleUsing(Closure $title): static
    {
        $this->recordTitle = $title;

        return $this;
    }

    /**
     * Extra URLs from activeOn(), evaluated at most once per request.
     *
     * @return array<int, string>
     */
    public function getActiveUrls(): array
    {
        return RequestState::current()->remember($this, 'active-urls', fn (): array => $this->activeUrls instanceof Closure ? ($this->activeUrls)() : $this->activeUrls);
    }

    /** Whether activeWhen() says this item is the current page (false without a condition). */
    public function isActiveByCondition(): bool
    {
        $condition = $this->activeWhen;

        return $condition !== null && RequestState::current()->remember($this, 'active-when', fn (): bool => (bool) $condition());
    }

    /** The custom header of this item's level (null = the default Back link and title). */
    public function getHeader(): Htmlable|string|null
    {
        return RequestState::current()->remember($this, 'header', function (): Htmlable|string|null {
            $header = $this->header instanceof Closure ? ($this->header)() : $this->header;

            return $header === '' ? null : $header;
        });
    }

    public function getLabel(): string|Htmlable
    {
        return $this->label;
    }

    /** Plain-text label (HTML stripped when the label is Htmlable). */
    public function getLabelText(): string
    {
        return static::plainText($this->label);
    }

    /** Plain text of a label: HTML stripped from an Htmlable; anything that is not text becomes ''. */
    protected static function plainText(mixed $value): string
    {
        if ($value instanceof Htmlable) {
            return trim(strip_tags($value->toHtml()));
        }

        return is_string($value) ? $value : (is_scalar($value) ? (string) $value : '');
    }

    /**
     * The NavItems in whatever an app closure returned (anything else is ignored).
     *
     * @return array<int, NavItem>
     */
    protected static function itemsFrom(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, fn (mixed $item): bool => $item instanceof NavItem));
    }

    /**
     * @param  string|(Closure(): ?string)|null  $badge
     * @param  string|array<mixed>|(Closure(): (string|array<mixed>|null))|null  $color
     */
    public function badge(string|Closure|null $badge, string|array|Closure|null $color = null): static
    {
        $this->badge = $badge;
        $this->badgeColor = $color;

        return $this;
    }

    public function getBadge(): ?string
    {
        $badge = $this->badge instanceof Closure ? ($this->badge)() : $this->badge;

        return $badge === null || $badge === '' ? null : $badge;
    }

    /** @return string|array<mixed>|null */
    public function getBadgeColor(): string|array|null
    {
        return $this->badgeColor instanceof Closure ? ($this->badgeColor)() : $this->badgeColor;
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->icon;
    }

    public function getActiveIcon(): string|BackedEnum|Htmlable|null
    {
        return $this->activeIcon;
    }

    public function isHeading(): bool
    {
        return $this->heading;
    }

    public function hasDivider(): bool
    {
        return $this->dividerBefore;
    }

    public function isDefaultExpanded(): bool
    {
        return $this->defaultExpanded;
    }

    /** Whether the children are shown in place (true) or as a new level (false). */
    public function isInline(bool $default = false): bool
    {
        return $this->inline ?? $default;
    }

    /** Opens its own level (has children and is not inline). */
    public function opensLevel(bool $inlineByDefault = false): bool
    {
        return ! $this->isInline($inlineByDefault) && $this->hasChildren();
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    /** Where clicking the item goes: its own URL, or (for a pure group) its first reachable child. */
    public function getLinkUrl(): ?string
    {
        if ($this->url !== null) {
            return $this->url;
        }

        foreach ($this->getChildren() as $child) {
            if (($link = $child->getLinkUrl()) !== null) {
                return $link;
            }
        }

        return null;
    }

    public function isVisible(): bool
    {
        return (bool) ($this->visible instanceof Closure ? ($this->visible)() : $this->visible);
    }

    /**
     * Visible children. The closure runs at most once per request.
     *
     * @return array<int, NavItem>
     */
    public function getChildren(): array
    {
        return RequestState::current()->remember($this, 'children', function (): array {
            $children = $this->children instanceof Closure ? ($this->children)() : ($this->children ?? []);

            return array_values(array_filter($children, fn (NavItem $n) => $n->isVisible()));
        });
    }

    /**
     * The children to display, shortened by ->limit(): the first N plus any child on the current path.
     *
     * @param  array<int, int>  $pathIds  spl_object_id()s of the items on the current path
     * @return array{items: array<int, NavItem>, hidden: int, moreUrl: ?string}
     */
    public function getDisplayChildren(array $pathIds = []): array
    {
        $children = $this->getChildren();

        if ($this->limit === null || count($children) <= $this->limit) {
            return ['items' => $children, 'hidden' => 0, 'moreUrl' => null];
        }

        $shown = array_slice($children, 0, $this->limit);

        foreach (array_slice($children, $this->limit) as $child) {
            if (in_array(spl_object_id($child), $pathIds, true)) {
                $shown[] = $child; // never hide where the visitor is
            }
        }

        return ['items' => $shown, 'hidden' => count($children) - count($shown), 'moreUrl' => $this->moreUrl];
    }

    /** Children given as an array (not a closure): cheap to look into when the item is not on the current path. */
    public function hasStaticChildren(): bool
    {
        return is_array($this->children) && $this->children !== [];
    }

    public function hasChildren(): bool
    {
        return $this->getChildren() !== [];
    }

    public function hasRecordMenu(): bool
    {
        return $this->recordMenu !== null;
    }

    /**
     * The node for the record whose key follows this item's URL in $path: its URL is
     * this item's URL + key, its label the record title, its children the record menu.
     * Null when there is no record menu, no key, an ignored key, or no such record.
     */
    public function getRecordNode(string $path): ?NavItem
    {
        if ($this->recordMenu === null || $this->url === null) {
            return null;
        }

        $base = ActivePathResolver::normalise($this->url);
        $prefix = $base === '/' ? '/' : $base.'/';

        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        $key = explode('/', substr($path, strlen($prefix)))[0];

        if ($key === '') {
            return null;
        }

        $url = $this->url;

        return RequestState::current()->remember($this, 'record:'.$key, fn (): ?NavItem => $this->makeRecordNode($key, rtrim($url, '/').'/'.$key));
    }

    protected function makeRecordNode(string $key, string $url): ?NavItem
    {
        $ignored = RequestState::current()->remember($this, 'ignored-keys', fn (): array => $this->ignoredRecordKeys instanceof Closure ? ($this->ignoredRecordKeys)() : $this->ignoredRecordKeys);

        if (in_array($key, $ignored, true)) {
            return null;
        }

        try {
            $record = $this->recordResolver ? ($this->recordResolver)($key) : $key;
        } catch (QueryException) {
            return null; // e.g. a non-numeric key on an integer column in PostgreSQL
        }

        if ($record === null) {
            return null;
        }

        $menu = $this->recordMenu;

        if ($menu === null) {
            return null;
        }

        $recordHeader = $this->recordHeader;

        $title = $this->recordTitle !== null ? ($this->recordTitle)($record) : $record;

        return static::make(static::plainText($title))
            ->url($url)
            ->children(fn (): array => static::itemsFrom($menu($record)))
            ->header($recordHeader === null ? null : function () use ($recordHeader, $record): Htmlable|string|null {
                $header = $recordHeader($record);

                return $header instanceof Htmlable || is_string($header) ? $header : null;
            });
    }
}
