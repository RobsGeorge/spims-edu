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
composer test                 # full PHPUnit suite
composer test:api             # /api/v1 only
composer lint                 # deploy/CI artifact checks
php artisan test --testsuite=Smoke
```

CI (every push/PR): lint → full suite on SQLite → `migrate:fresh --seed` on PostgreSQL 16 → full suite on PostgreSQL. Deploy workflows reuse that workflow; a red gate never ships.

## Deploy

Push to `main` → CI → production (`/var/www/spims`).
Push to `staging` → CI → staging (`/var/www/spims-staging`).
Manual rollback: Actions → **Rollback** (environment + SHA).

Mobile apps talk to the **same** origin: `https://<domain>/api/v1` (Sanctum Bearer). No extra process.

**What I need from you** (DNS, SSH keys, GitHub secrets, first `.env`): [docs/owner-actions.md](docs/owner-actions.md).

- Client system overview (for school leadership): [docs/client-system-overview.md](docs/client-system-overview.md)
- Spec summary: [docs/spims-spec-summary.md](docs/spims-spec-summary.md)
- Design gap analysis & next phases: [docs/portal-design-gap-analysis.md](docs/portal-design-gap-analysis.md)
- Academic roadmap (S0–S9: SIS gaps, mobile API): [docs/academic-roadmap/](docs/academic-roadmap/)
- Parking lot (out-of-phase): [PARKING-LOT.md](PARKING-LOT.md)
- Owner actions (secrets, DNS, keys): [docs/owner-actions.md](docs/owner-actions.md)
- How `/api/v1` runs: [docs/mobile-api-runtime.md](docs/mobile-api-runtime.md)
- VPS provisioning: [docs/vps-setup.md](docs/vps-setup.md)
- Backups: [docs/backups-and-restore.md](docs/backups-and-restore.md)
- Release checklist: [docs/release-runbook.md](docs/release-runbook.md)

## Super Admin (seeded)

- Email: `robeir.george@outlook.com`
- Password: set via `SUPERADMIN_PASSWORD` in `.env` (default `Spims@Dev2026!`)

Sample curriculum (`DEMO-DIP` / `DEMO101`) is seeded when `SEED_SAMPLE_DATA=true` (default).
Rich demo users/curriculum when `SEED_DEMO_DATA=true` — see [docs/demo-accounts.md](docs/demo-accounts.md).
