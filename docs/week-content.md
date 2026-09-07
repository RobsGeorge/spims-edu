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
- `SPIMS_ALLOW_UNKNOWN_READING_URLS=false` — unknown HTTPS hosts are rejected; Drive, Dropbox, and OneDrive remain embeddable via `reading_embed_hosts`
- `SPIMS_UPLOAD_MAX_MB=20`
- `SPIMS_UPLOAD_MIMES=pdf,jpg,jpeg,png,webp,gif`
- `SPIMS_STUDENT_FILE_DOWNLOAD=true`

## 2. Dual content builder + draft/publish (shipped)

Teach Content tab and admin offering show both include `offerings/partials/week-content-builder`.

- Add / edit / delete / publish / unpublish on the same web routes (`offerings.content`, own offering).
- New items created through the form or `OfferingService::addContentItem` start unpublished unless the caller passes `published => true`.
- `DemoDataSeeder` publishes Week 1 classroom items so the student walkthrough and catalog stay usable after `migrate:fresh --seed`.
- Students, learn routes, student API item lists, and public Week 1 preview omit drafts.
- Staff still see drafts in both editors.

## 3. Video embeds (shipped)

Instructors paste a Vimeo URL/ID or a YouTube watch / youtu.be / Shorts / embed link. `OfferingService` stores `video_provider` + canonical id in `vimeo_id`.

Student item page and the staff builder preview use `youtube-nocookie.com` or `player.vimeo.com`. Playlists and Live are rejected. Draft videos stay hidden from students.

## 4. Readings and files (shipped)

`READING` and `FILE` accept an upload (PDF / JPG / PNG / WebP / GIF, default 20 MB) or an HTTPS link.

- Drive view/open links become `/preview` embeds.
- Unknown HTTPS hosts are link-only (no iframe).
- Uploaded files stream from `GET /learn/items/{item}/file` after publish + enrollment + week unlock. Download uses `?download=1`.
- Students never receive a public `/storage/` path.

## 5. Reorder and move (shipped)

Both editors have Move up / Move down (POST, no JS required) and Move to another week on the same offering. `OfferingService` rewrites `order` 1..n and audits the change. Cross-offering moves are rejected.

## 6. View as student (shipped)

Teach and admin have **View as student**. It opens the real learn player with student gating (cohort dates, self-paced week 1 only), drafts hidden, and writes blocked. Banner + Exit. No impersonation. Audited as `learning.student_preview`.

## 7. Public catalog Week 1 embeds (shipped)

`/offerings/{id}/preview` embeds published Week 1 video/PDF/image. Later weeks stay titles only. Stored files use `/offerings/{id}/preview/items/{item}/file` (Week 1 published only). Storage paths are not exposed in the public JSON.

## 8. Superadmin settings and CSP (shipped)

Env-only (`config/spims.php`): video providers, reading hosts, unknown-URL toggle, upload max/MIMEs, student download. `SecurityHeaders` sends `Content-Security-Policy: frame-src` for Vimeo, YouTube-nocookie, Drive, and Dropbox, and keeps `X-Frame-Options: SAMEORIGIN`.
