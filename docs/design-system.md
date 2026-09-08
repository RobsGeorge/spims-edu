# SPIMS Design System

> Authoritative reference for all UI work. Read this before writing any markup.
> Tokens only — never hardcode a colour, size, or spacing value.
> RTL-safe: logical CSS properties only (margin-inline, padding-inline, inset-inline, text-align: start/end).

---

## Surface Tiers

Three distinct surface treatments to create visual hierarchy. Always vary the treatment — never give every card the same shadow and radius.

| Tier | When to use | CSS classes | Blade example |
|------|-------------|-------------|---------------|
| `panel` | Primary content panels; highest elevation | `.spims-card.spims-card-panel` | `<x-card variant="panel">…</x-card>` |
| `quiet` | Secondary surfaces, sidebar sections | `.spims-card.spims-card-quiet` | `<x-card variant="quiet">…</x-card>` |
| `bare` | Inline groupings, no elevation needed | `.spims-card.spims-card-bare` | `<x-card variant="bare">…</x-card>` |

**Visual distinctions:**
- `panel`: highest shadow (`--shadow-lift`), largest radius (`--radius-lg`), `--color-surface` background, `--color-surface-border` border
- `quiet`: lower shadow (`--shadow-soft`), medium radius (`--radius-md`), `--color-surface-low` background
- `bare`: no shadow, no border, smallest radius (`--radius-sm`), transparent background

---

## Tokens

All tokens are defined in `public/css/spims-theme.css`. Every new token appears in both the light block (`:root`) and dark block (`body.theme-dark`).

### Colour tokens

| Token | Light value | Dark value |
|-------|-------------|------------|
| `--color-bg-1` | `#f8f9ff` | `#0d1322` |
| `--color-bg-2` | `#eff4ff` | `#151b2b` |
| `--color-bg-3` | `#e6eeff` | `#191f2f` |
| `--color-surface` | `#ffffff` | `#191f2f` |
| `--color-surface-low` | `#eff4ff` | `#151b2b` |
| `--color-surface-border` | `rgba(219,192,196,0.55)` | `rgba(85,66,69,0.85)` |
| `--color-hairline` | `rgba(219,192,196,0.65)` | `rgba(219,192,196,0.28)` |
| `--color-title` | `#5d0326` | `#ffb1c0` |
| `--color-title-accent` | `#380014` | `#e9c16d` |
| `--color-text` | `#0b1c30` | `#dde2f8` |
| `--color-text-muted` | `#554245` | `#dbc0c4` |
| `--color-link` | `#5d0326` | `#ffb1c0` |
| `--color-primary` | `#5d0326` | `#ffb1c0` |
| `--color-primary-hover` | `#380014` | `#ffd9df` |
| `--color-primary-text` | `#ffffff` | `#380014` |
| `--color-accent` | `#eac167` | `#e9c16d` |
| `--color-accent-text` | `#251a00` | `#251a00` |
| `--color-success` | `#10b981` | `#10b981` |
| `--color-warning` | `#f59e0b` | `#f59e0b` |
| `--color-danger` | `#ef4444` | `#ef4444` |

### Typography tokens

| Token | Value/Description |
|-------|------------------|
| `--font-display` | `'Playfair Display', Georgia, serif` |
| `--font-sans` | `'Inter', system-ui, -apple-system, sans-serif` |
| `--font-arabic` | `'IBM Plex Sans Arabic', 'Cairo', system-ui, sans-serif` |
| `--text-xs` | `clamp(0.75rem, 0.75rem + 0vw, 0.75rem)` |
| `--text-sm` | `clamp(0.875rem, 0.875rem + 0vw, 0.875rem)` |
| `--text-base` | `clamp(1rem, 0.956rem + 0.222vw, 1rem)` |
| `--text-lg` | `clamp(1.125rem, 1.039rem + 0.428vw, 1.25rem)` |
| `--text-xl` | `clamp(1.25rem, 1.122rem + 0.639vw, 1.5rem)` |
| `--text-2xl` | `clamp(1.5rem, 1.317rem + 0.917vw, 1.875rem)` |
| `--text-3xl` | `clamp(1.875rem, 1.623rem + 1.261vw, 2.25rem)` |
| `--text-4xl` | `clamp(2.25rem, 1.872rem + 1.889vw, 3rem)` |
| `--leading-xs` | `1.5` |
| `--leading-sm` | `1.5` |
| `--leading-base` | `1.5` |
| `--leading-lg` | `1.4` |
| `--leading-xl` | `1.3` |
| `--leading-2xl` | `1.25` |
| `--leading-3xl` | `1.2` |
| `--leading-4xl` | `1.1` |

