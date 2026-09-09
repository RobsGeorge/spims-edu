## Seven roles at a glance

SPIMS has exactly seven roles. A person may hold more than one. Effective permissions are the **union** of every role they hold — for example, an instructor who is also a student sees Teach plus Learning.

| Role | Plain-language ownership |
|---|---|
| **Super Admin** | Full platform access: security, audit, observability, roles matrix, system health. Bypasses permission checks. |
| **Administrative Admin** | People, admissions forms, application review, enrollment overrides, holds/waitlists, theme/branding, Help CMS. |
| **Academic Admin** | Programs, courses, offerings, semesters (shared view), grading schemes, assessment templates, translations, credentials issue, **gradebook reopen**. |
| **Financial Admin** | Offering prices, invoice queue, manual payments, refunds, wallet top-ups/points, finance reports, donations oversight. |
| **Instructor** | Staffed offerings: content, assessments, grading, announcements, discussions, live schedule, attendance, **gradebook lock**. |
| **TA** | Same teaching desk as instructor for content, grading, discussions, announcements — **cannot** lock final grades. |
| **Student** | Apply, enroll, learn, submit, take exams, pay, view released grades, transcript, wallet, join live sessions. |

Authorization is always by **permission keys**, not by comparing role-name strings in the UI. What someone sees follows what they are allowed to do.

---

## Ownership table (who owns what)

| Area | Super Admin | Admin Admin | Academic Admin | Financial Admin | Instructor | TA | Student |
|---|---|---|---|---|---|---|---|
| Users & role assign | Yes | Create / suspend / assign | — | — | — | — | Own profile |
| Theme / branding | Yes | Full | — | — | — | — | Preference only |
| Programs / courses / offerings | Yes | View (read) | Full manage | Pricing (finance) | View staffed | View staffed | Catalog / enroll |
| Admissions forms & decisions | Yes | Full | — | — | — | — | Apply / track |
| Enrollment override / holds | Yes | Full | Advising views | Hold (finance) | Waitlist (scoped) | Waitlist (scoped) | Register / drop |
| Content & assessments | Yes | — | School-wide where granted | — | Own offerings | Own offerings | Take / submit |
| Gradebook lock | Yes | — | Reopen only | — | **Lock** | No | View released |
| Invoices / wallet / refunds | Yes | — | — | Full | — | — | Pay / donate |
| Live Zoom / attendance | Yes | — | Policies / reports | — | Schedule / record | Schedule / record | Join / own attendance |
| Credentials issue | Yes | Issue | Issue | — | — | — | View / verify public |
| Audit / ops / roles matrix | Full | Audit read | Audit read | Audit read | — | — | — |

---

## Multi-role union

- Permissions from all assigned roles are combined.
- An Academic Admin who also teaches is **not** limited to offerings they teach for school-wide academic keys — an unscoped admin grant wins.
- Instructor and TA grants on teaching keys are **offering-scoped**: they apply only to offerings where they are staffed.
- Super Admin always wins and skips the matrix.

---

## What each role typically sees

### Student

- Dashboard bento: my courses, next live, due soon, wallet, notifications
- Learning hub: catalog, enrollments, grades, applications, live, transcript, settings
- Finance hub: wallet, invoices, checkout, donate
- Help articles for students

### Instructor / TA

- **Teach** in the sidebar (and often as the third mobile bottom-nav item)
- Teach hub listing staffed offerings → content, assessments, gradebook, live, discussions, roster
- Instructor-only: lock gradebook after submit
- TA: grade and teach, but cannot lock

### Academic Admin

- **Academic** hub: programs, courses, offerings, templates, grading schemes, translations, credentials, reports
- Grade reopen when an instructor has locked a book that must change

### Administrative Admin

- **Admin** hub: users, application forms, application queue, enrollment admin, theme editor, Help CMS
- Matriculate accepted applicants; place or lift financial holds with finance

### Financial Admin

- **Finance** hub admin tiles: invoice queue, manual pay, refunds, wallet grants, dual-currency reports
- Students still use the same Finance hub for their own wallet and payments

### Super Admin

- **Super Admin** control plane: people shortcuts, roles hub, security, theme, school desks, audit, observability, health, scheduled tasks, system tests
- Roles hub can override the default permission matrix stored in config (DB `RolePermission` rows)

---

## Practical notes for school leadership

1. Give people the **smallest** set of roles that covers their job.
2. Prefer Administrative Admin for registrar work and Academic Admin for curriculum — do not conflate them.
3. Keep Super Admin accounts few; use the Roles hub carefully when changing the matrix.
4. Demo accounts on staging should mirror real role combinations the school will use.

See also: [Staff journeys](staff-journeys.md), [Portal navigation](portal-navigation.md).
