# D1 remaining — shell chrome gaps

Closes leftover D1 items from the app shell without changing navigation IA (sidebar, drawer, bottom-nav, Teach) or restyling D2 landing/catalog/auth.

**Status:** Implemented. Tokens stay in `public/css/spims-theme.css` (D0). Shell-only overrides live in `public/css/spims-shell.css`.

## What landed

### Topbar catalog search
Authenticated topbar includes a GET form to `catalog.index` with `q`, a visually-hidden label (`ui.search`), and the catalog placeholder. On viewports below `lg` the control is clipped (still in the accessibility tree); from `lg` up it is a compact field. Logical spacing (`margin-inline-*`, `padding-inline`, `inset-inline-start`) keeps RTL mirrored.

### Touch targets
`.app-bottom-link`, `.app-icon-btn`, `.app-menu-btn`, `.app-user-menu-btn`, `.app-topbar-select`, and the search input are min **44×44px** in `spims-shell.css` so Bootstrap `btn-sm` / `form-select-sm` cannot shrink chrome below the D1 floor.

### RTL layout
No physical `margin-left` / `padding-right` / `left` / `right` were present on shell chrome in `resources/views/layouts/app.blade.php`. New chrome uses logical CSS only. Guest `ms-auto` is a Bootstrap 5 logical utility and is left for D2.

Authenticated `dir="rtl"` follows `SetLocale`: profile `preferred_locale` wins over the `locale` cookie. Tests send `locale=ar` **and** set `preferred_locale` to `ar` so the dashboard document is RTL.

### User menu
Avatar circle shows initials (first + last name; email fallback) with `aria-hidden`. Visible first name plus `ui.user_menu` on the toggle. Sign out (`ui.logout`) is unchanged.

## Out of scope
- Landing, catalog page chrome, and auth screens (D2).
- Edits to `public/css/spims-theme.css` or `docs/portal-design-gap-analysis.md`.
- NavigationHub destinations / Teach IA.

## Tests
`tests/Feature/Portal/PortalD1RemainingTest.php` (Portal suite).
