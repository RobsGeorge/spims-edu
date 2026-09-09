# Deploy SPIMS on a VPS — spims-edu.com

This is the first-time operator guide. Follow it on a fresh Ubuntu 22.04 or 24.04 VPS. When you finish, `https://spims-edu.com` serves the app, PostgreSQL on the same box holds the data, and every push to `main` runs CI then deploys over SSH.

The GitHub Actions workflows already live in the repo:

- `.github/workflows/ci.yml` — test gate (blocks deploy)
- `.github/workflows/deploy.yml` — push to `main` → production at `/var/www/spims`
- `.github/workflows/deploy-staging.yml` — push to `staging` → `/var/www/spims-staging`

You do **not** need to write a new pipeline. You provision the VPS, create the database, put secrets in GitHub, and do one manual first deploy so later pushes can take over.

Related ops docs:

- Backups and restore: [backups-and-restore.md](backups-and-restore.md)
- Release checklist / rollback: [release-runbook.md](release-runbook.md)
- Demo accounts after seed: [demo-accounts.md](demo-accounts.md)

---

## What you will have

| Piece | Location / value |
|-------|------------------|
| App code | `/var/www/spims` |
| Public web root | `/var/www/spims/public` |
| Production URL | `https://spims-edu.com` |
| Database | PostgreSQL 16 on `127.0.0.1:5432`, database `spims` |
| Cache / queue / sessions | Redis on `127.0.0.1:6379` |
| Web server | Nginx + PHP 8.2-FPM + Let's Encrypt |
| Deploy user | `deploy` (GitHub Actions SSHs in as this user) |
| Queue worker | `spims-queue.service` |
| Scheduler | `deploy` crontab: `php8.2 artisan schedule:run` every minute |

Stack matches the project: PHP 8.2, Laravel 10, PostgreSQL 16, Redis, Nginx. There is no npm build step.

---

## Before you start

Have these ready:

1. **VPS** — Ubuntu 22.04 or 24.04, root (or a sudo user). 2 GB RAM is the practical minimum; 4 GB is more comfortable for PHP-FPM + PostgreSQL + Redis.
2. **Domain** — `spims-edu.com` you can edit DNS for.
3. **GitHub repo access** — this repository, with permission to add Actions secrets (and a deploy key if the repo is private).
4. **Your laptop SSH key** — so you can log into the VPS as `root` or your sudo user.

Decide two strong passwords now and store them in a password manager (do not commit them):

```bash
# Run on your laptop. Use one as the Postgres password, one as SUPERADMIN_PASSWORD.
openssl rand -base64 32
openssl rand -base64 24
```

---

## 1. Point DNS at the VPS

In your domain registrar (or DNS host), create:

| Type | Name | Value |
|------|------|--------|
| A | `@` (`spims-edu.com`) | your VPS public IPv4 |
| A | `www` | same VPS IPv4 |
| A | `staging` | same VPS IPv4 (optional; only if you will run staging) |

Wait until the apex resolves before requesting TLS:

```bash
dig +short spims-edu.com
```

It should print the VPS IP. Let's Encrypt will fail if DNS is still pointing elsewhere.

No other public hostnames are required for v1.

---

## 2. SSH in and update the box

```bash
ssh root@YOUR_VPS_IP
# or: ssh your-sudo-user@YOUR_VPS_IP
```

```bash
sudo apt update && sudo apt upgrade -y
sudo timedatectl set-timezone Africa/Cairo
```

---

## 3. Install PHP 8.2, Nginx, PostgreSQL 16, Redis, Composer

Ubuntu's default PHP is often 8.3 (24.04) or 8.1 (22.04). SPIMS CI and the deploy scripts use **php8.2**. Add the Ondřej PHP PPA. PostgreSQL 16 is in Ubuntu 24.04; on 22.04 add the PGDG repo.

```bash
sudo apt install -y software-properties-common curl gnupg lsb-release ca-certificates unzip git ufw

# PHP 8.2
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y nginx redis-server \
  php8.2-fpm php8.2-cli php8.2-pgsql php8.2-sqlite3 php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath php8.2-redis \
  certbot python3-certbot-nginx
```

PostgreSQL 16:

```bash
# Ubuntu 24.04 — usually enough:
sudo apt install -y postgresql postgresql-contrib

# Ubuntu 22.04 — if `apt install postgresql-16` is not found:
sudo apt install -y postgresql-common
sudo /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh
sudo apt install -y postgresql-16 postgresql-contrib-16
```

Confirm versions:

```bash
php8.2 -v          # PHP 8.2.x
psql --version     # 16.x
redis-server --version
nginx -v
```

Composer (system-wide):

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer -V
```

Enable services:

```bash
sudo systemctl enable --now nginx php8.2-fpm postgresql redis-server
```

---

## 4. Firewall

Only SSH, HTTP, and HTTPS should be public. PostgreSQL and Redis stay on localhost.

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw --force enable
sudo ufw status
```

Do **not** open port 5432 or 6379 to the internet. The app connects to both on `127.0.0.1`.

---

## 5. Create the `deploy` user (GitHub Actions logs in as this user)

```bash
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG www-data deploy
sudo mkdir -p /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
sudo touch /home/deploy/.ssh/authorized_keys
sudo chmod 600 /home/deploy/.ssh/authorized_keys
sudo chown -R deploy:deploy /home/deploy/.ssh
```

`deploy` has **no password**. `ssh deploy@YOUR_VPS_IP` will always fail with `deploy@…'s password:` / `Permission denied` until a public key is in `authorized_keys` and you connect with the matching private key (`ssh -i …`). Do not run `passwd deploy` for GitHub Actions — key-only is what the pipeline uses. Stay logged in as `root` (or your sudo user) to install the key below.

Give `deploy` passwordless sudo **only** for the commands the deploy workflow runs (`chown`, `chmod`, `systemctl`):

```bash
sudo visudo -f /etc/sudoers.d/spims-deploy
```

Paste exactly:

```
deploy ALL=(ALL) NOPASSWD: /usr/bin/chown, /usr/bin/chmod, /bin/chown, /bin/chmod, /usr/bin/systemctl, /bin/systemctl
```

```bash
sudo chmod 440 /etc/sudoers.d/spims-deploy
```

### 5a. Laptop access to the VPS (optional but useful)

If you want to SSH as `deploy` from your laptop, append your laptop public key:

```bash
# On the VPS, as root:
echo 'YOUR_LAPTOP_SSH_PUBLIC_KEY' >> /home/deploy/.ssh/authorized_keys
```

### 5b. Dedicated key for GitHub Actions → VPS

Generate a **new** key pair used only by Actions. Do this on your laptop (keep the private key off the VPS disk if you can):

```bash
ssh-keygen -t ed25519 -C "spims-github-actions" -f ./spims-deploy-actions -N ""
```

That creates:

- `spims-deploy-actions.pub` — goes on the VPS
- `spims-deploy-actions` — private key; becomes GitHub secret `SSH_PRIVATE_KEY`

On the VPS:

```bash
# Paste the contents of spims-deploy-actions.pub
echo 'ssh-ed25519 AAAA... spims-github-actions' >> /home/deploy/.ssh/authorized_keys
```

Test from your laptop before adding GitHub secrets:

```bash
ssh -i ./spims-deploy-actions deploy@YOUR_VPS_IP 'whoami && hostname'
```

It must print `deploy` with no password prompt. If you see `deploy@…'s password:`, key auth did not run — see the troubleshooting entry below.

---

## 6. Create PostgreSQL users and databases

All of this stays on the VPS. Laravel will connect as user `spims` to database `spims` on `127.0.0.1`.

Replace `YOUR_STRONG_DB_PASSWORD` with the password you generated earlier.

```bash
sudo -u postgres psql -c "CREATE USER spims WITH PASSWORD 'YOUR_STRONG_DB_PASSWORD';"
sudo -u postgres psql -c "CREATE DATABASE spims OWNER spims;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE spims TO spims;"

# Optional staging database on the same instance:
sudo -u postgres psql -c "CREATE DATABASE spims_staging OWNER spims;"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE spims_staging TO spims;"
```

On PostgreSQL 15+, also grant schema rights (harmless on 16):

```bash
sudo -u postgres psql -d spims -c "GRANT ALL ON SCHEMA public TO spims;"
sudo -u postgres psql -d spims -c "ALTER SCHEMA public OWNER TO spims;"
```

Confirm the app user can log in over TCP (this is what Laravel uses — `127.0.0.1`, not the Unix socket):

