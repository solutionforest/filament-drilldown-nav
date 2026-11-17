# Support matrix

> **This package supports Filament v5 only.** Filament v3 and v4 are not supported and will not be.

<!-- support: filament=^5.7 php=^8.3 -->

| Package | Status | Filament | PHP | Laravel | Livewire | Octane |
|---|---|---|---|---|---|---|
| **0.x** (latest release) | ✅ supported | **`^5.7`** (CI runs 5.7.0, the oldest, and the latest 5.x) | **`^8.3`** (CI runs 8.4) | 13 (tested) | 4 (tested) | RoadRunner, Swoole, FrankenPHP (all three in CI) |

| Filament | Status | Notes |
|---|---|---|
| **5.7 and newer** | ✅ supported and tested | Everything works, including `aria-current` on Filament's own items (added in 5.7). |
| **5.0 to 5.6** | ⛔ not supported | 5.6 works but Filament's own items lack `aria-current="page"`; 5.5 and older break the top-navigation handling; 5.4 and older also lack methods we use. 5.0 to 5.3 cannot be installed with Laravel 13, so they were never tested. |
| **4.x, 3.x** | ⛔ not supported | Different sidebar internals and a different plugin API. Use a plugin written for those versions. |

> **Run a patched Filament.** Filament **below 5.8.3 has published security advisories** and Composer blocks those versions by default. The supported range above is a *compatibility* range, not a security recommendation; the CI job that tests 5.7.0 lifts Composer's block on purpose.

## What "tested" means

- **Package:** Pest, PHPStan level 10, Pint — on every push and pull request.
- **Demo app (Laravel 13, Filament 5, Livewire 4):** feature tests that render the real panels, on the latest Filament **and** on the oldest supported one (`--prefer-lowest`).
- **Octane:** a real one-worker server and a four-worker server under parallel requests, on RoadRunner, Swoole and FrankenPHP.
- **Not tested here:** PHP 8.3 specifically (CI runs 8.4), Laravel other than 13, Windows.

## Policy

- Until 1.0 only the **latest release** gets fixes. After 1.0, the latest minor of the current major.
- Dropping or raising a Filament, PHP or Laravel requirement is a **minor** version before 1.0 (a major after), always listed under *Changed* in the [changelog](../CHANGELOG.md).
- A new Filament minor is added to CI as soon as it is released; the plugin is declared compatible when CI passes.

## Updating this page (maintainers)

1. Change the constraint in `composer.json` (and `demo/composer.json`).
2. Update the hidden `<!-- support: … -->` line above and the tables.
3. Update the name of the `lowest` job in `.github/workflows/tests.yml` (it names the minimum Filament version).
4. Run `vendor/bin/pest tests/DocsTest.php`. It fails if `composer.json`, this page, the README and the workflow disagree.
