# API stability

The package is **0.x**: it works and is tested, but the API is not frozen. This page says what you can rely on, what may change, and what has to be true before 1.0.

## Public API (what to build on)

These are intended to stay as they are; before 1.0, a change to any of them is announced in the [changelog](../CHANGELOG.md) and is a **minor** version bump (0.x → 0.y), never a patch.

| Area | API |
|---|---|
| Plugin | `DrilldownNavPlugin::make()`, `->items()`, `->childDisplay()`, `->searchable()`, `->recent()`, `->pinnable()` |
| Display mode | `ChildDisplay::Drilldown`, `ChildDisplay::Inline` |
| Building items | `NavItem::make()`, `::page()`, `::resource()`, `::heading()`, `::fromFilamentNavigation()` |
| Item options | `url()`, `icon()`, `activeIcon()`, `badge()`, `visible()`, `children()`, `inline()`, `drilldown()`, `defaultExpanded()`, `divider()`, `limit()`, `activeOn()`, `header()`, `record()`, `recordHeader()`, `resolveRecordUsing()`, `recordTitleUsing()`, `ignoreRecordKeys()` |
| Translations | the keys in `resources/lang/*/messages.php` (publish tag `filament-drilldown-nav-translations`) |
| Behaviour | the level is computed from the request URL; closures run once per request; only items returned from `items()` are shown |

## Not guaranteed (may change in any release)

| Area | Why |
|---|---|
| **Classes marked `@internal`**: `RequestState`, `ActivePathResolver`, `NativeNavigation` | implementation details |
| **Read accessors on `NavItem`** (`getLabel()`, `getChildren()`, `getDisplayChildren()`, `getRecordNode()` …) | they exist for the sidebar view; use the fluent setters instead |
| **Markup and CSS**: the Blade views and the `fi-drilldown-*` class names | styling hooks, not a contract. If you publish and override views, expect to re-check them on upgrades |
| **Browser storage keys** (`fi-drilldown:*`) | pinned/recent lists may be reset by an upgrade |

## Versioning policy

- **Patch** (0.2.x): fixes, docs, translations, CI. No API changes.
- **Minor** (0.x): new features, and any change to the public API above, always listed under *Changed* or *Removed* in the changelog.
- **1.0** freezes the public API; after that, breaking changes only in a major version.

## What has to be true before 1.0

| | Requirement | Status |
|---|---|---|
| ✅ | Tested on Pest + PHPStan level 9 + feature tests on the real panel | done |
| ✅ | Worker reuse verified on real Octane servers (RoadRunner, Swoole, FrankenPHP) | done, in CI |
| ✅ | Multi-panel, tenancy/domains, right-to-left | done (RTL checked with Arabic only) |
| ✅ | Docs: README, how it works, API stability, changelog | done |
| ⬜ | **Used in at least one real application** (not the demo) with its real menus | pending: to be done by the owner |
| ⬜ | Public API reviewed and frozen (this page becomes a promise) | pending, after the real-app trial |
| ⬜ | Collapsed (icon-only) sidebar fallback for items without an icon | not built |
| ⬜ | Published on Packagist | on hold: the repository is private for now |
