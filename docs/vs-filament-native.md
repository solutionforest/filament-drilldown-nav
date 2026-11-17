# This plugin or Filament's own navigation?

Filament already ships good navigation. Use this plugin only if you need something it doesn't do. This page is the quick decision guide.

## What Filament gives you

| Feature | What it does |
|---|---|
| Navigation groups | collapsible headings in the one sidebar |
| Parent items (`$navigationParentItem`) | one nested level under an item, all visible at once |
| Clusters | group related resources/pages; inside a cluster Filament shows a *sub-navigation* beside the content |
| Page sub-navigation | a secondary menu per page |
| Top navigation | the menu in the top bar instead of the sidebar |

## What this plugin adds

| Need | Plugin |
|---|---|
| 3–4 menu levels in the **left sidebar** | one level at a time with a Back link (`drilldown`), or expand in place (`inline`) |
| A **menu per record** ("click a project, get that project's menu") | `NavItem::resource(…)->record(fn ($record) => […])`, at any depth, including nested resources |
| One menu location instead of sidebar **plus** a second menu beside the content | everything stays in the sidebar |
| Search, recent and pinned pages, long-list shortening | `->searchable()`, `->recent()`, `->pinnable()`, `->limit()` |
| Keep your existing `$navigationGroup`, badges, `canAccess()` | `NavItem::fromFilamentNavigation()` |

## Choose

| If… | Use |
|---|---|
| Two levels are enough | **Filament's native navigation** (groups, parent items): simpler, nothing to maintain |
| One extra level per area and the second menu beside the content is fine | **Filament clusters** (they also work next to this plugin) |
| Records have their own areas with their own sub-menus | **this plugin** |
| You want a deep or per-record menu in the sidebar | **this plugin** |

## Costs to know about

- The plugin **owns the sidebar list**: in the sidebar it replaces Filament's list, so items you don't list (or import with `fromFilamentNavigation()`) are not shown there. Filament's navigation itself is untouched (top navigation, breadcrumbs, other plugins).
- Menu levels come from the **URL**, so a record's menu needs its pages under the resource's own URL (`/admin/projects/{record}/…`).
- It is a **0.x** package: see [API stability](api-stability.md).

## Clusters next to the plugin

Tested: a cluster shows up in the menu through `fromFilamentNavigation()`, its pages render Filament's own sub-navigation beside the content (the plugin leaves it alone), and the cluster's menu item stays current while you are on its pages. So the two can be mixed on one panel; the cluster's sub-navigation is still a second menu beside the content, which is exactly what the drill-down sidebar is meant to avoid for deep or per-record menus.
