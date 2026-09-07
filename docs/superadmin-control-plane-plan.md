# Super Admin control-plane plan

**Status:** plan only — do not implement until a phase is accepted.  
**Audience:** implementers building Super Admin to the same depth as Learn / Teach.  
**Traces to:** spec v0.2 Super Admin role (“everything; admin-role grants; cross-system audit”), `AuthorizeService` bypass, Roles Hub, unused `settings` table.

This is the remaining Super Admin work: complete control of **features**, **themes**, **users**, **school config**, **reporting**, and **auditing**, without turning `/superadmin` into a second copy of every admin CRUD screen.

---

## 1. Verdict

Super Admin today is a **thin ops hub**. Student Learn and instructor Teach are far more complete. The exclusive pages that exist are either a launchpad of deep-links or static info:

| Exclusive surface | What it actually does |
|---|---|
| `/superadmin` | Tile grid. Most tiles open the same UIs `adm` / `aca` / `fin` already use. |
| Roles Hub `/roles-hub` | The one real unique feature: rewrite every permission key for every non–Super Admin role. |
| Audit | Newest 40 rows. No filters, no before/after, no export. Schema already has `before`, `after`, `ip`, `user_agent`, `request_id`. |
| Observability | Counts + queue name + last backup mtime. No actions. |
| Security | Flush *other* sessions — only if `SESSION_DRIVER=database`. |
| Scheduled tasks | Hard-coded list of 3 commands (matches `Kernel` on this branch). Later slices add more (e.g. `communications:fire-reminders`). |
| System tests | Prints `php artisan test --testsuite=…`. Does not run tests. |
| Theme tile | Deep-link to `/admin/theme` (name, site name, 3 token colors × 2 modes, logo *URLs*). |
| People tile | Deep-link to `/admin/users` (create, paginated list, suspend). No search, unsuspend, edit, role revoke, impersonate, or password reset. |

`settings.manage` is in `config/permissions.php` and the `settings` table + `Setting` model exist — **there is no Super Admin (or admin) UI that edits school settings**. Seeded keys (`attendance.default_threshold`, `late_penalty.escalating`, `zoom.concurrent_hosts`) and runtime keys (`enrollment.financial_holds`, `finance.receipt_counter`, `credentials.serial_counter`) are written only by services.

That unused table is the store for feature flags and school config. Do not invent a second key-value table.

---

## 2. Locked decisions

These are the defaults so implementation can start without another design pass. Residual questions are in §14.

| # | Decision | Why |
|---|---|---|
| D1 | Super Admin stays a **control plane**. Domain CRUD (programs, invoices, teach, admissions) stays on existing permission-gated routes. Exclusive `/superadmin/*` pages own school-wide ops only. | Avoids duplicating 50+ screens. Matches current IA. Super Admin already bypasses `AuthorizeService`, so deep-links work. |
| D2 | Keep `EnsureSuperAdmin` + Super Admin **matrix bypass**. New exclusive actions get permission keys with **empty role maps** (same pattern as `roles.manage_matrix`, `feedback.identity.reveal` on later branches). No `if ($user->isSuperAdmin())` in new controllers. | Hard rule. Controllers authorize a key; middleware guards the console door. |
| D3 | Every mutation goes through a service + `AuditLogWriter::withAudit()`. | Hard rule. Especially impersonation, feature toggles, settings, theme activate, session flush, failed-job retry. |
| D4 | Feature kill-switches and school knobs live in `settings`. Registry in `config/features.php` + `config/system_settings.php` (allowlists). | Table already exists. Env remains source of secrets. |
| D5 | **Secrets never appear in the UI** — not even masked. Show “configured / missing” for PayPal, Paymob, Zoom, Vimeo, Gemini, mail password, `SUPERADMIN_*`. | A compromised Super Admin session must not dump `.env`. |
| D6 | Super Admin may impersonate any **non–Super Admin** user. Confirm-gated, time-boxed, audited, sticky “stop impersonating” banner. Distinct from the public `/demo` persona enter (later branch). | Needed for support. Impersonating Super Admin is a privilege-escalation loop. |
| D7 | The Super Admin **role cannot be granted or created via the UI**, including by Super Admin. Break-glass: artisan / seeder / `SUPERADMIN_EMAIL`. `canAssignRole()` today returns `true` for Super Admin → Super Admin; that is a defect to close in SA1. | Spec: one break-glass operator. Roles Hub must not list `SUPER_ADMIN` as an editable role (already true). User create form must not offer the checkbox. |
| D8 | System-tests page stays **documentation**. Do not run PHPUnit, `migrate:fresh`, or arbitrary Artisan from the web. Allowlisted ops only: retry/delete a failed job, trigger `spims:backup-database`, flush sessions. | Running the suite or migrations on production is destructive. |
| D9 | Administrative Admin keeps day-to-day **people** (`users.manage`) and **theme** (`theme.manage`). Super Admin exclusive: feature flags, school-config dangerous knobs, impersonation, audit export, Roles Hub, failed-job actions, backup trigger. | School staff should not need Super Admin to create an instructor or change a logo. |
| D10 | Money stays integer minor units. All Super Admin strings in `lang/{ar,en,fr}/superadmin.php` (+ feature/settings keys). Additive migrations only. No npm. | Hard rules. |
| D11 | Out of phase (stay in `PARKING-LOT.md`): Reverb, WhatsApp driver, lockdown browser, native apps, multi-tenant, writing `.env` from the UI, rotating `SUPERADMIN_PASSWORD` from the UI, parent/guardian roles. | Do not dilute this plan. |

