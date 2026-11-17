# How it works

This page explains **what problem the plugin solves**, **how a request turns into a sidebar**, and **how it reuses Filament's own navigation**. For usage, see the [README](../README.md).

## 1. The problem

Filament's sidebar shows the whole tree at once, and sub-navigation for one cluster or page sits *next to the content* as a second menu. That works for two levels. It breaks down when:

- an area has its own menu ("click an project → Tasks, Files, Settings");
- those items have sub menus (Files → Documents, Links), and you want 3–4 levels;
- you want the menu to stay in **one place** (the left sidebar), not split across two.

```mermaid
flowchart LR
    subgraph native["Filament defaults"]
        direction TB
        N1["Sidebar: everything nested in place<br/>(groups, one parent level)"]
        N2["Cluster / page sub-navigation:<br/>a SECOND menu beside the content"]
        N1 --- N2
    end
    subgraph plugin["With this plugin"]
        direction TB
        P1["ONE left sidebar"]
        P2["Shows ONE level at a time"]
        P3["Click in = next level + Back<br/>(any depth, per record)"]
        P1 --> P2 --> P3
    end
    native -->|"3-4 levels? per-record menus?"| plugin
```

| You want | Filament alone | This plugin |
|---|---|---|
| Menu for one record (project, site, ...) | no (you build a custom sub-navigation per page) | `->record(fn ($record) => [...])` |
| 3–4 levels in the left sidebar | no (groups + one parent level) | yes, any depth |
| Keep existing Resources/Pages and their `$navigationGroup` etc. | n/a | `NavItem::fromFilamentNavigation()` |
| One menu location | no (sidebar + sub-navigation) | yes |

## 2. From request to sidebar

The plugin never stores "where the user is" in JavaScript. On **every request** the server works it out from the URL, so refreshes, deep links and shared links always land on the right level.

```mermaid
flowchart TD
    A["Request: /admin/projects/7/documents"] --> B["Plugin items() closure<br/>(evaluated once per request)"]
    B --> C["ActivePathResolver::resolve()<br/>longest URL prefix wins"]
    C --> D{"Item has a record menu<br/>and the URL has a record key?"}
    D -- "no" --> F
    D -- "yes: key is not 'create'/ignored" --> E["Look up the record once<br/>(Resource route binding)<br/>build a record node:<br/>URL = list URL + key, children = your menu"]
    E --> F["Match continues inside the children<br/>(sub menus nest as deep as needed)"]
    F --> G["Path: Projects > Website redesign > Files > Documents"]
    G --> H["level(): children of the deepest item<br/>on the path that HAS children"]
    H --> I["Sidebar: Back + title 'Files'<br/>items: Documents, Links"]
```

### Choosing the level

```mermaid
flowchart TD
    S["Resolved path<br/>root ... deepest match"] --> Q{"Walk from the deepest<br/>item upwards"}
    Q --> R{"Has visible children?"}
    R -- "yes" --> L["Render ITS children<br/>title = its label<br/>Back = the link URL of its parent"]
    R -- "no" --> U["Go one level up"]
    U --> Q
    Q -- "reached the top" --> T["Render the top-level items<br/>(no Back link)"]
```

Rules worth knowing:

- **Longest prefix wins** (on a tie the deeper item wins). Only the *path* part of a URL is compared, so absolute URLs from `Resource::getUrl()` work.
- **The panel root URL and same-URL ancestors match exactly only** — otherwise Dashboard (`/admin`) would light up on every page, and a record's "Overview" (same URL as the record node) would always be active.
- **A URL-less item is a group.** Clicking it goes to its first reachable child.
- **Unknown URLs** show the top level with nothing highlighted.
- **Closures run once per request**, and only for items on the current path (children of other items are never opened).

## 3. A menu per record

`NavItem::resource(ProjectResource::class)->record(fn (Project $project) => [...])` makes the record menu one more level of the same tree:

