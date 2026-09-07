# Unknown reading URLs

`SPIMS_ALLOW_UNKNOWN_READING_URLS` defaults to `false`. Instructors may paste HTTPS links only for hosts on `SPIMS_READING_EMBED_HOSTS` (`config('spims.content.reading_embed_hosts')`).

Drive, Dropbox, and OneDrive stay embeddable through that list. Other HTTPS hosts are rejected with `offerings.reading_url_host_blocked`. Set the env flag to `true` only when a deployment must accept arbitrary HTTPS readings as link-only (no iframe).