---

## 3. Target information architecture

Restructure `NavigationHub::superadminSections()` from one flat “exclusive” grid into **six sections**. Existing deep-links stay; new exclusive pages fill the gaps.

```
/superadmin                         Hub (sectioned tiles)
/superadmin/features                Feature flags (SA3)
/superadmin/config                  School settings allowlist (SA3)
/superadmin/people                  People directory (SA1) — or keep /admin/users and deepen it
/superadmin/people/{user}           Person dossier (SA1)
/superadmin/people/{user}/impersonate  POST, confirm (SA1)
/impersonation/stop                 POST, any impersonating session (SA1)
/superadmin/theme                   Theme studio (SA4) — or deepen /admin/theme
/superadmin/reports                 School reports hub (SA5)
/superadmin/reports/{report}        One report + CSV (SA5)
/superadmin/audit                   Audit explorer (SA2)
/superadmin/audit/{log}             Detail: before/after JSON (SA2)
/roles-hub                          Unchanged owner; polish in SA0
/superadmin/security                Sessions + per-user revoke (SA1/SA6)
/superadmin/observability           Counts + failed jobs + backup action (SA6)
/superadmin/scheduled-tasks         Read schedule from Kernel (SA6)
/superadmin/system-tests            Keep as runbook (no execute)
/superadmin/feedback-reveals        Keep when that route exists (later branch)
```

Deep-link tiles that remain (not rebuilt):

- Academics hub, finance ops, credentials, admissions/enrollment admin, communications report, email templates, events, Teach — whatever `Route::has()` is true after other branches merge.

Sidebar: Super Admin already has a shield nav item. After SA1, when impersonating, the topbar shows a persistent danger banner (`You are viewing as {name}`) and a Stop control. Do not hide the banner on Learn/Teach pages.

---

## 4. Authz additions

Add keys with **empty role maps** (Super Admin bypass only), unless noted.

| Key | Who | Purpose |
|---|---|---|
| `features.manage` | SA only | Toggle feature flags |
| `system_settings.manage` | SA only | Edit allowlisted school settings (dangerous knobs) |
| `users.impersonate` | SA only | Start/stop impersonation |
| `users.unsuspend` | SA + optional ADM `F` later | Restore `SUSPENDED` → `ACTIVE` |
| `users.reset_password` | SA only first; ADM later if needed | Force OTP / temp password |
| `audit.export` | SA only | CSV / JSON export of filtered audit |
| `reports.school` | SA only | School-wide reports hub |
| `ops.failed_jobs` | SA only | Retry / delete failed jobs |
| `ops.backup` | SA only | Trigger on-demand dump |

Keep existing:

- `users.manage`, `roles.assign` — ADM (and SA bypass)
- `roles.assign_admin`, `roles.manage_matrix` — empty maps (SA only)
- `audit.view` — ADM/ACA/FIN `R` (they may use a **filtered** audit view later; SA2 ships the explorer behind `superadmin` middleware first)
- `settings.manage` — ADM `F` today but unused. **Do not** point the new school-config form at this key. Leave it for future ADM-safe knobs (attendance threshold). Super Admin config uses `system_settings.manage`.
- `theme.manage` — ADM `F`

