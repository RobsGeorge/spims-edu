## What SPIMS is

SPIMS is a standalone **Student Information System (SIS)** and **Learning Management System (LMS)** for Spims, a Coptic Orthodox online school. It is a single-school product — not a multi-tenant platform — and runs as a mobile-friendly website (no separate native app in this version).

Students apply, enroll, learn week by week, sit timed exams, join live Zoom classes, pay invoices, and receive verifiable transcripts or certificates. Staff run admissions, curriculum, teaching, grading, live sessions, and finance, with an audit trail on important changes.

### Live sites

| Environment | URL | Server path |
|---|---|---|
| Production | [https://spims-edu.com](https://spims-edu.com) | `/var/www/spims` |
| Staging (preview) | [https://staging.spims-edu.com](https://staging.spims-edu.com) | `/var/www/spims-staging` |

### Languages and appearance

| Capability | Detail |
|---|---|
| Languages | **Arabic** (primary, RTL), **English**, **French** — switch anytime |
| Theme | Light, dark, or follow the device |
| Branding | School name, logos, favicon, and color tokens editable by administrators |
| Sign-in | Email + password, with OTP email verification; forgot-password uses the same OTP flow |
| Devices | Browser on phone, tablet, and desktop — sidebar on desktop, bottom nav on phones |

---

## One-sentence pitch

A student can create an account in Arabic, apply to a program, enroll, study week by week, sit a timed exam, join a live Zoom class, pay an invoice, and later receive a transcript or certificate that anyone can verify with a public link.

---

## Capabilities summary

| Domain | What the system supports |
|---|---|
| **Identity** | OTP auth, multi-role accounts, theme preference, school branding |
| **Academics** | Programs, courses, prerequisites, interest flags, grading schemes, templates, translations |
| **Offerings** | Academic years/semesters, cohort vs self-paced, weeks/items, content gating, regional pricing |
| **Admissions** | Dynamic application forms, review queue, accept / waitlist / reject, matriculation |
| **Enrollment** | Registration windows, waitlist, financial holds, drop/withdraw, degree audit |
| **Finance** | Invoices, four-bucket wallet, PayPal/Paymob/Cashier, split pay, receipts, donations |
| **Assessment** | Question banks, exam runner (autosave, server timer), AI essay suggest, assignments |
| **Gradebook** | Weighted components, instructor lock, academic reopen, GPA, academic records |
| **Live** | Zoom scheduling (one-host aware), attendance, reminders |
| **Community** | Discussion boards, announcements, in-app (+ email) notifications |
| **Credentials** | Transcripts, program/standalone certificates, public QR/link verify |
| **Help** | In-app Help CMS articles (separate from these System Docs) |

Multi-role users receive the **union** of permissions from all roles they hold. Menus and actions only appear when allowed.

---

## What needs school configuration

Workflows above are built into the product. These integrations need real credentials in production:

| Area | Ready in the product | Needs school / IT setup |
|---|---|---|
| **PayPal / Paymob / Cashier** | Wired; mock/demo path for staging | Live gateway keys for real charges |
| **Zoom** | Schedule, join window, attendance, reminders | Zoom app credentials for real meeting links |
| **Mail** | OTP, decisions, receipts, reminders | Production mail so messages leave the log and reach inboxes |
| **Vimeo** | Embed in the course player | Vimeo IDs / account for hosted video |
| **S3 (object storage)** | Upload paths for documents, assignments, logos | S3-compatible storage for production files |
| **AI (Gemini)** | Translation draft + essay score suggestion | Google/Gemini API key; without it, staff work unaided |

Without those secrets, the school can still run admissions, teaching, grading, and **manual** payments (cash, bank transfer, cheque) end to end.

---

## What is NOT in this version

These are intentionally out of scope so clients do not expect them yet:

- WhatsApp notifications
- Native iOS / Android apps (the site is mobile-friendly)
- Parent / guardian accounts
- Multi-school / multi-campus tenancy
- Hard exam proctoring (camera lockdown) — only soft integrity (focus-loss logging)
- Multiple concurrent Zoom hosts (scheduler assumes **one** licensed host)
- Public marketing website beyond the in-app landing and catalog

---

## How to review with the school

1. Open production or staging and switch language to Arabic, English, or French.
2. Walk the **student** path: catalog → application → enrollment → course player → exam → wallet.
3. Walk the **Teach** path: content → assessment → gradebook lock.
4. Walk **admissions** and **finance** queues as the matching admin roles.

A demonstration dataset can be loaded on staging for a guided tour. Production should use real staff and student accounts only.

Related client pages: [Roles guide](roles-guide.md), [Student journey](student-journey.md), [Staff journeys](staff-journeys.md), [Portal navigation](portal-navigation.md).
