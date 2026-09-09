## Rendering stack

SPIMS UI is server-rendered **Blade** with **Bootstrap 5.3** and **Bootstrap Icons** loaded from CDN. There is **no npm build step**, no Vite/webpack pipeline for app CSS/JS beyond what Laravel already ships for optional assets.

Primary layout: `resources/views/layouts/app.blade.php` — sidebar, topbar, mobile drawer, bottom nav, flash messages, locale direction (`dir="rtl"` for Arabic).

Auth and marketing surfaces may use lighter shells (e.g. `x-auth-shell`) but signed-in portal pages extend `layouts.app`.

---

## Navigation

`App\Support\NavigationHub` supplies:

- `primaryNav()` — desktop sidebar / drawer
- `bottomNav()` — mobile max-five bar
- Hub tile lists: `learningLinks`, `academicLinks`, `adminLinks`, `financeLinks`, `superadminSections`

Views must not invent parallel nav trees. Hub gates:

| Hub | Gate |
|---|---|
| Academic | `programs.manage` |
| Admin | `users.manage` |
| Finance desk tiles | `offerings.pricing` |
| Teach | `TeachAccessService::canTeach` |
| Super Admin | `$user->isSuperAdmin()` |

Details: [navigation-map.md](navigation-map.md).

---

## Design system documents

| Doc | Role |
|---|---|
| `docs/design-system.md` | Authoritative tokens, surface tiers, components, banned patterns |
| `prompts/GLOBAL-CONTRACT.md` | Agent rules for UI steps (layouts ownership, lang keys, validate-step) |
| `public/css/spims-theme.css` | CSS variables for light (`:root`) and dark (`body.theme-dark`) |
| `public/css/spims-shell.css` | Shell chrome, sidebar, bottom nav, bento grid |

Visual direction: “Sacred Academic” — cool near-white field (`#f8f9ff`), liturgical burgundy (`#5d0326`), gold accent; Playfair Display + Inter (Latin); IBM Plex Sans Arabic for Arabic. Not warm parchment skeuomorphism.

Use **logical CSS** properties for RTL (`margin-inline`, `padding-inline`, `inset-inline`, `text-align: start/end`).

---

## Blade components

Reusable components under `resources/views/components/`:

| Component | Use |
|---|---|
| `x-card` | Surface tiers: `panel` / `quiet` / `bare` |
| `x-page-header` | Title + subtitle (+ optional actions slot) |
| `x-data-table` | Operator tables |
| `x-money` | Format integer minor units + currency |
| `x-badge` / `x-status-badge` | Status chips |
| `x-empty-state` | Empty lists with optional CTA |
| `x-field` | Form fields |
| `x-tabs` / `x-toolbar` | Section chrome |
| `x-modal` / `x-confirm-dialog` | Dialogs |
| `x-stat` / `x-progress` / `x-timeline` | Metrics and history |
| `x-file-drop` | Uploads |
| `x-avatar` / `x-icon` | People and icons |

### Card surface tiers

| Tier | Class | When |
|---|---|---|
| Panel | `.spims-card.spims-card-panel` | Primary elevated content |
| Quiet | `.spims-card.spims-card-quiet` | Secondary / sidebar sections |
| Bare | `.spims-card.spims-card-bare` | Inline grouping, no elevation |

Never hardcode colors — use theme tokens (`--color-primary`, `--color-surface`, etc.).

---

## Hub link tile

Partial: `resources/views/partials/hub-link-tile.blade.php`.

Hub indexes pass NavigationHub link arrays into a grid of tiles (icon + label + description). Dashboard also uses `app-tile` / `hub-tile` shortcut cards under the bento.

---

## Dashboard widgets

`resources/views/dashboard.blade.php` — asymmetric **bento-grid**:

1. **My courses** — enrollments + progress
2. **Next live** — feature panel for next Zoom
3. **Due soon** — assessments nearing close
4. **Wallet** — four balance chips
5. **Notifications** — recent + unread badge

Then role-aware hub shortcut tiles.

---

## Theme and branding

Administrative / Super Admin theme editor writes school tokens and logos into `Theme` / settings. Runtime CSS variables come from `spims-theme.css` plus injected school overrides. Users pick light / dark / system preference.

---

## Agent / contributor guardrails

- Do not edit `layouts/`, `partials/`, or `components/` unless the step explicitly owns them.
- Append routes at the track anchor in `routes/web.php` — never reorder the file.
- Add lang keys to the step’s own lang file when possible; append only to shared files; **all three locales**.
- Banned patterns are blocked by `guard-design.sh` — consult the migration table in `docs/design-system.md` if a write is denied.
- A step is not done until `./scripts/validate-step.sh <step-id>` exits 0.

---

## Accessibility and motion

- Focus rings via `--focus-ring`
- Motion tokens: `--motion-fast` / `--motion-base` / `--motion-slow`
- Prefer semantic headings and existing empty-state / status patterns over ad-hoc markup

Related: [architecture.md](architecture.md), [portal-navigation.md](portal-navigation.md) (client).
