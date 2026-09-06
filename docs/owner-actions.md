# Owner actions — what I need from you

The repo now has the test gate, production/staging deploy, rollback, VPS templates, and the
`/api/v1` mobile surface. **I cannot finish a live deploy from here.** GitHub Actions needs
secrets I must not invent, and your VPS/DNS live on your side.

Do these once. After they are in place, every push to `staging` / `main` deploys itself.

## 1. Confirm the hostname plan

Reply with the exact names (defaults below match the current docs):

| Role | Default | Yours |
|------|---------|-------|
| Production | `spims-edu.com` + `www.spims-edu.com` | |
| Staging | `staging.spims-edu.com` | |
| VPS IPv4 | — | **required** |
| VPS IPv6 (optional) | — | |

DNS at your registrar (or Cloudflare):

| Type | Name | Value |
|------|------|-------|
| A | `@` (apex) | VPS IPv4 |
| A | `www` | VPS IPv4 |
| A | `staging` | VPS IPv4 |
| AAAA | same names | VPS IPv6, if you have one |

Do **not** put Cloudflare orange-cloud proxy in front until the first Let's Encrypt certificate
has been issued (or use DNS-01). Grey-cloud / DNS-only is fine.

## 2. GitHub Actions secrets (repo Settings → Secrets and variables → Actions)

Create these. Empty optional secrets only skip the public health probe; deploy still runs.

| Secret | Required | Example | What it is |
|--------|----------|---------|------------|
| `SSH_HOST` | yes | `203.0.113.10` | VPS public IP or hostname |
| `SSH_USER` | yes | `deploy` | Linux user that owns `/var/www/spims` |
| `SSH_PRIVATE_KEY` | yes | `-----BEGIN OPENSSH PRIVATE KEY-----` | Private half of the key in `deploy`'s `authorized_keys` |
| `SSH_PORT` | yes | `22` | SSH port |
| `PRODUCTION_HEALTH_URL` | recommended | `https://spims-edu.com` | Public origin used for `/health` + `/api/v1/branding` after deploy |
| `STAGING_HEALTH_URL` | recommended | `https://staging.spims-edu.com` | Same for staging |
| `PRODUCTION_DOMAIN` | recommended | `spims-edu.com` | Host header for the loopback `/health` probe on the box |
| `STAGING_DOMAIN` | recommended | `staging.spims-edu.com` | Same for staging |

How to mint the Actions → VPS key (on your laptop):

```bash
ssh-keygen -t ed25519 -f spims-github-deploy -C "github-actions-spims" -N ""
# public  → /home/deploy/.ssh/authorized_keys on the VPS
# private → GitHub secret SSH_PRIVATE_KEY (entire file, including BEGIN/END lines)
```

## 3. VPS → GitHub deploy key (so `git fetch` works)

This is a **second** key, not the Actions SSH key.

1. On the VPS as `deploy`: `ssh-keygen -t ed25519 -f ~/.ssh/github_spims -C "spims-vps-fetch" -N ""`
2. GitHub repo → Settings → Deploy keys → add `~/.ssh/github_spims.pub` (read-only).
3. `~/.ssh/config` on the VPS:

```
Host github.com
  IdentityFile ~/.ssh/github_spims
  IdentitiesOnly yes
```

Clone with SSH: `git@github.com:RobsGeorge/spims-edu.git`.

If the repo is public, HTTPS clone also works and you can skip this key.

## 4. First boot on the VPS

As **root**, from a checkout of this repo (or a throwaway clone):

```bash
git clone git@github.com:RobsGeorge/spims-edu.git /tmp/spims-bootstrap
DOMAIN=spims-edu.com STAGING_HOST=staging.spims-edu.com \
  sudo -E /tmp/spims-bootstrap/deploy/first-boot.sh
```

Save the printed PostgreSQL password. Then as `deploy`:

```bash
git clone git@github.com:RobsGeorge/spims-edu.git /var/www/spims
cp /var/www/spims/.env.example /var/www/spims/.env
# edit .env — production values below

git clone git@github.com:RobsGeorge/spims-edu.git /var/www/spims-staging
cd /var/www/spims-staging && git checkout staging
cp /var/www/spims-staging/.env.example /var/www/spims-staging/.env
# APP_ENV=staging, APP_URL=https://staging…, DB_DATABASE=spims_staging
```

Issue certificates **after DNS answers**:

```bash
sudo certbot --nginx -d spims-edu.com -d www.spims-edu.com
sudo certbot --nginx -d staging.spims-edu.com
```

First release (once `.env` has `APP_KEY` and the database password):

```bash
cd /var/www/spims
composer install --no-dev --optimize-autoloader
php8.2 artisan key:generate
# set APP_KEY stays; then:
php8.2 artisan migrate --seed --force
GIT_REF=origin/main SKIP_GIT=1 ./scripts/vps-sync.sh
HEALTH_URL=https://spims-edu.com LOOPBACK_HEALTH_HOST=spims-edu.com \
  QUEUE_UNIT=spims-queue ./scripts/vps-release.sh
```

Manual walkthrough: [vps-setup.md](vps-setup.md). Release checklist: [release-runbook.md](release-runbook.md).

## 5. Production `.env` you must set on the box

Never commit these. The pipelines do **not** overwrite `.env`.

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://spims-edu.com
FORCE_HTTPS=true
APP_KEY=base64:…          # artisan key:generate, once
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=spims
DB_USERNAME=spims
DB_PASSWORD=…             # from first-boot
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SUPERADMIN_EMAIL=…        # your login
SUPERADMIN_PASSWORD=…     # change from the example default before public launch
SEED_SAMPLE_DATA=true     # false after you have real catalog data
SEED_DEMO_DATA=false
BACKUP_PATH=/var/backups/spims
BACKUP_RETENTION_DAYS=14
SANCTUM_EXPIRATION=43200  # 30 days; mobile clients re-login when it expires
MAIL_MAILER=log           # or smtp / ses when you have mail
```

Optional integrations (app degrades without them): `AWS_*` (R2/S3), Zoom, Vimeo, PayPal, Paymob.

Staging `.env` is the same except `APP_ENV=staging`, `APP_DEBUG=true` if you want it, `APP_URL=https://staging…`, `DB_DATABASE=spims_staging`.

## 6. After secrets + first boot exist

| Event | What happens |
|-------|----------------|
| Push / merge to `staging` | CI (lint + SQLite suite + Postgres migrate + Postgres suite) → SSH deploy to `/var/www/spims-staging` |
| Push / merge to `main` | Same CI → SSH deploy to `/var/www/spims` |
| Actions → **Rollback** | Manual: pick `production` or `staging` + a known-good SHA |

I do not need SSH access to your VPS. Once the secrets above are in GitHub, the workflows I pushed are sufficient.

If any secret name or path must differ (`/srv/spims` instead of `/var/www/spims`), say so and we will parameterize it.