```mermaid
sequenceDiagram
    participant U as Browser
    participant R as ActivePathResolver
    participant I as NavItem (Projects)
    participant F as Filament Resource
    U->>R: GET /admin/projects/7/documents
    R->>I: getRecordNode("/admin/projects/7/documents")
    I->>I: key = "7" (skip 'create' and fixed pages like 'import')
    I->>F: resolveRecordRouteBinding("7")
    F-->>I: Project #7 (or null: not a record)
    I->>I: node(title = record title, url = /admin/projects/7,<br/>children = menu(project))
    I-->>R: record node (cached for this request)
    R->>R: keep matching inside the node's children
    R-->>U: sidebar level for "Files"
```

A failing lookup (for example a non-numeric key on a PostgreSQL integer column) is treated as "no such record", so such URLs fall back to the top level instead of erroring.

The record node takes its header from `recordHeader()`, called with the record when that level is rendered; without one, the level shows Back and the record title. Separately, `activeOn()` gives any item extra URLs: the resolver tries the item's own URL and these the same way (prefix, or exact only for the panel root and ancestor URLs), and the longest match counts. So a list at `/agents/5/sources` can stay current on `/agents/5/knowledge/9/edit`.

## 4. Reusing Filament's navigation

Everything you already declare the Filament way keeps working: `$navigationGroup`, `$navigationParentItem`, `$navigationSort`, `$navigationBadge`, `shouldRegisterNavigation()`, `canAccess()`, `NavigationItem::make()`.

```mermaid
flowchart LR
    A["Resources and Pages<br/>navigationGroup, parentItem,<br/>sort, badge, canAccess()"] --> B["Filament NavigationManager<br/>(scoped per request)"]
    B -->|"captured while Filament builds<br/>its sidebar"| C["NativeNavigation::items()"]
    C --> D["Ungrouped item -> NavItem<br/>Group -> URL-less NavItem group<br/>Parent item -> children"]
    D --> E["NavItem::fromFilamentNavigation()"]
    F["Hand-written NavItems<br/>(per record menus, custom URLs)"] --> G["Plugin items()"]
    E --> G
    G --> H["Drill-down sidebar"]
```

Why it is done this way: Resources and Pages only register their navigation items while Filament is *building* its sidebar. The plugin's navigation builder therefore captures the items at that moment (into a fresh `NavigationManager`, so Filament's own one is never mounted twice). Use the optional `$filter` to drop items you list by hand.

## 5. Two ways to show children

```mermaid
flowchart TD
    I["Item with children"] --> Q{"Inline?<br/>(item flag, else plugin default)"}
    Q -- "no: drilldown (default)" --> D["Opens its own level<br/>Back + title, children replace the list"]
    Q -- "yes: ->inline()" --> E["Children expand under the item<br/>guide line, chevron, remembered state"]
    D --> L["level(): deepest item on the path<br/>that OPENS a level"]
    E --> L2["Stays in its parent's level<br/>(open if the current page is inside)"]
```

- **Which level is shown** is decided by `opensLevel()`: an item with children that is *not* inline. Inline items never create a level, so a page inside an inline section keeps the sidebar on the level that contains the section, with the section open and the current page marked.
- **Open or closed:** open when the current page is inside it, otherwise the stored choice (the `fi-drilldown-open` cookie, read on the server so the first paint is right), otherwise `defaultExpanded()`. With `accordion()`, only sections on the current path start open, and opening one closes its siblings.
- **Collapsed sidebar** (`sidebarCollapsibleOnDesktop()`): text, titles, headings and badges are bound to Filament's `$store.sidebar.isOpen`, so only icons remain; items reuse Filament's own tooltips; clicking an expandable row opens the sidebar instead of toggling hidden children.
- **Headings and dividers** are items without URL or children, so they never take part in URL matching.

## 6. Several panels, long-lived workers, tenants

```mermaid
flowchart TD
    subgraph worker["One long-lived worker (Octane)"]
        P1["Panel admin + plugin<br/>(built once at boot)"]
        P2["Panel portal + plugin<br/>(built once at boot)"]
        P3["Panel plain (no plugin)"]
    end
    R1["Request 1: /admin"] --> S1["RequestState #1<br/>trees, children, records"]
    R2["Request 2: /portal"] --> S2["RequestState #2"]
    S1 -. "discarded after the request<br/>(scoped container binding)" .-> X(("gone"))
    P1 -- "panel-scoped render hook" --> S1
    P2 -- "panel-scoped render hook" --> S2
    P3 -- "no hook: Filament's own sidebar" --> R2
```

