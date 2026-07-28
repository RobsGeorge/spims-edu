# Release runbook — SPIMS

## Pre-release

- [ ] CI green on the commit (all PHPUnit suites + PostgreSQL migrate:fresh --seed)
- [ ] Changelog / release notes drafted
- [ ] `FORCE_HTTPS=true` and `APP_DEBUG=false` on production `.env`
- [ ] `SUPERADMIN_PASSWORD` rotated from default if still using bootstrap secret
- [ ] Mail / payment / Zoom secrets present (or mock flags intentional)
- [ ] Staging smoke passed (below)

## Staging promote

1. Merge to `staging` (or push the release commit).
2. Wait for GitHub Actions staging deploy.
3. Smoke:
   - `GET https://staging.spims-edu.com/health` → `status: ok`
   - Login as Super Admin
   - Open sample catalog / offering preview (`DEMO101`)
   - Create a dummy enrollment path if finance mocks enabled
4. Confirm workers: `systemctl status spims-queue` (staging unit if separate)
5. Confirm scheduler: `php8.2 artisan schedule:list`

## Production release

1. Push / merge to `main`.
2. Watch Deploy workflow: test job → SSH deploy.
3. Post-deploy:
   - `/health` returns 200
   - Security headers present (`X-Content-Type-Options: nosniff`, HSTS if HTTPS)
   - Queue restarted (`spims-queue`)
   - No spike of 5xx in Nginx/Laravel logs for 15 minutes

## Rollback

1. `cd /var/www/spims && git fetch && git reset --hard <previous-sha>`
2. `composer install --no-dev --optimize-autoloader`
3. `php8.2 artisan migrate --force` (only if new migrations are backward-compatible; otherwise restore DB snapshot first — see `docs/backups-and-restore.md`)
4. `php8.2 artisan optimize:clear && php8.2 artisan config:cache && php8.2 artisan route:cache`
5. `sudo systemctl reload php8.2-fpm && sudo systemctl restart spims-queue`
6. `php8.2 artisan up`

## Seed after fresh install

```bash
php8.2 artisan migrate --seed --force
```

Yields: languages, default grading scheme, active theme, settings, Super Admin, sample diploma program `DEMO-DIP` + course `DEMO101` + open Fall offering.

Disable sample curriculum with `SEED_SAMPLE_DATA=false`.
