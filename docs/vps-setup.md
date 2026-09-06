# VPS Setup — SPIMS

Dedicated Ubuntu 22.04/24.04 VPS (Hetzner or similar) for production + staging.
Owner checklist (secrets, DNS, keys): [owner-actions.md](owner-actions.md).
How mobile `/api/v1` is served: [mobile-api-runtime.md](mobile-api-runtime.md).

Preferred path: run `deploy/first-boot.sh` as root (packages, user, Postgres, Nginx, systemd),
then follow the clone / `.env` / certbot steps in owner-actions. The numbered sections below
are the same work, spelled out for a manual install.

## 1. System packages

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y nginx postgresql redis-server \
    php8.2-fpm php8.2-cli php8.2-pgsql php8.2-sqlite3 php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath php8.2-redis \
    certbot python3-certbot-nginx git unzip curl

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

## 2. Deploy user & sudoers

```bash
sudo adduser deploy
sudo usermod -aG www-data deploy
sudo visudo -f /etc/sudoers.d/spims-deploy
```

Add:
```
deploy ALL=(ALL) NOPASSWD: /usr/bin/chown, /usr/sbin/chown, /bin/chown, /usr/bin/chmod, /bin/chmod, /usr/bin/systemctl, /bin/systemctl
```

## 3. PostgreSQL

```bash
sudo -u postgres psql -c "CREATE USER spims WITH PASSWORD 'YOUR_STRONG_PASSWORD';"
sudo -u postgres psql -c "CREATE DATABASE spims OWNER spims;"
sudo -u postgres psql -c "CREATE DATABASE spims_staging OWNER spims;"
```

## 4. App directories

Use SSH clone if the repo is private (deploy key — see owner-actions §3).

```bash
sudo mkdir -p /var/www/spims /var/www/spims-staging /var/backups/spims
sudo chown -R deploy:www-data /var/www/spims /var/www/spims-staging
sudo chown deploy:deploy /var/backups/spims

sudo -u deploy git clone git@github.com:RobsGeorge/spims-edu.git /var/www/spims
cd /var/www/spims && sudo -u deploy cp .env.example .env
# Edit .env — production values in owner-actions §5

sudo -u deploy git clone git@github.com:RobsGeorge/spims-edu.git /var/www/spims-staging
cd /var/www/spims-staging && sudo -u deploy git checkout staging
cd /var/www/spims-staging && sudo -u deploy cp .env.example .env
# Set APP_ENV=staging, DB_DATABASE=spims_staging, APP_URL=https://staging.…
```

## 5. First deploy (manual)

```bash
cd /var/www/spims
sudo -u deploy composer install --no-dev --optimize-autoloader
sudo -u deploy php8.2 artisan key:generate
sudo -u deploy php8.2 artisan migrate --seed --force
sudo -u deploy php8.2 artisan storage:link
QUEUE_UNIT=spims-queue HEALTH_URL=https://spims-edu.com \
  LOOPBACK_HEALTH_HOST=spims-edu.com sudo -u deploy ./scripts/vps-release.sh
```

`scripts/vps-release.sh` caches config/routes/views, reloads php-fpm, restarts the queue,
installs the scheduler cron, and probes `/health`.

## 6. Nginx

Copy [deploy/nginx/spims.conf](../deploy/nginx/spims.conf) and substitute:

| Placeholder | Production | Staging |
|-------------|------------|---------|
| `__SERVER_NAME__` | `spims-edu.com www.spims-edu.com` | `staging.spims-edu.com` |
| `__ROOT__` | `/var/www/spims/public` | `/var/www/spims-staging/public` |

```bash
sudo ln -s /etc/nginx/sites-available/spims-edu.com /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d spims-edu.com -d www.spims-edu.com
sudo certbot --nginx -d staging.spims-edu.com
```

`/api/v1` is not a second vhost. Nginx `try_files` sends it to the same `index.php`.

## 7. Queue worker (systemd)

Units live in [deploy/systemd/](../deploy/systemd/):

```bash
sudo cp deploy/systemd/spims-queue.service /etc/systemd/system/
sudo cp deploy/systemd/spims-queue-staging.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now spims-queue spims-queue-staging
```

## 8. Scheduler cron

`scripts/vps-release.sh` runs `php8.2 artisan scheduler:ensure-cron --apply`. To do it by hand:

```
* * * * * cd /var/www/spims && php8.2 artisan schedule:run >> /dev/null 2>&1
```

## 9. GitHub Actions secrets

See [owner-actions.md](owner-actions.md) §2. Minimum: `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY`,
`SSH_PORT`.

## 10. DNS

| Record | Value |
|--------|-------|
| A `spims-edu.com` | VPS IP |
| A `www` | VPS IP |
| A `staging` | VPS IP |

No hidden subdomains in v1 — only production and staging hostnames above.

## 11. Production `.env` hardening

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://spims-edu.com
FORCE_HTTPS=true
SEED_SAMPLE_DATA=true
BACKUP_PATH=/var/backups/spims
BACKUP_RETENTION_DAYS=14
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SANCTUM_EXPIRATION=43200
```

Change `SUPERADMIN_PASSWORD` before first public launch.

## 12. Backups, monitoring, release

- Backups & restore drill: [backups-and-restore.md](backups-and-restore.md)
- Release checklist: [release-runbook.md](release-runbook.md)
- Exam concurrency on box: [exam-concurrency.md](exam-concurrency.md)

Uptime: probe `GET /health` every minute (UptimeRobot / Hetrix / Grafana). Alert on non-200.
Mobile smoke: `GET /api/v1/branding` must stay 200 without a token.

## 13. Queue restart after deploy

Production deploys restart `spims-queue`. Staging deploys restart `spims-queue-staging`.
Rollback is Actions → **Rollback** (target + SHA); see `.github/workflows/rollback.yml`.
