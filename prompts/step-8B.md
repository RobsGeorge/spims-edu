# Step 8B — Demo Mode

Step ID: `8B`  
Wave: W5 (needs 8A) | New UI — design system native.  
Scope: routes, app/Http/Controllers, resources/views

## What to build

### Environment flag
`SPIMS_DEMO_MODE` in `.env`. Defaults false. When false, all demo routes return 403.
In `APP_ENV=production`, the flag cannot be enabled at all (fails closed).

### Dismissible banner
Appears on all pages when demo mode is on. Shows current-user role and quick-login links.
Dismissible (cookie-based persistence). Styled as a distinct non-error info bar using design tokens.

### `/demo/login` — role quick-login
- Rate-limited (≤10 requests/minute per IP)
- **Returns 403 when `SPIMS_DEMO_MODE` is false**
- Audited: every quick-login writes an AuditLog entry with actor, target role, timestamp
- Available roles: student, instructor, admin (at minimum, whatever is seeded in 8A)

### `/demo/guide` — per-role checklist
A page with a checklist of what to demonstrate for each role, with deep links into each flow.
Every deep link must resolve to a live route (validate all links).

## Gate
- With the flag OFF: `/demo/login` returns 403 AND the banner does not render (test both)
- With `APP_ENV=production`: flag cannot be enabled at runtime
- Quick-login is rate limited (assert the limit trips after the configured number of requests)
- Every quick-login writes an audit entry
- Every `/demo/guide` deep link resolves (no 404s)

`echo "8B" > .claude/current-step` then `./scripts/validate-step.sh 8B`
