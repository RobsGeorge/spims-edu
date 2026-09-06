# SPIMS mobile API — `/api/v1` contract

**Implemented in:** this repository
**Built by:** phases S1, S6, and S8 of [`implementation-plan.md`](implementation-plan.md)
**Consumers:** the SPIMS student app and the SPIMS instructor app (one binary or two — the API does
not care; abilities on the token decide what is reachable).

This is the wire contract. It exists so the mobile client can be written against a fixed shape
before the endpoints are all built, and so no endpoint invents its own conventions.

### A note on the reference implementation

The Khedma `/api/v1` surface is the proof that this shape works in production, and its **feature
sequencing** (waves A–E) is worth copying directly. Its **conventions are not.** Four places where
this spec deliberately diverges, so nobody "matches Khedma" and inherits the weaker choice:

| Concern | Khedma today | This spec |
|---|---|---|
| Serialization | Inline private `serialize()` methods per controller; no `App\Http\Resources` at all | API Resource classes, so a shape is defined once |
| Pagination | Only on `/notifications`, with `per_page` hard-coded to 25 and other lists capped at arbitrary limits (attendance 200, sessions 100) | Every collection paginated, `per_page` client-controlled to a maximum of 100 |
| Localization | No `Accept-Language` handling — `SetLocale` is not on the API middleware stack, so responses come back in the app default | `Accept-Language`, then stored preference, then `en` |
| Token lifetime | `sanctum.expiration` is `null`; tokens never expire and there is no refresh | Expiring tokens; re-authenticate with email and password |

Khedma also has no OpenAPI document. SPIMS should, because its mobile client is a separate codebase
and the contract needs to be machine-checkable from both sides.

---

## 1. Conventions

### Base and versioning

All routes live under `/api/v1`. A breaking change means `/api/v2`; additive fields do not. Clients
must ignore unknown fields.

### Authentication

Sanctum personal access tokens as `Authorization: Bearer {token}`, issued against **email and
password**. Shipped by S1:

```
POST /api/v1/login   { email, password, device_name? } → 200 { data: { token, token_type, user } }
POST /api/v1/logout                                    → 204, revokes only the token used
GET  /api/v1/me                                        → 200 { data: { …profile, roles } }
GET  /api/v1/branding                                  → 200, public, safe pre-login
```

> **Corrected during S1.** This section originally specified a two-step OTP exchange
> (`POST /api/v1/login` returning `otp_required` plus a `challenge_id`, then
> `POST /api/v1/login/verify`), on the assumption that it mirrored the web login. It does not:
> `AuthService::login()` has always been email and password, and OTP exists in this app only for
> email verification and password reset. There is no `login/verify` endpoint and no
> `otp_required` field. See the S1 section of [`implementation-plan.md`](implementation-plan.md).

Throttle: `login` uses the app's named `login` limiter, so a sixth consecutive failure returns 429
with `code: "RATE_LIMITED"`.

Tokens carry role abilities (`role:STUDENT`, `role:INSTRUCTOR`, `role:TA`, `role:ACADEMIC_ADMIN`, …).
Abilities are a coarse first gate; the permission matrix and resource scope are the real check. A
token minted for a student is rejected on `/teach/*` before any query runs. An account holding no
role gets a token with no abilities, so any future `tokenCan()` check fails closed.

### Response envelope

Single resource:

```json
{ "data": { "id": "01J...", "title": "..." } }
```

Collection, always paginated:

```json
{
  "data": [ ... ],
  "meta": { "page": 1, "per_page": 20, "total": 137, "last_page": 7 }
}
```

`?page=` and `?per_page=` (default 20, maximum 100).

### Errors

One shape, every failure:

```json
{
  "message": "لا تملك صلاحية تنفيذ هذا الإجراء.",
  "code": "FORBIDDEN",
  "errors": { "field": ["..."] }
}
```

`errors` is present only on 422.

| Status | `code` | Cause |
|---|---|---|
| 401 | `UNAUTHENTICATED` | Missing, malformed, or revoked token |
| 403 | `FORBIDDEN` | Permission key denied, or resource outside the actor's scope |
| 404 | `NOT_FOUND` | Unknown id, or an id outside the actor's scope where existence itself is private |
| 409 | `CONFLICT` | Stale `lock_version`, or a state-machine transition that is no longer valid |
| 422 | `VALIDATION_FAILED` | Input validation |
| 423 | `LOCKED` | The gradebook or session is locked or closed |
| 429 | `RATE_LIMITED` | Throttle; `Retry-After` header set |