Close in SA1:

- `AuthorizeService::canAssignRole()` must refuse `RoleType::SuperAdmin` for **every** actor, including Super Admin.
- User create/edit forms must not list `SUPER_ADMIN`.
- Roles Hub must refuse unknown keys and refuse `SUPER_ADMIN` as a target (already skipped in `syncFromConfig`).

Controllers stay thin. New services:

- `App\Services\SuperAdmin\FeatureFlagService`
- `App\Services\SuperAdmin\SystemSettingService`
- `App\Services\SuperAdmin\ImpersonationService`
- `App\Services\SuperAdmin\AuditExplorerService`
- `App\Services\SuperAdmin\SchoolReportService`
- `App\Services\Admin\UserAdminService` (extend — unsuspend, update, revoke role, reset password)

`UserAdminService` already authorizes `users.manage` / `roles.assign`. Keep that. Impersonation is a separate service so ADM cannot reach it by sharing the user form.

---

## 5. Gap register

Severity: **Absent** / **Thin** / **Present**.

### 5.1 Features (module kill-switches) — Absent

Nothing school-wide can be turned off from the UI. The only kill-switch pattern in the product is `config('spims.demo_console')` / `DEMO_CONSOLE` on the later demo-console branch.

**Need:** a registry of features. Each flag hides nav + 404s routes (middleware), and is audited on change.

| Flag key | Default | What it gates |
|---|---|---|
| `features.public_catalog` | on | Guest catalog / apply CTAs |
| `features.registration` | on | Public register + OTP signup |
| `features.admissions` | on | Apply / review / forms |
| `features.enrollment` | on | Student register / waitlist (admin override stays) |
| `features.learn` | on | Course player, grades, assignments submit |
| `features.teach` | on | Teach hub |
| `features.finance_checkout` | on | Student pay / donate (FIN admin stays) |
| `features.live` | on | Zoom schedule / join |
| `features.discussions` | on | Boards / posts |
| `features.surveys` | on | When S6E is present |
| `features.events` | on | When S6E is present |
| `features.live_quiz` | on | When S6E is present |
| `features.projects` | on | When S6E is present |
| `features.credentials_public` | on | Public `/verify/{token}` |
| `features.demo_console` | follow env, overridable | Public `/demo` when that branch is merged |

Implementation notes:

- `config/features.php` lists key, default, label, routes/nav hooks.
- `FeatureFlagService::enabled(string $key): bool` reads `settings` then default.
- `EnsureFeatureEnabled` middleware: `abort(404)` when off (do not leak “this exists but is disabled” to students).
- `NavigationHub::*Links()` skip disabled features.
- Super Admin hub always visible; flags do not hide the control plane from Super Admin.
- Demo console flag, when present, must still honor `DEMO_CONSOLE=false` in PHPUnit (`phpunit.xml`).

### 5.2 Themes — Thin

Present: `themes` table, `Theme` model, `ThemeTokens` (full Sacred Academic palette), `ThemeAdminService` (audited), editor for **name, site_name, 3 colors × light/dark, logo/favicon URLs**, one active row. Seeder activates “Sacred Academic” and retires parchment “Liturgical”.

Gaps:

| Gap | Fix (SA4) |
|---|---|
| Only 6 colors editable; `ThemeTokens` has ~25 per mode | Full token studio grouped (field, type, nav, semantic). Color + text inputs. |
| No theme list / create / duplicate / activate | Index of presets. Activate is audited. One `is_active`. |
| Logos are URL-only | Upload via existing `ObjectStorageService` + `/api/uploads`; store path; branding API already serves URLs. |
| No reset to Sacred Academic defaults | Confirm + write `ThemeTokens::defaults()`. |
| No live shell preview | iframe or in-page chip of sidebar/button/alert using CSS variables. |
| Super Admin tile is a deep-link | Keep `/admin/theme` for ADM; add Super Admin “Theme studio” that lists presets. Same service. |
| `theme-system` historically forced dark | `ThemeTokens::inlineStyleBlock()` already follows `prefers-color-scheme`. Verify in SA4 tests; do not re-break. |

Do not add a second theme CSS build. Blade + `spims-theme.css` + inline token override stays the only path (no npm).

### 5.3 Users — Thin

Present: create (email, name, password, role checkboxes, `is_reviewer`), paginated list, show (financial hold + enrollment override only), suspend. Tests: ADM can create; ADM cannot assign Super Admin; student 403.

