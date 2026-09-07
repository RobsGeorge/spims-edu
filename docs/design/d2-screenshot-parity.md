# D2 screenshot parity

**Date:** 2026-09-07  
**Branch:** `cursor/design-reference-parity-78d1`  
**Scope:** Close the leftover D2 acceptance item — visual parity of landing, auth, and catalog against the original design-reference pack.

## What this pass closes

- Marketing landing (hero card, chip, display title, stats, featured bento, journey band, burgundy footer)
- Split auth (burgundy brand panel + gold rule + existing `auth-card`)
- Catalog featured banner, card media headers, and real loading skeletons
- `spims-public.css` actually linked from `layouts/app.blade.php` on `home`, `auth.*`, and `catalog.*`
- Local SVG atmosphere (no hotlinked photography)
- Contrast AA assertions on Sacred Academic token pairs
- Light / dark / RTL structure hooks in `DesignReferenceParityTest`

## Source

`RobsGeorge/Spims` `design-reference/` HTML. LFS `screen.png` files are 28-byte pointers and cannot be vendored. Checklist: `docs/design-reference/README.md`.

## Intentionally unchanged

- OTP remains one `name="code"` field (not six inputs)
- Guest → catalog → offering preview stays 200
- D0 tokens in `spims-theme.css` / `ThemeTokens` (gold remains accent-only)
- Authenticated app shell (D1) — catalog content restyles inside the existing shell when signed in
