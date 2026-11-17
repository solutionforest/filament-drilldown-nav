<?php

declare(strict_types=1);

namespace SolutionForest\DrilldownNav;

/**
 * Filament render hooks the drill-down sidebar exposes, so an app can add its own content (a help
 * link, a workspace switcher, a banner …) without publishing or overriding the plugin's views.
 *
 * Register them the Filament way (https://filamentphp.com/docs/5.x/advanced/render-hooks):
 *
 * ```php
 * $panel->renderHook(DrilldownNavHook::NAV_END, fn () => view('nav-help-link'));
 * // or: FilamentView::registerRenderHook(DrilldownNavHook::NAV_END, fn () => ..., scopes: 'admin');
 * ```
 *
 * Every hook is scoped to the id of the panel being rendered, so a hook registered with
 * `scopes: 'admin'` only shows in the `admin` panel. The closure may declare `array $data`, which holds:
 * `plugin` (the DrilldownNavPlugin), `depth` (0 = top level), `title` (the level's title or null) and
 * `panel` (the panel id).
 */
final class DrilldownNavHook
{
    /** First thing inside the navigation (above the search box). */
    public const NAV_START = 'filament-drilldown-nav::nav.start';

    /** Last thing inside the navigation (below the list, search note and shortcuts). */
    public const NAV_END = 'filament-drilldown-nav::nav.end';

    /** Before the Back link and title of a level (only on levels that have them). */
    public const HEADER_BEFORE = 'filament-drilldown-nav::header.before';

    /** After the Back link and title of a level. */
    public const HEADER_AFTER = 'filament-drilldown-nav::header.after';

    /** Between the header/search and the list of items. */
    public const LIST_BEFORE = 'filament-drilldown-nav::list.before';

    /** Right after the list of items. */
    public const LIST_AFTER = 'filament-drilldown-nav::list.after';
}