Gaps:

| Gap | Fix (SA1) |
|---|---|
| No search / filter | Email, name, role, status, locale. |
| No unsuspend / activate pending | `UserStatus::Suspended` → `Active`; `Pending` → verify or force-activate (audited). |
| No edit profile | Name, phone, locale, reviewer flag. Email change is confirm + audit (optional phase; can park if risky). |
| No role revoke | Remove `user_roles` row; `roles.assign` / `roles.assign_admin`. Cannot leave a user with zero roles without an explicit “student only” default. |
| Create form lists every `RoleType` including Super Admin | Filter assignable roles through `canAssignRole()`. |
| Super Admin can assign Super Admin | Close `canAssignRole()` (D7). |
| Show page is enrollment-only | Dossier: roles, status, locale, last login, enrollments, invoices summary, recent audit by this actor, sessions. |
| No impersonate | SA1 exclusive. |
| No admin-initiated password reset | Issue OTP (`OtpPurpose` password reset) or set a one-time password and force change. Never display a shared demo password on Super Admin pages. |
| No per-user session revoke | If session driver is database, delete that user’s rows except current SA session. |
| No “cannot suspend yourself / the seeded Super Admin” | Guard both. |

Public `/demo` impersonation (later branch) stays guest-only and `@spims.test` only. Super Admin impersonation is authenticated, any real user except Super Admin, and writes `users.impersonate.start` / `.stop`.

### 5.4 Configs — Absent UI, Present store

| Layer | Store | Super Admin UI |
|---|---|---|
| Safe school knobs | `settings` table | **Build (SA3)** |
| Feature flags | `settings` (`features.*`) | **Build (SA3)** |
| Secrets & infra | `.env` / `config/*` | Read-only “configured?” (SA3) |
| Permission matrix | `role_permissions` | Roles Hub (present) |
| Theme tokens | `themes` | SA4 |
| Per-user prefs | `users.*` + notification prefs | Settings page (present, not Super Admin) |

**Allowlist** (`config/system_settings.php`) — editable:

| Key | Type | Notes |
|---|---|---|
| `school.default_locale` | `ar\|en\|fr` | Guest + mail fallback. App locale is still per-user. |
| `school.timezone` | timezone string | Today `config/app.php` is hard-coded `UTC`. SA3 may write a setting that a service reads for display/schedule labels; changing PHP `date.timezone` at runtime is optional and must be tested. |
| `school.registration_open` | bool | Mirrors / overlaps `features.registration`. Prefer the feature flag; keep this only if we need a copy for admissions copy. **Pick one in SA3 — recommended: feature flag only.** |
| `attendance.default_threshold` | int | Already seeded; already read. Surface in UI. |
| `late_penalty.escalating` | int[] | Already seeded; already read. |
| `zoom.concurrent_hosts` | int ≥ 1 | Already seeded; already read. Multi-host UI stays parked. |
| `audit.retention_days` | int | SA2 prune command; default 365. |
| `backup.retention_days` | int | Display + override of `BACKUP_RETENTION_DAYS`. |
| `mail.from_name` | string | Display name only. Address stays env. |
| `demo.console_enabled` | bool | Override when demo console exists; PHPUnit still forces false. |

**Never editable (status only):**

`APP_KEY`, `DB_*`, `REDIS_*`, `MAIL_PASSWORD`, `MAIL_USERNAME`, `SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD`, PayPal / Paymob / Zoom / Vimeo / Gemini secrets, `BACKUP_PATH` (path shown read-only).

**Never editable from UI (internal counters):**

`finance.receipt_counter`, `credentials.serial_counter`, `enrollment.financial_holds` (holds stay on the person dossier).

`settings.manage` remains unused by the Super Admin form on purpose (D9). A later ADM “school calendar knobs” page can reuse it for attendance threshold only.

### 5.5 Reporting — Thin / fragmented

Present on this base or later branches:

- Finance: outstanding + paid by currency (no date range, no CSV).
- Communications: report + CSV (later).
- Attendance: per-offering report + roster CSV (later).
- Surveys: per-survey report (later).
- Gradebook: CSV export.
- Observability: raw counts.

**Absent:** one Super Admin place that answers “how is the school doing?”

SA5 reports hub (`reports.school`):

