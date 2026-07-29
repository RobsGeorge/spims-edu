# Portal design gap analysis & next-phase plan

**Date:** 2026-07-29  
**Scope:** Laravel `spims-edu` (this repo) vs original Spims design package (`RobsGeorge/Spims`:
spec v0.2, build brief, frontend architecture, PRODUCT.md, DESIGN.md, ~62 design-reference screens).  
**Verdict:** Domain Phases 0–9 are largely **implemented as services + thin Blade CRUD**. The portal
does **not** yet match the planned product UX or the Sacred Academic design system. The largest gaps
are design-token fidelity, app shell, Course Player, Teach hub, and unfinished integrations.

---

## 1. What is already solid

| Area | Status |
|---|---|
| Schema (~60 domain tables) + additive migrations | Done |
| `AuthorizeService` + `config/permissions.php` + Roles Hub DB matrix | Done |
| `AuditLogWriter` on mutations | Done |
| Money minor-units + dual currency model | Done |
| Auth OTP lifecycle, multi-role users | Done |
| Programs / courses / offerings / gating services | Done |
| Admissions + enrollment engine (waitlist, holds, drop/withdraw refunds) | Done |
| Finance services (invoice, wallet ledger, mock gateways, refunds) | Done |
| Assessment engine + exam runner (Alpine) + grade lock | Done |
| Live Zoom (mock-capable) + attendance + discussions + notifications | Done |
| Credentials + public verify | Done |
| Hub tiles + Superadmin shield console + demo seed | Done (functional shell only) |
| Feature tests per domain + CI | Done |

Backend acceptance from the original build brief is mostly met. Frontend acceptance from
`frontend-architecture.md` / `DESIGN.md` / design-reference is mostly **not**.

---

## 2. Design-system gaps (highest visibility)

Original **Sacred Academic** system vs current `public/css/spims-theme.css`:

| Spec (DESIGN.md / PRODUCT.md) | Current Laravel UI | Gap |
|---|---|---|
| Cool near-white field `#f8f9ff` | Warm parchment `#faf6ee` / gold cream gradient | **Violates Cool-Field Rule** (explicit anti-reference) |
| Liturgical burgundy `#5d0326` primary | Gold `#b8860b` as primary | Brand spine missing |
| Academic gold as **accent** only | Gold used as primary + title color | One-Burgundy Rule broken |
| Playfair Display + Inter (Latin) | Cairo only | No scholarly display voice |
| IBM Plex Sans Arabic for Arabic | Cairo for all locales | Arabic-Is-Sans partially ok; Latin wrong |
| Pill primary buttons, tonal surface ramp | Bootstrap defaults + translucent cards | Component language missing |
| Soft Lift shadow + hairline rose borders | Generic Bootstrap shadows | Elevation wrong |
| Sidebar 280px + sticky topbar | Top navbar only | Shell architecture wrong |
| Mobile drawer + bottom-nav | Collapse hamburger only | Mobile IA incomplete |
| Bento student dashboard | Uniform hub tiles | Signature component missing |
| Theme tokens + logos from `/api/branding` | Theme editor: name + active only; logos unused | Branding incomplete |
| SYSTEM theme follows OS | `system` cookie forced to `theme-dark` | Broken |
| Skeletons / teaching empty states | Bare tables / empty text | Missing |
| Auth / landing / catalog screens from design-reference | Compact Bootstrap forms | Visual mismatch across ~20 designed screens |

**Bottom line:** the shipped theme reads as warm parchment-gold (exactly the AI-default aesthetic
PRODUCT.md rejects). Re-skinning to burgundy / cool-field is a prerequisite for all later UI work.

---

## 3. Product-surface gaps (frontend architecture map)

From `docs/frontend-architecture.md` route map → Laravel reality:

### 3.1 Missing entirely (or no discoverable UI)

