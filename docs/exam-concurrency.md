# Exam concurrency check (production box)

Phase 9 acceptance: re-run a concurrency smoke on the **target VPS** after go-live.

## Goal

Confirm timed exam start/save under concurrent students does not dead-lock or 5xx.

## Procedure

1. Seed or create one released assessment with a short `due_at` and multiple enrolled students (or factory users in staging).
2. From a workstation with `ab` or `hey`:

```bash
# Authenticated cookie jar required — prefer staging with test accounts.
# Example with hey (install separately):
hey -n 200 -c 20 -m POST \
  -H "Cookie: laravel_session=..." \
  -H "X-CSRF-TOKEN=..." \
  "https://staging.spims-edu.com/assessments/{id}/start"
```

3. Watch during the run:
   - `php8.2 artisan assessments:auto-submit-expired` still succeeds
   - Postgres `pg_stat_activity` shows no long locks
   - Nginx 5xx rate stays near zero
4. Pass criteria: ≥95% HTTP 2xx/3xx on start/save; no corrupted attempt rows; auto-submit still locks expired attempts.

Document results (date, concurrency, pass/fail) with the release notes.