| Report | Source | Export |
|---|---|---|
| Census | Users by role × status × locale | CSV |
| Admissions funnel | Applications by status / form | CSV |
| Enrollment | Active / waitlist / dropped by offering | CSV |
| Finance rollup | Reuse outstanding/paid; add date range | CSV |
| Attendance school-wide | Rates by offering (when S3 present) | CSV |
| Communications volume | Delivery log counts (when S2 present) | Link + CSV |
| Audit volume | Actions per day / top actors | CSV |
| Queue health | Failed jobs, pending jobs | — |

Each report is a service method, not a live SQL string in a Blade file. Date range default: current semester if one is marked current, else last 90 days. Money formatted via `Money`, never floats.

Deep-link out to existing FIN/ACA reports rather than forking them.

### 5.6 Auditing — Thin UI, Present ledger

Schema is sufficient (`audit_logs` indexes on actor+time, entity, action+time). Writer is used on mutations.

UI gaps (SA2):

- Filters: actor (email), action prefix (`users.`, `theme.`, `roles.`), entity type/id, date from/to, request id.
- Detail page: `before` / `after` JSON, IP, user agent, request id, actor role.
- CSV export (`audit.export`), cap (e.g. 10k rows) + flash if truncated.
- No delete, no edit. Append-only.
- Retention: scheduled `spims:prune-audit-logs` using `audit.retention_days`. **Do not** prune `users.impersonate.*`, `roles.hub.*`, `features.*`, `system_settings.*` (control-plane actions) — or prune them on a longer floor (e.g. 3× retention).
- ADM `audit.view` can later get a redacted list (no IP/UA). Not in SA2.

### 5.7 Ops / security / observability — Thin

| Surface | Gap | Phase |
|---|---|---|
| Sessions | File/cookie/redis drivers cannot flush; no per-user revoke | SA1 (per-user if DB), SA6 (document others) |
| Failed jobs | Count only | SA6 retry / delete, audited |
| Backups | mtime only | SA6 “run backup now” → `spims:backup-database`, audited |
| Schedule | Hard-coded 3 rows; Kernel may have more | SA6 read `Illuminate\Console\Scheduling\Schedule` |
| Health | Public JSON `/health` | Keep public; Super Admin page embeds status |
| System tests | Print commands | Keep; do not execute |
| Feedback identity reveals | Exclusive on later branch | Keep; tile already Super Admin only |

---

## 6. Phases

Each phase is one PR-sized slice: tests first, `pint` on owned files, no `migrate:fresh` on shared DBs.

### SA0 — Console IA + safety locks

**Goal:** Hub is navigable as a control plane; Super Admin cannot mint Super Admins; Roles Hub polish.

- Sectioned `superadminSections()` (People, Access, Appearance, School, Evidence, Ops) even if some tiles 404 until later phases — **prefer hide until route exists** (`Route::has`).
- Close `canAssignRole()` Super Admin loophole.
- Filter Super Admin out of user-create role checkboxes.
- Roles Hub: search keys, “reset role to `config/permissions.php`” (confirm + `syncFromConfig` scoped to one role), localized group titles.
- Permission keys added to `config/permissions.php` (empty maps) so Roles Hub shows them.
- Tests: `SuperAdmin/ControlPlaneSafetyTest` — student 403 on `/superadmin`; ADM 403 on Roles Hub; Super Admin cannot assign Super Admin; create-user with `SUPER_ADMIN` errors.

**Done when:** hub sections render; safety tests green; no new mutations except the assign-role lock.

### SA1 — People control

**Goal:** Super Admin (and ADM where noted) can find, edit, suspend/unsuspend, assign/revoke roles, reset password, impersonate.

- Search/filter index (deepen `/admin/users` — do not fork a second list unless the Super Admin dossier needs extra columns).
- `UserAdminService`: `update`, `unsuspend`, `revokeRole`, `forceActivate`.
- `ImpersonationService` + session key `impersonator_id` + middleware to restore. Banner partial in `layouts.app`. Add a trusted session helper on `AuthService` (this branch has password login only; do not reuse the public demo enter if that lands later).
- Per-user dossier.
- Tests: impersonate student → dashboard as student → stop → back as SA; cannot impersonate SA; unsuspend audited; ADM cannot impersonate; suspend-self refused.

**Done when:** a Super Admin can support a real user end-to-end without knowing their password, and the audit log shows start/stop.

### SA2 — Audit explorer

**Goal:** Cross-system audit is usable.

