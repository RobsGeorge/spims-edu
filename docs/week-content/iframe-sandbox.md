# Content iframe sandbox

Student, catalog, and staff week-content embeds run in a sandboxed iframe so a third-party frame cannot navigate the parent tab (`allow-top-navigation` is omitted).

## Surfaces

| Surface | Template |
|---|---|
| Student item player | `resources/views/learn/partials/item-media.blade.php` (also used by catalog `/offerings/{id}/preview` and staff reading/file preview) |
| Staff builder video preview | `resources/views/offerings/partials/week-content-builder.blade.php` |
| Legacy course player | `resources/views/courses/player.blade.php` |

Certificate template `srcdoc` preview is not week content and is unchanged.

## Attribute

Every content iframe uses:

```
sandbox="allow-scripts allow-same-origin allow-presentation allow-popups"
```

Existing `allow` / `allowfullscreen` stay as-is. Video hosts remain `youtube-nocookie.com` and `player.vimeo.com`. This is HTML-only; CSP `frame-src` and `X-Frame-Options` are unchanged.
