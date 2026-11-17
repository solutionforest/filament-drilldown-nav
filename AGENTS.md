# AGENTS.md

Guidance for AI coding agents (and humans) working **on this repository**. If you are *using* the package in an app, read [`SKILLS.md`](SKILLS.md) instead.

## What this is

`solutionforest/filament-drilldown-nav`: a Filament **v5** panel plugin that replaces the sidebar list with a drill-down menu (one level at a time, Back link, any depth, per-record menus). Why and how: [`docs/how-it-works.md`](docs/how-it-works.md).

## Layout

| Path | What |
|---|---|
| `src/Tree/ActivePathResolver.php` | Pure logic (URL → active path → level). Most bugs are here. |
| `src/Tree/NavItem.php` | The item API users touch. Keep it small and Filament-like (fluent, `Closure`-friendly). |
| `src/Tree/NativeNavigation.php` | Adapter from Filament's native navigation. |
| `src/DrilldownNavPlugin.php` | Plugin registration and render hook. |
| `resources/views/sidebar.blade.php` | The sidebar view (own scoped CSS: Filament's compiled Tailwind does not include arbitrary utility classes). |
| `tests/` | Pest tests for the package (no app boot needed). |
| `demo/` | Laravel 13 + Filament v5 app wired via a path repository; its `tests/Feature` render real pages. |
| `src/Tree/RecordPages.php` | Record menu from a resource's own record pages (`recordPages()`). |
| `src/Livewire/` | `DrilldownSidebar` and the `HidesNativeNavigation` trait: hides Filament's list while the sidebar renders, and serves the Livewire endpoints (search index, pins). |
| `src/Models/DrilldownPin.php`, `database/migrations/*.stub` | Pinned pages in the database (`pinnable(database: true)`). |
| `docs/` | `how-it-works.md` (diagrams), `api-stability.md` (public API, freeze review), `compatibility.md`, `screenshots/`. |
| `bin/changelog-section.sh` | Prints a version's changelog section; used by the release workflow. |

## Commands (all must pass before you push)

```bash
composer install
vendor/bin/pest                                   # package tests
vendor/bin/pint --test                            # code style (Laravel preset)
vendor/bin/phpstan analyse --memory-limit=1G      # PHPStan level 10 on src/
(cd demo && composer install && cp -n .env.example .env && php artisan key:generate \
   && touch database/database.sqlite && php artisan test)   # demo feature tests
```

`composer test`, `composer lint`, `composer analyse` and `composer check` wrap the package-level ones.

## Rules

- Every change updates `CHANGELOG.md` under `## [Unreleased]`. Tags are released by `.github/workflows/release.yml`, which fails when the tag has no changelog section.

- **PHPStan level 10 (the strictest: `mixed` is checked) stays clean with no ignores and no baseline.** Fix the type, do not suppress it. No `@phpstan-ignore`, no `assert()`/inline `@var`/casts just to silence an error. Type class-name parameters as `class-string<…>`, and validate what app closures return (`instanceof`, `is_string`) instead of casting.
- **Resolver changes need a test** in `tests/ActivePathResolverTest.php` (pure) and, if they change rendering, a demo feature test. Every past bug has a regression test; keep that habit.
- Keep `ActivePathResolver` free of Filament/HTTP dependencies.
- Closures given to `children()`, `record()` or `items()` must run **at most once per request** and only for items on the current path. Do not add code that walks the whole tree eagerly. The one exception is `DrilldownNavPlugin::flatten()` (search all levels, menu export): it runs only when asked for, and is capped by `$limit` and `$maxDepth`.
- Do not use `navigation(false)` on the panel: it removes the whole sidebar, so the render hook never fires.
- Never empty Filament's navigation for the whole panel: top navigation, breadcrumbs and other plugins read it. The builder closure returns an empty `NavigationBuilder` only while `RequestState::$hidingNativeNavigation` is set (by `HidesNativeNavigation` on the sidebar component) or while capturing for `fromFilamentNavigation()`; otherwise it returns `true` (Filament's default navigation).
- No inline `<style>` or `style=""` in views (strict CSP): styles go in `resources/dist/filament-drilldown-nav.css`, registered as a Filament asset. Run `php artisan filament:assets` in the demo after changing it.
- Minimum Filament version is **5.7** (see `docs/api-stability.md` for why); the `lowest` CI job proves it. It lifts Composer's security block on purpose (Filament < 5.8.3 has advisories). Raise the constraint in both `composer.json` files only for something you truly need.
- Demo tests must start every request like a real worker (`demo/tests/TestCase.php` flushes scoped instances and facades before each request). Do not remove it: older Filament keeps request state between requests in one app instance, which made tests fail for reasons that cannot happen on a server.
- Style: Laravel Pint, `declare(strict_types=1)`, Filament conventions. Conventional Commit messages.
- Do not commit `.env`, `vendor/`, `demo/database/database.sqlite`, or the RoadRunner binary `demo/rr`.