Scope failures on *reads of other people's records* return 404 rather than 403, so an id cannot be
probed for existence. Scope failures on *writes* return 403.

### Localization

`Accept-Language: ar | en | fr` sets the locale for that response. Absent, the user's stored
preference applies; absent that, `en`. Server-rendered strings (statuses, validation, notification
copy) are returned already translated.

S1 ships this as `App\Support\Api\ApiLocale`, resolved **at the point each translated string is
produced** rather than only as a middleware side-effect. Laravel's global middleware-priority list
runs `Authenticate` before ordinary route middleware regardless of registration order, so an
unauthenticated request's 401 renders before any locale-setting middleware would have run. A
middleware alone is therefore not sufficient, and error envelopes must resolve the locale directly.

The final fallback is `en`, not `ar`: the header and the stored preference between them cover every
authenticated caller, and an anonymous caller that states no preference is far more likely to be a
tool or a client in development than an Arabic-speaking user. Content authored by staff is returned in the requested
locale when a translation exists, with the source locale as a fallback and a
`"locale_fallback": true` marker on the field's parent object.

### Money

Never a float, never a bare string:

```json
{ "amount": { "minor_units": 15000, "currency": "EGP", "formatted": "EGP 150.00" } }
```

### Timestamps

ISO-8601 with an offset, in UTC (`2026-09-05T14:30:00Z`). Clients localize for display. The server
is authoritative for everything time-sensitive — exam timers, attendance windows, join windows, quiz
questions. A client-supplied timestamp is never trusted.

### Idempotency

Mutations that could be retried on a flaky connection (`submit`, `reserve`, `join`, `check-in`,
`pay`, and **every** instructor `POST` / `PUT` / `DELETE` under `/teach/*`) accept an
`Idempotency-Key` header. A repeat from the same actor with the same key returns the original
result instead of acting twice. A missing key still runs the write (backward compatible). The
OpenAPI component is `#/components/parameters/IdempotencyKey`.

### Confirmation tokens for irreversible actions

`gradebook.lock`, `offering.close`, `projects.announce`, and `assessments.announce_results` require a
two-step call. The preceding `GET` returns a short-lived `confirmation_token` alongside a
human-readable summary of consequences; the mutation must echo it. A missing or replayed token is
422. A repeated GET reuses the unexpired token so a pull-to-refresh cannot invalidate the dialog.
A retry that also sends `Idempotency-Key` returns the original success after the token is consumed.
This is the API form of SPIMS's consequence-aware confirm dialogs, and it is what stops a
mis-tap on a phone from locking a term.

### Uploads

Multipart, through `ObjectStorageService`, with the same MIME and size limits as web. Large files
may instead request a signed URL from `POST /api/v1/uploads/signed-url` and confirm with the
returned key.

### Offline behaviour

The API is read-cacheable and write-explicit. Every collection response carries an `ETag`;
clients send `If-None-Match` and handle 304. There is no sync protocol and no server-side
write queue — a failed mutation is the client's to retry, with `Idempotency-Key`.

---

## 2. Student surface

Grouped by the wave that ships it (see plan §S6).

### Wave A — foundation

| Method | Path | Notes |
|---|---|---|
| `GET` | `/me` | Profile, roles, locale, theme, avatar, enrolled offering count |
| `PUT` | `/me/preferences` | `locale`, `theme`, `notify_email` |
| `POST` | `/me/picture` | Multipart |
| `GET` | `/dashboard` | Bento payload: current offerings, next live session, due items, wallet snapshot, unread count |
| `GET` | `/branding` | Theme tokens and logos; safe to call before login |
| `GET` | `/notifications` | Paginated; `?unread=1` |
| `GET` | `/notifications/{id}` | Marks read |
| `POST` | `/notifications/{id}/read` | |
| `POST` | `/notifications/mark-all-read` | |
| `GET` | `/notification-settings` | Per-event, per-channel matrix |
| `PUT` | `/notification-settings` | |
| `GET` | `/announcements` | Scoped to the student's offerings and programs |
| `GET` | `/announcements/{id}` | |
| `POST` | `/announcements/{id}/dismiss-banner` | |

### Wave B — academic read

