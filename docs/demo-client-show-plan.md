# Client demo show — environment & seeder plan

Goal: a **production-shaped** walkthrough for school leadership and future users, without putting fake `@spims.test` accounts on production.

---

## 1. How the three hosts relate

| Host | Code path | Purpose | Seed |
|------|-----------|---------|------|
| **Production** `spims-edu.com` | `/var/www/spims` (`main`) | Real school | `SEED_DEMO_DATA=false`. Real users only. |
| **Staging** `staging.spims-edu.com` | `/var/www/spims-staging` (`staging` branch) | CI + engineering UAT | May use demo seed; **resets on every staging deploy / migrate**. Not ideal for client meetings. |
| **Demo** `demo.spims-edu.com` | `/var/www/spims-demo` (`main`, frozen until refreshed) | Client show + training | `SEED_DEMO_DATA=true`. Same *product* as prod; **fake rich data**. |

**Answers:**

- Demo is **not** production data. It runs the **same app** (prod codebase) with a **demo database**.
- Demo is **closer to staging’s seed** than to production content. Staging and demo both use (or can use) `DemoDataSeeder`; production must not.
- Seeders do **not** copy production. They synthesize a school (programs, students, invoices, classroom) so every major flow has something to click.

Until DNS for `demo` is live, use **staging** for shows, knowing a push to `staging` can wipe/replace data.

---

## 2. What the seeder already covers (today)

Strong enough for a **~15 minute** product tour:

- Roles: Super Admin, Adm, Academic, Finance, Instructors, TA, Students, dual-role
- Catalog / programs / offerings / admissions statuses
- Student learn (TH101/BI101), announcements, attendance, discussions
- Finance invoices + one paid cash + wallet money
- Teach hub on TH101, live session (mock Zoom)
- Extra 8A fixtures: event, survey (no response), project membership, live-quiz Ready, optional transcript

**Still thin or empty for a “complete school” show:** see §4.

Authoritative account list: [demo-accounts.md](demo-accounts.md) (refresh §4/§5.2 when seeder advances).

---

## 3. Target experience (definition of done)

A client on **demo.spims-edu.com** can, without hand-entering data:

1. Browse public catalog and a rich offering preview.
2. Log in as student / instructor / each admin type and see **non-empty** primary hubs.
3. Walk one full academic loop: apply → accept → enroll → learn → attempt quiz → submit assignment → see grade → (optional) locked grade / transcript.
4. Walk one finance loop: open invoice → payment plan or pay → receipt → wallet.
5. Walk one staff loop: admissions review → teach attendance → gradebook → finance verify.
6. See ar/fr locale evidence (translated titles + Arabic/French student personas).
7. Hit `/help` with real articles; notifications list not empty.

Out of scope to fake: real Zoom, real card gateways, Parent portal (does not exist), hollow certificates if PDF issuance is unreliable.

---

## 4. Seeder backlog (prioritized)

Extend `DemoDataSeeder` (and `DemoDataSeederTest`) only — same services + `AuditLogWriter` as production. No role-name checks.

### Phase A — “Nothing important is empty” (P0)

| Fixture | Roles / paths |
|---------|----------------|
| Assignment content + submissions (pending + graded) | `/assignments/{id}`, `/teach/{}/assignments` |
| Quiz attempt submitted + graded for `student1` | attempt routes, teach assessment grading |
| Lock grades on one offering (honest lock after submit) | gradebook, `/grades`, `/transcript` |
| Refresh `demo-accounts.md` to match code | operators |

### Phase B — Client wow / finance & locale (P1)

| Fixture | Roles / paths |
|---------|----------------|
| Survey submission (+ optional identity reveal) | `/surveys`, admin survey report, SA feedback-reveals |
| Event reservation (+ optional check-in) | `/events`, `/admin/events` |
| Payment plan on one invoice + installment state | student finance, `/admin/finance` |
| Wallet **points** ledger row; optional refund pending | finance |
| Translation rows for 1–2 program/course titles (ar/fr) | `/admin/translations` |
| In-app notifications (announcement / invoice) | `/notifications` |

