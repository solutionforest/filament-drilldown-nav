# Changelog

All notable changes to this package. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [Semantic Versioning](https://semver.org/) (see [docs/api-stability.md](docs/api-stability.md) for what counts as the public API before 1.0).

## [Unreleased]

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