| Method | Path | Notes |
|---|---|---|
| `GET` | `/offerings` | The student's enrolled offerings with progress |
| `GET` | `/offerings/{id}` | Detail, staff, semester, gating summary |
| `GET` | `/offerings/{id}/weeks` | Week list with lock state and completion |
| `GET` | `/offerings/{id}/weeks/{weekId}/items` | Content items; gated items return metadata only |
| `GET` | `/items/{id}` | Item payload — text, Vimeo id, or signed file URL |
| `POST` | `/items/{id}/complete` | |
| `POST` | `/offerings/{id}/weeks/{weekId}/complete` | |
| `GET` | `/offerings/{id}/grades` | **Released items only** |
| `GET` | `/transcript` | Academic records with GPA |
| `GET` | `/degree-audit/{studentProgramId}` | Requirements met and outstanding |
| `GET` | `/attendance/mine` | Across all offerings, paginated |
| `GET` | `/offerings/{id}/attendance/mine` | Per offering, with the computed percentage and the policy threshold |
| `GET` | `/me/credentials` | |
| `GET` | `/credentials/{id}/download` | PDF stream |
| `GET` | `/offerings/{id}/completion` | Own result: criteria met and outcome |

### Wave C — academic write

| Method | Path | Notes |
|---|---|---|
| `GET` | `/offerings/{id}/assignments` | |
| `GET` | `/assignments/{id}` | Includes own submission and grade if released |
| `POST` | `/assignments/{id}/submit` | Multipart; `Idempotency-Key` |
| `POST` | `/assignments/{id}/resubmit` | 422 outside the resubmission window |
| `GET` | `/offerings/{id}/assessments` | |
| `GET` | `/assessments/{id}` | Rules, attempts used, attempts allowed, window |
| `POST` | `/assessments/{id}/start` | Returns `attempt_id`, `due_at`, and the drawn question set |
| `GET` | `/attempts/{id}` | Resume; returns saved answers |
| `POST` | `/attempts/{id}/save` | Autosave; partial answers |
| `POST` | `/attempts/{id}/submit` | `Idempotency-Key` |
| `GET` | `/attempts/{id}/timer` | Server-authoritative remaining seconds |
| `POST` | `/attempts/{id}/focus-loss` | Feeds `ProctorService`; may return 423 on termination |
| `GET` | `/offerings/{id}/discussions` | Board and threads |
| `GET` | `/discussions/threads/{id}` | Posts, paginated |
| `POST` | `/discussions/threads/{id}/posts` | |
| `POST` | `/offerings/{id}/discussions/threads` | When the board allows student threads |
| `POST` | `/sessions/{id}/check-in` | `{ code }`; attendance self check-in |

The exam endpoints are the highest-stakes surface. Three rules: the timer is server-side and
`/timer` is the only truth; `save` is idempotent and never rejects a partial answer; `submit` is
terminal and returns the final state so the client never has to guess.

### Wave D — admissions, enrollment, money

| Method | Path | Notes |
|---|---|---|
| `GET` | `/catalog` | Public course and program catalog with filters |
| `GET` | `/catalog/courses/{id}` | Includes prerequisites and pricing for the caller's region |
| `POST` | `/catalog/courses/{id}/interest` | |
| `GET` | `/application-forms/{id}` | Field definitions for dynamic rendering |
| `POST` | `/applications` | Create or update a draft |
| `POST` | `/applications/{id}/submit` | |
| `GET` | `/applications` / `/applications/{id}` | Status and decision |
| `GET` | `/enrollments` | |
| `POST` | `/enrollments` | Register; 409 on a closed window, a hold, or a conflict |
| `POST` | `/enrollments/{id}/drop` | |
| `POST` | `/enrollments/{id}/withdraw` | |
| `GET` | `/invoices` / `/invoices/{id}` | |
| `POST` | `/invoices/{id}/checkout` | Returns a gateway redirect payload |
| `GET` | `/payments/{id}/receipt` | PDF |
| `GET` | `/wallet` | Four balances plus ledger |
| `POST` | `/donations` | |

Payment completion happens through the existing server-side webhooks. The client never confirms a
payment; it polls the invoice or waits for the notification.

### Wave E — engagement

