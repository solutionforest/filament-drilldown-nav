<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Tree;

use BackedEnum;
use Closure;
use Filament\Navigation\NavigationItem;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;

class NavItem
{
    /** @var array<int, NavItem>|Closure|null */
    protected array|Closure|null $children = null;

    /** @var array<int, NavItem>|null memoized result of, per request */
    protected ?array $resolvedChildren = null;

    protected string|BackedEnum|Htmlable|null $icon = null;

    protected ?string $url = null;

    /** @var string|(Closure(): ?string)|null */
    protected string|Closure|null $badge = null;

    /** @var string|array<mixed>|(Closure(): (string|array<mixed>|null))|null */
    protected string|array|Closure|null $badgeColor = null;

    protected bool|Closure $visible = true;

    protected ?Closure $recordMenu = null;

    protected ?Closure $recordResolver = null;

    protected ?Closure $recordTitle = null;

    /** @var array<int, string>|Closure */
    protected array|Closure $ignoredRecordKeys = ['create'];

    /** @var array<string, NavItem|null> record nodes, per record key, per request */
    protected array $recordNodes = [];

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

    /** A Filament Page: label, icon, URL and access come from the page class. */
    public static function page(string $page): static
    {
        return static::make($page::getNavigationLabel())
            ->icon($page::getNavigationIcon())
            ->url($page::getUrl())
            ->visible(fn (): bool => $page::canAccess());
    }

    /**
     * A Filament Resource: label, icon, list URL and access come from the resource class.
     * Pass URL parameters for a nested resource, e.g. `['agent' => $agent]`.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function resource(string $resource, array $parameters = []): static
    {
        $url = $resource::getIndexUrl($parameters);

        return static::make($resource::getNavigationLabel())
            ->icon($resource::getNavigationIcon())
            ->url($url)
            ->visible(fn (): bool => $resource::canViewAny())
            ->ignoreRecordKeys(fn (): array => ['create', ...static::staticRouteSegments($url)])
            ->resolveRecordUsing(fn (string $key): ?Model => $resource::resolveRecordRouteBinding($key))
            ->recordTitleUsing(fn (Model $record): string => (string) $resource::getRecordTitle($record));
    }

    /**
     * Fixed URL segments registered right below $url (e.g. "import" for /admin/agents/import),
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

    public function icon(string|BackedEnum|Htmlable|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /** @param array<int, NavItem>|Closure $children */
    public function children(array|Closure $children): static
    {
        $this->children = $children;
        $this->resolvedChildren = null;

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

    public function getLabel(): string|Htmlable
    {
        return $this->label;
    }

    /** Plain-text label (HTML stripped when the label is Htmlable). */
    public function getLabelText(): string
    {
        return $this->label instanceof Htmlable ? trim(strip_tags($this->label->toHtml())) : $this->label;
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
        if ($this->resolvedChildren === null) {
            $children = $this->children instanceof Closure ? ($this->children)() : ($this->children ?? []);
            $this->resolvedChildren = array_values(array_filter($children, fn (NavItem $n) => $n->isVisible()));
        }

        return $this->resolvedChildren;
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

        if (! array_key_exists($key, $this->recordNodes)) {
            $this->recordNodes[$key] = $this->makeRecordNode($key, rtrim($this->url, '/').'/'.$key);
        }

        return $this->recordNodes[$key];
    }

    protected function makeRecordNode(string $key, string $url): ?NavItem
    {
        if ($this->ignoredRecordKeys instanceof Closure) {
            $this->ignoredRecordKeys = ($this->ignoredRecordKeys)();
        }

        $ignored = $this->ignoredRecordKeys;

        if (is_array($ignored) && in_array($key, $ignored, true)) {
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

        return static::make($this->recordTitle ? ($this->recordTitle)($record) : (string) $record)
            ->url($url)
            ->children(fn (): array => $menu($record));
    }
}
