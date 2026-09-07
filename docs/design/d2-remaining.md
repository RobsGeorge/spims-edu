# D2 remaining — first-run trust gaps

**Date:** 2026-09-07  
**Branch:** `cursor/design-d2-surfaces-78d1`  
**Scope:** Close leftover D2 gaps on landing, auth, and catalog. D2 core (hero, auth-card, catalog filters) already shipped.

## What this pass closes

### Landing (`resources/views/home.blade.php`)

- Still one headline (`ui.home_heading`) and one CTA group (register/sign-in for guests; dashboard/catalog when signed in).
- Adds a **secondary band** (not a second hero): three typographic “how it works” beats plus a catalog teaser link.
- Copy lives in `lang/{ar,en,fr}/home.php`. No clipart; type + hairline only.
- `spims-landing` class is unchanged for existing tests.

### Auth (`resources/views/auth/*.blade.php`)

- Login, register, forgot, OTP verify, reset, and set-password share the same card: `auth-card`, `col-md-6 col-lg-5`, `p-4 p-md-5`, `h3` title, muted `auth-help` text.
- Labels have matching `for`/`id`. Controllers and form actions are unchanged.

### Catalog

- Sort `sort=interest` (“Most flagged”) orders by `interest_flags_count`.
- Authenticated `interest=flagged` limits to the current user’s flags. Guests may pass the param; it is ignored (no 500).
- Results wrap in `#catalog-results` with static `aria-busy="false"`.
- Empty hint is unchanged. `price=free` / `q=` filters still apply. Guest catalog → offering preview is intact.

## CSS

`public/css/spims-public.css` is linked from `layouts/app.blade.php` on `home`, `auth.*`, and `catalog.*` only. `public/css/spims-theme.css` is not edited. The layout already had a stylesheet stack (`spims-theme.css` + `@stack('styles')`).

## Tests

`tests/Feature/Portal/PortalD2RemainingTest.php`:

- Home heading + primary CTA + secondary band copy
- Arabic landing `dir=rtl`
- Login / register / forgot / verify (plus reset and set-password) render `auth-card`
- Interest sort and flagged filter do not 500; empty catalog shows the empty hint
- FREE1 / PAID1 filters still pass
- Guest home → catalog → offering preview

## Later closed (screenshot parity pass)

See `docs/design/d2-screenshot-parity.md` — marketing landing, split auth, catalog featured banner + real skeletons, contrast AA test, guest-nav catalog button.
