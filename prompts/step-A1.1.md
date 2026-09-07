# Step A1.1 — Token Layer

Step ID: `A1.1`  
Wave: W1 (sequential — only this substep is running)  
Scope: public/css/spims-theme.css  
**Edit the existing theme file. Do not create a separate file.**

## Read first
Open `public/css/spims-theme.css`. The palette (`--color-*`), radius, shadow and font tokens
already exist and are GOOD — keep them exactly. You are adding two missing scales and two
missing primitives.

## What to add

### Type scale
Fluid, clamp-based, 360px→1440px:
```
--text-xs    (≈12px at 360, ≈12px at 1440)
--text-sm    (≈14px → 14px)
--text-base  (≈16px → 16px)
--text-lg    (≈18px → 20px)
--text-xl    (≈20px → 24px)
--text-2xl   (≈24px → 30px)
--text-3xl   (≈30px → 36px)
--text-4xl   (≈36px → 48px)
```
Each token paired with a line-height: `--leading-xs` … `--leading-4xl`.
Use `clamp()` — do not use media queries for size.

### Spacing scale
Consistent 4px-based ratio:
```
--space-0: 0
--space-1: 0.25rem  (4px)
--space-2: 0.5rem   (8px)
--space-3: 0.75rem  (12px)
--space-4: 1rem     (16px)
--space-6: 1.5rem   (24px)
--space-8: 2rem     (32px)
--space-10: 2.5rem  (40px)
--space-12: 3rem    (48px)
--space-16: 4rem    (64px)
--space-20: 5rem    (80px)
--space-24: 6rem    (96px)
```

### Focus ring
`--focus-ring: 0 0 0 3px var(--color-primary);`
All interactive elements must use this. No per-component focus ring variations.

### Motion
```
--motion-fast: 100ms ease
--motion-base: 200ms ease
--motion-slow: 350ms ease
```
Plus a global `prefers-reduced-motion` guard that sets all three to `0ms ease`.

## Rules
- Define every new token in **both** the light block and the dark block (even if the value is
  identical — the light block is not the fallback for dark; both must be explicit).
- Do not touch any existing `--color-*`, `--radius-*`, `--shadow-*`, or `--font-*` tokens.
- Do not add utility classes — tokens only.

## Gate
`tests/Feature/Design/DesignTokenTest.php` (you must create this test):
- Every `--text-*`, `--leading-*`, `--space-*`, `--focus-ring`, `--motion-*` token is present in the file
- Each is defined in both the light and dark CSS blocks
- No token references a literal hex colour outside the palette block
- A `prefers-reduced-motion` media query exists and contains `--motion-*` overrides

Write `echo "A1.1" > .claude/current-step` then run `./scripts/validate-step.sh A1.1`.
