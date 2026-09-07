# Week content file hardening

Stored Week 1 / learn files are never served from a public `/storage/` path. Uploads go through `OfferingService::storeItemFile`; streaming goes through `ContentItemFileController`.

## Magic-byte MIME check

Extension allow-list (`SPIMS_UPLOAD_MIMES`, default `pdf,jpg,jpeg,png,webp,gif`) still runs first. HTML, HTM, SVG, and JS stay blocked by extension.

After the allow-list, `storeItemFile` inspects file bytes and rejects a spoofed extension (for example HTML named `notes.pdf`):

| Extension | Required prefix |
|---|---|
| `pdf` | `%PDF` |
| `jpg` / `jpeg` | `\xFF\xD8\xFF` |
| `png` | `\x89PNG` |
| `gif` | `GIF87a` or `GIF89a` |
| `webp` | `RIFF` at offset 0 and `WEBP` at offset 8 |

Mismatch returns the same `file` validation error as a blocked type.

This does **not** change `SPIMS_ALLOW_UNKNOWN_READING_URLS` (unknown HTTPS readings stay link-only by default) and does not expand CSP `frame-src`.

## Content-Disposition

`ContentItemFileController` builds `Content-Disposition` with `Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition`.

- Inline vs attachment still follows `?download=1`.
- The download name comes from the item title plus stored extension, not the raw basename of the storage path.
- Path separators, `%`, NULs, and `..` are stripped. ASCII fallback is `[A-Za-z0-9._-]` so RFC 5987 `filename*` can carry the UTF-8 title.
- Responses keep `X-Content-Type-Options: nosniff` and `Cache-Control: private, no-store`.

## Public Week 1 preview rate limit

Guest `GET /offerings/{offering}/preview/items/{item}/file` (`offerings.preview.item.file`) uses named limiter `catalog-preview-file`: **30 requests per minute per IP**.

Enrolled `GET /learn/items/{item}/file` is unchanged (publish + enrollment + week unlock; no catalog throttle).
