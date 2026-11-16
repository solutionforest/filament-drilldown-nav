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
| `docs/` | `how-it-works.md` (diagrams) and `screenshots/`. |

## Commands (all must pass before you push)

```bash
composer install
vendor/bin/pest                                   # package tests
vendor/bin/pint --test                            # code style (Laravel preset)
vendor/bin/phpstan analyse --memory-limit=1G      # PHPStan level 9 on src/
(cd demo && composer install && cp -n .env.example .env && php artisan key:generate \
   && touch database/database.sqlite && php artisan test)   # demo feature tests
```

`composer test`, `composer lint`, `composer analyse` and `composer check` wrap the package-level ones.

## Rules

- **PHPStan level 9 stays clean with no ignores and no baseline.** Fix the type, do not suppress it. No `@phpstan-ignore`, no `assert()`/inline `@var` just to silence an error.
- **Resolver changes need a test** in `tests/ActivePathResolverTest.php` (pure) and, if they change rendering, a demo feature test. Every past bug has a regression test; keep that habit.
- Keep `ActivePathResolver` free of Filament/HTTP dependencies.
- Closures given to `children()`, `record()` or `items()` must run **at most once per request** and only for items on the current path. Do not add code that walks the whole tree eagerly.
- Do not use `navigation(false)` on the panel: it removes the whole sidebar, so the render hook never fires. The plugin empties the list with a `NavigationBuilder` instead.
- Style: Laravel Pint, `declare(strict_types=1)`, Filament conventions. Conventional Commit messages.
- Do not commit `.env`, `vendor/`, or `demo/database/database.sqlite`.

## Gotchas learned the hard way

- Filament only registers Resource/Page navigation items **while it builds its sidebar**; that is why `NativeNavigation::capture()` runs from the plugin's navigation builder and why it guards against running twice (Filament may resolve the builder several times per build).
- `NavigationItem` keys default to the label, so parent matching must de-duplicate `[key, label]`.
- Assert on the **rendered sidebar text** in demo tests (see `tests/Feature/NativeNavigationTest.php`), not on raw HTML substrings: the plugin's own `<style>` block contains class names like `fi-drilldown-back`.
- Playwright screenshots: log in at `/admin/login` (`demo@example.com` / `password` after `migrate --seed`), use a 2× device scale factor, and retake `docs/screenshots/*` when the UI changes.

## CI

`.github/workflows/tests.yml` runs on a **self-hosted** runner (PHP 8.3+, Composer). Fork pull requests are skipped on purpose, so untrusted code never runs on the runner.
