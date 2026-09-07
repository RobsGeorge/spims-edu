# Teach API week-content parity

`/api/v1/teach` content writes now match the web week-content builder. Authorization stays `AuthorizeService` + `offerings.content` inside `OfferingService` (no role-name checks). Every mutation is audited there and wrapped with `IdempotencyStore` like other teach writes.

## Create / update

`POST /api/v1/teach/weeks/{week}/items` and `PUT /api/v1/teach/items/{contentItem}` accept:

| Field | Rules |
|---|---|
| `video_url` | nullable string, max 2048. Parsed by `OfferingService::applyVideoInput` (YouTube watch / youtu.be / Shorts / embed, or Vimeo URL/id). |
| `vimeo_id` | nullable string, max 256 (same parser; stores the canonical id). |
| `file_url` | nullable HTTPS reading/file link, max 2048. Drive/Dropbox/OneDrive are canonicalized. |
| `published` | optional boolean. New items are drafts unless `true`. |
| `file` | optional upload. Max `config('spims.content.upload_max_mb') * 1024` KB. |

FILE and READING do not require an upload when `file_url` is present. FILE never requires a file field.

Response `data` includes `id`, `week_id`, `type`, `title`, `order`, `vimeo_id`, `video_provider`, `file_url`, `body`, `published`, `published_at`.

## Publish, unpublish, reorder, move

Under the existing teach prefix + `api.instructor` middleware:

| Method | Path | Service |
|---|---|---|
| POST | `/api/v1/teach/items/{contentItem}/publish` | `publishContentItem` |
| POST | `/api/v1/teach/items/{contentItem}/unpublish` | `unpublishContentItem` |
| POST | `/api/v1/teach/items/{contentItem}/move-up` | `moveContentItemByDelta(-1)` |
| POST | `/api/v1/teach/items/{contentItem}/move-down` | `moveContentItemByDelta(+1)` |
| POST | `/api/v1/teach/items/{contentItem}/move` | `moveContentItem` (body `{ week_id }`, same offering only) |

Cross-offering staff receive 403. Students and outsider tokens are denied by instructor middleware. Enrolled students listing `GET /api/v1/offerings/{offering}/weeks/{week}/items` see published items only.
