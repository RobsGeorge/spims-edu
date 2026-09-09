## Controllers and services

Controllers stay **thin**: validate input, call a domain service, return a view or redirect. Business rules live under `app/Services/` (and related support classes), not in controllers or Blade.

Destructive and financially sensitive mutations must go through services wrapped by:

```php
AuditLogWriter::withAudit(/* ... */, function () {
    // mutation
});
```

Every important write (enrollment, payment, grade lock, admissions decision, etc.) lands in `audit_logs`.

---

## Authorization

| Piece | Role |
|---|---|
| `AuthorizeService` | `allows` / `authorize`; Super Admin bypass |
| `config/permissions.php` | Default role → level matrix |
| `config/permission_scopes.php` | Offering-scoped keys; scoped roles `INSTRUCTOR`, `TA` |
| `ResourceScopeResolver` | Maps a resource to offering ID(s) for scope checks |
| `RolePermission` model | Optional DB override from the Roles hub |

### Grant levels

| Level | Meaning |
|---|---|
| `F` | Full |
| `R` | Read |
| `O` | Own / scoped (student self, or instructor staffed offerings) |
| Special verbs | e.g. lock, reopen, issue — represented as dedicated keys |

Calling an offering-scoped key without a resource **fails closed**. An unscoped admin grant on any role wins over a scoped instructor grant for the same user.

Never authorize by comparing `$user->role === 'INSTRUCTOR'` strings in controllers — always permission keys.

---

## Routes — web (`routes/web.php`)

Routes are appended under **track anchors**. Do not reorder the file. Portal/UI tracks:

| Anchor | Ownership |
|---|---|
| `A2-a` | Layouts / partials / hubs / roles-hub |
| `A2-b` | Auth / errors / catalog / announcements / notifications / settings |
| `A2-c` | Learn / courses / assessments / assignments / completion / discussions / attendance / live |
| `A2-d` | Teach / offerings |
| `A2-e` | Finance / credentials / enrollments / grades / advising / applications |
| `A2-f` | Admin offerings / gradebook / assessments / attendance / completion / offering-closing |
| `A2-g` | Admin programs / courses / semesters / academic-years / grading-schemes / users / translations / theme |
| `A2-h` | Admin applications / forms / enrollments / communications / credentials / certificate-templates / events / staff |

Additional marketing / landing tracks exist above the A2 block. Named routes must stay stable for NavigationHub.

---

## Routes — API (`routes/api.php`)

Versioned mobile/API surface under **`/api/v1`**. Auth via **Laravel Sanctum** (token for mobile clients).

Parity rule: no new student or instructor API endpoint merges without shipping or explicitly deferring its web equivalent in `docs/api-web-parity-matrix.md`. Roadmap detail: `docs/academic-roadmap/`.

---

## Identity and OTP

- Registration and email verification use **OTP** tokens (`OtpToken`).
- Forgot-password reuses the OTP flow.
- In local/dev, mailers are optional — OTP may be **logged** instead of emailed and must not block CI.
- Sessions for web; Sanctum tokens for API.

---

## Domain service map (illustrative)

| Domain | Typical service concerns |
|---|---|
| Admissions | Forms, submit, review, matriculate |
| Enrollment | Register, waitlist, holds, drop/withdraw |
| Content / learn | Weeks, gating, completion |
| Assessment | Attempts, autosave, timer, grading |
| Gradebook | Components, lock, reopen, academic records |
| Finance | Invoices, wallet, gateways, refunds |
| Live | Zoom schedule, join window, attendance |
| Credentials | Issue, regenerate, public verify |
| Teach | Staff access, offering desk |
| Help | Article visibility by audience/locale |
| Superadmin | Audit queries, observability, roles matrix |

Exact class names vary by phase; follow existing folders under `app/Services/`.

---

## Settings and feature flags

School settings and theme live in DB (`Setting`, `Theme`). Some Super Admin-only keys (`features.manage`, `system_settings.manage`, …) ship with empty role maps — SA only until the Roles hub grants them.

---

## Testing expectations

- Feature tests under `tests/Feature/` for new flows.
- Locale parity for new strings.
- Permission / scope tests when adding offering-owned keys or models.
- CI must pass before deploy.

Related: [roles-permissions.md](roles-permissions.md), [feature-flows.md](feature-flows.md), [architecture.md](architecture.md).