| Planned surface | Spec role | Laravel today |
|---|---|---|
| **Course Player** (`courses/[offeringId]`) | Student core LMS | **Absent** — no week accordion, Vimeo player, item renderer, progress UI |
| **Teach hub** (`teach/*`) | Instructor / TA | **Absent** — no teaching dashboard; gradebook/banks/live exist as orphan admin URLs |
| **Profile / settings** | All | **Absent** (`profile.edit_own` exists; no page) |
| **Student grades** (released items only) | Student | **Absent** |
| **Announcements** UI | INS/TA/Student | Model + table only; no controller/views |
| **Live session calendar / scheduler** | ADM | List forms only; no license-aware calendar |
| **Translations verify UI** | ACA | Service + POST routes; **no Blade page** |
| **Grading schemes editor** | ACA | Seeded / selectable on programs; no dedicated CRUD UI |
| **Application document uploads** | Student/ADM | Prefill text fields only; no R2/signed upload |
| **Receipt PDF view/download** | Student/FIN | Serial allocated; `receipt_url` is placeholder HTML path, no PDF job |
| **Finance reports** | FIN | Absent |
| **Object storage (R2/S3)** | Cross-cutting | Absent |
| **Real PayPal / Paymob / Cashier SDKs** | FIN | Mock `GatewayRouter` |
| **Real Gemini** translate + essay | ACA/INS | Stubs / heuristics |
| **Vimeo embed player** | Student | `vimeo_id` stored; preview shows raw ID text |
| **Custom error pages** | All | Absent |
| **Instructor / TA nav hub** | INS/TA | NavigationHub has Learning/Academic/Admin/Finance only — **no Teach** |

### 3.2 Present but thin / non-designed

| Surface | Issue |
|---|---|
| Landing | Minimal hero card — not design-reference landing |
| Auth (login/register/OTP/reset) | Functional; not matching designed auth screens / suspended states |
| Catalog | Simple card grid; no filters, empty/loading states, program browse, offering CTAs |
| Dashboard | Hub launchpad, not bento (enrolled courses / next live / due items / wallet) |
| Degree audit | Raw progress numbers |
| Wallet / billing | Tables; not four BalanceCards + ledger + split-pay allocator UX |
| Exam runner | Richest student UI, but not design-polished / mobile one-question default |
| Admin CRUD (programs, courses, offerings, admissions, finance, users…) | ULID text inputs, dense forms, English hard-codes, poor discoverability |
| Offering admin show | Content builder present but **no links** to banks, assessments, gradebook, live, discussions |
| Superadmin observability / scheduled / system-tests | Static info pages, not live ops |
| i18n | Catalog key parity mostly OK except `auth.php` ar/fr; many Blade strings still English |

### 3.3 Access / polish defects found in scan

- Assignment detail and discussion board/thread readable by any authenticated user who knows an ID (membership not enforced).
- Discussion board `GET` can create a board (`ensureBoard`) without an audited mutation path.
- Hard-coded English on assignments, live admin, discussions, gradebook, assessment admin.
- `welcome.blade.php` leftover Laravel scaffold (unused by `/`).

---

## 4. Spec-vs-backend residual gaps

These are product/integration gaps still open after Phase 9:

1. **Payment providers** — mock charge/signature only (`GatewayRouter`).
2. **Receipt PDF** — serial only; no DomPDF/job/R2 artifact.
3. **Storage** — no signed uploads for application docs, assignment files, readings, logos.
4. **Vimeo** — IDs stored; no player / privacy / upload flow.
5. **Gemini** — translation stub; essay grader heuristic.
6. **Email delivery** — OTP/notifications degrade to log (acceptable in dev; production mail still optional).
7. **Schedule conflict** — staff overlap blocked for live sessions; student schedule-conflict warn on enrollment not clearly surfaced in UI.
8. **Cashier** — enum option present; no distinct integration path beyond mock.
9. **Lifetime preference SYSTEM** — incorrectly maps to dark.
10. **Theme tokens / logos / favicon** — schema ready; editor + layout unused.
11. **Missing planning docs in-repo** — `docs/spims-spec-summary.md` was referenced by CLAUDE.md but absent (restored by this work).

WhatsApp, hard proctoring, multi-host Zoom → [PARKING-LOT.md](../PARKING-LOT.md).

---

## 5. Design-reference coverage matrix

~62 folders under original `design-reference/`. Priority mapping:

| Reference cluster | Priority | Target phase |
|---|---|---|
| Style tile / Sacred Academic tokens / buttons / controls / alerts | P0 | D0–D1 |
| App shell (light/dark/RTL/mobile drawer) | P0 | D1 |
| Landing + auth (sign-in/up, OTP, set/reset password, suspended, errors) | P0 | D2 |
| Course catalog (+ empty/loading, RTL, dark) | P0 | D2 |
| Student dashboard (bento, empty, RTL, dark) | P0 | D3 |
| Profile/settings | P1 | D3 |
| Forms / modals / empty-loading system | P1 | D1 + ongoing |
| (Implied by frontend arch, fewer Stitch screens) Course player, exam, teach, checkout | P0–P1 | D3–D6 |

