## Stack

| Layer | Choice |
|---|---|
| Language / runtime | PHP **8.2** |
| Framework | **Laravel 10** |
| Database | **PostgreSQL 16** |
| Cache / queues | **Redis** |
| Web server | **Nginx** |
| Frontend | Blade + Bootstrap **5.3** + Bootstrap Icons (CDN) — **no npm build step** |

SPIMS is a standalone SIS/LMS for Spims (Coptic Orthodox online school), separate from multi-tenant Ava Pakhomios / Khedma platforms.

### Deployed environments

| Env | Host | Path on server |
|---|---|---|
| Production | spims-edu.com | `/var/www/spims` |
| Staging | staging.spims-edu.com | `/var/www/spims-staging` |

Deploy via GitHub Actions on push to `main` / `staging`.

---

## Hard rules (non-negotiable)

1. **Authorization** — `AuthorizeService` + keys in `config/permissions.php`. Never compare role-name strings in controllers. Offering-scoped keys must also be listed in `config/permission_scopes.php`, and models must resolve offerings in `ResourceScopeResolver::offeringIdsFor()`. Miss either and scope is silently wrong (role-only check or fail-closed 403).
2. **Audit** — Destructive / money / grade / admissions mutations go through domain services wrapped by `AuditLogWriter::withAudit()`.
3. **Money** — Integer **minor units** + `Currency` enum (`EGP`, `USD`). No floats. No automatic currency conversion.
4. **i18n** — All user-facing strings localized: **ar** (RTL primary), **en**, **fr**. `LocaleParityTest` enforces key parity.
5. **Migrations** — Additive only.
6. **Tests** — Required per change/phase; CI gate must pass before deploy.
7. **Mail / OTP** — Optional in dev; OTP may be logged; never block the build.
8. **No npm** — Blade + CDN CSS/JS only.

---

## Key repository paths

| Path | Purpose |
|---|---|
| `app/Support/AuthorizeService.php` | Permission guard |
| `app/Support/AuditLogWriter.php` | Audit wrapper on writes |
| `app/Support/NavigationHub.php` | Sidebar, bottom nav, hub tiles |
| `app/Support/Scope/ResourceScopeResolver.php` | Resource → offering IDs |
| `config/permissions.php` | Default permission matrix |
| `config/permission_scopes.php` | Offering-scoped keys + scoped roles |
| `public/css/spims-theme.css` | Design tokens (light + dark) |
| `public/css/spims-shell.css` | App shell / layout chrome |
| `routes/web.php` | Web routes with A2-a..h track anchors |
| `routes/api.php` | `/api/v1` mobile/API contract |
| `tests/Feature/` | Categorized feature suites |
| `docs/spims-spec-summary.md` | Condensed product contract |
| `docs/design-system.md` | UI tokens and components |
| `docs/academic-roadmap/` | S0–S9 SIS gaps + mobile API |
| `docs/superadmin-control-plane-plan.md` | Remaining Super Admin work |
| `PARKING-LOT.md` | Out-of-phase ideas |
| `prompts/GLOBAL-CONTRACT.md` | Agent UI change contract |

---

## Help CMS vs System Docs

| Surface | Audience | Storage / location |
|---|---|---|
| **Help** (in-app) | Students and staff using the product | DB: `HelpCategory`, `HelpArticle`, locales, audiences — managed under Admin Help CMS |
| **System Docs** (this tree) | Client briefing + technical onboarding | Files under `resources/system-docs/{en,ar,fr}/` |

Client pages exist in **en / ar / fr**. Technical pages (**architecture**, **frontend**, **backend**, **database**, **navigation-map**, **feature-flows**, **roles-permissions**) are **English only**; other locales intentionally fall back to English.

---

## Domain map (v1)

Identity → Academics → Offerings → Admissions → Enrollment → Finance → Assessment → Gradebook → Live → Discussions → Credentials → Notifications. Portal hubs and the Roles hub sit on top for navigation and matrix overrides.

See [backend.md](backend.md), [frontend.md](frontend.md), and [database.md](database.md) for deeper detail.
