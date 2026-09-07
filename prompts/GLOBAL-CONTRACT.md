=== SPIMS GLOBAL UI CONTRACT (v5) — applies to every line of UI you write ===

BEFORE WRITING MARKUP:
- Read public/css/spims-theme.css and resources/views/components/*.
- Read docs/design-system.md (authoritative over this block once it exists — Phase A creates it).

DESIGN SYSTEM — USE IT, DO NOT REINVENT IT:
- Consume tokens only. Never hard-code a colour, font, radius, shadow, spacing value, or font-size.
  Use --color-*, --font-*, --radius-*, --shadow-*, --space-*, --text-*.
- Reuse the Blade component library before writing raw markup. If a component is missing, ADD it
  to the library with the same API shape as its siblings — never inline a one-off.
- Forbidden patterns (the guard hook will block these at write time):
    card border-0 shadow-sm  → use <x-card variant=...>
    bare <h1>                → use <x-page-header>
    text-muted               → use --color-text-muted token class
    bg-light / bg-secondary  → use --color-* token classes
    inline style="" for anything themeable
    raw enum ->value in output → map through a lang key or use <x-badge>
    raw *_minor integer        → use <x-money :minor=... :currency=...>
- Surface hierarchy: VARY the treatment. A primary panel, a quiet secondary surface, and a bare
  listing are three different things. Do not give every block the same radius + shadow.
- Icons: Bootstrap Icons, used deliberately for scanability. The portal already has 92 icon
  instances — your job is to make them CONSISTENT with the vocabulary in docs/design-system.md,
  not to add more arbitrarily. Icon + text for anything destructive or ambiguous; never icon alone.

RESPONSIVE — MOBILE-FIRST, THREE BREAKPOINTS MUST VERIFIED:
- Mobile-first: unprefixed classes are the phone layout. A grid with only col-md-* is a bug.
- 360px, 768px, 1440px must ALL render with: no horizontal scroll; no clipped or overlapping text;
  tap targets ≥44×44px with ≥8px between adjacent targets; forms single-column on phone.
- DATA TABLES: every table in .spims-table-wrap. Any table with >4 columns must ALSO have a mobile
  card fallback (<x-data-table> renders stacked labelled cards below md).
- Multi-input inline form rows become a modal or stacked fieldset on phone.
- Money and figures stay legible at 360px; never wrap mid-number; tabular-nums for numeric columns.

CONTENT AND CORRECTNESS:
- NEVER print a raw enum ->value. Map through a lang key.
- NEVER print a *_minor integer. Use Money::fromMinor($minor, $currency)->format() or <x-money>.
- Localize every string in lang/{ar,fr,en}/*.php — ALL THREE LOCALES, every key, every time.
  Arabic is RTL-first: logical CSS properties only (margin-inline, padding-inline, inset-inline,
  text-align: start/end). Never left/right.
- Copy: active voice; a button says what happens ("Save changes", not "Submit"); empty states
  invite an action; errors say what happened and how to fix it; sentence case, no ALL-CAPS labels.

QUALITY FLOOR — part of "done", every step:
  light + dark + RTL correct · 360/768/1440 correct · visible keyboard focus ·
  prefers-reduced-motion respected · no new hard-coded English · no raw enum · no raw minor units.

AUTHORIZATION:
- Protected pages: permission: middleware + AuthorizeService. No role-name string checks.
- Mutations: existing service wrapped by AuditLogWriter::withAudit().
- New offering-owned permission key: register in config/permission_scopes.php.
- New offering-owned model: add to ResourceScopeResolver::offeringIdsFor().
- Guest pages are the only public ones.

ROUTES:
- Append routes at the anchor comment for your track in routes/web.php. Never reorder the file.

LANG FILES:
- Add new keys to your step's own lang file where possible.
- Only APPEND to shared lang files — never rewrite them.
- Every key goes into all THREE locales (ar, en, fr). LocaleParityTest enforces this.

DEFINITION OF DONE — you are NOT finished until:
  ./scripts/validate-step.sh <your-step-id>
exits 0. Write your step ID to .claude/current-step so the Stop hook can verify it.
