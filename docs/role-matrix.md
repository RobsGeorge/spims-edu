# SPIMS role matrix

Complete, generated-from-source reference for **who may do what** in SPIMS.

Sources of truth, in the order the runtime consults them:

| Layer | File | Role |
|---|---|---|
| Roles | `app/Enums/RoleType.php` | The seven static roles |
| Shipped defaults | `config/permissions.php` | Permission key → role → level |
| Live overrides | `role_permissions` table (Roles Hub) | Replaces the config matrix wholesale once populated |
| Scope rules | `config/permission_scopes.php` | Which keys are offering-scoped, and for which roles |
| Scope resolution | `app/Support/Scope/ResourceScopeResolver.php` | Resource → offering id(s) |
| Guard | `app/Support/AuthorizeService.php` | The only place a decision is made |

Product intent: `docs/spims-spec-summary.md` §Roles. This file is the mechanical companion —
every key, every level, every scope caveat.

**Counts as shipped:** 127 permission keys · 45 offering-scoped · 12 Super-Admin-only (empty role map).

---

## 1. The seven roles

| Enum case | Value | Roles Hub label | Owns |
|---|---|---|---|
| `RoleType::SuperAdmin` | `SUPER_ADMIN` | *(not a matrix row)* | Everything. Bypasses `AuthorizeService` entirely |
| `RoleType::AdministrativeAdmin` | `ADMINISTRATIVE_ADMIN` | Administrative admin | Admissions, application forms, semesters, enrollment overrides, users, branding |
| `RoleType::AcademicAdmin` | `ACADEMIC_ADMIN` | Academic admin | Programs, courses, offerings, templates, translations, grade **reopen**, completion |
| `RoleType::FinancialAdmin` | `FINANCIAL_ADMIN` | Financial admin | Pricing, invoices, manual payments, refunds, wallet |
| `RoleType::Instructor` | `INSTRUCTOR` | Instructor | Their offerings: content, assessments, grading, grade **lock**, announcements, advising holds |
| `RoleType::Ta` | `TA` | Teaching assistant | Their offerings: content, grading, discussions, attendance — **no grade lock** |
| `RoleType::Student` | `STUDENT` | Student | Apply, enroll, learn, submit, sit exams, pay, view own grades and transcript |

A person may hold several roles. **Effective permission = union**, with one refinement:
an unscoped grant from *any one* role wins outright, so an Academic Admin who also teaches is
not confined to the offerings they are staffed on (`AuthorizeService::authorize()`).

### Grant counts (shipped defaults)

| Role | Keys held | Level breakdown |
|---|--:|---|
| `ADMINISTRATIVE_ADMIN` | 32 | 19 `F`, 9 `R`, 3 `O`, 1 `issue` |
| `ACADEMIC_ADMIN` | 76 | 60 `F`, 11 `R`, 4 `O`, 1 `reopen` |
| `FINANCIAL_ADMIN` | 12 | 5 `F`, 4 `R`, 3 `O` |
| `INSTRUCTOR` | 61 | 56 `O`, 4 `R`, 1 `lock` |
| `TA` | 45 | 40 `O`, 5 `R` |
| `STUDENT` | 29 | 25 `O`, 4 `R` |

### How roles are granted and revoked

- Assignment happens in `UserAdminService::assignRole()` / `revokeRole()`, gated by `roles.assign`.
- `AuthorizeService::canAssignRole()` is the hard rule, and it is **not** matrix-driven:
  - `SUPER_ADMIN` can never be granted from the UI — by anyone, Super Admin included.
    It exists only via `SuperAdminSeeder` / `SUPERADMIN_EMAIL`.
  - `ADMINISTRATIVE_ADMIN` can be granted only by Super Admin.
  - Everything else needs `roles.assign` (Administrative Admin holds it, level `F`).
- Revoking a user's last role auto-assigns `STUDENT` (audited as `roles.assign_default_student`).
  The same fallback runs in `forceActivate()` and at self-registration (`AuthService`).

---

## 2. Levels

`AuthorizeService::levelGrants()` treats a level as a *grant or no grant* — the string itself carries
documentation value, not extra enforcement.

