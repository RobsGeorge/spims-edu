# CLAUDE.md — SPIMS (SIS + LMS)

## What this is
Standalone Laravel SIS/LMS for Spims (Coptic Orthodox online school).
Separate from Ava Pakhomios / Khedma multi-tenant platform.

Full spec: docs/spims-spec-summary.md (traces to spims-spec v0.2).
Role matrix (every permission key × role, scope rules): docs/role-matrix.md.
Design gaps & next phases: docs/portal-design-gap-analysis.md.
Academic roadmap (S0–S9, SIS gaps + mobile API): docs/academic-roadmap/.
Super Admin remaining work: docs/superadmin-control-plane-plan.md.
Legacy data import (L0–L6, two predecessor systems): docs/legacy-data-import-plan.md.
Out-of-phase ideas: PARKING-LOT.md.

## Hard rules
1. Authorization via `AuthorizeService` + `config/permissions.php` — no role-name string checks in controllers.
   A new offering-owned permission key must be registered in `config/permission_scopes.php`, and a new
   offering-owned model in `ResourceScopeResolver::offeringIdsFor()`. Miss either and scope is silently
   not enforced — the key is checked at role level only, or the model resolves to no offering (fails
   safe, but presents as an unexplained 403). Scoped keys fail closed: pass the resource.
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
- `config/permission_scopes.php` — which permission keys are offering-scoped, and for which roles
- `app/Support/Scope/ResourceScopeResolver.php` — maps a resource to its offering(s)
- `public/css/spims-theme.css` — liturgical theme (light + dark)
- `tests/Feature/` — categorized test suites
- `docs/academic-roadmap/` — S0–S9 roadmap: gap analysis, implementation plan, execution order, `/api/v1` contract

## Working this repo with agents
- Every UI change follows `prompts/GLOBAL-CONTRACT.md`. Read `docs/design-system.md` before any markup.
- Never edit `layouts/`, `partials/`, or `components/` unless your step's scope says you own them.
- Append routes at the anchor comment for your track in `routes/web.php`. Never reorder the file.
- Add new lang keys to your step's own lang file where possible. Only APPEND to shared ones.
  Every key goes into ALL THREE locales (ar, en, fr) — `LocaleParityTest` enforces this.
- A step is not done until `./scripts/validate-step.sh <step-id>` exits 0.
  Write your step ID to `.claude/current-step` first.
- Design system reference: `docs/design-system.md` (created in Phase A).
- Banned patterns are blocked at write time by the `guard-design.sh` hook — if a write is denied,
  consult the migration table in `docs/design-system.md`.

## Parity rule
No new student or instructor API endpoint merges without shipping or explicitly deferring
its web equivalent in `docs/api-web-parity-matrix.md`.
