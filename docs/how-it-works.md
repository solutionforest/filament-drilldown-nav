# How it works

This page explains **what problem the plugin solves**, **how a request turns into a sidebar**, and **how it reuses Filament's own navigation**. For usage, see the [README](../README.md).

## 1. The problem

Filament's sidebar shows the whole tree at once, and sub-navigation for one cluster or page sits *next to the content* as a second menu. That works for two levels. It breaks down when:

- an area has its own menu ("click an agent → Playground, Knowledge, Settings");
- those items have sub menus (Knowledge → Documents, Websites), and you want 3–4 levels;
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
| Menu for one record (agent, site, ...) | no (you build a custom sub-navigation per page) | `->record(fn ($record) => [...])` |
| 3–4 levels in the left sidebar | no (groups + one parent level) | yes, any depth |
| Keep existing Resources/Pages and their `$navigationGroup` etc. | n/a | `NavItem::fromFilamentNavigation()` |
| One menu location | no (sidebar + sub-navigation) | yes |

## 2. From request to sidebar

The plugin never stores "where the user is" in JavaScript. On **every request** the server works it out from the URL, so refreshes, deep links and shared links always land on the right level.

```mermaid
flowchart TD
    A["Request: /admin/agents/7/documents"] --> B["Plugin items() closure<br/>(evaluated once per request)"]
    B --> C["ActivePathResolver::resolve()<br/>longest URL prefix wins"]
    C --> D{"Item has a record menu<br/>and the URL has a record key?"}
    D -- "no" --> F
    D -- "yes: key is not 'create'/ignored" --> E["Look up the record once<br/>(Resource route binding)<br/>build a record node:<br/>URL = list URL + key, children = your menu"]
    E --> F["Match continues inside the children<br/>(sub menus nest as deep as needed)"]
    F --> G["Path: Agents > Support bot > Knowledge > Documents"]
    G --> H["level(): children of the deepest item<br/>on the path that HAS children"]
    H --> I["Sidebar: Back + title 'Knowledge'<br/>items: Documents, Websites"]
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

`NavItem::resource(AgentResource::class)->record(fn (Agent $agent) => [...])` makes the record menu one more level of the same tree:

```mermaid
sequenceDiagram
    participant U as Browser
    participant R as ActivePathResolver
    participant I as NavItem (Agents)
    participant F as Filament Resource
    U->>R: GET /admin/agents/7/documents
    R->>I: getRecordNode("/admin/agents/7/documents")
    I->>I: key = "7" (skip 'create' and fixed pages like 'import')
    I->>F: resolveRecordRouteBinding("7")
    F-->>I: Agent #7 (or null: not a record)
    I->>I: node(title = record title, url = /admin/agents/7,<br/>children = menu(agent))
    I-->>R: record node (cached for this request)
    R->>R: keep matching inside the node's children
    R-->>U: sidebar level for "Knowledge"
```

A failing lookup (for example a non-numeric key on a PostgreSQL integer column) is treated as "no such record", so such URLs fall back to the top level instead of erroring.

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

Why it is done this way: Resources and Pages only register their navigation items while Filament is *building* its sidebar. The plugin's navigation builder (which keeps the sidebar shell alive while emptying the default list) therefore captures the items at that moment, using Filament's public `NavigationManager` API. Use the optional `$filter` to drop items you list by hand.

## 5. What is in the box

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
| `src/DrilldownNavPlugin.php` | Registers the plugin: empties Filament's default list (keeps the sidebar shell) and renders ours at `SIDEBAR_NAV_START`. |
| `src/Tree/NavItem.php` | The menu item: label, icon, URL, badge, visibility, children, per-record menu. |
| `src/Tree/ActivePathResolver.php` | Pure logic: request path → active path → level to render. No Filament or HTTP dependencies, so it is easy to test. |
| `src/Tree/NativeNavigation.php` | Adapter from Filament's navigation to `NavItem`s. |
| `resources/views/sidebar.blade.php` | Renders Back, title and items (reusing `x-filament-panels::sidebar.item`), plus the slide animation. |

## 6. Limits

- Collapsed (icon-only) sidebar mode is not specially handled yet.
- The plugin owns the sidebar list: items you do not list (or import with `fromFilamentNavigation()`) are not shown.
- The level is derived from the URL. A record menu therefore needs its URLs to live under the resource's own URL (`/admin/agents/{record}/...`).
