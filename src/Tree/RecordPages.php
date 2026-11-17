<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Tree;

use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

/**
 * Builds a record menu from a resource's own record pages (View, Edit, Manage… related records,
 * custom pages on `/{record}/…`), the same way Filament builds a record's sub-navigation: each
 * page's `shouldRegisterNavigation()`, `canAccess()` and `getNavigationItems()` for that record.
 *
 * @internal Not part of the public API: it may change in any release. See docs/api-stability.md.
 */
final class RecordPages
{
    /**
     * @param  class-string<resource>  $resource
     * @param  array<int, class-string<ResourcePage>>|null  $pages  null = every page whose only route parameter is the record
     * @return array<int, NavItem>
     */
    public static function items(string $resource, Model $record, ?array $pages = null): array
    {
        $parameters = ['record' => $record];
        $items = [];

        foreach ($pages ?? self::recordPageClasses($resource) as $page) {
            if (! $page::shouldRegisterNavigation($parameters) || ! $page::canAccess($parameters)) {
                continue;
            }

            foreach ($page::getNavigationItems($parameters) as $navigationItem) {
                $items[] = NativeNavigation::toNavItem($navigationItem);
            }
        }

        return $items;
    }

    /**
     * Pages registered on the resource whose route needs the record and nothing else
     * (a page like `/{record}/{section}` needs more than a record, so it is left out).
     *
     * @param  class-string<resource>  $resource
     * @return array<int, class-string<ResourcePage>>
     */
    public static function recordPageClasses(string $resource): array
    {
        $routes = Route::getRoutes();
        $base = $resource::getRouteBaseName();
        $classes = [];

        foreach ($resource::getPages() as $name => $registration) {
            $route = $routes->getByName($base.'.'.$name);

            if ($route === null) {
                continue;
            }

            $parameters = array_values(array_filter($route->parameterNames(), fn (mixed $name): bool => $name !== 'tenant'));

            if ($parameters === ['record']) {
                $classes[] = $registration->getPage();
            }
        }

        /** @var array<int, class-string<ResourcePage>> */
        return array_values(array_unique($classes));
    }
}