## Long-lived workers (Octane): where state may live

- **Never store request-specific state on the plugin, on `NavItem`, or in static properties.** Panels, plugins and any `NavItem` built outside a closure live for the whole worker. Use `RequestState::current()->remember($owner, $key, fn)` (a *scoped* binding, discarded after each request) as `NavItem`, the plugin and `NativeNavigation` do.
- The sidebar hook is registered with `$panel->renderHook()` (panel-scoped) **and** guarded to render only for the panel being shown: Filament boots the default panel's hooks even while another panel is served.
- Build panel/tenant URLs through Filament (`Panel::getUrl($tenant)`), never from `$panel->getPath()` alone.
- Tests that simulate a worker must do what Octane does between requests: `$app->forgetScopedInstances()` **and** `Facade::clearResolvedInstances()` (see `demo/tests/Feature/PhaseATest.php`). Forgetting the second one makes Filament's facade keep the previous request's manager and produces misleading failures.
- New user-visible strings go in `resources/lang/*/messages.php` for **all** shipped languages (a test checks the keys match).

## Docs are tested

- `docs/api-reference.md` is **generated**: `php bin/api-reference.php > docs/api-reference.md` after changing any public method (and give each public method a one-line summary in its docblock; `@internal` hides it from the reference).
- `docs/support.md` holds the support matrix and a hidden `<!-- support: filament=… php=… -->` line. `tests/DocsTest.php` fails if it disagrees with `composer.json`, the README or the `lowest` job name in `.github/workflows/tests.yml`. **Only Filament v5 is supported:** never claim v3/v4.
- Every relative Markdown link and image must exist, and every page in `docs/` must be listed in `docs/README.md`.

## Real Octane check

`demo/bin/octane-smoke.sh <roadrunner|swoole|frankenphp>` starts a real one-worker Octane server and verifies per-request isolation with 21 checks (`demo/bin/octane-smoke.php`). Run it after any change to `RequestState`, the plugin's hooks or anything that caches. It needs `DEMO_AUTOLOGIN=true`, which the script sets; that demo-only login route exists only when the variable is set and the app runs in `local`/`testing`. CI runs it for all three servers.

## API stability

Public API vs `@internal` is defined in `docs/api-stability.md`; keep it and `CHANGELOG.md` current in every PR that changes behaviour (add an entry under *Unreleased*). Do not remove or rename a public method without a changelog line under *Changed*/*Removed*.

## Views (Blade) rules

- **Never put `@if`/`@foreach` inside a Blade component tag** (`<x-filament::icon ... @if ... />`): Blade swallows the whole tag silently. Wrap the component in a plain element that carries the directive/`x-show` instead (this once made every chevron disappear while all tests passed).
- Text, headings and badges that must disappear in the collapsed sidebar get `@if ($collapsible) x-show="$store.sidebar.isOpen" @endif` (on plain elements only).
- A level is opened only by `opensLevel()`; inline items must never open one.
- When a UI feature has no assertion that would fail if it broke, add one, and prove it by breaking the feature once (mutation check).

## Gotchas learned the hard way

- Filament only registers Resource/Page navigation items **while it builds its sidebar**; that is why `NativeNavigation::capture()` runs from the plugin's navigation builder and why it guards against running twice (Filament may resolve the builder several times per build).
- `NavigationItem` keys default to the label, so parent matching must de-duplicate `[key, label]`.
- Assert on the **rendered sidebar text** in demo tests (see `tests/Feature/NativeNavigationTest.php`), not on raw HTML substrings: the plugin's own `<style>` block contains class names like `fi-drilldown-back`.
- Playwright screenshots: log in at `/admin/login` (`demo@example.com` / `password` after `migrate --seed`), use a 2× device scale factor, and retake `docs/screenshots/*` when the UI changes.

## CI

`.github/workflows/tests.yml` runs on GitHub-hosted `ubuntu-latest` runners (PHP 8.3 via `shivammathur/setup-php`), including fork pull requests, with a read-only token.
