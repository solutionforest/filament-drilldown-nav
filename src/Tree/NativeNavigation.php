<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Tree;

use BackedEnum;
use Closure;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationManager;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;
use SolutionForest\DrilldownNav\RequestState;
use UnitEnum;

/**
 * Converts Filament's own navigation (everything registered through `$navigationGroup`,
 * `$navigationParentItem`, `$navigationSort`, `$navigationBadge`, `shouldRegisterNavigation()`,
 * `canAccess()`, `NavigationItem::make()` ...) into drill-down items, so existing
 * Resources and Pages keep working without being listed again.
 *
 * @internal Not part of the public API: it may change in any release. See docs/api-stability.md.
 */
class NativeNavigation
{
    /**
     * Called from the plugin's navigation builder while Filament builds its sidebar: only then
     * are Resources and Pages registering their navigation items on the (scoped) manager.
     */
    public static function capture(): void
    {
        $state = RequestState::current();

        // Filament may resolve the builder several times per build; mounting twice would duplicate items.
        if (! $state->capturingNativeNavigation || $state->capturedNativeNavigation) {
            return;
        }

        $state->capturedNativeNavigation = true;

        $manager = app(NavigationManager::class);
        $manager->mountNavigation();
        $state->nativeItems = $manager->getNavigationItems();
    }

    /** @return array<int, NavigationItem> */
    protected static function collectNativeItems(): array
    {
        $state = RequestState::current();
        $state->capturingNativeNavigation = true;
        $state->capturedNativeNavigation = false;
        $state->nativeItems = [];

        // Collect into a fresh navigation manager: Filament's own (request-scoped) manager may
        // already be mounted this request (top navigation, breadcrumbs, other plugins), and
        // mounting it a second time would register every item twice.
        $container = app();
        $previous = $container->resolved(NavigationManager::class) ? $container->make(NavigationManager::class) : null;
        $container->instance(NavigationManager::class, new NavigationManager);

        try {
            Filament::getCurrentOrDefaultPanel()?->getNavigation();

            return $state->nativeItems;
        } finally {
            $state->capturingNativeNavigation = false;
            $state->nativeItems = [];

            if ($previous !== null) {
                $container->instance(NavigationManager::class, $previous);
            } else {
                $container->forgetInstance(NavigationManager::class);
            }
        }
    }

    /**
     * @param  (Closure(NavigationItem): bool)|null  $filter  return false to leave an item out
     * @return array<int, NavItem> ungrouped items first, then one URL-less group per navigation group
     */
    public static function items(?Closure $filter = null): array
    {
        /** @var array<string, array{label: string|Htmlable, icon: string|BackedEnum|Htmlable|null, items: list<NavigationItem>}> $groups */
        $groups = [];
        /** @var list<NavigationItem> $ungrouped */
        $ungrouped = [];

        $items = collect(static::collectNativeItems())
            ->filter(fn (NavigationItem $item): bool => $item->isVisible() && ($filter === null || $filter($item)))
            ->sortBy(fn (NavigationItem $item): int => $item->getSort())
            ->values();

        foreach (static::withParents($items->all()) as $item) {
            $group = $item->getGroup();

            if ($group === null || $group === '') {
                $ungrouped[] = $item;

                continue;
            }

            $key = $group instanceof UnitEnum ? $group->name : $group;
            $groups[$key] ??= [
                'label' => $group instanceof HasLabel ? ($group->getLabel() ?? $key) : $key,
                'icon' => $group instanceof HasIcon ? $group->getIcon() : null,
                'items' => [],
            ];
            $groups[$key]['items'][] = $item;
        }

        $result = array_map(fn (NavigationItem $item): NavItem => static::convert($item), $ungrouped);

        foreach (static::orderGroups($groups) as $group) {
            $result[] = NavItem::make($group['label'])
                ->icon($group['icon'] ?? static::registeredGroupIcon($group['label']))
                ->children(array_map(fn (NavigationItem $item): NavItem => static::convert($item), $group['items']));
        }

        return $result;
    }

    /**
     * Attach items that declare `$navigationParentItem` to their parent (same group).
     *
     * @param  array<int, NavigationItem>  $items
     * @return list<NavigationItem>
     */
    protected static function withParents(array $items): array
    {
        $top = [];
        $children = [];

        foreach ($items as $item) {
            $parent = $item->getParentItem();

            if ($parent === null || $parent === '') {
                $top[] = $item;
            } else {
                $children[$parent][] = $item;
            }
        }

        foreach ($top as $item) {
            $label = $item->getLabel();
            $match = array_unique([$item->getKey(), $label instanceof Htmlable ? $label->toHtml() : $label]);
            $extra = [];

            foreach ($match as $name) {
                $extra = [...$extra, ...($children[$name] ?? [])];
            }

            if ($extra !== []) {
                $item->childItems([...static::childItemsOf($item), ...$extra]);
            }
        }

        return $top;
    }

    /** @return list<NavigationItem> */
    protected static function childItemsOf(NavigationItem $item): array
    {
        $children = $item->getChildItems();
        $children = is_array($children) ? $children : $children->toArray();
        $result = [];

        foreach ($children as $child) {
            if ($child instanceof NavigationItem) {
                $result[] = $child;
            }
        }

        return $result;
    }

    protected static function convert(NavigationItem $item): NavItem
    {
        $nav = NavItem::make($item->getLabel())
            ->icon($item->getIcon())
            ->url($item->getUrl())
            ->badge(fn (): ?string => $item->getBadge(), fn (): string|array|null => $item->getBadgeColor());

        $children = static::childItemsOf($item);

        if ($children !== []) {
            $nav->children(array_map(fn (NavigationItem $child): NavItem => static::convert($child), $children));
        }

        return $nav;
    }

    /**
     * Honour the order in which groups were registered on the panel; unregistered groups follow.
     *
     * @param  array<string, array{label: string|Htmlable, icon: string|BackedEnum|Htmlable|null, items: list<NavigationItem>}>  $groups
     * @return list<array{label: string|Htmlable, icon: string|BackedEnum|Htmlable|null, items: list<NavigationItem>}>
     */
    protected static function orderGroups(array $groups): array
    {
        $registered = [];

        foreach (static::registeredGroups() as $index => $group) {
            $registered[] = $group instanceof NavigationGroup ? static::text($group->getLabel()) : $group;
            $registered[] = (string) $index;
        }

        uksort($groups, function (string $a, string $b) use ($registered): int {
            $positionA = array_search($a, $registered, true);
            $positionB = array_search($b, $registered, true);

            return ($positionA === false ? PHP_INT_MAX : $positionA) <=> ($positionB === false ? PHP_INT_MAX : $positionB);
        });

        return array_values($groups);
    }

    /** @return array<int|string, NavigationGroup|string> */
    protected static function registeredGroups(): array
    {
        return Filament::getCurrentOrDefaultPanel()?->getNavigationGroups() ?? [];
    }

    protected static function text(string|Htmlable|null $value): string
    {
        return $value instanceof Htmlable ? trim(strip_tags($value->toHtml())) : (string) $value;
    }

    protected static function registeredGroupIcon(string|Htmlable $label): string|BackedEnum|Htmlable|null
    {
        foreach (static::registeredGroups() as $group) {
            if ($group instanceof NavigationGroup && static::text($group->getLabel()) === static::text($label)) {
                return $group->getIcon();
            }
        }

        return null;
    }
}
