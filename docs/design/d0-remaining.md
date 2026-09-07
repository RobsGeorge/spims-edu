# Remaining D0 closed (2026-09-07)

D0 shipped 2026-07-29 with cool field `#f8f9ff` and burgundy `#5d0326`. Those values stay.
This note records the leftover token gaps that were locked in after that ship date.

## What closed

| Gap | Resolution |
|---|---|
| Gold used as primary or body text (One-Burgundy) | Gold `#eac167` / `#e9c16d` is `--color-accent` (and dark title accent) only. `--color-primary` stays burgundy / rose; `--color-text` stays ink / cool light. |
| Soft Lift incomplete | `--shadow-soft` kept. Added `--shadow-lift` (slightly stronger: `0 6px 24px`) on light, dark, and `theme-system` dark. Cards use `--shadow-lift`. |
| Hairline rose missing as a named token | Added `--color-hairline: rgba(219, 192, 196, …)` and applied it to `.card` / `.app-card` (and shared `.spims-hero`) borders. |
| Parchment regression | `#faf6ee` and gold-as-primary `#b8860b` remain absent from `public/css/spims-theme.css` and `app/Support/ThemeTokens.php`. Tests fail if they return. |
| SYSTEM theme | `body.theme-system` still follows `@media (prefers-color-scheme: dark)`. Comment added in CSS and `ThemeTokens::inlineStyleBlock()`. |

PHP defaults in `ThemeTokens` stay in sync with the stylesheet: `hairline` → `--color-hairline`, `shadowLift` → `--shadow-lift`.

## Tests

- `tests/Unit/Support/ThemeTokensTest.php` — cool field, burgundy primary, gold-as-accent, parchment source ban, Soft Lift / hairline maps.
- `tests/Feature/Design/PortalD0TokensTest.php` — CSS locks for primary/bg1, no parchment, no gold `--color-primary`, theme-system + prefers-color-scheme, `--shadow-lift` + hairline on cards.
- `tests/Feature/Design/SacredAcademicFoundationTest.php` — existing seed/home/branding coverage; stylesheet test also asserts Soft Lift + SYSTEM media.

## Out of scope (other agents)

- D1 app shell / `layouts/app.blade.php`
- D2 landing, auth, catalog Blade restyle
- Week-content PHP services
