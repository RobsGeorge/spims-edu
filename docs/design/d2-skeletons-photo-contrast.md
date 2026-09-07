# D2 follow-through — skeletons, contrast, photography

**Date:** 2026-09-07  
**Branch:** `cursor/d2-skeletons-photo-contrast-78d1`

Closes the three leftover D2 acceptance items without changing Sacred Academic tokens, fonts, or card/widget chrome.

## Real catalog loading skeletons

- Filter submit no longer only flashes `aria-busy` and then does a full navigation.
- `public/js/catalog-loading.js` `fetch`es `?fragment=1`, keeps `#catalog-skeletons` visible for the network wait, then replaces `#catalog-results`.
- Pagination uses the same path. Full-page GET still works without JS.
- `?skeleton=1` still forces the skeleton markup for tests.

## Measured WCAG contrast AA

- `ThemeTokens::relativeLuminance()`, `contrastRatio()`, and `aaPairs()` implement WCAG 2 math on the locked token set.
- `tests/Feature/Design/WcagContrastAaTest.php` and `ThemeTokensTest` assert each pair (4.5:1 text, 3:1 gold-on-burgundy large/accent).
- Tokens themselves are unchanged.

## Liturgical photography

- Local JPEGs only: `public/img/landing-hero.jpg`, `landing-featured-1.jpg`, `landing-featured-2.jpg`.
- Cool-field / burgundy documentary frames at low opacity under a field wash so Playfair/Inter copy stays AA.
- No parchment, no gold-leaf, no CDN photos. Existing landing cards, stats, auth split, and catalog widgets are unchanged.
