# Backups & restore — SPIMS

## Automated daily backups

Laravel schedules `php artisan spims:backup-database` at **02:30** (app timezone).
The deploy user cron must run `schedule:run` every minute (see `docs/vps-setup.md`).

Alternatively use the shell script (same dump format):

```bash
sudo mkdir -p /var/backups/spims
sudo chown deploy:deploy /var/backups/spims
chmod +x /var/www/spims/scripts/backup-db.sh
# crontab (deploy):
30 2 * * * /var/www/spims/scripts/backup-db.sh >> /var/log/spims-backup.log 2>&1
```

Env:

| Variable | Default | Meaning |
|----------|---------|---------|
| `BACKUP_PATH` | `storage/app/backups` | Local dump directory |
| `BACKUP_RETENTION_DAYS` | `14` | Prune older `spims_*` files |

### Off-site copies

After a local dump, sync with rclone / restic / Hetzner Storage Box. Example:

```bash
rclone copy /var/backups/spims remote:spims-backups
```

Verify at least one restore from off-site every quarter.

## Tested restore procedure

1. Put the app in maintenance: `php8.2 artisan down`
2. Stop queue: `sudo systemctl stop spims-queue`
3. Restore: `./scripts/restore-db.sh /var/backups/spims/spims_YYYYMMDD_HHMMSS.sql.gz`
4. `php8.2 artisan migrate --force`
5. `php8.2 artisan optimize:clear && php8.2 artisan config:cache`
6. Start queue + `php8.2 artisan up`
7. Hit `https://spims-edu.com/health` and log in as Super Admin

Record the date of the last successful restore drill in the ops checklist.

## Log rotation

`/etc/logrotate.d/spims`:

```
/var/www/spims/storage/logs/*.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    copytruncate
}
```
