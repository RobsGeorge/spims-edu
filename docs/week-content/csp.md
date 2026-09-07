# Content-Security-Policy (week content)

`App\Http\Middleware\SecurityHeaders` sends a single `Content-Security-Policy` header on every response. It is a real allowlist, not `frame-src` alone.

## Why this policy

Week content iframes Vimeo, YouTube (privacy-enhanced), and reading hosts from `config('spims.content.reading_embed_hosts')` (Drive, Dropbox, OneDrive by default). The layout still loads Bootstrap and Bootstrap Icons from jsDelivr, Google Fonts, Alpine from jsDelivr on the exam runner, and inline theme CSS plus `onchange` handlers. CSP has to allow those without opening `object-src` or `frame-ancestors`.

Config defaults, the file controller, and Blade embed markup are unchanged. Superadmin still controls reading hosts with `SPIMS_READING_EMBED_HOSTS`.

## Directives

| Directive | Value |
|---|---|
| `default-src` | `'self'` |
| `script-src` | `'self' 'unsafe-inline' https://cdn.jsdelivr.net` |
| `style-src` | `'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com` |
| `font-src` | `'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:` |
| `img-src` | `'self' data: https:` |
| `connect-src` | `'self'` |
| `frame-src` | `'self'` + `https://player.vimeo.com` + `https://www.youtube-nocookie.com` + `https://{each reading_embed_hosts}` |
| `object-src` | `'none'` |
| `base-uri` | `'self'` |
| `form-action` | `'self'` |
| `frame-ancestors` | `'self'` |

`'unsafe-inline'` on scripts covers Alpine's inline runner bootstrap and locale/theme `onchange` submits. `'unsafe-inline'` on styles covers `$themeCssBlock` in `layouts/app.blade.php`. jsDelivr is the only script CDN in that layout (Bootstrap bundle) and in the exam runner (Alpine 3).

`frame-src` hosts are lowercased and prefixed with `https://`. Empty entries are skipped.

## Other headers (unchanged)

- `X-Frame-Options: SAMEORIGIN` (aligned with `frame-ancestors 'self'`)
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `X-XSS-Protection: 0`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` when the request is HTTPS or `spims.force_https` is true

## Layout CDNs checked

`resources/views/layouts/app.blade.php`:

- `https://fonts.googleapis.com` / `https://fonts.gstatic.com` (stylesheet + font files)
- `https://cdn.jsdelivr.net` (Bootstrap CSS/JS, Bootstrap Icons CSS/fonts)

`resources/views/assessments/runner.blade.php` also loads Alpine from jsDelivr; that origin is already in `script-src`.
