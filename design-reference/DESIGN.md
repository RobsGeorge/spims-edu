# SPIMS — Sacred Academic design system (canonical)

> **Source of truth for agents.** Live tokens live in `app/Support/ThemeTokens.php` and `public/css/spims-theme.css`. Do not invent parallel palettes. Do not treat Stitch HTML under `design-reference/*/` as a Tailwind runtime — the app ships **Bootstrap 5.3 CDN + one public CSS file** (no npm/Tailwind build).

## Brand & tone

Calm, authoritative, quietly reverent — a Coptic Orthodox academic institution. Cool near-white field, liturgical burgundy spine, academic gold accent. Friendly and clear, never sterile or parchment-skeuomorphic.

## Rejected (do not use)

| Banned | Why |
|--------|-----|
| Warm parchment / cream (`#FAF8F6`, `#faf6ee`, etc.) | Replaced by Sacred Academic cool field |
| Old burgundy `#7B1E3B` as default primary | Live primary is `#5d0326` / spine `#380014` |
| Tailwind-as-runtime / shadcn / Framer Motion stacks | Map patterns onto Bootstrap + `spims-theme.css` |
| Dark Stitch serifs (EB Garamond, Source Serif 4) as production fonts | **Reference-only**; keep Inter + Playfair + IBM Plex Arabic |

## Color tokens (live)

Semantic names map to CSS variables `--color-*` and to keys in `ThemeTokens::defaults()`. Light and dark sets are required.

| Token key | CSS var | Light | Dark |
|-----------|---------|-------|------|
| bg1 | `--color-bg-1` | `#f8f9ff` (cool field) | `#0d1322` |
| bg2 | `--color-bg-2` | `#eff4ff` | `#151b2b` |
| bg3 | `--color-bg-3` | `#e6eeff` | `#191f2f` |
| surface | `--color-surface` | `#ffffff` | `#191f2f` |
| surfaceLow | `--color-surface-low` | `#eff4ff` | `#151b2b` |
| surfaceBorder | `--color-surface-border` | `rgba(219, 192, 196, 0.55)` | `rgba(85, 66, 69, 0.85)` |
| title | `--color-title` | `#5d0326` | `#ffb1c0` |
| titleAccent | `--color-title-accent` | `#380014` | `#e9c16d` |
| text | `--color-text` | `#0b1c30` | `#dde2f8` |
| textMuted | `--color-text-muted` | `#554245` | `#dbc0c4` |
| link / primary | `--color-link` / `--color-primary` | `#5d0326` | `#ffb1c0` |
| primaryHover | `--color-primary-hover` | `#380014` | `#ffd9df` |
| primaryText | `--color-primary-text` | `#ffffff` | `#380014` |
| accent | `--color-accent` | `#eac167` | `#e9c16d` |
| accentText | `--color-accent-text` | `#251a00` | `#251a00` |
| success | `--color-success` | `#10b981` | `#10b981` |
| warning | `--color-warning` | `#f59e0b` | `#f59e0b` |
| danger | `--color-danger` | `#ef4444` | `#ef4444` |
| info | `--color-info` | `#3b82f6` | `#60a5fa` |

Gradient background: `linear-gradient(160deg, bg1 → bg2 → bg3)` via `--gradient-bg`. Soft elevation: `--shadow-soft` / `--shadow-floating`.

Administrative Admin can override editable color keys via Theme Editor (`admin/theme`); radii/fonts stay in CSS unless product later adds DB control.

## Typography (shipped)

- **LTR:** Inter (UI/body) + Playfair Display (brand / page titles).
- **RTL (`ar`):** IBM Plex Sans Arabic for body and titles (Playfair not loaded).
- **Type scale CSS vars:** `--text-display` … `--text-label-sm` in `spims-theme.css`.
- Headings semibold/bold; body regular; tabular nums in tables.

Optional Stitch dark-mode serif stacks remain **documentation/reference only** — do not swap production fonts without an explicit brand pass.

## Spacing & radius

- Spacing vars: `--space-xs` (4) · `--space-sm` (8) · `--space-md` (16) · `--space-lg` (24) · `--space-xl` (32) · `--space-2xl` (48).
- Radius: `--radius-sm` 8 · `--radius-md` 16 · `--radius-lg` 24 · `--radius-full` (pills/avatars). Same scale in light and dark.

## Elevation

- Cards: 1px surface border + `--shadow-soft`.
- Popovers / drawers / floating: `--shadow-floating`. Dark mode: higher shadow opacity, clearer borders.

## Stack & component recipes

Use **Bootstrap 5.3** primitives + theme classes in `public/css/spims-theme.css`:

- **Buttons:** `.btn-primary` (burgundy pill), `.btn-accent` (gold), outline/secondary variants. Touch target ≥ 44px.
- **Cards:** `.academic-card` / `.app-card` / `.card` — surface, border, radius-md, soft shadow.
- **Forms:** themed `.form-control` / `.form-select` + `.academic-form` helpers.
- **Alerts:** Bootstrap alerts bridged to success/warning/danger/info tokens.
- **Modals:** `.academic-modal` / themed `.modal-content`.
- **Shell:** sidebar `lg+`; offcanvas drawer + bottom nav `<lg`; logical properties for RTL.

Map Stitch HTML/PNG patterns onto these recipes — do not copy Tailwind utility strings into Blade.

## States

- Hover: slight lift / muted surface tint.
- Focus: visible 2–3px ring using primary (keyboard a11y).
- Disabled: ~50% opacity, no pointer.
- Loading / empty: skeletons or `<x-empty-state>` with a primary CTA.

## Responsive (mobile-first)

- Breakpoints follow Bootstrap: sm 576 · md 768 · lg 992 · xl 1200.
- `< lg`: sidebar → drawer; bottom nav visible; tables scroll or stack.
- Touch targets ≥ 44px; content not trapped under bottom nav (`padding-bottom` on main column).

## RTL (Arabic)

- `dir="rtl"` on `<html>` for `ar`. Prefer Bootstrap RTL build + **logical** CSS (`inset-inline-*`, `padding-inline`, `border-inline-end`) — never hard-code left/right for chrome.
- Mirror directional icons (chevrons, back/next); keep brand marks unflipped.

## Motion

- Durations ~150–250ms (`--transition-base`). Respect `prefers-reduced-motion`.
- Prefer CSS transitions already in `spims-theme.css`; no Framer Motion dependency.

## Token → code mapping (for the agent)

1. Defaults: `ThemeTokens::defaults()` → seeded Theme row (`ThemeSeeder`).
2. CSS baseline: `public/css/spims-theme.css` (`:root` / `body.theme-light|dark|system`).
3. Runtime overrides: `ThemeTokens::toCssVariables()` + `inlineStyleBlock()` injected as `#spims-theme-tokens`.
4. Editable subset in Theme Editor: primary, accent, bg1, surface, textMuted (light + dark) at minimum.
5. Components consume CSS variables / recipe classes — never hard-code parchment or ad-hoc hex for brand chrome.

## Related references

- Stitch screen HTML/PNG under `design-reference/*_spims/` — visual targets for Phase B surface work.
- `design-reference/sacred_academic*/DESIGN.md` — Material-style dumps; defer to this file + live PHP/CSS when they conflict.
- Gap analysis: `docs/portal-design-gap-analysis.md`.
