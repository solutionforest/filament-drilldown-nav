# Changelog

All notable changes to this package. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [Semantic Versioning](https://semver.org/) (see [docs/api-stability.md](docs/api-stability.md) for what counts as the public API before 1.0).

## [Unreleased]

### Changed
- The open/closed state of inline sections is stored in a `fi-drilldown-open` cookie (excluded from cookie encryption, validated when read) instead of `localStorage`, and **rendered on the server**: a closed section no longer shows open until JavaScript starts. Previously stored states are not carried over.

### Fixed
- Content from the sidebar's render hooks was not hidden in the collapsed (icon-only) sidebar, so free text widened the icon rail (to about 250 px in the demo). Hook output is now wrapped in `.fi-drilldown-hook` and shown only while a collapsible sidebar is open.

### Added
- **Record menus with less code:** `NavItem::resource(X)->recordPages()` builds a record's menu from the resource's own record pages (labels, icons, badges and access from each page, as Filament builds a record's sub-navigation); or implement `Contracts\HasDrilldownRecordMenu` on the resource. Demo: the new `auto` panel.
- **Live updates:** `DrilldownNavPlugin::pollBadges('30s')` re-renders the sidebar on an interval; `refresh-sidebar` keeps working.
- **`DrilldownNavPlugin::accordion()`:** one inline section open at a time.
- `docs/compatibility.md`: checked next to Hover Sidebar, Sidebar Resize, Refresh Sidebar, Mobile Bottom Nav and Collapsible Sub-Nav (all work; Hover Sidebar and Sidebar Resize conflict with each other).

## [0.5.0] - 2026-11-17

### Added
- **Render hooks:** `DrilldownNavHook::NAV_START`, `NAV_END`, `HEADER_BEFORE`, `HEADER_AFTER`, `LIST_BEFORE`, `LIST_AFTER`: Filament render hooks around the drill-down menu, scoped to the panel and carrying `plugin`, `panel`, `depth` and `title` as data. Register them the Filament way (`$panel->renderHook()` / `FilamentView::registerRenderHook()`).
- **Locales:** Hebrew (`he`), Persian (`fa`) and Urdu (`ur`), so every right-to-left locale Filament ships with a layout direction for (Arabic, Hebrew, Persian, Urdu) has the plugin's own strings. (Machine-written; a native-speaker review is welcome.)
- **Pinned pages clean up after themselves:** once a day a pinned page that no longer opens (deleted record, lost access: 403/404/410) is dropped, and a renamed page's label is refreshed.
- **Concurrency check:** the Octane smoke test now also runs against **four workers with parallel requests** (300 requests from two users across three panels; every response must belong to its user and panel).

### Changed
- **PHPStan level 10** (was 9), still with no ignores and no baseline.
- **Minimum Filament is 5.7** (was 5.10, which was a guess from failures that turned out to come from the tests, not the plugin). Verified on 5.7.0 (all 81 demo tests and the real-server smoke test). Below 5.7 the plugin still renders but Filament's own items lack `aria-current="page"` (added in 5.7), and from 5.5 down the top-navigation handling breaks. Note: Filament **below 5.8.3 has published security advisories** and Composer blocks those versions by default; the supported range is a compatibility range, not a security recommendation: run a patched Filament.
- The Back link's collapsed-sidebar tooltip no longer depends on Filament's `filamentSidebarTooltip` helper (it only exists in newer versions); it uses the same `x-tooltip` directive Filament's own items use.
- The demo's Composer path repository pins a stable version, so `demo/composer.lock` no longer goes stale when the Git branch changes.

### Fixed
- Demo tests that made several requests in one app instance depended on Filament resetting its per-request state (newer versions do, 5.9 and older do not); every test request now starts like a real worker's.

### Verified
- **Filament clusters** next to the plugin: a cluster shows up in the menu through `fromFilamentNavigation()`, its pages render Filament's own sub-navigation beside the content, and its menu item stays current (4 tests).

## [0.4.0] - 2026-11-17

### Added
- **`NavItem::activeWhen(fn () => …)`:** marks an item current by any condition (route name, query string, React route), for pages the URL cannot place.
- **Collapsed sidebar fallback:** a top-level item without an icon shows its initial instead of nothing.
- CI job on the **lowest** supported versions (`--prefer-lowest`): package and demo.
- **Own header for a level:** `NavItem::recordHeader(fn ($record) => view(...))` for a record's menu and `->header(...)` for any item that opens a level replace the default Back link and title (a view renders as HTML, a string as text; the header gets the focus the title would). Demo: one task's menu.
- **Extra active URLs:** `NavItem::activeOn([...])` keeps an item current on pages under other URLs (matched like its own: by prefix, exact only for the panel root and ancestor URLs), e.g. a list at `/agents/5/sources` whose records open at `/agents/5/knowledge/{id}`.

### Changed
- **Filament's navigation stays intact outside the sidebar.** The plugin used to empty the panel's navigation, which also emptied `->topNavigation()` menus, breadcrumbs built from navigation, and anything else calling `Filament::getNavigation()`. Now only the sidebar's own list is hidden, via the plugin's sidebar component `DrilldownSidebar` (or the `HidesNativeNavigation` trait on your own).
- **Stylesheet is a Filament asset** (`php artisan filament:assets`) instead of an inline `<style>` block and `style=""` attributes: works with a strict Content-Security-Policy and can be overridden by a theme.
- **Requires Filament 5.10+** (was `^5.0`): 5.9 and older fail the demo tests; 5.10 is the oldest version tested in CI.
- Recent and pinned pages are stored per **tenant** too, and only entries under the current panel/tenant URL are shown (stored entries are also checked to be same-site paths).
- `->recent()` and `->pinnable()` are marked **experimental** in `docs/api-stability.md`.

## [0.3.0] - 2026-11-17

### Added
- **Menu search:** `DrilldownNavPlugin::searchable(minItems: …)` adds a filter box above the menu. It filters what is already on screen (the current level and inline sections), keeps a section visible when one of its children matches, hides headings while searching, and shows a "no matches" note. `Esc` clears it.
- **Recent and pinned pages:** `->recent($limit)` lists the last pages visited at the top level; `->pinnable()` adds "Pin this page" and a "Pinned" list. Stored per browser, panel and user.
- **Long menus:** `NavItem::limit($max, $moreUrl)` shows the first N children plus a "View all (N)" link (or "+N more"). All children still take part in URL matching and the child containing the current page is never hidden.
- Translations for the new strings in all shipped languages.
- `CHANGELOG.md`, `docs/api-stability.md`, `docs/vs-filament-native.md`.

### Changed
- `RequestState`, `ActivePathResolver` and `NativeNavigation` are marked `@internal`.

## [0.2.2] - 2026-11-17
### Added
- Arabic (`ar`) translations; right-to-left layout checked in a browser.
- Real Octane check in CI (`demo/bin/octane-smoke.sh`) on RoadRunner, Swoole and FrankenPHP.

## [0.2.1] - 2026-11-16
### Changed
- Demo and docs only: a realistic agency client portal (about 3,000 rows, four roles, five menu levels).

## [0.2.0] - 2026-11-16
### Added
- **Modern navigation:** `->inline()` (expand in place, nesting, remembered state), `->drilldown()`, `->defaultExpanded()`, `NavItem::heading()`, `->divider()`, `->activeIcon()`, `ChildDisplay`, collapsed (icon-only) sidebar, accessibility (`aria-expanded`, focus on the level title, reduced motion).
- **Multi-panel, worker reuse, tenants, languages:** per-request state (`RequestState`), panel-scoped hook, tenant/domain/sub-directory aware URLs, translations (en, zh_CN, zh_TW, zh_HK, ja, ko, es).
### Fixed
- Stale record titles and children across requests on a reused worker (Octane).
- Sidebar rendered twice on a worker's second request.
- Crash on a panel without the plugin after another panel was served by the same worker.

## [0.1.0] - 2026-11-16
### Added
- First release: drill-down sidebar (one level at a time, Back link, any depth), per-record menus (`->record()`), `NavItem::page()` / `resource()`, `NavItem::fromFilamentNavigation()`, badges, visibility, URL-less groups.