```bash
PGPASSWORD='YOUR_STRONG_DB_PASSWORD' psql -h 127.0.0.1 -U spims -d spims -c '\conninfo'
```

You should see a connection to database `spims` as user `spims`. If it fails with a peer-auth error, you used a socket instead of `-h 127.0.0.1`. If it fails with password authentication, check `pg_hba.conf` has a `scram-sha-256` (or `md5`) line for `127.0.0.1/32` and reload PostgreSQL:

```bash
sudo grep -E '127\.0\.0\.1|::1' /etc/postgresql/*/main/pg_hba.conf
sudo systemctl reload postgresql
```

Keep PostgreSQL listening on localhost only (default on Ubuntu). Confirm:

```bash
sudo ss -lntp | grep 5432
# Should show 127.0.0.1:5432, not 0.0.0.0:5432
```

---

## 7. Redis

Ubuntu's Redis already binds to localhost. Confirm and start:

```bash
sudo systemctl enable --now redis-server
redis-cli ping    # PONG
```

Leave `REDIS_PASSWORD` empty (`null`) unless you set `requirepass` in `/etc/redis/redis.conf`. If you do set a password, put the same value in `.env` as `REDIS_PASSWORD`.

---

## 8. Clone the app into `/var/www/spims`

```bash
sudo mkdir -p /var/www/spims /var/www/spims-staging /var/backups/spims
sudo chown -R deploy:www-data /var/www/spims /var/www/spims-staging
sudo chown deploy:deploy /var/backups/spims
```

### Public repo (HTTPS)

```bash
sudo -u deploy git clone https://github.com/RobsGeorge/spims-edu.git /var/www/spims
```

### Private repo (deploy key)

GitHub Actions SSHs **into** the VPS. The VPS still needs its own read-only key to **pull from** GitHub. These are two different keys.

On the VPS:

```bash
sudo -u deploy ssh-keygen -t ed25519 -C "spims-vps-git-pull" -f /home/deploy/.ssh/id_ed25519 -N ""
sudo -u deploy cat /home/deploy/.ssh/id_ed25519.pub
```

In GitHub: **Settings → Deploy keys → Add deploy key**. Paste the public key. Read-only is enough. Do not reuse the Actions private key.

```bash
sudo -u deploy git clone git@github.com:RobsGeorge/spims-edu.git /var/www/spims
```

If GitHub's host key is unknown:

```bash
sudo -u deploy ssh-keyscan github.com >> /home/deploy/.ssh/known_hosts
```

---

## 9. Connect Laravel to PostgreSQL (production `.env`)

`.env` is gitignored. `git reset --hard` during deploy will **not** overwrite it. Create it once on the server.

```bash
cd /var/www/spims
sudo -u deploy cp .env.example .env
sudo -u deploy nano .env
```

Set production values. The database block is what wires the app to the Postgres user you created in step 6.

```dotenv
APP_NAME=SPIMS
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://spims-edu.com
APP_TIMEZONE=Africa/Cairo
FORCE_HTTPS=true
SEED_SAMPLE_DATA=true
SEED_DEMO_DATA=true
BACKUP_PATH=/var/backups/spims
BACKUP_RETENTION_DAYS=14

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=spims
DB_USERNAME=spims
DB_PASSWORD=YOUR_STRONG_DB_PASSWORD

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_LOG_CHANNEL=mail
MAIL_FROM_ADDRESS="noreply@spims-edu.com"
MAIL_FROM_NAME="${APP_NAME}"

SUPERADMIN_EMAIL=robeir.george@outlook.com
SUPERADMIN_PASSWORD=YOUR_ROTATED_SUPERADMIN_PASSWORD

FILESYSTEM_DISK=local
```

Notes:

- `DB_HOST` must be `127.0.0.1` (TCP + password). `localhost` can silently use a Unix socket and fail peer auth.
- Rotate `SUPERADMIN_PASSWORD` before the site is public. The seed default `Spims@Dev2026!` is for local/dev only.
- `SEED_DEMO_DATA=true` loads demo users and curriculum (see [demo-accounts.md](demo-accounts.md)). Set it `false` for a clean production school.
- Mail can stay `log` until you add SMTP. OTP then appears in the mail log, not in the user's inbox.
- Payment / Zoom / Paymob secrets: leave mocks only if you intend to. Production refuses `*-test` webhook secrets; replace them before go-live (see `.env.example`).

