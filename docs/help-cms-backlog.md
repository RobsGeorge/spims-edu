# Help CMS backlog (CMS-4)

Explicit **deferred** enhancements for the portal Help / knowledge-base CMS. Do not implement these in the current phase; track here for later prioritization.

| Item | Notes |
|------|--------|
| Full-text search | Postgres `tsvector` / ranking, or Laravel Scout — replace LIKE v1 when catalog size warrants it |
| Article version history / restore | Snapshot on save; compare and restore prior Markdown |
| WYSIWYG editor | Still no npm theme build — CDN-only editor only if product-approved |
| Feedback (“Was this helpful?”) | Optional ratings/analytics on reader show page |
| Scheduled publish | Set `published_at` in the future; scheduler flips draft → published |
| Marketing / website CMS | Out of scope — this product is portal Help KB only, not a general page builder |

Current shipped surface: DB catalog, `/help` reader, `/admin/help` editor, `help:import-lang` / `help:export`, locale completeness warnings, LIKE search with current-locale preference. See [`docs/user-guides/README.md`](user-guides/README.md).
