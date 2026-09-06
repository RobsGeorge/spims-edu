# How the mobile APIs run

There is **no separate API process**. `/api/v1` is the same Laravel 10 app that serves the website,
behind the same Nginx + PHP-FPM on the VPS.

```
Mobile app
   │  HTTPS
   ▼
Your domain  (Nginx → /var/www/spims/public/index.php)
   │
   ├─ Blade / session  →  /login, /portal, …
   └─ JSON API         →  /api/v1/*   (Sanctum Bearer token)
```

Staging is the same shape on `staging.<domain>` → `/var/www/spims-staging`.

## Base URL

| Environment | Origin | API root |
|-------------|--------|----------|
| Production | `https://<your-domain>` | `https://<your-domain>/api/v1` |
| Staging | `https://staging.<your-domain>` | `https://staging.<your-domain>/api/v1` |
| Local | `php artisan serve` | `http://127.0.0.1:8000/api/v1` |

Point both the student app and the instructor app at that root. Role abilities on the token decide
which routes succeed (`role:STUDENT` vs `role:INSTRUCTOR` / `role:TA`).

## Auth (already shipped)

```
POST /api/v1/login     { email, password, device_name? }
                     → { data: { token, token_type: "Bearer", user } }

Authorization: Bearer <token>
GET  /api/v1/me
POST /api/v1/logout    → 204, revokes only this token

GET  /api/v1/branding  → public, safe before login (also used as a deploy smoke)
```

Web login stays session + cookie. Mobile login calls `AuthService::issueApiToken()` and never
starts a session (`EnsureFrontendRequestsAreStateful` is off on the `api` group).

Token lifetime is `SANCTUM_EXPIRATION` minutes in `.env`. Empty means no expiry. Prefer `43200`
(30 days) in production; the client re-posts email + password when it gets `401 UNAUTHENTICATED`.

## What is live today vs later waves

Shipped under `/api/v1` (see `routes/api.php` and `docs/api/openapi.yaml`):

- Foundation: login, logout, me, branding, locale, one error envelope
- Communications: announcements, notifications, notification-settings
- Instructor announcements (`/teach/announcements/*`)
- Attendance: student mine/check-in; instructor sessions/roster/report

Still to build (S6 student waves B–E, S8 instructor remainder): catalog, enrollments, invoices,
assignments, exams, gradebook, projects, live quiz. The contract is in
[academic-roadmap/mobile-api-spec.md](academic-roadmap/mobile-api-spec.md). Missing routes are
absent, not stubbed — a client should treat 404 as “not shipped yet”.

`OpenApiCoverageTest` fails CI if a registered `/api/v1` route is missing from
`docs/api/openapi.yaml`.

## CORS, rate limits, HTTPS

- Native iOS/Android do not use CORS. Expo web / Capacitor webviews do; `config/cors.php` already
  allows `api/*`.
- Login is throttled (`throttle:login`, 5/min). Other API routes use 60/min per user or IP.
- Production Nginx + `FORCE_HTTPS=true` terminate TLS. Clients must use `https://`.

## Workers the API depends on

Same systemd units as the website:

- `spims-queue` / `spims-queue-staging` — notifications, mail, backups triggered from jobs
- `* * * * * php8.2 artisan schedule:run` — exam auto-submit, reminders, daily DB dump

A deploy restarts the matching queue unit and re-installs the scheduler cron
(`scheduler:ensure-cron --apply`).

## Quick local check

```bash
php artisan test --testsuite=Api --compact
curl -s http://127.0.0.1:8000/api/v1/branding
curl -s -X POST http://127.0.0.1:8000/api/v1/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"student@example.com","password":"…"}'
```