Generate the app key (writes `APP_KEY=`):

```bash
cd /var/www/spims
sudo -u deploy php8.2 artisan key:generate
```

Protect the file:

```bash
sudo chmod 640 /var/www/spims/.env
sudo chown deploy:www-data /var/www/spims/.env
```

---

## 10. First deploy (manual — do this once)

Later deploys are automated. The first one installs Composer packages, creates tables, and seeds the Super Admin.

```bash
cd /var/www/spims
sudo -u deploy composer install --no-dev --optimize-autoloader --no-interaction
sudo -u deploy php8.2 artisan migrate --seed --force
sudo -u deploy php8.2 artisan storage:link
sudo -u deploy php8.2 artisan config:cache
sudo -u deploy php8.2 artisan route:cache
sudo -u deploy php8.2 artisan view:cache

sudo chown -R deploy:www-data /var/www/spims/storage /var/www/spims/bootstrap/cache
sudo find /var/www/spims/storage /var/www/spims/bootstrap/cache -type d -exec chmod 2775 {} +
```

`migrate --seed` creates schema and seeds languages, grading scheme, theme, settings, Super Admin, role permissions, and (if flags are on) sample/demo data.

Prove PHP can see Postgres:

```bash
cd /var/www/spims
sudo -u deploy php8.2 artisan tinker --execute="echo DB::connection()->getDatabaseName().PHP_EOL;"
```

It should print `spims`. If it throws a connection exception, fix `.env` / password / `pg_hba.conf` before touching Nginx.

---

## 11. Nginx + TLS

Create `/etc/nginx/sites-available/spims-edu.com`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name spims-edu.com www.spims-edu.com;
    root /var/www/spims/public;
    index index.php;
    client_max_body_size 64M;

    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Enable and reload:

```bash
sudo ln -s /etc/nginx/sites-available/spims-edu.com /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Issue certificates (DNS must already point here):

```bash
sudo certbot --nginx -d spims-edu.com -d www.spims-edu.com
```

Certbot will rewrite the site file for HTTPS and install a renew timer. Check:

```bash
sudo systemctl status certbot.timer
curl -sI https://spims-edu.com/health
curl -s https://spims-edu.com/health
```

Healthy JSON looks like:

```json
{"status":"ok","checks":{"app":true,"database":true,"cache":true},"..."}
```

`database: true` means the `.env` credentials reached PostgreSQL. `status` is `degraded` / HTTP 503 if the database check fails.

---

## 12. Queue worker (systemd)

Create `/etc/systemd/system/spims-queue.service`:

```ini
[Unit]
Description=SPIMS Queue Worker
After=network.target redis-server.service postgresql.service

