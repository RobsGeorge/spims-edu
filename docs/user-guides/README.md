# User guides

**End-user help lives in the SPIMS portal Help CMS (database)**, not in this folder.

Open **Help Center** at [`/help`](https://spims-edu.com/help) (or `/help` on your local/staging host). Articles are localized in Arabic, English, and French via the portal locale switcher and follow Sacred Academic light/dark chrome.

- Role-filtered lists: `/help/role/{ROLE}` (e.g. `STUDENT`, `INSTRUCTOR`)
- Single article: `/help/{slug}` (e.g. `/help/minor-units-explained`)
- Category browse: `/help/c/{category}`

**Editors:** Administrative Admin and Super Admin manage content at **`/admin/help`** (`help.manage`). Draft, publish, archive, locales (en/ar/fr), audiences, and media — no deploy required.

Demo personas for exploring role-specific guides: see [`docs/demo-accounts.md`](../demo-accounts.md).

## Source of truth

| Surface | What lives there |
|---------|------------------|
| Portal DB (Help CMS) | Article titles, summaries, Markdown bodies, categories, audiences, media |
| `lang/{en,ar,fr}/help.php` | UI chrome only (nav labels, admin form strings, empty states) |
| This `docs/user-guides/` folder | Engineer notes — not the learner-facing catalog |

## Ops commands

```bash
# Idempotent upsert by slug. Priority: --path JSON → legacy lang articles key → HelpSeeder
php artisan help:import-lang
php artisan help:import-lang --path=storage/app/help-export/backup.json
php artisan help:import-lang --seed   # force HelpSeeder even if lang articles exist

# Optional JSON backup for ops
php artisan help:export
php artisan help:export --path=/tmp/help-backup.json
```

Fresh environments also get the MVP catalog via `HelpSeeder` (called from `DatabaseSeeder`). After go-live, prefer editing in `/admin/help` and use `help:export` / `help:import-lang --path=…` for backup/restore.

Deferred product ideas (FTS, versions, WYSIWYG, etc.) are listed in [`docs/help-cms-backlog.md`](../help-cms-backlog.md).
