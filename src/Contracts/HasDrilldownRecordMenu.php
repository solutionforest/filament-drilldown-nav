<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav\Contracts;

use Illuminate\Database\Eloquent\Model;
use SolutionForest\DrilldownNav\Tree\NavItem;

/**
 * Implement on a Filament Resource to declare the menu shown inside one of its records, next to
 * the resource itself. `NavItem::resource(YourResource::class)` then uses it with no extra config
 * (an explicit `->record()` or `->recordPages()` on the item still wins).
 */
interface HasDrilldownRecordMenu
{
    /** @return array<int, NavItem> */
    public static function drilldownRecordMenu(Model $record): array;
}
