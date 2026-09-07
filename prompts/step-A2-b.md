# Step A2-b — Auth, Errors, Catalog, Notifications, Settings

Step ID: `A2-b`  
Wave: W2b (rebase onto merged A2-a first)  
Scope: resources/views/auth, resources/views/errors, resources/views/catalog,
       resources/views/announcements, resources/views/notifications, resources/views/settings  
**Unauthenticated and brand-critical. Review with extra care.**

## Scope (17 files)
`auth/` (login, register, forgot-password, reset-password, set-password, verify + partials),
`errors/` (403, 404, 500), `catalog/` (+ partials), `announcements/`, `notifications/`, `settings/`

## Why extra care
The auth and error pages are the first thing a visitor sees and the ones most associated with
the school's brand. They must look polished at 360px. The error pages must be friendly and helpful.

## What to do (per file)
Follow the migration table in `docs/design-system.md`. For each file:
1. Apply all replacements from the migration table
2. Mobile-first grid pass
3. Icons from vocabulary
4. Add missing lang keys to ALL THREE locales
5. Verify 360/768/1440 × light/dark/RTL

## Additional for auth/
- Forms must be single-column on phone (no multi-column auth form at 360px)
- The brand panel (`auth/partials/brand-panel.blade.php`) should use the liturgical theme palette
  to feel distinctive — not a generic gradient

## Additional for errors/
- 403: explain what permission is needed; link back to the dashboard
- 404: link back; suggest search or catalog
- 500: apologize briefly; link to support if a contact exists
- All copy: active voice, sentence case, localized in three locales

## Done
`echo "A2-b" > .claude/current-step` then `./scripts/validate-step.sh A2-b`
