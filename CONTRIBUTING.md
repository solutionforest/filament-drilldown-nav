# Contributing

Thanks for helping. The short version: small pull requests, a test for every change, and the checks below green.

## Set up

```bash
composer install
(cd demo && composer install && cp -n .env.example .env && php artisan key:generate \
   && touch database/database.sqlite && php artisan migrate --seed)
```

The demo (`demo/`) is a Laravel 13 + Filament 5 app wired to the package through a path repository. `cd demo && php artisan serve`, then log in as `demo@example.com` / `password`.

## Before you open a pull request

```bash
vendor/bin/pest                                   # package tests
vendor/bin/pint --test                            # code style (Laravel preset)
php bin/api-reference.php > docs/api-reference.md  # regenerate the API reference when you change a public method
vendor/bin/phpstan analyse --memory-limit=1G      # PHPStan level 10, no ignores, no baseline
(cd demo && php artisan test)                     # demo feature tests (real panels)
```

- **Every behaviour change needs a test.** Resolver logic: `tests/ActivePathResolverTest.php`. Anything rendered: a feature test in `demo/tests/Feature`.
- **Update `CHANGELOG.md`** under `## [Unreleased]` (Added / Changed / Fixed / Removed). A release can't be tagged without its entry (see below).
- **Public API changes** follow [`docs/api-stability.md`](docs/api-stability.md). Changed or added a public method? Regenerate the reference: `php bin/api-reference.php > docs/api-reference.md` (a test fails otherwise).
- **Changing a supported version** (Filament, PHP)? Follow the checklist in [`docs/support.md`](docs/support.md); `tests/DocsTest.php` fails when `composer.json`, the support matrix, the README and the CI job disagree.
- **Docs are tested:** every relative link and image must exist, and every page in `docs/` must be listed in `docs/README.md`.
- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `docs:`, `chore:` …).
- More detail on the code layout and rules: [`AGENTS.md`](AGENTS.md).

## Releasing (maintainers)

1. Rename `## [Unreleased]` in `CHANGELOG.md` to `## [x.y.z] - YYYY-MM-DD` (and add a new empty `## [Unreleased]`), merge that to `main`.
2. Tag `main`: `git tag vx.y.z && git push origin vx.y.z`.
3. The **release** workflow checks that the changelog has a `## [x.y.z]` section and creates the GitHub release with that section as its notes. A tag without a changelog entry fails the workflow and no release is made.

Check locally first: `bin/changelog-section.sh x.y.z`.
