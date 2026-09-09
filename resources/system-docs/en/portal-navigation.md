## Portal shell

SPIMS uses a role-aware **sidebar** on desktop and a **bottom navigation bar** on phones (max five items). Hub pages present **tiles**; the home dashboard uses an asymmetric **bento** layout rather than a uniform tile grid.

Navigation is built by `NavigationHub` — items appear only when the signed-in user passes the matching gate and the route exists.

---

## Sidebar (primary nav)

Typical order for a fully privileged operator (students see a subset):

| Item | Route name | Who sees it |
|---|---|---|
| **Home** | `dashboard` | Everyone signed in |
| **Learning** | `hubs.learning` | Everyone signed in |
| **Teach** | `teach.index` | Users who can teach (instructor/TA on offerings) |
| **Academic** | `hubs.academic` | Academic desk (`programs.manage`) |
| **Admin** | `hubs.admin` | Administrative desk (`users.manage`) |
| **Finance** | `hubs.finance` | Everyone signed in (admin tiles only if finance desk) |
| **Super Admin** | `superadmin.index` | Super Admin only |
| **Help** | `help.index` | Everyone signed in |

Learning stays active across course player, grades, enrollments, attendance, events, live quiz, projects, surveys, and learn routes. Teach stays active for `teach.*` and advising when linked from teach.

---

## Mobile bottom nav (max 5)

| Slot | Default | Alternate |
|---|---|---|
| 1 | Home (`dashboard`) | — |
| 2 | Learning (`hubs.learning`) | — |
| 3 | **Teach** if the user can teach | Otherwise **Catalog** (`catalog.index`) |
| 4 | Finance (`hubs.finance`) | — |
| 5 | **Super Admin** if Super Admin | Otherwise **More** → settings (`settings.edit`) |

Overflow destinations (Academic, Admin, Help, notifications) remain reachable from the sidebar drawer, hub tiles, or settings.

---

## Hub tiles

Each hub lists link tiles (label, icon, short description). Missing routes are omitted.

### Learning hub

Catalog, grades, my applications, enrollments, projects, live sessions, live quiz join, events, attendance, surveys, finance shortcut, transcript, settings, notifications, announcements, notification preferences, Help.

### Academic hub

Advising, programs, courses, offerings, assessment templates, semesters, credentials, grading schemes, translations, attendance policy, communications report, email templates, certificate templates, surveys, reports, Help.

### Admin hub

Users, advising, enrollment admin, theme, application forms, applications queue, Help CMS, communications, events, reports, Help.

### Finance hub

Personal finance / wallet, donate; for finance admins also finance admin desk, finance reports, school reports; Help.

### Super Admin sections

Grouped tiles: People, Access (roles + security), Appearance (theme), School (academics/finance/credentials/admissions/enrollment shortcuts), Evidence (audit, observability, health), Ops (scheduled tasks, system tests, feedback reveals).

---

## Dashboard bento widgets

The home dashboard (`dashboard`) is an asymmetric bento, not a flat hub grid:

| Widget | Shows | Primary action |
|---|---|---|
| **My courses** | Current enrollments with progress % | Open course player; link to catalog if empty |
| **Next live** | Next scheduled Zoom session (feature panel) | Join / go to live list |
| **Due soon** | Upcoming assessments with close times | Continue to assessment |
| **Wallet** | Four chips: EGP money, USD money, EGP points, USD points | Finance hub |
| **Notifications** | Recent items + unread badge | Notifications index |

Below the bento, shortcut tiles open Learning and any admin hubs the user can access.

---

## Hub link tile pattern

Hub pages reuse the shared partial `hub-link-tile` (and dashboard `hub-tile` / `app-tile` cards): icon, title, one-line description, whole-tile link. Prefer existing hub destinations over inventing new top-level sidebar entries.

---

## Guest vs signed-in

| State | Navigation |
|---|---|
| Guest | Public landing, catalog, auth screens — no sidebar hubs |
| Signed in | Full shell per role gates |
| Locale | Language switcher available; Arabic is RTL-primary |

---

## Tips for training staff

1. Start every demo on **Home** so the bento matches the student phone experience.
2. Show how **Teach** appears only for staffed instructors/TAs.
3. Show that **Finance** is always present for students, while admin finance tiles appear only for Financial Admins.
4. Use **Help** for end-user articles; use **System Docs** (this set) for product/ops briefing.

Related: [Student journey](student-journey.md), [Staff journeys](staff-journeys.md), [Roles guide](roles-guide.md).
