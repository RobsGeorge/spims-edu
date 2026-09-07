# Week content builder — edit-row parity

The Teach / admin week-item **edit** row in `resources/views/offerings/partials/week-content-builder.blade.php` matches the **add** row field set.

## Fields (add and edit)

| Field | Add | Edit |
|---|---|---|
| `type` | select | select (current type selected) |
| `title` | empty | stored title |
| `video_url` | empty, placeholder `__('offerings.video_url_ph')` | empty, same placeholder (paste a new Vimeo / YouTube link) |
| `vimeo_id` | empty | stored canonical id |
| `file_url` | empty | stored URL |
| `file` | upload | upload |
| `body` | empty | stored body |

Both rows use `form-label small` labels (`offerings.video_url`, `offerings.vimeo_id`, and the same type / title / file / body labels as add).

## Update behaviour

`ContentItemController::validated` already accepts `video_url`. `OfferingService::updateContentItem` prefers a non-empty `video_url` over `vimeo_id`, then stores `video_provider` + canonical id in `vimeo_id` (YouTube watch / youtu.be / Shorts / embed, or Vimeo URL/id).

Leaving `video_url` blank on save keeps the stored `vimeo_id`.