| Level | Meaning | Grants? |
|---|---|:--:|
| `F` | Full — create, read, update, delete | yes |
| `R` | Read-only in practice (the guard does not distinguish; the owning service does) | yes |
| `O` | "Own" — bounded by scope, see §3 | yes |
| `lock` | Instructor's grade lock (`gradebook.lock`) | yes |
| `reopen` | Academic Admin's grade reopen (`gradebook.reopen`) | yes |
| `issue` | Administrative Admin's credential issuance (`credentials.issue`) | yes |
| `submit` | Accepted by the guard but **unused** in the shipped matrix | yes |
| *(absent / empty string)* | Denied | no |

> `R` does not by itself prevent a write. A key granted at `R` still passes `authorize()`; the
> read-only intent lives in the service and in which routes the role can reach. Treat `R` as
> documentation, and enforce read-only by not exposing the write route.

---

## 3. Scope

The **Scope** column in §5 says how an `O` grant on that key is bounded. `F` and `R` grants are
always school-wide.

| Scope | Bounded by | Enforced in |
|---|---|---|
| **Offering** | Actor is staffed on the offering behind the resource (`offering_staff` row) | `AuthorizeService` + `ResourceScopeResolver`, for `INSTRUCTOR` and `TA` only |
| **Self** | The actor's own record — own submission, own invoice, own reservation | The owning service (enrollment / delivery / ownership checks) |
| **Advisee / self** | Assigned advisee (`advisor_assignments`), or the student's own record | `AdvisingService` |
| **School** | Not bounded | — |
| **Super Admin only** | Empty role map; only the bypass reaches it | `AuthorizeService::authorize()` early return |

Three rules that matter:

1. **Offering scope applies only to `INSTRUCTOR` and `TA`** (`permission_scopes.scoped_roles`).
   A `STUDENT` `O` on an offering-scoped key such as `completion.view` or `projects.view` is *self*
   scope, resolved by the service that owns the data.
2. **Offering-scoped keys fail closed.** Calling one without a resource throws, even for an actor
   who holds it everywhere. A scoped action reached with no resource is a bug at the call site.
3. **A model absent from `ResourceScopeResolver::offeringIdsFor()` resolves to no offering**, which
   fails closed and surfaces as an unexplained 403. Adding an offering-owned model means adding
   an arm to that `match`.

`RequirePermission` middleware (`permission:<key>`, 252 uses in `routes/web.php`) auto-binds the
resource: for an offering-scoped key it walks the route's bound parameters and hands the guard the
first one that resolves to an offering. Routes whose model is not in the resolver get no resource
and therefore fail closed.

---

## 4. Where the matrix actually lives at runtime

`AuthorizeService::levelFor()` prefers the database:

```
role_permissions table exists AND has ≥1 row?
  → the DB matrix is authoritative, config/permissions.php is ignored entirely
  → else fall back to config/permissions.php
```

Consequences worth knowing before editing anything:

- `DatabaseSeeder` runs `RolePermissionSeeder`, so **every seeded environment (staging, demo, most
  dev boxes) is DB-driven**. On those, editing `config/permissions.php` alone changes nothing.
- The switch is all-or-nothing, not per-key. A **new key added to config is denied for every
  non-Super-Admin role** until `php artisan permissions:sync` (or `--force`) writes it in.
- Roles Hub (`roles.manage_matrix`, Super Admin only) replaces a role's grants from checkboxes.
  Newly checked keys default to the config level, or `F` when the config has no level for that role.
  Keys not present in `config/permissions.php` are silently dropped on save.
- "Reset to shipped defaults" (`resetRoleFromConfig`) restores one role from config. Both writes
  are audited and flush the static matrix cache (`forgetMatrixCache()`).
- `SUPER_ADMIN` is never written to `role_permissions` and is not editable in Roles Hub.

---

## 5. Full matrix

Columns: **ADM** = Administrative Admin · **ACA** = Academic Admin · **FIN** = Financial Admin ·
**INS** = Instructor · **TA** = Teaching assistant · **STU** = Student.
Super Admin holds everything by bypass and has no column. `–` = denied.

Groups follow the Roles Hub grouping (`RolePermissionService::groupedPermissionKeys()`).

### Academic standing (`academic_standing.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `academic_standing.manage` | School | `F` | `F` | – | – | – | – |

