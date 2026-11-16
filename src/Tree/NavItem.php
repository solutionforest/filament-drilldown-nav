<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Tree;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class NavItem
{
    /** @var array<int, NavItem>|Closure|null */
    protected array|Closure|null $children = null;

    /** @var array<int, NavItem>|null memoized result of, per request */
    protected ?array $resolvedChildren = null;

    protected string|BackedEnum|Htmlable|null $icon = null;

    protected ?string $url = null;

    protected bool|Closure $visible = true;

    protected ?Closure $recordMenu = null;

    protected ?Closure $recordResolver = null;

    protected ?Closure $recordTitle = null;

    /** @var array<string, array{menu: array<int, NavItem>, title: string}|null> */
    protected array $resolvedRecords = [];

    final public function __construct(protected string|Htmlable $label) {}

    public static function make(string|Htmlable $label): static
    {
        return new static($label);
    }

    /** A Filament Page: label, icon, URL and access come from the page class. */
    public static function page(string $page): static
    {
        return static::make($page::getNavigationLabel())
            ->icon($page::getNavigationIcon())
            ->url($page::getUrl())
            ->visible(fn (): bool => $page::canAccess());
    }

    /** A Filament Resource: label, icon, index URL and access come from the resource class. */
    public static function resource(string $resource): static
    {
        return static::make($resource::getNavigationLabel())
            ->icon($resource::getNavigationIcon())
            ->url($resource::getUrl('index'))
            ->visible(fn (): bool => $resource::canViewAny())
            ->resolveRecordUsing(fn (string $key): ?Model => $resource::resolveRecordRouteBinding($key))
            ->recordTitleUsing(fn (Model $record): string => (string) $resource::getRecordTitle($record));
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

    /** @param Closure(string): mixed $resolver turns the URL key into a record (null = not found) */
    public function resolveRecordUsing(Closure $resolver): static
    {
        $this->recordResolver = $resolver;

        return $this;
    }

    /** @param Closure(mixed): string $title label of the record, shown above its menu */
    public function recordTitleUsing(Closure $title): static
    {
        $this->recordTitle = $title;

        return $this;
    }

    public function getLabel(): string|Htmlable
    {
        return $this->label;
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
     * Record menu for the record whose key follows this item's URL in $path, if any.
     *
     * @return array{menu: array<int, NavItem>, title: string}|null
     */
    public function getRecordContext(string $path): ?array
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

        if ($key === '' || $key === 'create') {
            return null;
        }

        if (! array_key_exists($key, $this->resolvedRecords)) {
            $record = $this->recordResolver ? ($this->recordResolver)($key) : $key;

            $this->resolvedRecords[$key] = $record === null ? null : [
                'menu' => array_values(array_filter(($this->recordMenu)($record), fn (NavItem $n) => $n->isVisible())),
                'title' => $this->recordTitle ? ($this->recordTitle)($record) : (string) $record,
            ];
        }

        return $this->resolvedRecords[$key];
    }
}