### Phase C — Admin depth (P2)

| Fixture | Roles / paths |
|---------|----------------|
| Advising assignment + one hold (and released variant) | `/advising` |
| Certificate template + one reliable verifyable credential | `/admin/certificate-templates`, `/verify/{token}` |
| Communication log rows | `/admin/communications` |
| Completion criteria + closing evaluation on one offering | teach/admin closing |
| Real FILE storage path or drop FILE type from demo | learn file download |
| Project deliverable upload (pending / accepted) | `/projects` |

### Phase D — Polish (P3)

| Fixture | Roles / paths |
|---------|----------------|
| Live-quiz session in lobby (or scripted “press Start”) | teach live-quiz / join |
| Sample email template | `/admin/email-templates` |
| Assessment template row | `/admin/assessment-templates` |
| Enrich secondary offerings (BI102, LI101…) with Week 1 content so teach isn’t empty | instructor |

---

## 5. Delivery plan (execution order)

| Step | Work | Exit criteria |
|------|------|----------------|
| **S0** | Confirm `demo.spims-edu.com` DNS + TLS; keep demo DB independent of staging deploys | `GET /health` ok; 17 `@spims.test` users |
| **S1** | Phase A seeder + tests; update demo-accounts walkthrough | `DemoDataSeederTest` covers assignment + attempt + locked grade |
| **S2** | Phase B seeder + tests | Finance plan, translations, notifications, survey/event asserted |
| **S3** | Phase C as product-safe (skip hollow certs if issuance flaky) | Advising/comms/closing demoable |
| **S4** | Operator runbook: `demo:reset` (or `migrate:fresh --seed`) schedule; “day before client visit” checklist | One-pager in demo-accounts.md |
| **S5** | Optional: GitHub workflow `demo-refresh` (manual) SSH → reset seed on `/var/www/spims-demo` | One-click refresh without touching prod/staging |

**Parity rule:** every new seeded surface that students/instructors use should already have a web UI (or be explicitly deferred in the parity matrix).

---

## 6. Environment policy (hard rules)

1. **Never** enable `SEED_DEMO_DATA` on production.
2. **Demo host** is the client-facing show; **staging** is for engineers before merge.
3. Resetting demo is allowed and expected; resetting production is not.
4. Client passwords stay `Spims@Test2026!` on demo only; rotate Super Admin on demo independently of prod.
5. Document “what not to promise” (live Zoom, card rails, PDF polish) in the walkthrough.

---

## 7. Suggested walkthrough script (after Phase A–B)

1. Guest → `/catalog` → TH101 preview  
2. `student1@spims.test` → learn → assignment → quiz result → grades → finance (plan + wallet) → notifications → events  
3. `ins1@spims.test` → teach TH101 → attendance → grade submissions → discussions  
4. `adm@spims.test` → applications queue → events admin  
5. `aca@spims.test` → programs → translations → gradebook lock / credentials  
6. `fin@spims.test` → invoices → payment plan → verify payment  
7. `dual@spims.test` → teach FREE1 + learn ET101  

---

## 8. Immediate next action

1. Point client meetings at **demo.spims-edu.com**, not staging or production.
2. Merge seeder phases A–D as they land; keep [demo-accounts.md](demo-accounts.md) in sync.
3. Optional: demo-refresh workflow (S5) after seeder backlog is stable.

---

## Status notes (branch tracking)

| Phase | Status | Notes |
|-------|--------|-------|
| S0 demo host | Ops | Prefer `demo.spims-edu.com` for client shows; see [demo-accounts.md](demo-accounts.md). |
| Phase A | Separate branch | Assignment / attempt / lock grades — not this branch. |
| Phase B | Separate branch | Survey response, event reservation, payment plan, translations, notifications. |
| Phase C | Separate branch | Advising, certificate templates, communications, completion, project deliverables. |
| **Phase D** | **This branch (`demo/phase-d-seeder`)** | Live-quiz Lobby session, sample email + assessment templates, BI102/LI101 Week 1 TEXT+READING. |

Refresh [demo-accounts.md](demo-accounts.md) when merging seeder phases.