- **Per request, not per worker.** The evaluated item tree, children closures, record lookups and the native-navigation capture live in `RequestState`, a *scoped* container binding: Laravel (and Octane) drop it after every request. The plugin object, panel and any `NavItem` you built outside a closure stay alive for the whole worker, so nothing request-specific is stored on them. This is why one request can never see another's record titles or children.
- **Panel-scoped hook.** The sidebar is rendered by a hook registered on the panel itself (not globally) and only when that panel is the one being shown. Two panels can use the plugin with different trees, and a panel without the plugin keeps Filament's own sidebar.
- **Tenants and domains.** Home, Back-to-root and the "exact match" rule use the URL Filament itself builds for the panel (`Panel::getUrl($tenant)`): domain, path and tenant included. The request path includes the application's base path, so sub-directory installs match too.
- **Languages.** The two strings the plugin owns ("Back" and the landmark label) are translated in English, Simplified/Traditional Chinese (`zh_CN`, `zh_TW`, `zh_HK`), Japanese, Korean, Spanish and the right-to-left languages Arabic, Hebrew, Persian and Urdu, and can be overridden by publishing the translations. Your own labels are yours to translate.

## 7. What is in the box

```mermaid
classDiagram
    class DrilldownNavPlugin {
        +items(array|Closure) static
        +getTree() NavItem[]
        register(Panel)
        boot(Panel)
    }
    class NavItem {
        +make(label) NavItem
        +page(class) NavItem
        +resource(class, params) NavItem
        +fromFilamentNavigation(filter) NavItem[]
        +url() icon() badge() visible()
        +children(array|Closure)
        +record(Closure)
        +header() recordHeader() activeOn()
        +getLinkUrl() string
    }
    class ActivePathResolver {
        +resolve(tree, path, root) path
        +level(tree, resolved, home) level
    }
    class NativeNavigation {
        +items(filter) NavItem[]
        +capture()
    }
    DrilldownNavPlugin --> NavItem : tree
    DrilldownNavPlugin --> ActivePathResolver : sidebar view
    NavItem --> NativeNavigation : fromFilamentNavigation
    NavItem --> NavItem : children / record menu
```

| File | Responsibility |
|---|---|
| `src/DrilldownNavPlugin.php` | Registers the plugin: swaps in `DrilldownSidebar`, hides Filament's default list in the sidebar only (navigation stays intact elsewhere) and renders ours at `SIDEBAR_NAV_START`. |
| `src/Livewire/DrilldownSidebar.php`, `Concerns/HidesNativeNavigation.php` | Filament's sidebar component, with its native list hidden while it renders. |
| `src/Tree/NavItem.php` | The menu item: label, icon, URL, extra active URLs, badge, visibility, children (drilldown or inline), headings, dividers, per-record menu, custom level header. |
| `src/ChildDisplay.php` | Enum: `Drilldown` or `Inline` default for a panel. |
| `src/Tree/ActivePathResolver.php` | Pure logic: request path → active path → level to render. No Filament or HTTP dependencies, so it is easy to test. |
| `src/RequestState.php` | Per-request memo (scoped binding): item tree, children, record nodes, native capture. |
| `src/Tree/NativeNavigation.php` | Adapter from Filament's navigation to `NavItem`s. |
| `resources/views/sidebar.blade.php` | Renders Back and title (or a custom header), and items (reusing `x-filament-panels::sidebar.item`), plus the slide animation. |

## 8. Limits

- Items without an icon show nothing in the collapsed (icon-only) sidebar: give top-level items an icon.
- Right-to-left: supported and checked in a browser with Arabic (mirrored chevrons, Back arrow and animation); Hebrew, Persian and Urdu use the same rules and ship the plugin's strings (machine-written; native-speaker review welcome).
- The plugin owns the sidebar list: items you do not list (or import with `fromFilamentNavigation()`) are not shown.
- Right-to-left languages: strings and `dir` are fine, but the slide animation and chevrons are not mirrored yet.
- The level is derived from the URL. A record menu therefore needs its URLs to live under the resource's own URL (`/admin/projects/{record}/...`).
