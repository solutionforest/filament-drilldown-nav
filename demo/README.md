# Demo app

A Laravel 13 + Filament v5 app that uses **every feature** of [Filament Drilldown Nav](../README.md): a small agency's client portal with about **3,000 rows**, four roles, five menu levels, and four panels (`admin`, `portal`, `plain`, `workspace` with tenancy on its own domain).

It is also the package's test bench: the feature tests in `tests/Feature` render the real panels, and `bin/octane-smoke.sh` runs a real Octane server against it.

## Run it

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan filament:assets
php artisan serve
```

Open <http://localhost:8000/admin/login> and sign in (password `password`, local demo data only):

| Email | Role | Sees |
|---|---|---|
| `demo@example.com` | admin | everything, including *Audit log* and *Billing* |
| `manager@example.com` | manager | clients, projects, invoices |
| `member@example.com` | member | projects and tasks |
| `client@example.com` | client (Acme Corp) | only Acme's projects; no clients, time or settings |

Add `?lang=ja` (or `ar`, `he`, `fa`, `ur`, `ko`, `es`, `zh_CN`, `zh_TW`, `zh_HK`) to see another language.

## Test it

```bash
php artisan test                 # feature tests against the real panels
bin/octane-smoke.sh swoole       # a real Octane server (or roadrunner, frankenphp)
```

What the demo shows, page by page: [docs/demo.md](../docs/demo.md). The menus are defined in `app/Providers/Filament/AdminPanelProvider.php`.

> This app is for demonstration and testing. It signs everyone in with a known password and has a demo-only auto-login route (off unless `DEMO_AUTOLOGIN=true`). Do not deploy it.