### Admissions (`admissions.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `admissions.apply` | Self | – | – | – | – | – | `O` |
| `admissions.decide` | School | `F` | – | – | – | – | – |
| `admissions.forms` | School | `F` | – | – | – | – | – |
| `admissions.review` | School | `F` | – | – | – | – | – |

### Advising (`advising.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `advising.assign` | Advisee / self | `F` | `F` | – | – | – | – |
| `advising.hold` | Advisee / self | – | `F` | – | `O` | – | – |
| `advising.view` | Advisee / self | – | `F` | – | `O` | – | `O` |

### Announcements (`announcements.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `announcements.manage` | Offering | – | `F` | – | `O` | `O` | – |
| `announcements.publish` | Offering | – | `F` | – | `O` | – | – |
| `announcements.view` | Self | `R` | `R` | `R` | `O` | `O` | `O` |

### Assessment templates (`assessment_templates.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `assessment_templates.manage` | School | – | `F` | – | – | – | – |

### Assessments (`assessments.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `assessments.announce_results` | Offering | – | `F` | – | `O` | – | – |
| `assessments.clear_termination` | School | – | `F` | – | – | – | – |
| `assessments.grade` | Offering | – | `R` | – | `O` | `O` | – |
| `assessments.manage` | Offering | – | `F` | – | `O` | `O` | – |
| `assessments.proctor` | Offering | – | – | – | `O` | – | – |
| `assessments.take` | Self | – | – | – | – | – | `O` |

### Assignments (`assignments.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `assignments.dashboard` | Offering | – | `F` | – | `O` | `O` | – |
| `assignments.grade` | Offering | – | `R` | – | `O` | `O` | – |
| `assignments.manage` | Offering | – | `F` | – | `O` | `O` | – |
| `assignments.mark_received` | Offering | – | – | – | `O` | `O` | – |
| `assignments.remind` | Offering | – | – | – | `O` | `O` | – |
| `assignments.submit` | Self | – | – | – | – | – | `O` |

### Attendance (`attendance.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `attendance.configure` | School | – | `F` | – | – | – | – |
| `attendance.edit` | Offering | – | `F` | – | `O` | – | – |
| `attendance.manage` | Offering | – | `F` | – | `O` | `O` | – |
| `attendance.record` | Offering | – | – | – | `O` | `O` | – |
| `attendance.reopen` | School | – | `F` | – | – | – | – |
| `attendance.report` | Offering | – | `F` | – | `O` | `O` | – |
| `attendance.self_check_in` | Self | – | – | – | – | – | `O` |
| `attendance.view_all` | Offering | – | `F` | – | `O` | `O` | – |
| `attendance.view_own` | Self | – | – | – | – | – | `O` |

### Audit (`audit.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `audit.export` | Super Admin only | – | – | – | – | – | – |
| `audit.view` | School | `R` | `R` | `R` | – | – | – |

### Certificate templates (`certificate_templates.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `certificate_templates.manage` | School | – | `F` | – | – | – | – |

### Communications (`communications.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `communications.report` | School | `R` | `R` | – | – | – | – |

### Completion (`completion.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `completion.configure` | Offering | – | `F` | – | `O` | `O` | – |
| `completion.view` | Offering | – | `F` | – | `O` | `O` | `O` |

### Courses (`courses.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `courses.flag_interest` | Self | – | – | – | – | – | `O` |
| `courses.interest_counts` | School | `R` | `R` | – | – | – | – |
| `courses.manage` | School | – | `F` | – | – | – | – |
| `courses.view` | School | `R` | `F` | – | `R` | `R` | `R` |

### Credentials (`credentials.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `credentials.issue` | School | `issue` | `F` | – | – | – | – |

### Discussions (`discussions.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `discussions.configure` | Offering | – | `F` | – | `O` | `O` | – |
| `discussions.grade` | Offering | – | `R` | – | `O` | `O` | – |
| `discussions.moderate` | Offering | – | `F` | – | `O` | `O` | – |
| `discussions.post` | Self | – | `O` | – | `O` | `O` | `O` |
| `discussions.thread` | Self | – | `F` | – | `O` | `O` | `O` |

### Email templates (`email_templates.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `email_templates.manage` | Offering | – | `F` | – | `O` | – | – |

### Enrollment (`enrollment.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `enrollment.override` | School | `F` | – | – | – | – | – |
| `enrollment.register` | Self | – | – | – | – | – | `O` |
| `enrollment.waitlist` | School | `F` | `R` | – | – | – | – |

