# Week content (instructor builder + student player)

Web UI is the primary surface. Teach and admin keep separate pages and share Blade partials and `OfferingService` mutations.

## 1. Schema and parsers (shipped)

Additive columns on `content_items`:

| Column | Meaning |
|---|---|
| `published` | Students, catalog, and View as student see the item only when true. Existing rows stay published. New items created through the instructor form start as drafts. |
| `published_at` | Set when an item is first published. |
| `video_provider` | `VIMEO` or `YOUTUBE`. Existing rows with a `vimeo_id` are backfilled to `VIMEO`. The `vimeo_id` column still stores the canonical video id. |

Helpers:

- `App\Support\Content\VideoUrlParser` — Vimeo bare id / URL; YouTube `watch`, `youtu.be`, Shorts, `embed`. Rejects playlists and Live. YouTube iframe host is `youtube-nocookie.com`.
- `App\Support\Content\ExternalReadingUrl` — HTTPS only. Drive view/open → `/preview` (embed). Dropbox share → `raw=1`. Unknown HTTPS → link-only. `http`, `javascript:`, `data:`, `file:` rejected.

Superadmin env (defaults; no settings screen):

- `SPIMS_VIDEO_PROVIDERS=VIMEO,YOUTUBE`
- `SPIMS_READING_EMBED_HOSTS=...`
- `SPIMS_ALLOW_UNKNOWN_READING_URLS=true`
- `SPIMS_UPLOAD_MAX_MB=20`
- `SPIMS_UPLOAD_MIMES=pdf,jpg,jpeg,png,webp,gif`
- `SPIMS_STUDENT_FILE_DOWNLOAD=true`

## Later slices

2. Dual content builder + draft/publish
3. YouTube / Vimeo URL embeds in the player
4. Readings, uploads, gated file viewer
5. Reorder + move to another week
6. View as student
7. Public catalog Week 1 embeds
8. CSP headers