[Service]
User=deploy
Group=www-data
WorkingDirectory=/var/www/spims
ExecStart=/usr/bin/php8.2 artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now spims-queue
sudo systemctl status spims-queue
```

The production deploy workflow restarts this unit after every push to `main`.

---

## 13. Laravel scheduler cron

The scheduler runs exam auto-submit, live/comms reminders, finance dunning, daily DB backup (`02:30`), and audit-log prune.

```bash
sudo -u deploy crontab -e
```

Add:

```
* * * * * cd /var/www/spims && php8.2 artisan schedule:run >> /dev/null 2>&1
```

Confirm:

```bash
sudo -u deploy crontab -l
cd /var/www/spims && sudo -u deploy php8.2 artisan schedule:list
```

The deploy script also runs `php8.2 artisan scheduler:ensure-cron --php=php8.2`, which **prints** the line if missing; it does not write crontab for you. Add the line once by hand.

---

## 14. Wire GitHub Actions (push to `main` → this VPS)

The workflow is already in `.github/workflows/deploy.yml`:

1. Push (or merge) to `main`.
2. Job `test` calls `ci.yml` (PHPUnit suites + PostgreSQL migrate:fresh --seed).
3. If tests pass, job `deploy` SSHs to the VPS as `deploy` and:
   - `git fetch origin main && git reset --hard origin/main`
   - `composer install --no-dev`
   - `artisan down` → `migrate --force` → cache config/routes/views → `artisan up`
   - reload `php8.2-fpm`, restart `spims-queue`

`.env` is never pulled from git.

### 14a. Repository secrets

In GitHub: **Settings → Secrets and variables → Actions → New repository secret**.

| Secret | Value |
|--------|--------|
| `SSH_HOST` | VPS public IPv4 (or a hostname that resolves to it) |
| `SSH_USER` | `deploy` |
| `SSH_PRIVATE_KEY` | full contents of `spims-deploy-actions` (the **private** key from step 5b), including the `-----BEGIN … KEY-----` lines |
| `SSH_PORT` | `22` unless you changed `sshd` |

Paste the private key exactly. Extra quotes or missing newline at the end are a common failure.

### 14b. GitHub → VPS network

Actions runners need outbound SSH to your VPS on `SSH_PORT`. If the provider's firewall is not UFW (Hetzner Cloud Firewall, AWS SG, etc.), allow `0.0.0.0/0` (or GitHub's published Actions IP ranges) on that port. UFW `OpenSSH` already allows it on the box.

### 14c. Trigger and watch

```bash
# From your laptop, on a clone of this repo:
git checkout main
git pull
# make a change, or merge a PR
git push origin main
```

Then open the repo **Actions** tab. You should see **CI** (on every push) and **Deploy to Production** (on `main` only). Deploy waits for the CI reusable workflow. First production deploy from Actions can take 15–30 minutes because of the full test gate.

After it is green:

```bash
curl -s https://spims-edu.com/health
ssh deploy@YOUR_VPS_IP 'cd /var/www/spims && git rev-parse --short HEAD && sudo systemctl is-active spims-queue'
```

The SHA should match `origin/main`.

---

## 15. Daily backups

Laravel already schedules `php artisan spims:backup-database` at 02:30 (app timezone) **if** the cron in step 13 is installed. Dumps go to `BACKUP_PATH` (`/var/backups/spims`).

Optional extra cron (same dump format as `scripts/backup-db.sh`):

```
30 2 * * * /var/www/spims/scripts/backup-db.sh >> /var/log/spims-backup.log 2>&1
```

Copy dumps off-box (rclone / Storage Box). Restore steps: [backups-and-restore.md](backups-and-restore.md).

Log rotation — `/etc/logrotate.d/spims`:

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

---

## 16. Optional: staging on the same VPS

Only if you will use `staging.spims-edu.com`.

```bash
sudo -u deploy git clone https://github.com/RobsGeorge/spims-edu.git /var/www/spims-staging
# or the same git@ URL as production if the repo is private
cd /var/www/spims-staging
sudo -u deploy git checkout staging
sudo -u deploy cp .env.example .env
```

Staging `.env` differences:

```dotenv
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://staging.spims-edu.com
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=spims_staging
DB_USERNAME=spims
DB_PASSWORD=YOUR_STRONG_DB_PASSWORD
```

The staging workflow **refuses to deploy** unless `.env` contains both `DB_DATABASE=spims_staging` and `APP_ENV=staging`.

Duplicate the Nginx site (`staging.spims-edu.com` → `/var/www/spims-staging/public`), then:

```bash
sudo certbot --nginx -d staging.spims-edu.com
```

Optional separate worker: copy `spims-queue.service` to `spims-queue-staging.service` with `WorkingDirectory=/var/www/spims-staging`. The staging workflow restarts `spims-queue-staging` if it exists, otherwise `spims-queue`.

Staging uses the **same** four SSH secrets. Push to branch `staging` to trigger `.github/workflows/deploy-staging.yml`.

---

## 17. Post-install hardening checklist

- [ ] `APP_DEBUG=false` and `FORCE_HTTPS=true` on production `.env`
- [ ] `SUPERADMIN_PASSWORD` is not the bootstrap default
- [ ] `https://spims-edu.com/health` → `status: ok`, `database: true`
- [ ] Log in as Super Admin
- [ ] `systemctl is-active spims-queue` → `active`
- [ ] `crontab -l` as `deploy` has `schedule:run`
- [ ] GitHub Actions **Deploy to Production** succeeded on a `main` push
- [ ] UFW does not expose 5432 / 6379
- [ ] Off-site backup copy configured
- [ ] Uptime probe on `GET https://spims-edu.com/health` (UptimeRobot / Hetrix / Grafana)

---

## Troubleshooting