### Events (`events.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `events.admin` | School | `F` | – | – | – | – | – |
| `events.check_in` | School | `F` | `F` | – | – | – | – |
| `events.reserve` | Self | – | – | – | – | – | `O` |
| `events.view` | Self | `F` | `F` | `R` | `R` | `R` | `O` |

### Feature flags (`features.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `features.manage` | Super Admin only | – | – | – | – | – | – |

### Feedback surveys (`feedback.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `feedback.identity.request` | Offering | – | `F` | – | `O` | – | – |
| `feedback.identity.reveal` | Super Admin only | – | – | – | – | – | – |
| `feedback.manage` | Offering | – | `F` | – | `O` | – | – |
| `feedback.report` | Offering | – | `F` | – | `O` | – | – |
| `feedback.view` | Self | – | `F` | – | `O` | `O` | `O` |

### Finance (`finance.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `finance.donate` | Self | `O` | `O` | `O` | `O` | `O` | `O` |
| `finance.invoices` | School | `R` | – | `F` | – | – | – |
| `finance.manual` | School | – | – | `F` | – | – | – |
| `finance.pay` | Self | – | – | – | – | – | `O` |
| `finance.refunds` | School | – | – | `F` | – | – | – |
| `finance.wallet` | School | – | – | `F` | – | – | – |

### Foundation (`foundation.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `foundation.demo` | School | – | `F` | – | – | – | – |

### Import (`import.*`)

Legacy data import from Populi and Canvas — see [`docs/legacy-data-import-plan.md`](legacy-data-import-plan.md).
None of these keys are offering-scoped; a batch is school-wide by nature.

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `import.view` | School | `R` | `R` | `R` | – | – | – |
| `import.configure` | School | `F` | – | – | – | – | – |
| `import.stage` | School | `F` | `F` | – | – | – | – |
| `import.commit` | School | `F` | – | – | – | – | – |
| `import.rollback` | School | `F` | – | – | – | – | – |
| `import.activate` | School | `F` | – | – | – | – | – |
| `import.merge_resolve` | School | `F` | – | – | – | – | – |

### Gradebook (`gradebook.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `gradebook.configure` | Offering | – | `F` | – | `O` | `R` | – |
| `gradebook.lock` | Offering | – | `F` | – | `lock` | – | – |
| `gradebook.reopen` | School | – | `reopen` | – | – | – | – |

### Grading schemes (`grading_schemes.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `grading_schemes.manage` | School | – | `F` | – | – | – | – |

### Live sessions (`live.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `live.join` | Self | `F` | `F` | – | `O` | `O` | `O` |
| `live.schedule` | Offering | `F` | `R` | – | `O` | `O` | – |

### Live quiz (`live_quiz.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `live_quiz.host` | Offering | – | – | – | `O` | `O` | – |
| `live_quiz.manage` | Offering | – | `F` | – | `O` | – | – |
| `live_quiz.play` | Self | – | – | – | – | – | `O` |

### Module assessment (`module_assessment.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `module_assessment.manage` | Offering | – | `F` | – | `O` | `O` | – |
| `module_assessment.view` | Offering | – | `F` | – | `O` | `O` | – |

### Notifications (`notifications.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `notifications.preferences` | Self | `O` | `O` | `O` | `O` | `O` | `O` |

### Offering lifecycle (`offering.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `offering.close` | Offering | – | `F` | – | `O` | – | – |

### Offerings (`offerings.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `offerings.content` | Offering | – | `F` | – | `O` | `O` | – |
| `offerings.manage` | School | – | `F` | – | – | – | – |
| `offerings.pricing` | School | – | – | `F` | – | – | – |
| `offerings.view` | Offering | `R` | `F` | – | `O` | `O` | `R` |

### Operations (`ops.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `ops.backup` | Super Admin only | – | – | – | – | – | – |
| `ops.failed_jobs` | Super Admin only | – | – | – | – | – | – |

### Profile (`profile.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `profile.edit_own` | Self | `O` | `O` | `O` | `O` | `O` | `O` |

### Programs (`programs.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `programs.manage` | School | – | `F` | – | – | – | – |
| `programs.view` | School | `R` | `F` | – | `R` | `R` | `R` |

