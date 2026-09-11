# AGENTS.md — SPIMS

See `CLAUDE.md` for product overview and hard rules, and `README.md` for the standard local setup/run/test commands. Do not duplicate those here.

## Cursor Cloud specific instructions

Single Laravel 10 app (PHP 8.2). SQLite is the dev database; there is no npm build step (Blade only), so `vite`/`npm` are not required to run or test the app.

### Running the app
- Dev server: `php artisan serve --host=0.0.0.0 --port=8000`. Health check: `GET /health` returns JSON with `app`/`database`/`cache` booleans.
- Seeded super admin login: `robeir.george@outlook.com` / password from `SUPERADMIN_PASSWORD` in `.env` (default `Spims@Dev2026!`). Sample curriculum seeds when `SEED_SAMPLE_DATA=true`.
- Mail is `MAIL_MAILER=log` in dev — OTPs/verification links are written to `storage/logs/laravel.log`, never sent, and never block flows.

### Database
- Dev DB is `database/database.sqlite` (a plain file). If missing, create it with `touch database/database.sqlite` before migrating. Reset with `php artisan migrate:fresh --seed`.
- Migrations are additive-only by project rule; CI also verifies `migrate:fresh --seed` against real PostgreSQL 16 (see `.github/workflows/ci.yml`).

### Tests and lint
- `php artisan test --compact` runs every suite. CI runs each `phpunit.xml` testsuite separately (Unit, Database, Auth, Audit, Smoke, Admin, Api, Academics, Offerings, Admissions, Enrollment, Finance, Assessment, Live, Credentials, Hardening, Portal) as the deploy gate.
- Tests use an in-memory SQLite DB (configured in `phpunit.xml`), independent of `.env`.
- Lint: `./vendor/bin/pint` (formatter). Note: `pint --test` currently reports pre-existing style deviations across the repo and is NOT part of the CI gate — a non-clean `pint --test` is expected and not a blocker.