### Shape & shadow tokens

| Token | Value/Description |
|-------|------------------|
| `--radius-sm` | `8px` |
| `--radius-md` | `16px` |
| `--radius-lg` | `24px` |
| `--radius-full` | `9999px` |
| `--shadow-soft` | Subtle elevation — card resting state |
| `--shadow-lift` | Panel elevation — primary cards |
| `--shadow-floating` | Highest elevation — dropdowns, popovers |

### Spacing tokens

| Token | Value |
|-------|-------|
| `--space-0` | `0` |
| `--space-1` | `0.25rem` |
| `--space-2` | `0.5rem` |
| `--space-3` | `0.75rem` |
| `--space-4` | `1rem` |
| `--space-6` | `1.5rem` |
| `--space-8` | `2rem` |
| `--space-10` | `2.5rem` |
| `--space-12` | `3rem` |
| `--space-16` | `4rem` |
| `--space-20` | `5rem` |
| `--space-24` | `6rem` |

### Interaction tokens

| Token | Value/Description |
|-------|------------------|
| `--focus-ring` | `0 0 0 3px var(--color-primary)` — keyboard focus indicator |
| `--motion-fast` | `100ms ease` — micro-interactions |
| `--motion-base` | `200ms ease` — standard transitions |
| `--motion-slow` | `350ms ease` — enter/exit animations |

---

## Component Library

### card

Three-tier surface system (`panel`, `quiet`, `bare`). Throws `InvalidArgumentException` on unknown variant.

**Required props:** `variant` (string: panel|quiet|bare)

**Optional props:** `tag` (string, default: `div`), passthrough attributes (class, id, etc.)

```blade
<x-card variant="panel">Primary content here</x-card>
<x-card variant="quiet">Secondary section</x-card>
<x-card variant="bare" tag="article">Bare listing</x-card>
```

### badge

Renders a backed enum as a localized, semantically coloured badge. Throws `InvalidArgumentException` if given a non-enum value.

**Required props:** `value` (backed enum instance)

**Optional props:** `variant` (string, default: `default`)

```blade
<x-badge :value="$invoice->status" />
```

### money

Formats an integer minor-unit amount using the `Money` class. Throws if `minor` is not an integer.

**Required props:** `minor` (int), `currency` (Currency enum)

**Optional props:** `class` (passthrough)

```blade
<x-money :minor="$invoice->total_minor" :currency="$invoice->currency" />
```

### icon

Renders a Bootstrap Icon from a controlled vocabulary. Throws `InvalidArgumentException` for unknown concept keys.

**Required props:** `name` (string — vocabulary key)

**Optional props:** `size` (sm|md|lg, default: `md`), `class` (passthrough)

```blade
<x-icon name="course" />
<x-icon name="student" size="lg" />
```

### page-header

Page title region. Existing usage must not change.

**Required props:** `title` (string)

**Optional props:** `subtitle` (string), `eyebrow` (string), `icon` (vocab key, `auto`, or `none`), `actions` slot

`icon="auto"` (default) resolves a vocabulary key from the current route via `HeadingIcon`. Pass `none` to hide the mark.

```blade
<x-page-header :title="__('courses.index_title')" :subtitle="__('courses.index_subtitle')">
    <x-slot:actions>
        <a href="..." class="btn btn-primary">...</a>
    </x-slot:actions>
</x-page-header>
```

### stat

KPI tile with label, value, optional icon and trend indicator.

**Required props:** `label` (string), `value` (string)

**Optional props:** `icon` (vocab key), `trend` ('+' | '-' | 'neutral')

```blade
<x-stat label="Enrolled students" value="142" icon="student" trend="+" />
```

### field

Form field wrapper with label, error, and hint. Wraps any input in the default slot.

**Required props:** `label` (string), `name` (string)

**Optional props:** `error` (string), `hint` (string), `required` (bool)

```blade
<x-field label="Email" name="email" :error="$errors->first('email')" required>
    <input type="email" name="email" class="form-control" />
</x-field>
```

### modal

Alpine.js modal with focus trap, Escape to close, ARIA attributes.

**Required props:** `id` (string), `title` (string)

**Optional props:** `size` (sm|md|lg, default: `md`)

**Slots:** default (body), `footer`

```blade
<x-modal id="confirm-delete" title="Delete item?" size="sm">
    <p>Are you sure?</p>
    <x-slot:footer>
        <button @click="open = false">Cancel</button>
    </x-slot:footer>
</x-modal>
```

### tabs

Deep-linkable tab list using Alpine.js. Reads `?tab=` from URL on load.

**Required props:** `id` (string), `items` (array of `{key, label, icon?}`)

