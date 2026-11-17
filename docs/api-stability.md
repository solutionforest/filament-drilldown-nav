# API stability

The package is **0.x**: it works and is tested, but the API is not frozen. This page says what you can rely on, what may change, and what has to be true before 1.0.

## Public API (what to build on)

These are intended to stay as they are; before 1.0, a change to any of them is announced in the [changelog](../CHANGELOG.md) and is a **minor** version bump (0.x → 0.y), never a patch.

| Area | API |
|---|---|
| Plugin | `DrilldownNavPlugin::make()`, `->items()`, `->childDisplay()`, `->searchable()` (with `allLevels`, `shortcut`), `::current()`, `->flatten()`, `->accordion()`, `->pollBadges()` |
| Record menus | `NavItem::recordPages()`, the `Contracts\HasDrilldownRecordMenu` interface |
| Render hooks | the six `DrilldownNavHook::*` names and the data they pass (`plugin`, `panel`, `depth`, `title`) |
| Sidebar component | `Livewire\DrilldownSidebar`, the `Livewire\Concerns\HidesNativeNavigation` trait (for panels with their own sidebar component) |
| Display mode | `ChildDisplay::Drilldown`, `ChildDisplay::Inline` |
| Building items | `NavItem::make()`, `::page()`, `::resource()`, `::heading()`, `::fromFilamentNavigation()` |
| Item options | `url()`, `icon()`, `activeIcon()`, `badge()`, `visible()`, `children()`, `inline()`, `drilldown()`, `defaultExpanded()`, `divider()`, `limit()`, `activeOn()`, `activeWhen()`, `header()`, `record()`, `recordHeader()`, `resolveRecordUsing()`, `recordTitleUsing()`, `ignoreRecordKeys()` |
| Translations | the keys in `resources/lang/*/messages.php` (publish tag `filament-drilldown-nav-translations`) |
| Behaviour | the level is computed from the request URL; closures run once per request; only items returned from `items()` are shown |

Keep in mind: `resolveRecordUsing()`, `recordTitleUsing()` and `ignoreRecordKeys()` are only needed for record menus that do **not** come from a Filament resource; `NavItem::resource()->record()` sets them for you.

## Experimental (may change or move to an add-on before 1.0)

| API | Why |
|---|---|
| `->recent()`, `->pinnable()` (incl. `database:` / `model:`, the `DrilldownPin` model and the `drilldown_nav_pins` table) | recent is per browser; pins can now live in the database. Kept out of the 1.0 promise until the API freeze decides |

## Not guaranteed (may change in any release)

| Area | Why |
|---|---|
| **Classes marked `@internal`**: `RequestState`, `ActivePathResolver`, `NativeNavigation` | implementation details |
| **Read accessors** on `NavItem` (`get…()`, `is…()`, `has…()`, `opensLevel()`) and on `DrilldownNavPlugin` (`getTree()`, `getRootUrl()`, `getPins()`, `togglePin()` …), all tagged `@internal` | they exist for the sidebar view and its Livewire endpoints; use the fluent setters instead |
| **Markup and CSS**: the Blade views and the `fi-drilldown-*` class names | styling hooks, not a contract. If you publish and override views, expect to re-check them on upgrades |
| **Browser storage keys** (`fi-drilldown:*`) and the `fi-drilldown-open` cookie format | pinned/recent lists may be reset by an upgrade |

## Freeze review (2026-11-17)

Every public method was listed and classified. **Done in this review:** every read accessor is tagged `@internal` (43 methods), so editors warn when an app calls them; nothing was removed or renamed.

**Decisions for the owner before 1.0.0-rc1** (my recommendation in bold):

| # | Question | Options | Recommendation |
|---|---|---|---|
| 1 | `pollBadges()` re-renders the whole sidebar, not only badges | keep the name / rename to `poll()` | **rename to `poll()`** now: it is new and unreleased, so renaming costs nothing |
| 2 | `searchable()` now has four parameters (`minItems`, `allLevels`, `shortcut`) | keep / split into `searchable()` + `searchShortcut()` | **keep**: named arguments read well and each has a safe default |
| 3 | Pinned pages (with the new database option) and recent pages | promote to the 1.0 promise / keep experimental | **promote `pinnable()`** (it now has server storage and tests); **keep `recent()` experimental** (browser-only) |
| 4 | `activeOn()` (more URLs) and `activeWhen()` (any condition) | keep both / keep only `activeWhen()` | **keep both**: `activeOn()` matches by prefix like the item's own URL, which `activeWhen()` can't do cleanly |
| 5 | `resolveRecordUsing()`, `recordTitleUsing()`, `ignoreRecordKeys()` | keep public / mark advanced | **keep, documented as advanced**: only for record menus without a Filament resource |
| 6 | `header()` and `recordHeader()` | keep both / merge | **keep both**: different targets (an item's level vs. a record's level) |
| 7 | `fi-drilldown-*` CSS class names | promise them / keep them unguaranteed | **keep unguaranteed**, but promise `.fi-drilldown-hook` and `data-hook` (themes and other plugins target them) |

## Versioning policy

- **Patch** (0.2.x): fixes, docs, translations, CI. No API changes.
- **Minor** (0.x): new features, and any change to the public API above, always listed under *Changed* or *Removed* in the changelog.
- **1.0** freezes the public API; after that, breaking changes only in a major version.

## What has to be true before 1.0

| | Requirement | Status |
|---|---|---|
| ✅ | Tested on Pest + PHPStan level 10 + feature tests on the real panel | done |
| ✅ | Worker reuse verified on real Octane servers (RoadRunner, Swoole, FrankenPHP): one worker sequentially, and four workers under parallel load | done, in CI |
| ✅ | Multi-panel, tenancy/domains, right-to-left | done (RTL checked in a browser with Arabic; Hebrew, Persian and Urdu share the rules and have strings) |
| ✅ | Docs: README, how it works, API stability, changelog | done |
| ⬜ | **Decisions below** (freeze review) taken by the owner | pending |
| ⬜ | Hebrew, Persian and Urdu strings reviewed by a native speaker | pending: machine-written |
| ⬜ | Public API frozen (this page becomes a promise), then **1.0.0-rc1** | after the two items above |
| ✅ | Works next to other sidebar plugins | done: [compatibility](compatibility.md) |
| ✅ | Collapsed (icon-only) sidebar fallback for items without an icon | done: the item's initial |
| ✅ | Leaves Filament's navigation intact outside the sidebar (top navigation, breadcrumbs, other plugins) | done, tested |
| ✅ | Strict Content-Security-Policy: no inline `<style>` or `style=""` | done: a published Filament asset |
| ✅ | Minimum Filament version tested in CI (`--prefer-lowest`) | done: **5.7** (older versions lack `aria-current` in Filament's own items; 5.5 and below break the top-navigation handling) |
| ✅ | Filament clusters next to the plugin | done, tested: they appear in the menu and keep their sub-navigation |
| ⬜ | Published on Packagist | on hold: the repository is private for now |