### Projects (`projects.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `projects.announce` | Offering | – | `F` | – | `O` | – | – |
| `projects.grade` | Offering | – | `F` | – | `O` | `O` | – |
| `projects.join` | Self | – | – | – | – | – | `O` |
| `projects.manage` | Offering | – | `F` | – | `O` | `O` | – |
| `projects.peer_eval` | Self | – | – | – | – | – | `O` |
| `projects.view` | Offering | – | `F` | – | `O` | `O` | `O` |

### Question banks (`questions.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `questions.manage` | Offering | – | `F` | – | `O` | `O` | – |

### Reports (`reports.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `reports.school` | Super Admin only | – | – | – | – | – | – |
| `reports.view` | School | `F` | `R` | `R` | – | – | – |

### Role grants (`roles.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `roles.assign` | School | `F` | – | – | – | – | – |
| `roles.assign_admin` | Super Admin only | – | – | – | – | – | – |
| `roles.manage_matrix` | Super Admin only | – | – | – | – | – | – |

### Roster (`roster.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `roster.announce` | Offering | – | `F` | – | `O` | – | – |
| `roster.export` | Offering | – | `F` | – | `O` | `O` | – |
| `roster.view` | Offering | – | `F` | – | `O` | `O` | – |

### Semesters (`semesters.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `semesters.manage` | School | `F` | – | – | – | – | – |
| `semesters.view` | School | `F` | `R` | – | `R` | `R` | `R` |

### Settings (`settings.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `settings.manage` | School | `F` | – | – | – | – | – |

### Student notes (`student_notes.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `student_notes.manage` | Offering | – | `F` | – | `O` | `O` | – |
| `student_notes.view` | Offering | – | `F` | – | `O` | `O` | – |

### School settings (`system_settings.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `system_settings.manage` | Super Admin only | – | – | – | – | – | – |

### Theme (`theme.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `theme.manage` | School | `F` | – | – | – | – | – |

### Transcript (`transcript.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `transcript.view` | Self | `R` | `F` | – | `O` | – | `O` |

### Translations (`translations.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `translations.manage` | School ⚠ | – | `F` | – | `O` | `O` | – |

> ⚠ The `O` is intent only — the key is not offering-scoped and `TranslationService` adds no
> narrowing, so Instructor and TA edit translations school-wide. See §9.1.

### People (`users.*`)

| Permission key | Scope | ADM | ACA | FIN | INS | TA | STU |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|
| `users.impersonate` | Super Admin only | – | – | – | – | – | – |
| `users.manage` | School | `F` | – | – | – | – | – |
| `users.reset_password` | Super Admin only | – | – | – | – | – | – |
| `users.unsuspend` | Super Admin only | – | – | – | – | – | – |

---

## 6. Role profiles

### Super Admin (`SUPER_ADMIN`)

Bypasses `AuthorizeService` before any matrix lookup, so it holds all 120 keys plus the 12 that
have no role map at all. Exclusive in practice:

`roles.manage_matrix` · `roles.assign_admin` · `feedback.identity.reveal` · `features.manage` ·
`system_settings.manage` · `users.impersonate` · `users.unsuspend` · `users.reset_password` ·
`audit.export` · `reports.school` · `ops.failed_jobs` · `ops.backup`

Also the only role that may grant `ADMINISTRATIVE_ADMIN`, and the only one behind the
`superadmin` middleware (`EnsureSuperAdmin`) that fronts `superadmin.*` and the Roles Hub.
Not grantable from the UI — seeder / `SUPERADMIN_EMAIL` only.

### Administrative Admin (`ADMINISTRATIVE_ADMIN`) — 32 keys

Owns the registrar side: `users.manage`, `roles.assign`, `settings.manage`, `theme.manage`,
`semesters.manage`, the whole admissions chain (`admissions.forms` / `.review` / `.decide`),
`enrollment.override`, `enrollment.waitlist`, `advising.assign`, `academic_standing.manage`,
`events.admin`, `events.check_in`, `courses.interest_counts`, `credentials.issue` (level `issue`),
`reports.view` (`F`), `live.schedule`, `live.join`.

Reads: programs, courses, offerings, transcripts, finance invoices, audit, communications report.
Cannot: change pricing or move money, author course content, grade, or edit the permission matrix.

### Academic Admin (`ACADEMIC_ADMIN`) — 76 keys, the widest non-super role