```blade
<x-tabs id="offering-tabs" :items="[
    ['key' => 'overview', 'label' => 'Overview'],
    ['key' => 'students', 'label' => 'Students', 'icon' => 'student'],
]">
    ...panels...
</x-tabs>
```

### toolbar

Horizontal flex row with `start` and `end` named slots. Wraps to two lines on phone.

```blade
<x-toolbar>
    <x-slot:start><h2>...</h2></x-slot:start>
    <x-slot:end><x-icon name="add" /> Add</x-slot:end>
</x-toolbar>
```

### avatar

Circular avatar with image or initials fallback.

**Required props:** `name` (string — used as alt text and initials fallback)

**Optional props:** `src` (string), `size` (sm|md|lg, default: `md`)

```blade
<x-avatar name="George Boutros" src="{{ $user->avatar_url }}" size="sm" />
```

### progress

Accessible progress bar with ARIA attributes.

**Required props:** `value` (int 0–100)

**Optional props:** `label` (string), `color` (token name)

```blade
<x-progress :value="$course->completion_percent" label="Completion" />
```

### timeline

Vertical timeline list. RTL: stem on logical-start side.

```blade
<x-timeline>
    <x-slot:items>[...items array...]</x-slot:items>
</x-timeline>
```

### file-drop

Alpine.js drag-and-drop upload zone with click-to-browse fallback.

**Required props:** `name` (string)

**Optional props:** `accept` (string), `max-size` (int, bytes)

```blade
<x-file-drop name="document" accept=".pdf,.docx" :max-size="5242880" />
```

### data-table

Responsive data table — standard `<table>` on desktop, stacked labelled cards on mobile (below `md`).

**Required props:** `columns` (array of `{key, label, numeric?, sortable?}`), `rows` (Collection or array)

**Optional slots:** `actions` (receives `$row`)

```blade
<x-data-table
    :columns="[
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'score', 'label' => 'Score', 'numeric' => true],
    ]"
    :rows="$students"
/>
```

### empty-state

Centred empty-state placeholder with icon, title, and optional message/actions.

**Required props:** `title` (string)

**Optional props:** `message` (string), `icon` (bi-* class)

```blade
<x-empty-state :title="__('ui.no_results')" icon="bi-inbox" />
```

### loader

System-wide SPIMS loader. Mounted once in `layouts/app.blade.php`. First session visit shows it until paint; subsequent POST submits and navigations reuse the same overlay. `public/js/spims-ui.js` exposes `window.SpimsLoader.show()` / `.hide()`. Respects `prefers-reduced-motion`.

### course-cover

Renders a course hero/thumb. Uses the stored `cover_image_url`, or a deterministic Unsplash stand-in from `CourseCoverLibrary` when the course was created without an image.

```blade
<x-course-cover :course="$course" class="catalog-card-media" />
```

### section-heading

Widget / card title with optional vocabulary icon and hover motion.

```blade
<x-section-heading :title="__('learning.my_courses')" icon="course" />
```

### confirm-dialog

Bootstrap modal for destructive actions. Accepts optional `confirm` slot to override the default submit button.

**Required props:** `id` (string), `title` (string), `message` (string)

**Optional props:** `confirmLabel` (string), `cancelLabel` (string), `tone` (`danger`|`primary`, default: `danger`)

**Optional slots:** `confirm` (override confirm button), default slot (extra body content)

```blade
<x-confirm-dialog
    id="delete-user"
    title="Delete user?"
    message="This action cannot be undone."
    tone="danger"
>
    <x-slot:confirm>
        <form method="POST" action="...">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete</button>
        </form>
    </x-slot:confirm>
</x-confirm-dialog>
```

### status-badge

String-keyed semantic status badge with automatic tone mapping.

**Required props:** `status` (string)

**Optional props:** `label` (string — display text override)

**Tone mapping:** success → active/enrolled/released/paid/open/present; warning → pending/waitlist/draft/partial; danger → failed/rejected/suspended/locked/absent/dropped/withdrawn; info → processing/completed; neutral → everything else.

```blade
<x-status-badge status="active" />
<x-status-badge status="suspended" label="Suspended" />
```

---

## Icon Vocabulary

Use `<x-icon name="..." />` — never bare `<i class="bi-...">` unless unavailable in this map.

