# FAQ and troubleshooting

## My sidebar is missing items

In the sidebar the plugin replaces Filament's own list, so **only the items returned from `items()` are shown**. Add `...NavItem::fromFilamentNavigation()` to import everything Filament would have shown (groups, parent items, badges, sorting, access checks), and `NavItem::resource()` / `NavItem::page()` for the ones you list by hand.

## The menu looks unstyled

Publish the plugin's stylesheet: `php artisan filament:assets` (`php artisan filament:upgrade` runs it too, and so does a `post-autoload-dump` script that calls `filament:upgrade`). The CSS is a normal Filament asset, so it works with a strict Content-Security-Policy and a theme can override it.

## A record's menu does not appear

The level comes from the **URL**: the segment after the resource's URL is treated as the record key. So the record's pages must live under the resource's own URL (`/admin/projects/{record}/…`), and the key must resolve to a record (`create` and unknown keys are ignored). Register extra pages on the resource, after `edit`:

```php
'edit' => EditProject::route('/{record}/edit'),
'files' => ManageProjectFiles::route('/{record}/files'),
```

If a page cannot live under that URL, keep its item current with `->activeOn([...])` (more URLs) or `->activeWhen(fn () => ...)` (any condition). See [usage](usage.md).

## The wrong item is highlighted, or none

The item whose URL is the **longest prefix** of the request path wins; the panel's root URL matches exactly only. Check the URLs you give `->url()` (relative or absolute, only the path counts), and use `activeOn()` / `activeWhen()` for pages the URL cannot place.

## Does it work with `->topNavigation()`?

Filament's own top navigation is left untouched and keeps showing the native menu. The plugin drives the **sidebar** (and the mobile drawer); it does not turn the top bar into a drill-down.

## My panel sets its own sidebar component

The plugin swaps in `DrilldownSidebar` (a subclass of Filament's `Sidebar`) unless your panel already calls `->sidebarLivewireComponent(...)`. In that case add the `SolutionForest\DrilldownNav\Livewire\Concerns\HidesNativeNavigation` trait to your component, or Filament's list will show under the drill-down menu.

## Is it safe on Octane or other long-lived workers?

Yes, if you follow one rule: **pass a closure to `items()`**. It runs once per request, so the menu can depend on the signed-in user, the tenant or the database. Anything request-specific kept in a `NavItem` built outside a closure lives as long as the worker. The package keeps its own state per request and is tested on real RoadRunner, Swoole and FrankenPHP servers, including under parallel load. See [panels, workers, tenants and languages](multi-panel-octane-i18n.md).

## Search only finds what is on screen

By default, yes. Use `->searchable(allLevels: true)` to also list matching pages from levels that are not open (loaded on first use), and press `/` to focus the box.

## Pinned pages disappear when I use another browser

Pins are stored in the browser by default. Use `->pinnable(database: true)` to store them per user, panel and tenant (publish and run the migration once). See [large menus](large-menus.md).

## How do I change the texts ("Back", "Search menu…")?

Publish the translations and edit them: `php artisan vendor:publish --tag=filament-drilldown-nav-translations`. Eleven languages ship with the package, including four right-to-left ones. Labels of your own items are yours to translate.

## Can I use it with other navigation plugins?

Plugins that **replace Filament's sidebar list** (such as Pinnable Navigation or another drill-down sidebar) cannot run next to this one, since this plugin replaces that list itself. Plugins that add something *around* the sidebar were checked: [compatibility](compatibility.md).

## Does it support Filament v3 or v4?

No. **Filament v5 only**: see the [support matrix](support.md).

## Where do I report a bug?

[GitHub issues](https://github.com/solutionforest/filament-drilldown-nav/issues). For a security problem, follow [SECURITY.md](../SECURITY.md) instead.
