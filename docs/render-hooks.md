# Render hooks

Add your own content around the menu without publishing views.

The sidebar exposes [Filament render hooks](https://filamentphp.com/docs/5.x/advanced/render-hooks), so you can add a help link, a workspace switcher or a banner without publishing or overriding the plugin's views:

```php
use SolutionForest\DrilldownNav\DrilldownNavHook;

$panel
    ->renderHook(DrilldownNavHook::NAV_END, fn () => view('nav-help-link'))
    ->renderHook(DrilldownNavHook::HEADER_AFTER, fn (array $data) => view('level-note', ['title' => $data['title']]));
// or anywhere: FilamentView::registerRenderHook(DrilldownNavHook::NAV_END, fn () => …, scopes: 'admin');
```

| Hook | Where |
|---|---|
| `NAV_START` / `NAV_END` | first / last thing inside the navigation |
| `HEADER_BEFORE` / `HEADER_AFTER` | around the Back link and title (only on levels that have one) |
| `LIST_BEFORE` / `LIST_AFTER` | around the list of items |

Every hook is scoped to the id of the panel being rendered, so a hook registered for `admin` never shows in another panel. A closure may declare `array $data`, which holds `plugin`, `panel`, `depth` (0 = top level) and `title`.

Hook output is wrapped in `<div class="fi-drilldown-hook" data-hook="…">`. In a panel with `->sidebarCollapsibleOnDesktop()` it shows only while the sidebar is open, so free text never widens the icon rail.