| Concept | Bootstrap Icon class |
|---------|---------------------|
| `course` | `bi-journal-text` |
| `student` | `bi-person` |
| `enrollment` | `bi-person-check` |
| `grade` | `bi-star-half` |
| `finance` | `bi-wallet2` |
| `assessment` | `bi-clipboard-check` |
| `attendance` | `bi-calendar-check` |
| `credential` | `bi-award` |
| `settings` | `bi-gear` |
| `delete` | `bi-trash` |
| `add` | `bi-plus-circle` |
| `edit` | `bi-pencil` |
| `view` | `bi-eye` |
| `download` | `bi-download` |
| `warning` | `bi-exclamation-triangle` |
| `success` | `bi-check-circle` |
| `empty-state` | `bi-inbox` |
| `home` | `bi-house` |
| `catalog` | `bi-grid` |
| `learning` | `bi-book-half` |
| `teach` | `bi-easel2` |
| `academic` | `bi-mortarboard` |
| `admin` | `bi-building-gear` |
| `superadmin` | `bi-shield-lock` |
| `live` | `bi-broadcast` |
| `discussion` | `bi-chat-dots` |
| `announcement` | `bi-megaphone` |
| `notification` | `bi-bell` |
| `event` | `bi-calendar-event` |
| `quiz` | `bi-ui-radios` |
| `project` | `bi-kanban` |
| `survey` | `bi-ui-checks` |
| `advising` | `bi-compass` |
| `application` | `bi-file-earmark-text` |
| `report` | `bi-graph-up` |
| `people` | `bi-people` |
| `theme` | `bi-palette` |
| `translation` | `bi-translate` |
| `program` | `bi-diagram-3` |
| `offering` | `bi-collection` |
| `search` | `bi-search` |
| `inbox` | `bi-inbox` |
| `history` | `bi-clock-history` |
| `check-in` | `bi-qr-code-scan` |
| `roster` | `bi-person-lines-fill` |
| `content` | `bi-folder2-open` |
| `completion` | `bi-flag` |
| `transcript` | `bi-file-text` |
| `login` | `bi-box-arrow-in-right` |
| `register` | `bi-person-plus` |
| `lock` | `bi-lock` |
| `page` | `bi-bookmark` |
| `wallet` | `bi-wallet2` |
| `calendar` | `bi-calendar3` |

---

## Responsive Rules

- **Mobile-first**: unprefixed Bootstrap classes are the phone layout. A grid with only `col-md-*` is a bug.
- **Three breakpoints** must be verified: **360 px**, **768 px**, **1440 px** — no horizontal scroll; no clipped text; tap targets ≥ 44×44 px.
- **Data tables**: every table in `.spims-table-wrap`. Any table with > 4 columns must also use `<x-data-table>` which renders stacked labelled cards below `md`.
- **Forms**: single-column on phone. Multi-input inline rows become a modal or stacked fieldset below `sm`.
- **Money and figures**: stay legible at 360 px; tabular-nums for numeric columns.

### CSS grid utilities

```css
.grid-auto-sm  /* auto-fill columns min 14rem, gap --space-4 */
.grid-auto-md  /* auto-fill columns min 20rem, gap --space-6 */
.grid-auto-lg  /* auto-fill columns min 28rem, gap --space-8 */
```

---

## Migration Table

When you encounter any of these legacy patterns, replace with the listed alternative.

| Legacy pattern | Replacement |
|----------------|-------------|
| `card border-0 shadow-sm` | `<x-card variant="panel">` |
| bare `<h1>` | `<x-page-header>` |
| `text-muted` class | `--color-text-muted` token (`.text-muted-theme`) |
| `bg-light` | `--color-bg-1` token class |
| `bg-secondary` | `--color-surface-low` token class |
| `{{ $amount_minor }}` | `<x-money :minor="$amount_minor" :currency="$currency" />` |
| `{{ $status->value }}` | `<x-badge :value="$status" />` |
| `style="color: ..."` | token class from docs/design-system.md |
| `col-md-*` without unprefixed | add `col-*` for phone layout first |
| Table with > 4 columns | `<x-data-table :columns="..." :rows="...">` |

---

## Design Review Checklist

Before marking any step done, verify:

- [ ] Surface tiers are visually distinct — panel vs quiet vs bare are not the same treatment
- [ ] 360 px, 768 px, and 1440 px viewports have no horizontal scroll and no clipped text
- [ ] Light mode and dark mode both render correctly (no hardcoded colours)
- [ ] RTL layout (Arabic) is correct — logical CSS properties, no `left`/`right`, no mirrored icons
- [ ] No raw enum `->value` output — all enums rendered through `<x-badge>` or a lang key
- [ ] No raw `*_minor` integer output — all money values rendered through `<x-money>`
- [ ] Keyboard focus is visible on all interactive elements (`:focus-visible` ring)
- [ ] `prefers-reduced-motion` is respected — animations have `@media` guard or use `--motion-*` tokens