- Filters + detail + export.
- Prune command + retention setting (read default if SA3 not landed).
- Tests: filter by action prefix; export CSV headers; prune keeps control-plane actions; student 403.

**Done when:** Super Admin can answer “who changed this offering yesterday?” from the UI.

### SA3 — Features + school config

**Goal:** Complete control of features and safe configs.

- `config/features.php`, `FeatureFlagService`, `EnsureFeatureEnabled`.
- Wire 4–6 flags that exist on this branch first (`registration`, `public_catalog`, `learn`, `teach`, `finance_checkout`, `live`). Add S6E flags when those routes exist.
- `SystemSettingService` + allowlist form. Validation per type. Audit before/after values (never secrets).
- Integrations panel: configured/missing for payment, Zoom, mail, Vimeo, Gemini.
- Tests: turning `features.learn` off 404s course player for a student and hides the Learning hub link; Super Admin still opens `/superadmin`; setting attendance threshold is read by `AttendanceService`; unknown setting key 422.

**Done when:** Super Admin can close registration and hide Learn without a deploy, and cannot see `MAIL_PASSWORD`.

### SA4 — Theme studio

**Goal:** Complete control of appearance.

- Theme index: Sacred Academic + any custom; activate; duplicate; reset defaults.
- Full token editor (all keys in `ThemeTokens::defaults()`).
- Logo/favicon upload.
- Preview.
- Tests: activate B deactivates A; branding/CSS variables reflect tokens; ADM can still edit active theme; reset restores defaults.

**Done when:** Super Admin can ship a school’s burgundy/gold (or a checked deviation) without editing CSS.

### SA5 — Reports hub

**Goal:** One evidence desk.

- Hub + census + finance rollup (date range) + audit volume. Add admissions/enrollment/attendance/communications when tables exist.
- CSV download, locale-aware headers, money via `Money`.
- Tests: census counts match factories; finance totals are integers; student 403.

**Done when:** Super Admin can export a census and a finance snapshot for a date range.

### SA6 — Ops desk

**Goal:** Observability is actionable.

- Failed jobs table + retry/delete.
- Backup now (queue the command; do not block the request on `pg_dump` if it is slow — or sync with a timeout and flash).
- Schedule read from Kernel (include `communications:fire-reminders` when present).
- Document session flush limits when driver ≠ database.
- Tests: retry increments attempt / deletes row; backup action writes audit; non-SA 403.

**Done when:** Super Admin can clear a stuck job and kick a backup without SSH.

---

## 7. Data & migrations

Additive only.

| Change | Phase | Notes |
|---|---|---|
| None required for flags/settings | SA3 | Reuse `settings` |
| None required for audit explorer | SA2 | Indexes already exist |
| `users.last_login_at` nullable timestamp | SA1 | Optional; or derive from latest `auth.login` audit |
| Theme upload paths | SA4 | Existing nullable URL columns can store storage URLs |
| `impersonation` not a table | SA1 | Session + audit is enough |

If `last_login_at` is skipped, the dossier shows the latest `auth.login` / `auth.loginAs` audit row.

---

## 8. Localization & RTL

- New strings in `lang/{ar,en,fr}/superadmin.php`, `features.php`, `system_settings.php`.
- Permission keys stay machine IDs in Roles Hub; labels can be translated.
- Reports CSV: UTF-8 BOM for Excel; Arabic headers when locale is `ar`.
- Impersonation banner must be readable in RTL (icon + Stop on the logical start).

---

## 9. Test plan (CI)

New suite: `tests/Feature/SuperAdmin/`.

| Test class | Phase | The test that matters |
|---|---|---|
| `ControlPlaneSafetyTest` | SA0 | Super Admin role cannot be assigned; non-SA 403 on exclusive routes |
| `PeopleDirectoryTest` | SA1 | Search, unsuspend, revoke role |
| `ImpersonationTest` | SA1 | Start/stop; cannot target Super Admin; audit actions |
| `AuditExplorerTest` | SA2 | Filter + export + 403 |
| `FeatureFlagTest` | SA3 | Flag off 404s gated route; hub tile hidden; SA console remains |
| `SystemSettingTest` | SA3 | Allowlist write; unknown key rejected; secret keys absent from HTML |
| `ThemeStudioTest` | SA4 | Activate / reset / tokens applied |
| `SchoolReportTest` | SA5 | Census + finance integers |
| `OpsDeskTest` | SA6 | Failed job retry audited |