Owns the curriculum and everything school-wide on the teaching side: `programs.manage`,
`courses.manage`, `offerings.manage`, `offerings.content`, `assessment_templates.manage`,
`grading_schemes.manage`, `translations.manage`, `certificate_templates.manage`,
`completion.configure`, `offering.close`, `credentials.issue`, plus the school-wide versions of
every offering-scoped teaching key (assessments, assignments, attendance, roster, discussions,
announcements, projects, feedback, live quiz, student notes, module assessment).

Exclusive levers: `gradebook.reopen` (level `reopen` — nobody else, not even Instructor) and
`assessments.clear_termination` (clearing a proctor termination is deliberately an admin override,
never an instructor action).

Cannot: manage users or roles, decide admissions, manage semesters, or touch money.

### Financial Admin (`FINANCIAL_ADMIN`) — 12 keys, the narrowest admin

`offerings.pricing`, `finance.invoices` (`F`), `finance.manual`, `finance.refunds`,
`finance.wallet`, plus reads on `reports.view`, `audit.view`, `announcements.view`, `events.view`,
and the self-scoped `profile.edit_own`, `notifications.preferences`, `finance.donate`.

Deliberately excluded from `academic_standing.manage`: it keeps `reports.view` but cannot move GPA
cutoffs.

### Instructor (`INSTRUCTOR`) — 61 keys, 56 of them `O`

Everything an offering needs, confined to offerings they are staffed on: content, weeks, question
banks, assessments (manage / grade / proctor / announce results), assignments (manage / grade /
dashboard / remind / mark received), gradebook configure, attendance (record / edit / view all /
report), roster (view / export / announce), discussions (configure / moderate / grade),
announcements (manage / publish), email templates, completion, offering close, student notes,
module assessment, feedback surveys, live sessions, live quiz, projects.

Only Instructor holds `gradebook.lock` (level `lock`). Also `advising.hold` and `advising.view` at
advisee scope, and `transcript.view` at `O`.

Cannot: reopen a locked gradebook, clear a proctor termination, price an offering, or reach an
offering they are not staffed on.

### TA (`TA`) — 45 keys, 40 of them `O`

Instructor's set minus the authority keys. Specifically **not** held by TA:

`gradebook.lock` · `attendance.edit` · `roster.announce` · `announcements.publish` ·
`offering.close` · `email_templates.manage` · `assessments.proctor` ·
`assessments.announce_results` · `advising.hold` / `.view` · `transcript.view` ·
`feedback.manage` / `.report` / `.identity.request` · `projects.announce` · `live_quiz.manage`

That is the complete difference — there is no key TA holds that Instructor does not.

`gradebook.configure` drops from `F`/`O` to `R` for TA — a TA sees the weighting, and cannot change it.

### Student (`STUDENT`) — 29 keys: 25 `O` plus 4 catalog reads

Apply (`admissions.apply`), register (`enrollment.register`), flag interest
(`courses.flag_interest`), learn and be assessed (`assessments.take`, `assignments.submit`,
`discussions.thread` / `.post`, `live.join`, `live_quiz.play`, `projects.join` / `.peer_eval`),
attend (`attendance.view_own`, `attendance.self_check_in`), pay (`finance.pay`, `finance.donate`),
reserve events (`events.view`, `events.reserve`), and read their own `transcript.view`,
`completion.view`, `advising.view`, `feedback.view`, `announcements.view`, `projects.view`.

The four `R` grants are catalog reads, not personal data: `programs.view`, `courses.view`,
`semesters.view`, `offerings.view`.

---

## 7. Gates that are not in the matrix

The matrix is necessary but not sufficient. These checks run alongside it and use role names
directly — legitimately, because they answer "what do we show / which rows do we list", not
"may this action proceed".

