---
name: filament-drilldown-nav
description: Add drill-down sidebar navigation to a Filament v5 panel — one level at a time, Back link, any depth, per-record menus (click an project/site/customer and get that record's own menu), reusing existing Resources and Pages. Use when a Filament panel needs 3+ navigation levels, a menu per record, or a single left sidebar instead of a second sub-navigation.
---

# Skill: filament-drilldown-nav

Package: `solutionforest/filament-drilldown-nav` (Filament **v5**, PHP 8.3+). For repo maintenance see [`AGENTS.md`](AGENTS.md); for diagrams see [`docs/how-it-works.md`](docs/how-it-works.md).

## When to use it

- A record has its own area ("click an project → Tasks, Files, Settings") and you want that menu **in the left sidebar**.
- Menus nest 3–4 levels deep.
- You do **not** need it for two levels: native navigation groups, parent items and Clusters are enough.

## Install and register

```bash
composer config repositories.drilldown vcs https://github.com/solutionforest/filament-drilldown-nav
composer require solutionforest/filament-drilldown-nav:dev-main
```

```php
use SolutionForest\DrilldownNav\DrilldownNavPlugin;
use SolutionForest\DrilldownNav\Tree\NavItem;

$panel->plugin(DrilldownNavPlugin::make()->items(fn () => [
    NavItem::page(Dashboard::class),
    NavItem::resource(ProjectResource::class)->record(fn (Project $project) => [ /* record menu */ ]),
    ...NavItem::fromFilamentNavigation(),   // everything else Filament would show
]));
```

The plugin empties Filament's own list: **only items returned from `items()` are shown.**

## Recipes

**Page / Resource (label, icon, URL, access come from the class)**
```php
NavItem::page(Dashboard::class);
NavItem::resource(SiteResource::class);
```

**Any URL (custom page, React/Inertia page)**
```php
NavItem::make('Reports')->icon('heroicon-o-chart-bar')->url('/admin/reports')->badge('3', 'danger');
```

**Nested levels** — an item with children opens its own level. No URL = pure group (links to its first child).
```php
NavItem::make('Docs')->url('/admin/docs')->children([
    NavItem::make('Guides')->url('/admin/docs/guides')->children([
        NavItem::make('Install')->url('/admin/docs/guides/install'),
    ]),
]);
```

**Menu per record** — the URL segment after the resource URL is the record key.
```php
NavItem::resource(ProjectResource::class)->record(fn (Project $project) => [
    NavItem::make('Overview')->url(ProjectResource::getUrl('view', ['record' => $project])),
    NavItem::make('Files')->children([
        NavItem::resource(DocumentResource::class, ['project' => $project]), // nested resource
        NavItem::make('Links')->url(...),
    ]),
]);
```
For a non-resource item use `->resolveRecordUsing(fn (string $key) => ...)` and `->recordTitleUsing(fn ($record) => ...)`. Fixed pages under the list URL (like `import`) are excluded with `->ignoreRecordKeys(['create', 'import'])` (done automatically for `resource()`).

**Reuse Filament's own navigation** (`$navigationGroup`, `$navigationParentItem`, `$navigationSort`, badges, `canAccess()`):
```php
...NavItem::fromFilamentNavigation(fn (NavigationItem $item): bool => $item->getUrl() !== Dashboard::getUrl())
```

## Modern navigation (click to expand, headings, collapsed mode)

```php
NavItem::heading('Workspace')->divider();                      // section label + line above
NavItem::make('Team')->icon('heroicon-o-users')->inline()->defaultExpanded()->children([...]); // expand in place
DrilldownNavPlugin::make()->childDisplay(ChildDisplay::Inline)->items(...);  // inline by default for the panel
```

- `->inline()` / `->drilldown()` per item; inline sections can nest; the section containing the current page is open; state is remembered in the browser.
- Collapsed icon-only mode comes with the panel's `->sidebarCollapsibleOnDesktop()`: **give top-level items icons**.
- Keep headings/dividers out of URL logic: they have no URL by design.

## Multi-panel, Octane, tenants, languages

- Register the plugin on as many panels as you like, each with its own `items()`. Panels without it are untouched.
- Octane-safe: pass a **closure** to `items()`; it runs once per request. Don't keep request data (the current user, a tenant, a record) in `NavItem`s built outside a closure.
- Tenancy and domains work because Home/Back use `Panel::getUrl($tenant)`. Build your own item URLs with Filament (`Page::getUrl()`, `Resource::getUrl()`), which are tenant-aware, instead of hard-coding `/admin/...`.
- Shipped languages for the plugin's own text: en, zh_CN, zh_TW, zh_HK, ja, ko, es, ar (right-to-left mirrors automatically).
- Verified on real Octane workers: RoadRunner, Swoole and FrankenPHP. Translate your item labels yourself (`__()` in the closure).

## Rules of thumb

- **Record menu URLs must live under the resource's URL** (`/admin/projects/{record}/...`). Register extra pages on the resource (`'section' => ProjectSection::route('/{record}/{section}')`, after `edit`) and link to them with `Resource::getUrl()`.
- The sidebar level is computed from the **request URL** on every request: no JS state, so refresh and deep links work.
- Keep closures cheap: they run once per request, only for items on the current path.
- Hide items with `->visible(fn () => ...)` or the page/resource's own `canAccess()`; empty groups disappear.

## Verify

1. Open a record URL: the sidebar shows the record title, its menu and a Back link to the list.
2. Open `create` and the list: you are back on the top level.
3. Open a sub menu item: the sub level shows with the right item highlighted.
4. In tests, assert on the rendered sidebar text (strip tags from the `.fi-drilldown-nav` element), as in `demo/tests/Feature/NativeNavigationTest.php`.
