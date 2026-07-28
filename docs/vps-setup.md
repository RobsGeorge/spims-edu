# VPS Setup — spims-edu.com

Dedicated Hetzner (or similar) VPS for SPIMS production + staging.

## 1. System packages

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx postgresql-16 redis-server \
  php8.2-fpm php8.2-cli php8.2-pgsql php8.2-sqlite3 php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath php8.2-redis \
  certbot python3-certbot-nginx git unzip

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
deploy ALL=(ALL) NOPASSWD: /usr/bin/chown, /usr/bin/chmod, /bin/chown, /bin/chmod, /usr/bin/systemctl, /bin/systemctl
```

## 3. PostgreSQL

```bash
sudo -u postgres psql -c "CREATE USER spims WITH PASSWORD 'YOUR_STRONG_PASSWORD';"
sudo -u postgres psql -c "CREATE DATABASE spims OWNER spims;"
sudo -u postgres psql -c "CREATE DATABASE spims_staging OWNER spims;"
```

## 4. App directories

```bash
sudo mkdir -p /var/www/spims /var/www/spims-staging
sudo chown -R deploy:www-data /var/www/spims /var/www/spims-staging

sudo -u deploy git clone https://github.com/RobsGeorge/spims-edu.git /var/www/spims
cd /var/www/spims && cp .env.example .env
# Edit .env — see README for production values

sudo -u deploy git clone https://github.com/RobsGeorge/spims-edu.git /var/www/spims-staging
cd /var/www/spims-staging && sudo -u deploy git checkout staging
cd /var/www/spims-staging && cp .env.example .env
# Set APP_ENV=staging, DB_DATABASE=spims_staging
```

## 5. First deploy (manual)

```bash
cd /var/www/spims
composer install --no-dev --optimize-autoloader
php8.2 artisan key:generate
php8.2 artisan migrate --seed --force
php8.2 artisan config:cache && php8.2 artisan route:cache && php8.2 artisan view:cache
php8.2 artisan storage:link
sudo chown -R deploy:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} +
```

## 6. Nginx

`/etc/nginx/sites-available/spims-edu.com`:
```nginx
server {
    listen 80;
    server_name spims-edu.com www.spims-edu.com;
    root /var/www/spims/public;
    index index.php;
    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Staging: duplicate with `staging.spims-edu.com` → `/var/www/spims-staging/public`.

```bash
sudo ln -s /etc/nginx/sites-available/spims-edu.com /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d spims-edu.com -d www.spims-edu.com
sudo certbot --nginx -d staging.spims-edu.com
```

## 7. Queue worker (systemd)

`/etc/systemd/system/spims-queue.service`:
```ini
[Unit]
Description=SPIMS Queue Worker
After=network.target
[Service]
User=deploy
WorkingDirectory=/var/www/spims
ExecStart=/usr/bin/php8.2 artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
Restart=always
[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable spims-queue && sudo systemctl start spims-queue
```

## 8. Scheduler cron

```bash
sudo -u deploy crontab -e
```
```
* * * * * cd /var/www/spims && php8.2 artisan schedule:run >> /dev/null 2>&1
```

## 9. GitHub Actions secrets

In repo Settings → Secrets:
- `SSH_HOST` — VPS IP
- `SSH_USER` — `deploy`
- `SSH_PRIVATE_KEY` — deploy user private key
- `SSH_PORT` — `22`

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
```

Change `SUPERADMIN_PASSWORD` before first public launch.

## 12. Backups, monitoring, release

- Backups & restore drill: [backups-and-restore.md](backups-and-restore.md)
- Release checklist: [release-runbook.md](release-runbook.md)
- Exam concurrency on box: [exam-concurrency.md](exam-concurrency.md)

Uptime: probe `GET /health` every minute (UptimeRobot / Hetrix / Grafana). Alert on non-200.

Nginx security extras (inside TLS `server` block):

```nginx
add_header X-Content-Type-Options nosniff always;
add_header X-Frame-Options SAMEORIGIN always;
add_header Referrer-Policy strict-origin-when-cross-origin always;
```

(App middleware also sets these; Nginx duplicates are fine.)

## 13. Queue restart after deploy

Ensure `spims-queue.service` exists (section 7). Deploy workflow restarts it after each production push.