---

## 6. Recommended next phases

Keep Laravel stack (no npm). Implement design as CSS variables + Blade components + Alpine.
Treat original Next.js repo as **reference only** — do not port React.

### Phase D0 — Spec restore & design-token foundation ✅

**Status:** Implemented (2026-07-29).

**Goal:** Make Sacred Academic the active design language without rewriting every page yet.

**Build:**
- Vendor condensed specs (`spims-spec-summary.md` ✓, this gap doc ✓, PARKING-LOT ✓).
- Rewrite `public/css/spims-theme.css` tokens to DESIGN.md (burgundy spine, cool field, gold accent, dark tonal ramp, radii, Soft Lift).
- Load Playfair Display + Inter + IBM Plex Sans Arabic (locale-aware).
- Map Bootstrap primary/secondary to burgundy/gold via CSS overrides.
- Seed default theme `tokens` JSON to match; inject CSS vars from active theme when present.
- Fix SYSTEM theme → `prefers-color-scheme`.
- Remove unused `welcome.blade.php`.

**Acceptance:** light + dark + RTL smoke; contrast spot-check AA on primary button and body text; no parchment background.

### Phase D1 — App shell & navigation IA

**Goal:** Role-aware shell matching frontend-architecture.

**Build:**
- Sidebar (desktop) + sticky topbar (search placeholder optional, locale, theme, notifications bell with unread dot, avatar menu).
- Mobile: off-canvas drawer + bottom-nav (Home / Learn / Teach-or-Catalog / Finance / More).
- Add **Teach** hub links for Instructor/TA (offerings they staff).
- Wire Offering admin “workspace” tabs: Content · Assessments · Gradebook · Live · Discussions · Roster.
- Shared Blade partials: `x-page-header`, `x-empty-state`, `x-status-badge`, `x-confirm-dialog`.
- Logical CSS (`inset-inline-*`, `margin-inline-*`) audit on layout.

**Acceptance:** each role lands in correct nav; Superadmin shield preserved; RTL mirrors chevrons; ≥44px touch targets on bottom-nav.

### Phase D2 — Public & auth surfaces

**Goal:** Match design-reference for first-run trust.

**Build:**
- Landing (brand-first, one headline, one CTA group) — Blade, liturgical photography/atmosphere without skeuomorphism.
- Auth screens: login, register, OTP, set password, reset, suspended, wrong-credentials states.
- Catalog redesign: filters (program/standalone/free), interest, empty/loading, link to offering preview / apply.
- Complete `lang/{ar,fr}/auth.php` keys.

**Acceptance:** visual parity checklist against design-reference screenshots for light/dark/RTL auth + catalog; guest→student path unbroken.

### Phase D3 — Student learning core

**Goal:** Close the biggest product hole — learning delivery.

**Build:**
- **Bento dashboard:** My Courses, next live (burgundy feature tile), due assessments, wallet snapshot, notifications.
- **Course Player:** week accordion, gated locks, Vimeo embed, PDF/text items, assignment panel, quiz launcher, discussion/announcement entry points, progress %.
- Student **Grades** (released items only) + improved degree audit (checklist + progress).
- **Settings/profile** (name, locale, theme preference, notification prefs stub).
- Membership checks on player / assignment / discussion routes.

**Acceptance:** enrolled student completes Week 1 video → assignment → quiz path in UI; ungated users cannot open cohort weeks; RTL player usable on mobile.

### Phase D4 — Instructor / TA Teach hub

**Goal:** Density without intimidation for domain experts.

**Build:**
- `/teach` dashboard (my offerings).
- Per offering: content editor polish, assessment builder UX (banks/questions without raw ULIDs), gradebook grid, attendance override UI, discussions moderation, announcements CRUD, roster.
- Grade lock / reopen flows with consequence-aware confirm copy (ar/en/fr).
- Replace ULID text fields with searchable selects where practical.

**Acceptance:** Instructor builds Week 2 item, creates MCQ assessment, grades, locks; TA cannot lock; Academic Admin reopens.

### Phase D5 — Operator consoles (ADM / ACA / FIN)

**Goal:** Throughput UIs for queues and money.

