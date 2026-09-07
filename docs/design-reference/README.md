# Design-reference parity checklist

Source of truth: `RobsGeorge/Spims` `design-reference/*` (`code.html` + `screen.png`). PNGs in that repo are Git LFS pointers and are not vendored here. Parity is taken from the HTML structure and Sacred Academic tokens already in `public/css/spims-theme.css`.

Atmosphere is local liturgical photography (`public/img/landing-hero.jpg` and featured stills) at low opacity under a cool-field wash — no CDN, no parchment, no gold-as-primary. SVG motif remains as a fallback asset.

## Landing (`/`)

| Reference | Laravel |
|---|---|
| Fixed top nav: brand + Programs / Admissions / Academics / Spiritual Life + Browse / Apply | Guest `spims-public-nav` + in-page anchors; Browse courses + Sign in / Create account |
| Hero card, chip, display title, two pill CTAs | `.spims-landing-hero` + `.spims-landing-chip` + `.spims-landing-display` |
| Library photo ~20% opacity | Local SVG atmosphere at ~22% (dark: 12%) |
| 3 stat cards | Real student / course counts + “Global” faculty |
| Featured programs bento (2 photo + 1 burgundy tile) | First 3 active courses; third card is burgundy feature tile |
| “Your Academic Journey” 3 steps | Existing `home.how_*` band (`.spims-landing-band`) |
| Burgundy footer | `.spims-landing-footer` |

Keep `ui.home_heading` (SPIMS) and `ui.home_cta_primary` for existing tests.

## Auth (`/login`, register, OTP, forgot, reset, set-password)

| Reference | Laravel |
|---|---|
| Split panel: left burgundy brand + gold rule | `.spims-auth-split` + `.spims-auth-brand` |
| Right form | `.auth-card` unchanged (controllers / field names unchanged) |
| OTP 6 boxes | Single `name="code"` maxlength 6, styled `.auth-otp-input` |
| Language in corner | Existing locale select in guest topbar |
| Suspended / wrong credentials | Existing login alerts |

## Catalog (`/catalog`)

| Reference | Laravel |
|---|---|
| Featured program banner | `.catalog-featured` when no filters |
| Cards with image headers | `.catalog-card-media` CSS/SVG headers |
| Loading skeletons | `#catalog-skeletons` on filter submit and `?skeleton=1` |
| Empty state | Existing `catalog.empty` / `catalog.empty_hint` |
| Filters | Existing q / type / price / sort / interest |

## Light / dark / RTL

- Light: cool field `#f8f9ff`, burgundy spine, gold accent only
- Dark: `theme-dark` tokens (`.theme-dark` cookie)
- RTL: `dir="rtl"` + IBM Plex Sans Arabic (locale cookie `ar`)

## WCAG AA

Measured in `tests/Feature/Portal/DesignReferenceParityTest.php` against `ThemeTokens` defaults: body/title on field, primary button text, field on deep burgundy, gold-on-burgundy as large-text (≥3:1).