Also extend `UserAdminTest`, `ThemeEditorTest`, `RolesHubTest`, `PortalHubsTest`.

PHPUnit must set feature defaults on (or explicitly seed) so existing Learn/Teach tests do not 404.

---

## 10. Safety / trial-domain note

The Super Admin door is `/login` with `SUPERADMIN_EMAIL` / `SUPERADMIN_PASSWORD`. It is **not** on `/demo`.

`AuthorizeService` bypasses the entire matrix for this user. Roles Hub can grant `finance.refunds` to a student. After SA3, Super Admin can turn off Learn for the whole school.

Do not give this login to a school on a trial domain. Rotate `SUPERADMIN_PASSWORD` on real hosts (ops, not UI — D11).

---

## 11. What later branches add (do not block SA0–SA2)

If this plan lands on `feat/authz-scope-and-api-foundation` before other slices merge, implementers must re-scan `routes/web.php` and add tiles/flags:

| Later slice | Super Admin must govern |
|---|---|
| S2 communications | Email templates + communications report tiles; `communications:fire-reminders` on the schedule page |
| S3 attendance / roster | Attendance policy setting (already in `settings`); school-wide attendance report |
| S4 credentials PDFs | Certificate templates tile (already a deep-link when the route exists) |
| S6E surveys / events / live quiz / projects | Feature flags + hub tiles + feedback identity reveals (already exclusive) |
| Public demo console | `features.demo_console`; never list Super Admin as a persona |

---

## 12. Explicitly out of scope

Move or keep in `PARKING-LOT.md` — do not build in SA0–SA6:

- Run PHPUnit or `migrate` from the browser
- Edit `.env` or rotate Super Admin password in the UI
- WhatsApp driver, Reverb, lockdown browser, native apps, multi-tenant
- Parent / guardian role
- Arbitrary Artisan command runner
- Deleting audit rows
- Hollow / seeded professional credentials

---

## 13. Suggested implementation order

```
SA0 safety + IA
  └── SA1 people + impersonation
        └── SA2 audit explorer
              ├── SA3 features + config
              ├── SA4 theme studio        (parallel with SA3)
              └── SA5 reports             (after SA2; reads audit + finance)
                    └── SA6 ops desk
```

If only three phases ship: **SA0, SA1, SA2**. They turn Super Admin from a tile page into the role the spec describes (grants, people, cross-system audit). SA3 is the next highest leverage (features + config). Theme and reports are visibility; ops is convenience.

---

## 14. Residual questions (non-blocking)

Recommended answers are already locked in §2. Revisit only if product rejects them:

1. **Email change on a dossier** — recommended: park. Too easy to hijack OTP login.
2. **ADM audit view** — recommended: SA2 Super Admin only; ADM later, redacted.
3. **Timezone** — recommended: store `school.timezone` for labels; keep PHP `UTC` until a dedicated scheduling phase.
4. **People URL** — recommended: deepen `/admin/users` rather than `/superadmin/people`, so ADM and SA share one directory; impersonate POST stays under `/superadmin` + middleware.

---

## 15. Key files (today)

| Path | Role |
|---|---|
| `app/Http/Controllers/SuperAdmin/SuperAdminController.php` | Exclusive pages |
| `app/Http/Middleware/EnsureSuperAdmin.php` | Door |
| `app/Support/AuthorizeService.php` | Bypass + `canAssignRole()` |
| `app/Support/NavigationHub.php` | Tiles + hubs |
| `app/Http/Controllers/RolesHub/RolesHubController.php` | Matrix UI |
| `app/Services/Rbac/RolePermissionService.php` | Matrix writes |
| `app/Services/Admin/UserAdminService.php` | Create / assign / suspend |
| `app/Services/Admin/ThemeAdminService.php` | Theme write |
| `app/Support/ThemeTokens.php` | Sacred Academic defaults |
| `app/Models/Setting.php` + `database/seeders/SettingsSeeder.php` | Unused UI store |
| `config/permissions.php` | Keys |
| `resources/views/superadmin/*` | Thin Blade |
| `lang/*/superadmin.php` | Copy |
| `tests/Feature/Portal/PortalHubsTest.php` | Hub smoke |
| `tests/Feature/Rbac/RolesHubTest.php` | Matrix |
| `tests/Feature/Admin/UserAdminTest.php` | People |
| `tests/Feature/Admin/ThemeEditorTest.php` | Theme |