**Build:**
- Admissions queue (filters, decision drawer), form builder UX (field order).
- Semesters / registration windows calendar clarity.
- Live **scheduling calendar** with 1-host conflict visualization.
- Translations verify inbox.
- Grading scheme / band editor.
- Finance: invoice queue, manual verify, refund approve, wallet point grant, pricing screens; remove raw student ULID where possible.
- Theme editor: logos, favicon, light/dark token pickers, live preview.
- DataTable pattern: sort/filter/paginate; collapse to cards `< md`.

**Acceptance:** each admin role completes primary job without knowing ULIDs; theme logos appear in shell; FIN can grant points + verify manual payment with confirmation copy.

### Phase D6 — High-stakes polish & a11y

**Goal:** Phase 8 design acceptance that was deferred.

**Build:**
- Exam runner visual polish (progress rail, submit dialog, near-expiry pulse, mobile one-at-a-time default).
- Checkout / split-payment allocator UX + receipt page.
- Wallet four BalanceCards + ledger.
- Credential / transcript print styles.
- Empty / loading / error / toast system from design-reference.
- WCAG AA pass on shell + exam + checkout; full keyboard paths; reduce-motion already partial — extend.
- Sweep remaining hard-coded English strings.

**Acceptance:** RTL + mobile + a11y checklist on shell, catalog, player, exam, checkout; reduced-motion safe.

### Phase I1 — Integrations completion

**Goal:** Replace mocks where production requires them.

**Build:**
- S3-compatible storage service (R2/S3) + signed upload endpoints for docs, submissions, logos, readings.
- Real PayPal + Paymob (+ Cashier if still required) charge + webhook verify; keep mock flag for local.
- Receipt PDF generation job (language-aware) stored via signed URL.
- Vimeo player + optional upload token flow.
- Gemini behind `App\Services\Ai` interface (translate + essay); missing key still no-ops.
- Production mailer path for OTP / decisions / receipts.

**Acceptance:** with secrets set, end-to-end pay → receipt PDF; without secrets, mock path still green in CI.

### Phase I2 — Residual product + ops

**Goal:** Spec leftovers + production confidence.

**Build:**
- Application document requirements + upload.
- Student schedule-conflict warning UX on enrollment.
- Finance reports (outstanding, revenue by currency).
- Superadmin live observability (queue depth, failed jobs, last backup).
- Re-run exam concurrency on target VPS; restore drill.
- Optional: vendor selected `design-reference` PNGs into repo for agent/human QA.

**Acceptance:** release-runbook checklist green on staging with real or mock integrations explicitly documented.

---

## 7. Suggested execution order

```
D0 tokens → D1 shell → D2 auth/catalog → D3 course player/dashboard
                 ↘ D4 teach hub (can parallel after D1)
D5 operator UIs → D6 polish
I1 integrations (can start after D3 checkout surfaces exist)
I2 residual / ops
```

Do **not** start D3–D6 visual work before D0 tokens — otherwise pages will be redesigned twice.

---

## 8. Effort characterization (technical, not calendar)

| Phase | Touch surface | Risk |
|---|---|---|
| D0 | Global CSS, layout fonts, theme seed | Low code risk; high visual impact |
| D1 | Layout + NavigationHub + partials | Medium — every page inherits shell |
| D2 | Public/auth/catalog views | Low–medium |
| D3 | New controllers/views + gating | **Highest product value**; medium–high |
| D4 | New teach routes wrapping existing services | Medium; mostly UI over existing services |
| D5 | Many admin views | Medium volume |
| D6 | Cross-cutting polish | Medium; regression-prone |
| I1 | External SDKs + jobs | High env/secret dependency |
| I2 | Odds and ends + ops | Low–medium |

Most D-phases are Blade/CSS over existing services — invasive to UX, not to domain model.
I1 is the main place new infrastructure appears.

---

## 9. Definition of done for “design gaps closed”

The portal design gaps are closed when:

1. Light/dark/RTL screens use Sacred Academic tokens (cool field + burgundy + gold), not parchment.
2. App shell matches the planned sidebar / mobile IA, including Teach for INS/TA.
3. Student can learn inside a Course Player (not only preview + exam URL).
4. Instructor can operate an offering from a Teach workspace without hunting orphan admin URLs.
5. Auth, catalog, dashboard, checkout, and exam feel calm and consequence-aware per PRODUCT.md.
6. Theme editor drives logos + tokens live.
7. Integrations are real in production (or explicitly mock-flagged) with receipt PDFs and uploads.
8. ar/en/fr strings complete on user-facing surfaces; WCAG AA on high-stakes flows.

Until then, treat Phases 0–9 as **backend complete / portal incomplete**.
