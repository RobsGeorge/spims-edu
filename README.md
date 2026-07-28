# SPIMS — Student Information System + Learning Management

Laravel 10 SIS/LMS for Spims (Coptic Orthodox online school).

- **Production:** https://spims-edu.com
- **Staging:** https://staging.spims-edu.com
- **Stack:** PHP 8.2 · Laravel 10 · PostgreSQL 16 · Redis · Blade (no npm)

## Local development

```bash
composer install
cp .env.example .env
php artisan key:generate
# Use sqlite for quick tests:
# DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite
php artisan migrate --seed
php artisan serve
```

## Tests

```bash
php artisan test --compact
php artisan test --testsuite=Smoke
```

## Deploy

Push to `main` → CI → production deploy via GitHub Actions.
Push to `staging` → CI → staging deploy.

See [docs/vps-setup.md](docs/vps-setup.md) for VPS provisioning.

## Super Admin (seeded)

- Email: `robeir.george@outlook.com`
- Password: set via `SUPERADMIN_PASSWORD` in `.env` (default `Spims@Dev2026!`)
