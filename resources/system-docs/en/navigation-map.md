## Source of truth

`App\Support\NavigationHub` builds all portal navigation. Methods return arrays filtered by `Route::has(...)` so missing routes never break the shell.

Hub permission constants:

| Constant | Permission key | Desk |
|---|---|---|
| `HUB_ACADEMIC` | `programs.manage` | Academic Admin |
| `HUB_ADMIN` | `users.manage` | Administrative Admin |
| `HUB_FINANCE` | `offerings.pricing` | Financial Admin |

Teach uses `TeachAccessService::canTeach($user)`. Super Admin uses `$user->isSuperAdmin()`.

---

## `primaryNav(?User $user)`

Ordered sidebar items (only if route exists):

| Label key | Route | Gate |
|---|---|---|
| `hubs.nav_home` | `dashboard` | Signed in |
| `hubs.nav_learning` | `hubs.learning` | Signed in |
| `hubs.nav_teach` | `teach.index` | `hasTeach` |
| `hubs.nav_academic` | `hubs.academic` | `hasAcademicAdmin` |
| `hubs.nav_admin` | `hubs.admin` | `hasAdministrative` |
| `hubs.nav_finance` | `hubs.finance` | Signed in |
| `hubs.nav_superadmin` | `superadmin.index` | Super Admin (`tone: superadmin`) |
| `help.nav` | `help.index` | Signed in |

Active matching includes nested route groups (e.g. Learning → `courses.*`, `learn.*`, `grades.*`, `events.*`, …).

---

## `bottomNav(?User $user)`

Max five slots:

1. Home → `dashboard`
2. Learning → `hubs.learning`
3. Teach → `teach.index` **or** Catalog → `catalog.index`
4. Finance → `hubs.finance`
5. Super Admin → `superadmin.index` **or** More/settings → `settings.edit`

---

## `learningLinks(User $user)`

Concrete destinations (when registered):

| Route | Purpose |
|---|---|
| `catalog.index` | Course catalog |
| `grades.index` | My grades |
| `applications.index` | My applications |
| `enrollments.index` | My enrollments |
| `student.projects.mine` | Projects |
| `live.index` | Live sessions |
| `live-quiz.join` | Live quiz join |
| `events.index` | Events hub |
| `attendance.index` | My attendance |
| `student.surveys.index` | Surveys |
| `finance.index` | Finance / wallet |
| `transcript.show` | Transcript |
| `settings.edit` | Profile / settings |
| `notifications.index` | Notifications |
| `announcements.index` | Announcements |
| `settings.notifications.edit` | Notification preferences |
| `help.index` | Help |

---

## `academicLinks(User $user)`

Empty unless `hasAcademicAdmin`. Destinations:

| Route | Purpose |
|---|---|
| `advising.index` | Advising |
| `admin.programs.index` | Programs |
| `admin.courses.index` | Courses |
| `admin.offerings.index` | Offerings |
| `admin.assessment-templates.index` | Assessment templates |
| `admin.semesters.index` | Semesters |
| `admin.credentials.index` | Credentials |
| `admin.grading-schemes.index` | Grading schemes |
| `admin.translations.index` | Translations |
| `admin.attendance.policy` | Attendance policy |
| `admin.communications.report` | Communications report |
| `admin.email-templates.index` | Email templates |
| `admin.certificate-templates.index` | Certificate templates |
| `admin.surveys.index` | Surveys |
| `admin.reports.index` | Reports |
| `help.index` | Help |

---

## `adminLinks(User $user)`

Empty unless `hasAdministrative`. Destinations:

| Route | Purpose |
|---|---|
| `admin.users.index` | Users |
| `advising.index` | Advising |
| `admin.enrollments.index` | Enrollment admin |
| `admin.theme.edit` | Theme editor |
| `admin.application-forms.index` | Application forms |
| `admin.applications.index` | Applications queue |
| `admin.help.index` | Help CMS |
| `admin.communications.report` | Communications |
| `admin.events.index` | Events admin |
| `admin.reports.index` | Reports |
| `help.index` | Help |

---

## `financeLinks(User $user)`

Always (when routes exist):

| Route | Purpose |
|---|---|
| `finance.index` | Personal finance / wallet |
| `donate.create` | Donate |

If `hasFinanceAdmin`:

| Route | Purpose |
|---|---|
| `admin.finance.index` | Finance admin desk |
| `admin.finance.reports` | Finance reports |
| `admin.reports.index` | School reports |

Plus `help.index`.

---

## `superadminSections()`

Sections with tiles (each tile omitted if route missing):

| Section id | Tiles (route names) |
|---|---|
| `people` | `admin.users.index` |
| `access` | `roles.hub`, `superadmin.security` |
| `appearance` | `admin.theme.edit` |
| `school` | `hubs.academic`, `admin.finance.index`, `admin.credentials.index`, `admin.application-forms.index`, `admin.applications.index`, `admin.enrollments.index`, `admin.finance.reports` |
| `evidence` | `superadmin.audit.index`, `superadmin.observability.index`, `health` |
| `ops` | `superadmin.scheduled-tasks.index`, `superadmin.system-tests.index`, `superadmin.feedback-reveals.index` |

Empty sections are dropped.

---

## Permission gates summary

| UI surface | Gate helper | Underlying check |
|---|---|---|
| Academic hub + links | `hasAcademicAdmin` | `programs.manage` |
| Admin hub + links | `hasAdministrative` | `users.manage` |
| Finance admin tiles | `hasFinanceAdmin` | `offerings.pricing` |
| Teach nav | `hasTeach` | `TeachAccessService` |
| Super Admin nav / sections | `hasSuperadmin` | `isSuperAdmin()` |
| Learning / Finance / Help / Home | Signed-in only | Auth middleware |

Related: [frontend.md](frontend.md), [roles-permissions.md](roles-permissions.md), client [portal-navigation.md](portal-navigation.md).