| Gate | File | What it decides |
|---|---|---|
| `EnsureSuperAdmin` (`superadmin` alias) | `app/Http/Middleware/EnsureSuperAdmin.php` | Hard 403 on the whole control plane |
| `RequirePermission` (`permission:` alias) | `app/Http/Middleware/RequirePermission.php` | Route-level `authorize()` + resource auto-binding |
| `RequireInstructorToken` (`api.instructor`) | `app/Http/Middleware/Api/RequireInstructorToken.php` | Fronts `/api/v1/teach/*` |
| `TeachAccessService::canTeach()` | Teach hub visibility | Super Admin, Academic Admin, Instructor, TA, or anyone with an `offering_staff` row |
| `TeachAccessService::offeringsFor()` | Teach list | Super Admin / Academic Admin see all (capped 50); everyone else only staffed offerings |
| `OfferingAccessService::canAccessOffering()` | Learner access | Enrolled/completed enrollment, or staff/admin |
| `AdvisingService` | Advising | Instructor `O` = assigned advisee only; fails closed without a student |
| `DiscussionService` | Discussion staff view | Super Admin / Academic Admin / Instructor / TA |
| `Navigation` + `NavigationHub` | Sidebar, hubs, bottom nav | Which hubs a role sees (Academic, Admin, Finance, Teach, Superadmin) |
| `AuthorizeService::canAssignRole()` | Role assignment | Hard-coded Super Admin / Administrative Admin rules |

Navigation visibility is **not** an authorization boundary: hiding a tile does not protect the
route. Every route still needs `permission:` or a service-level `authorize()`.

---

## 8. Adding or changing a permission key

1. Add the key to `config/permissions.php` with a level per role.
2. If any `INSTRUCTOR` / `TA` grant means "an offering I am staffed on", add the key to
   `permission_scopes.offering_scoped`. **Miss this and the grant is school-wide**, silently.
3. If the action carries a new offering-owned model, add an arm to
   `ResourceScopeResolver::offeringIdsFor()`. **Miss this and the resource resolves to no
   offering** — fails safe, but presents as an unexplained 403.
4. Enforce it in the service (`authorize()` before the mutation, inside `AuditLogWriter::withAudit()`),
   and add `permission:<key>` to the route.
5. Pass the resource at every call site for a scoped key — no resource means denial.
6. Add a Roles Hub group label (`lang/*/roles_hub.php`, key `group_<prefix>`) in **all three**
   locales, or the group heading falls back to the raw prefix.
7. Run `php artisan permissions:sync` anywhere `role_permissions` already has rows — otherwise the
   key is denied for every non-Super-Admin role.
8. Cover it in `tests/Feature/Rbac/` or `tests/Feature/Auth/`, then `./scripts/validate-step.sh`.

---

## 9. Observations from this audit

Findings, not changes — nothing here was modified.

1. **`translations.manage` grants Instructor and TA school-wide translation editing.**
   The key carries `INSTRUCTOR => 'O'` and `TA => 'O'`, but it is not in
   `permission_scopes.offering_scoped` and `TranslationService` applies no narrowing of its own
   (`upsert()` and `requestAiTranslation()` authorize on the bare key). The `O` therefore reads as
   intent, not enforcement: any instructor can edit any entity's translations. Either add it to
   `offering_scoped` and pass the entity, or scope it inside `TranslationService`.
2. **`roles.assign_admin` is dead.** Defined with an empty role map, never checked anywhere.
   The rule it names lives hard-coded in `AuthorizeService::canAssignRole()`. Either wire it up or
   drop it — as it stands, Roles Hub shows a key that does nothing.
3. **The config→DB switch is a footgun on seeded environments.** Because `RolePermissionSeeder`
   runs in `DatabaseSeeder`, any environment seeded once ignores `config/permissions.php` entirely.
   A key added to config is denied for all non-Super-Admin roles until `permissions:sync` runs, and
   nothing in the deploy path runs it automatically.
4. **`R` is not read-only at the guard.** `levelGrants()` accepts `R` like any other grant, so
   read-only intent depends on route exposure. `assessments.grade` and `assignments.grade` at
   `ACADEMIC_ADMIN => 'R'` would authorize a write if a write route were exposed to that role.
5. **`submit` is a level the guard accepts but nothing uses.** Harmless, but it suggests a level
   that was renamed to `O` without cleaning up `levelGrants()`.
6. Deliberate and correct, recorded so nobody "fixes" them: `assessments.clear_termination`,
   `gradebook.reopen`, `reports.view`, `academic_standing.manage`, and the `advising.*` keys are
   intentionally **not** offering-scoped — the comments in both config files say so.

---

*Regenerate the §5 tables from `config/permissions.php` + `config/permission_scopes.php` whenever
either changes; the counts in §1 and §6 come from the same source.*
