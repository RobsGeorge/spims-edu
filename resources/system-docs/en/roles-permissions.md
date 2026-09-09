## RoleType enum

`App\Enums\RoleType` string-backed cases:

| Case | Value |
|---|---|
| `SuperAdmin` | `SUPER_ADMIN` |
| `AdministrativeAdmin` | `ADMINISTRATIVE_ADMIN` |
| `AcademicAdmin` | `ACADEMIC_ADMIN` |
| `FinancialAdmin` | `FINANCIAL_ADMIN` |
| `Instructor` | `INSTRUCTOR` |
| `Ta` | `TA` |
| `Student` | `STUDENT` |

Users may hold multiple roles via `UserRole`. Effective access is the **union** of grants.

---

## Super Admin bypass

In `AuthorizeService::authorize`, if `$user->isSuperAdmin()` the method returns immediately — no matrix lookup, no scope check. Super Admin still appears in audit logs when performing mutations through audited services.

---

## Grant levels

| Level | Meaning |
|---|---|
| `F` | Full manage |
| `R` | Read / view |
| `O` | Own — self actions for students, or staffed-offering actions for instructor/TA when the key is offering-scoped |
| *(empty string / null)* | No grant for that role |

Special capabilities are **dedicated keys**, not free-form level names:

| Capability | Example keys |
|---|---|
| Lock grades | `gradebook.lock` (Instructor `F`, not TA) |
| Reopen grades | `gradebook.reopen` (Academic Admin) |
| Issue credentials | `credentials.issue` |
| Assign admin roles | `roles.assign_admin` (empty map → SA only by default) |

---

## Offering-scoped keys (Instructor / TA)

`config/permission_scopes.php`:

- `offering_scoped` — list of keys that require a resource resolving to an offering the actor staffs
- `scoped_roles` — `INSTRUCTOR`, `TA`

Examples of scoped keys: `offerings.content`, `assessments.manage`, `assessments.grade`, `gradebook.lock`, `live.schedule`, `attendance.*` (manage/record/…), `roster.*`, `discussions.moderate`, `announcements.manage`, `projects.manage`, `live_quiz.host`, `feedback.manage`, …

Keys that mean “act on my own behalf” (e.g. `assessments.take`, `finance.pay`, `enrollment.register`) are **not** in `offering_scoped`; membership checks live in domain services.

Admin roles holding `F`/`R` on those keys are **school-wide** (not confined to staffed offerings). An Academic Admin who also teaches is not limited by their instructor scope for unscoped admin grants.

Fail closed: scoped key + scoped-only grants + missing/unscoped resource → 403.

---

## Roles Hub DB override

Default matrix: `config/permissions.php`.

Runtime override: `RolePermission` rows edited in the **Roles hub** (`roles.hub`, requires `roles.manage_matrix`). `AuthorizeService` loads the DB matrix (cached statically per request lifecycle) and prefers it when present.

Changing the matrix is Super Admin territory by default — keep grants minimal.

---

## Empty maps = Super Admin only

Keys with `'permission.key' => []` grant no non-SA role unless the Roles hub adds rows. Notable empty defaults:

| Key | Intent |
|---|---|
| `features.manage` | Feature flag control plane |
| `system_settings.manage` | Dangerous system settings |
| `users.impersonate` | Impersonation |
| `roles.manage_matrix` | Edit the Roles hub matrix |
| `roles.assign_admin` | Assign admin-tier roles |
| `users.reset_password` | Force reset |
| `audit.export` | Export audit |
| `reports.school` | School-wide reporting ops |
| `ops.failed_jobs` | Failed job ops |
| `ops.backup` | Backup ops |

---

## Permission domains (summary)

Grouped from `config/permissions.php` (illustrative — see file for full matrix):

| Domain | Example keys |
|---|---|
| **Identity / access** | `users.manage`, `roles.assign`, `profile.edit_own`, `users.impersonate` |
| **Settings / theme / help** | `settings.manage`, `theme.manage`, `help.manage`, `help.view` |
| **Curriculum** | `programs.*`, `courses.*`, `offerings.manage`, `semesters.*`, `translations.manage`, `assessment_templates.manage`, `grading_schemes.manage` |
| **Admissions** | `admissions.forms`, `admissions.apply`, `admissions.review`, `admissions.decide` |
| **Enrollment / advising** | `enrollment.register`, `enrollment.override`, `enrollment.waitlist`, `advising.*` |
| **Finance** | `offerings.pricing`, `finance.invoices`, `finance.pay`, `finance.manual`, `finance.refunds`, `finance.wallet`, `finance.donate` |
| **Assessment / assignments** | `questions.manage`, `assessments.*`, `assignments.*` |
| **Gradebook** | `gradebook.configure`, `gradebook.lock`, `gradebook.reopen` |
| **Live / attendance / roster** | `live.schedule`, `live.join`, `attendance.*`, `roster.*` |
| **Discussions / announcements** | `discussions.*`, `announcements.*` |
| **Credentials / completion** | `credentials.issue`, `transcript.view`, `completion.*`, `certificate_templates.manage`, `offering.close` |
| **Comms / reports** | `communications.report`, `reports.view`, `email_templates.manage`, `notifications.preferences` |
| **S6E extensions** | `feedback.*`, `events.*`, `live_quiz.*`, `projects.*`, `student_notes.*`, `module_assessment.*` |
| **Super Admin ops** | `audit.view`, `audit.export`, `features.manage`, `system_settings.manage`, `ops.*` |

---

## Navigation gates vs permission keys

Hub visibility uses a small set of representative keys (`programs.manage`, `users.manage`, `offerings.pricing`) plus Super Admin / teach checks. Individual tiles and controller actions still call `AuthorizeService` with the specific key (and resource when scoped).

Related: [backend.md](backend.md), [navigation-map.md](navigation-map.md), client [roles-guide.md](roles-guide.md).