| Symptom | What to check |
|---------|----------------|
| `deploy@IP's password:` then `Permission denied` | Expected: `deploy` has no password. Log in as **root**, install a public key, then `ssh -i ./spims-deploy-actions deploy@IP`. Do not guess a password. Repair steps in the next subsection. |
| Actions: `Permission denied (publickey)` | Public key not in `/home/deploy/.ssh/authorized_keys`; secret `SSH_PRIVATE_KEY` is the wrong key or malformed; `SSH_USER` is not `deploy` |
| Actions: `sudo: a password is required` | `/etc/sudoers.d/spims-deploy` missing or wrong paths |
| Actions: `git fetch` auth failure | Private repo without a VPS deploy key; or HTTPS clone with no credentials |
| `/health` → `database: false` | `.env` `DB_*` vs Postgres password; use `127.0.0.1` not `localhost`; `php8.2-pgsql` installed; `config:cache` stale — run `php8.2 artisan config:clear` then `config:cache` |
| 502 Bad Gateway | `php8.2-fpm` down (`systemctl status php8.2-fpm`); sock path in Nginx must be `/run/php/php8.2-fpm.sock` |
| 500 after deploy | `storage/logs/laravel.log`; `storage` / `bootstrap/cache` not writable by `www-data` (re-run the `chown`/`chmod 2775` block) |
| Certbot NXDOMAIN | DNS A records not pointing at this VPS yet |
| Queue jobs stuck | `systemctl status spims-queue`; `QUEUE_CONNECTION=redis`; Redis `PONG` |
| Super Admin cannot log in | Password is `SUPERADMIN_PASSWORD` **at seed time**. Changing `.env` later does not update the hash — reset in tinker or re-seed on a fresh DB only |

### Repair: `deploy@…'s password:` / `Permission denied`

`adduser --disabled-password` means there is no password to type. A password prompt means SSH did not accept a key and fell back to password auth, which then fails.

On your **laptop**, if you do not already have the Actions key pair:

```bash
ssh-keygen -t ed25519 -C "spims-github-actions" -f ./spims-deploy-actions -N ""
cat ./spims-deploy-actions.pub
```

On the VPS, as **root** (or a sudo user that can already log in):

```bash
ssh root@YOUR_VPS_IP
```

```bash
sudo mkdir -p /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
# Paste the one line from spims-deploy-actions.pub (starts with ssh-ed25519)
echo 'ssh-ed25519 AAAA... spims-github-actions' | sudo tee -a /home/deploy/.ssh/authorized_keys
sudo chmod 600 /home/deploy/.ssh/authorized_keys
sudo chown -R deploy:deploy /home/deploy/.ssh
sudo -u deploy cat /home/deploy/.ssh/authorized_keys
```

`authorized_keys` must contain the **public** line (`ssh-ed25519 AAAA…`), never the private key (`-----BEGIN … PRIVATE KEY-----`).

Back on the laptop:

```bash
ssh -i ./spims-deploy-actions -o IdentitiesOnly=yes deploy@YOUR_VPS_IP 'whoami'
```

`-o IdentitiesOnly=yes` stops SSH from trying other keys first. Success prints `deploy` and does not ask for a password.

For GitHub Actions, paste the **private** file `spims-deploy-actions` into secret `SSH_PRIVATE_KEY` (including the `BEGIN`/`END` lines). The public half stays only on the VPS.

Rollback: [release-runbook.md](release-runbook.md).

---

## How the production pipeline maps to the server

```
git push origin main
        │
        ▼
GitHub Actions: ci.yml  (PHPUnit + Postgres 16 migrate:fresh --seed)
        │  must pass
        ▼
GitHub Actions: deploy.yml
        │  appleboy/ssh-action
        │  secrets: SSH_HOST / SSH_USER / SSH_PRIVATE_KEY / SSH_PORT
        ▼
ssh deploy@VPS
        │
        ▼
cd /var/www/spims
git fetch + reset --hard origin/main
composer install --no-dev
artisan down → migrate --force → caches → storage:link
systemctl reload php8.2-fpm
systemctl restart spims-queue
artisan up
        │
        ▼
https://spims-edu.com   (Nginx → php8.2-fpm → Laravel → Postgres/Redis on localhost)
```

That is the entire production path. There is no separate database host to provision unless you later move Postgres off the VPS; until then, step 6 plus the `DB_*` keys in `.env` are the whole connection.
