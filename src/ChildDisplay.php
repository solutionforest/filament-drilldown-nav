<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav;

/** How an item with children shows them. */
enum ChildDisplay: string
{
    /** Click opens a new level in the sidebar, with a Back link (the plugin's signature behaviour). */
    case Drilldown = 'drilldown';

    /** Children expand in place under the item, with a guide line (the classic tree/accordion). */
    case Inline = 'inline';
}
