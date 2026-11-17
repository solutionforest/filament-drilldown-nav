# Upgrading

The package is **0.x**: a minor version (0.x → 0.y) may change the public API, always listed under *Changed* or *Removed* in the [changelog](../CHANGELOG.md). Patch versions never do. After 1.0 the usual semantic-versioning rules apply. The full history is in the [changelog](../CHANGELOG.md); this page lists only what you have to **do**.

After every update run `php artisan filament:assets` (or `php artisan filament:upgrade`), so the stylesheet matches the version.

## To 0.6

- **Nothing breaking.** The "Pinned" list moved to the top of the top-level menu (it was below it).
- Open/closed state of `->inline()` sections moved from `localStorage` to a `fi-drilldown-open` cookie that the server reads, so there is no flash on load. Sections go back to their default state once.
- The read accessors on `NavItem` and `DrilldownNavPlugin` are tagged `@internal`: use the fluent setters, not `get…()`.
- New and optional: `->recordPages()`, the `HasDrilldownRecordMenu` interface, `->pollBadges()`, `->accordion()`, `searchable(allLevels: true)`, `pinnable(database: true)` (publish and run its migration), `DrilldownNavPlugin::flatten()`.

## To 0.5

- **Minimum Filament is `^5.7`** (it was `^5.10` in 0.4, which was stricter than needed).
- New: the six `DrilldownNavHook` render hooks; Hebrew, Persian and Urdu strings.

## To 0.4

- **Run `php artisan filament:assets`.** The stylesheet is now a Filament asset instead of an inline `<style>` block.
- Filament's own navigation is no longer emptied for the whole panel: it stays intact for `->topNavigation()`, breadcrumbs and other plugins, and is hidden only inside the plugin's sidebar. If your panel sets `->sidebarLivewireComponent(...)`, add the `HidesNativeNavigation` trait to it.
- New: `->activeWhen()`, `->activeOn()`, `->header()` / `->recordHeader()`, a first-letter fallback for icon-less items in the collapsed sidebar.

## To 0.3

- New and optional: `->searchable()`, `->recent()`, `->pinnable()`, `NavItem::limit()`.

## To 0.2

- New: expand in place (`->inline()`), headings and dividers, the collapsed sidebar, Octane-safe per-request state, translations, tenant and domain support. A panel without the plugin is no longer affected by a panel with it.

## To 0.1 (first release)

Nothing to do.
