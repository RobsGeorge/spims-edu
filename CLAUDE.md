# CLAUDE.md — SPIMS (SIS + LMS)

## What this is
Standalone Laravel SIS/LMS for Spims (Coptic Orthodox online school).
Separate from Ava Pakhomios / Khedma multi-tenant platform.

Full spec: docs/spims-spec-summary.md (traces to spims-spec v0.2).
Design gaps & next phases: docs/portal-design-gap-analysis.md.
Out-of-phase ideas: PARKING-LOT.md.

## Hard rules
1. Authorization via `AuthorizeService` + `config/permissions.php` — no role-name string checks in controllers.
2. Every mutation through a service wrapped by `AuditLogWriter::withAudit()`.
3. Money = integer minor units + `Currency` enum. No floats.
4. All user-facing strings localized (ar primary RTL, en, fr).
5. Additive migrations only.
6. Tests required per phase; CI gate must pass before deploy.
7. Mailers optional in dev — OTP may be logged, never blocks build.
8. No npm build step.

## Environment
- PHP 8.2, Laravel 10, PostgreSQL 16, Redis, Nginx
- Production: spims-edu.com at `/var/www/spims`
- Staging: staging.spims-edu.com at `/var/www/spims-staging`
- Deploy: GitHub Actions on push to `main` / `staging`

## Key paths
- `app/Support/AuthorizeService.php` — permission guard
- `app/Support/AuditLogWriter.php` — audit on write
- `config/permissions.php` — permission matrix
- `public/css/spims-theme.css` — liturgical theme (light + dark)
- `tests/Feature/` — categorized test suites
