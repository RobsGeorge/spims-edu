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

## 2. Dual content builder + draft/publish (shipped)

Teach Content tab and admin offering show both include `offerings/partials/week-content-builder`.

- Add / edit / delete / publish / unpublish on the same web routes (`offerings.content`, own offering).
- New items created through the form or `OfferingService::addContentItem` start unpublished.
- Students, learn routes, student API item lists, and public Week 1 preview omit drafts.
- Staff still see drafts in both editors.

## 3. Video embeds (shipped)

Instructors paste a Vimeo URL/ID or a YouTube watch / youtu.be / Shorts / embed link. `OfferingService` stores `video_provider` + canonical id in `vimeo_id`.

Student item page and the staff builder preview use `youtube-nocookie.com` or `player.vimeo.com`. Playlists and Live are rejected. Draft videos stay hidden from students.

## Later slices

4. Readings, uploads, gated file viewer
5. Reorder + move to another week
6. View as student
7. Public catalog Week 1 embeds
8. CSP headers
