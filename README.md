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

- Client system overview (for school leadership): [docs/client-system-overview.md](docs/client-system-overview.md)
- Spec summary: [docs/spims-spec-summary.md](docs/spims-spec-summary.md)
- Design gap analysis & next phases: [docs/portal-design-gap-analysis.md](docs/portal-design-gap-analysis.md)
- Parking lot (out-of-phase): [PARKING-LOT.md](PARKING-LOT.md)
- VPS provisioning: [docs/vps-setup.md](docs/vps-setup.md)
- Backups: [docs/backups-and-restore.md](docs/backups-and-restore.md)
- Release checklist: [docs/release-runbook.md](docs/release-runbook.md)

## Super Admin (seeded)

- Email: `robeir.george@outlook.com`
- Password: set via `SUPERADMIN_PASSWORD` in `.env` (default `Spims@Dev2026!`)

Sample curriculum (`DEMO-DIP` / `DEMO101`) is seeded when `SEED_SAMPLE_DATA=true` (default).
Rich demo users/curriculum when `SEED_DEMO_DATA=true` — see [docs/demo-accounts.md](docs/demo-accounts.md).
