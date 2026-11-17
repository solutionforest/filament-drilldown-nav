# Testing and quality

What is tested, how, and how to run it.

## Run everything

```bash
composer install
composer check                                  # Pint + PHPStan level 10 + Pest
(cd demo && composer install && cp -n .env.example .env && php artisan key:generate \
   && touch database/database.sqlite && php artisan migrate --seed \
   && php artisan test)                         # feature tests against the real panels
(cd demo && bin/octane-smoke.sh swoole)         # a real Octane server (also: roadrunner, frankenphp)
```

## What each layer proves

| Layer | What it proves |
|---|---|
| **PHPStan level 10** | The strictest level, with **no ignores and no baseline**: `mixed` is checked everywhere, class names are typed as `class-string<…>`, and what your closures return is validated at runtime. |
| **Pest (package)** | URL matching and level selection, groups, per-record menus and nested sub-menus, once-per-request evaluation, ignored keys, failing lookups, search/limit/pinning logic, and the **documentation**: the support matrix matches `composer.json`, the generated API reference is current, every relative link and image exists. |
| **Demo feature tests** | Real pages rendered through real panels: each level, record menus, nested resources, native-navigation import and clusters, badges, permissions and roles, client data isolation, translations and right-to-left, render hooks, search, pins (also in the database), accessibility markup, and a query budget per page. Run on the latest Filament **and** on the oldest supported (`--prefer-lowest`). |
| **Real Octane servers** | RoadRunner, Swoole and FrankenPHP: one worker with sequential requests, and four workers with 300 parallel requests from two users across three panels. A response that shows another user's menu fails the run. |
| **Browser checks** | Behaviour that only exists in a browser (typing in search, expanding and collapsing, pinning, the collapsed sidebar, mobile drawer, dark mode, right-to-left) was run in a real browser; screenshots in the docs come from the running demo. |
| **Mutation checks** | Key features were broken on purpose (for example, request state that outlives the request) to confirm the tests fail. |

## Continuous integration

`.github/workflows/tests.yml` runs on every push and pull request:

| Job | What it runs |
|---|---|
| Package | Pest, Pint, PHPStan 10 |
| Demo | the demo's feature tests on the latest Filament |
| Lowest supported versions | package and demo on the **oldest** allowed Filament (5.7), with Composer's security block lifted on purpose (that version has advisories; this only proves compatibility) |
| Real Octane worker (×3) | `bin/octane-smoke.sh` for RoadRunner, Swoole and FrankenPHP |

Pull requests from forks are skipped on the maintainers' self-hosted runner, so untrusted code never runs there; maintainers re-run a contributor's branch after review.

Releases are cut from a tag: the `release` workflow checks that `CHANGELOG.md` has a section for the tag and creates the GitHub release from it.

## Testing your own menu

The package ships no test helpers; test it like any Filament page: sign in, request a URL, and assert on the rendered sidebar. The demo's `tests/Feature/NativeNavigationTest.php` extracts the sidebar's text and asserts on it; copy that helper.