| Method | Path | Notes |
|---|---|---|
| `GET` | `/offerings/{id}/project-assessments` | |
| `GET` | `/projects/{id}` | Team roster, phases, deliverable checklist, grade |
| `POST` | `/project-assessments/{id}/join` | 409 outside the join window or at capacity |
| `POST` | `/project-assessments/{id}/leave` | Allowed once |
| `POST` | `/projects/{id}/deliverables/{deliverableId}/submit` | Multipart |
| `DELETE` | `/projects/{id}/submission-files/{fileId}` | |
| `GET` | `/projects/{id}/peer-evaluations/pending` | |
| `POST` | `/projects/{id}/peer-evaluations` | |
| `GET` | `/feedback/surveys` / `/feedback/surveys/{id}` | |
| `POST` | `/feedback/surveys/{id}/submit` | Anonymous by default |
| `GET` | `/events` / `/events/{id}` / `/events/mine` | |
| `POST` | `/events/{id}/reserve` / `/events/{id}/cancel` | `Idempotency-Key` |
| `POST` | `/live-quiz/join` | `{ code }` |
| `GET` | `/live-quiz/sessions/{id}` | Current state; the polling fallback when Reverb is down |
| `POST` | `/live-quiz/sessions/{id}/questions/{qid}/answer` | One answer per question |

---

## 3. Instructor surface

Every route requires a `role:INSTRUCTOR` or `role:TA` ability **and** passes its resource through
`AuthorizeService`, so an instructor reaches only offerings listed in `offering_staff`. TA
restrictions are server-enforced, never merely hidden in the UI.

### Teaching context

| Method | Path | Notes |
|---|---|---|
| `GET` | `/teach/offerings` | Offerings the caller staffs, with counts needing attention |
| `GET` | `/teach/offerings/{id}` | Summary across content, roster, grading, attendance |

### Attendance

| Method | Path | Notes |
|---|---|---|
| `GET` | `/teach/offerings/{id}/sessions` | |
| `POST` | `/teach/offerings/{id}/sessions` | Create a session, including in-person |
| `GET` | `/teach/sessions/{id}/roster` | Students with current marks and the session `lock_version` |
| `POST` | `/teach/sessions/{id}/attendance` | Bulk mark; body carries `lock_version`; **409** if stale |
| `POST` | `/teach/sessions/{id}/attendance/fill-missing` | |
| `POST` | `/teach/sessions/{id}/close` | 423 afterwards on further writes |
| `POST` | `/teach/sessions/{id}/check-in-code` | Issue a self check-in code |
| `GET` | `/teach/offerings/{id}/attendance/report` | `?format=csv` for export |

The `lock_version` round trip matters: two instructors marking the same roster from two phones is
the ordinary case, not the edge case.

### Grading

| Method | Path | Notes |
|---|---|---|
| `GET` | `/teach/offerings/{id}/gradebook` | Grid: components, students, computed percentages |
| `GET` | `/teach/offerings/{id}/assignments` | Dashboard counts: submitted, ungraded, overdue |
| `GET` | `/teach/assignments/{id}/submissions` | |
| `POST` | `/teach/submissions/{id}/grade` | `{ raw_score, feedback }` |
| `POST` | `/teach/submissions/{id}/mark-received` | Offline hand-in |
| `POST` | `/teach/assignments/{id}/remind-unsubmitted` | |
| `GET` | `/teach/assessments/{id}/attempts` | Status, score, proctor warnings |
| `POST` | `/teach/answers/{id}/grade` | Override an auto or AI score |
| `POST` | `/teach/assessments/{id}/announce-results` | Confirmation token required |
| `POST` | `/teach/offerings/{id}/gradebook/submit` | |
| `POST` | `/teach/offerings/{id}/gradebook/lock` | **Instructor only**; confirmation token required; TA → 403 |

`gradebook/reopen` is deliberately absent from the instructor API. It is an Academic Admin action
and stays on the web.

### Roster and students

| Method | Path | Notes |
|---|---|---|
| `GET` | `/teach/offerings/{id}/roster` | `?format=csv` |
| `GET` | `/teach/offerings/{id}/birthdays` | |
| `GET` | `/teach/offerings/{id}/students/{studentId}` | Profile, grades, attendance, submissions |
| `GET` | `/teach/offerings/{id}/students/{studentId}/notes` | Staff-only; never visible to the student |
| `POST` | `/teach/offerings/{id}/students/{studentId}/notes` | |
| `PUT` | `/teach/offerings/{id}/weeks/{weekId}/students/{studentId}/assessment` | Per-week rating |

### Communication

| Method | Path | Notes |
|---|---|---|
| `POST` | `/teach/offerings/{id}/announcements` | Creates a draft |
| `PUT` | `/teach/announcements/{id}` | Writes a revision |
| `POST` | `/teach/announcements/{id}/publish` | Fans out; TA → 403 |
| `GET` | `/teach/offerings/{id}/discussions/threads` | |
| `POST` | `/teach/discussions/threads/{id}/moderate` | |
| `POST` | `/teach/discussions/threads/{id}/grade` | |

### Content

| Method | Path | Notes |
|---|---|---|
| `POST` | `/teach/offerings/{id}/weeks` | |
| `POST` | `/teach/weeks/{id}/items` | Multipart for file items |
| `PUT` | `/teach/items/{id}` | |
| `DELETE` | `/teach/items/{id}` | |

### Projects

| Method | Path | Notes |
|---|---|---|
| `GET` | `/teach/offerings/{id}/project-assessments` | |
| `GET` | `/teach/project-assessments/{id}/teams` | |
| `POST` | `/teach/projects/{id}/members/move` | |
| `POST` | `/teach/project-submissions/{id}/review` | |
| `POST` | `/teach/projects/{id}/grade` | Team and per-student against criteria |
| `POST` | `/teach/project-assessments/{id}/announce` | Confirmation token required |

### Live

| Method | Path | Notes |
|---|---|---|
| `GET` | `/teach/offerings/{id}/live-sessions` | |
| `POST` | `/teach/live-sessions/{id}/attendance/import` | Trigger a Zoom import |
| `POST` | `/teach/live-quiz/{id}/host/start` | |
| `POST` | `/teach/live-quiz/sessions/{id}/launch` | |
| `POST` | `/teach/live-quiz/sessions/{id}/results` | |
| `POST` | `/teach/live-quiz/sessions/{id}/end` | |

---

## 4. Permission mapping

Every endpoint asserts the same key as its web counterpart, with the resource passed. The API adds
no permission key of its own — if a mobile action has no web equivalent, the key is added to
`config/permissions.php` in the same PR and the web surface gains it too. Divergence between the two
surfaces is the failure mode this rule exists to prevent.

| Group | Keys |
|---|---|
| Profile | `profile.edit_own`, `notifications.preferences` |
| Learning | `offerings.view`, `assessments.take`, `assignments.submit`, `discussions.post`, `discussions.thread` |
| Records | `transcript.view`, `attendance.view_own`, `attendance.self_check_in`, `completion.view` |
| Money | `finance.pay`, `finance.donate`, `enrollment.register` |
| Engagement | `projects.view`, `projects.join`, `projects.peer_eval`, `feedback.view`, `events.view`, `events.reserve`, `live_quiz.play` |
| Teaching | `offerings.content`, `attendance.record`, `attendance.view_all`, `attendance.edit`, `roster.view`, `roster.export`, `student_notes.manage`, `module_assessment.manage` |
| Grading | `assignments.grade`, `assessments.grade`, `gradebook.configure`, `gradebook.lock`, `assessments.announce_results`, `projects.grade`, `projects.announce`, `discussions.grade`, `discussions.moderate` |
| Publishing | `announcements.manage`, `announcements.publish`, `live_quiz.host` |

---

## 5. What is deliberately not on mobile

Keeping these on the web is a scope decision, not an oversight. They are low-frequency, high-context
operator tasks where a phone is the wrong tool and the confirmation burden is high.

- Gradebook **reopen** (Academic Admin).
- Programs, courses, prerequisites, semesters, and offering creation.
- Admissions review and decisions; the application form builder.
- Finance operations: invoice creation, manual payment verification, refund approval, point grants,
  pricing.
- Roles Hub and the permission matrix.
- Theme editor, translations verification.
- Superadmin console, audit browsing, observability.
- Certificate template editing and credential issuance.
- Event administration (creation, publish, cancel) — student reservation and staff QR check-in are
  on mobile; the console is not.

---

## 6. Client implementation notes

- **Do not cache the permission matrix.** Ask the server. `GET /me` returns the caller's effective
  permission keys, and the client uses them for affordance only — the server re-checks everything.
- **Treat 409 as a normal outcome**, not an error dialog. Stale `lock_version` on attendance and a
  closed join window on projects are both expected races; refetch and show the current state.
- **Exam and quiz screens must survive a background or a network drop.** Autosave on every answer,
  resume from `GET /attempts/{id}`, and trust `GET /attempts/{id}/timer` over any local countdown.
- **Render RTL from the data, not from a locale guess.** The response carries the resolved locale;
  mirror on that.
- **Show the `formatted` money string.** Do not reformat `minor_units` on the client; currency
  formatting rules live server-side so web and mobile agree.
