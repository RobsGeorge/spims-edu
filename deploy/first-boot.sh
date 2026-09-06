#!/usr/bin/env bash
# One-time VPS bootstrap for SPIMS. Run as root on a fresh Ubuntu 22.04/24.04 box.
#
# Required:
#   DOMAIN=spims-edu.com
# Optional:
#   STAGING_HOST=staging.spims-edu.com
#   DEPLOY_USER=deploy
#   GIT_URL=git@github.com:RobsGeorge/spims-edu.git
#   DB_PASSWORD=...   (generated if omitted)
#
# This does NOT clone the app or write GitHub secrets. After it finishes, follow
# docs/owner-actions.md (deploy key, clone, .env, certbot, GitHub Actions secrets).
set -euo pipefail

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Run as root: sudo DOMAIN=example.com $0" >&2
  exit 1
fi

DOMAIN="${DOMAIN:-}"
if [[ -z "$DOMAIN" ]]; then
  echo "DOMAIN is required, e.g. DOMAIN=spims-edu.com sudo -E $0" >&2
  exit 1
fi

STAGING_HOST="${STAGING_HOST:-staging.${DOMAIN}}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
GIT_URL="${GIT_URL:-git@github.com:RobsGeorge/spims-edu.git}"
WWW_PROD="/var/www/spims"
WWW_STAGING="/var/www/spims-staging"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

if [[ -z "${DB_PASSWORD:-}" ]]; then
  DB_PASSWORD="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"
  GENERATED_DB_PASSWORD=1
else
  GENERATED_DB_PASSWORD=0
fi

echo "==> Installing packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y
apt-get install -y software-properties-common curl ca-certificates gnupg lsb-release unzip git

if ! php -v 2>/dev/null | grep -q 'PHP 8.2'; then
  add-apt-repository -y ppa:ondrej/php
  apt-get update -y
fi

apt-get install -y \
  nginx redis-server \
  php8.2-fpm php8.2-cli php8.2-pgsql php8.2-sqlite3 php8.2-mbstring \
  php8.2-xml php8.2-curl php8.2-zip php8.2-gd php8.2-bcmath php8.2-redis \
  certbot python3-certbot-nginx

if ! command -v psql >/dev/null; then
  apt-get install -y postgresql postgresql-contrib || apt-get install -y postgresql-16 postgresql-contrib-16
fi

if [[ ! -x /usr/local/bin/composer ]]; then
  curl -sS https://getcomposer.org/installer | php
  mv composer.phar /usr/local/bin/composer
fi

echo "==> Deploy user ${DEPLOY_USER}"
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
  adduser --disabled-password --gecos 'SPIMS deploy' "$DEPLOY_USER"
fi
usermod -aG www-data "$DEPLOY_USER"
mkdir -p "/home/${DEPLOY_USER}/.ssh"
chmod 700 "/home/${DEPLOY_USER}/.ssh"
touch "/home/${DEPLOY_USER}/.ssh/authorized_keys"
chmod 600 "/home/${DEPLOY_USER}/.ssh/authorized_keys"
chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh"

cat >/etc/sudoers.d/spims-deploy <<EOF
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /usr/bin/chown, /usr/sbin/chown, /bin/chown, /usr/bin/chmod, /bin/chmod, /usr/bin/systemctl, /bin/systemctl
EOF
chmod 440 /etc/sudoers.d/spims-deploy

echo "==> PostgreSQL databases"
sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
DO \$\$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'spims') THEN
    CREATE ROLE spims LOGIN PASSWORD '${DB_PASSWORD}';
  END IF;
END
\$\$;
SQL
sudo -u postgres psql -c "SELECT 1 FROM pg_database WHERE datname = 'spims'" | grep -q 1 \
  || sudo -u postgres psql -c "CREATE DATABASE spims OWNER spims;"
sudo -u postgres psql -c "SELECT 1 FROM pg_database WHERE datname = 'spims_staging'" | grep -q 1 \
  || sudo -u postgres psql -c "CREATE DATABASE spims_staging OWNER spims;"

echo "==> App directories"
mkdir -p "$WWW_PROD" "$WWW_STAGING" /var/backups/spims
chown -R "${DEPLOY_USER}:www-data" /var/www/spims /var/www/spims-staging
chown "${DEPLOY_USER}:${DEPLOY_USER}" /var/backups/spims

install_nginx_site() {
  local name="$1"
  local server_name="$2"
  local root="$3"
  local dest="/etc/nginx/sites-available/${name}"
  sed -e "s|__SERVER_NAME__|${server_name}|g" -e "s|__ROOT__|${root}|g" \
    "${REPO_ROOT}/deploy/nginx/spims.conf" > "$dest"
  ln -sfn "$dest" "/etc/nginx/sites-enabled/${name}"
}

if [[ -f "${REPO_ROOT}/deploy/nginx/spims.conf" ]]; then
  echo "==> Nginx sites"
  rm -f /etc/nginx/sites-enabled/default
  install_nginx_site "$DOMAIN" "$DOMAIN www.${DOMAIN}" "${WWW_PROD}/public"
  install_nginx_site "$STAGING_HOST" "$STAGING_HOST" "${WWW_STAGING}/public"
  nginx -t
  systemctl reload nginx
fi

if [[ -f "${REPO_ROOT}/deploy/systemd/spims-queue.service" ]]; then
  echo "==> systemd queue units"
  cp "${REPO_ROOT}/deploy/systemd/spims-queue.service" /etc/systemd/system/
  cp "${REPO_ROOT}/deploy/systemd/spims-queue-staging.service" /etc/systemd/system/
  systemctl daemon-reload
  systemctl enable spims-queue.service spims-queue-staging.service
fi

systemctl enable --now nginx php8.2-fpm redis-server postgresql

cat >/etc/logrotate.d/spims <<'EOF'
/var/www/spims/storage/logs/*.log
/var/www/spims-staging/storage/logs/*.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    copytruncate
}
EOF

echo
echo "========================================"
echo "First-boot complete for ${DOMAIN}"
echo "========================================"
echo "Git remote:     ${GIT_URL}"
echo "Production dir: ${WWW_PROD}"
echo "Staging dir:    ${WWW_STAGING}"
if [[ "$GENERATED_DB_PASSWORD" -eq 1 ]]; then
  echo "DB user:        spims"
  echo "DB password:    ${DB_PASSWORD}   ← save this now; it is not stored elsewhere"
fi
echo
echo "Next (see docs/owner-actions.md):"
echo "  1. DNS A/AAAA ${DOMAIN}, www, ${STAGING_HOST} → this VPS"
echo "  2. Add GitHub Actions SSH public key to /home/${DEPLOY_USER}/.ssh/authorized_keys"
echo "  3. Add a repo deploy key so ${DEPLOY_USER} can git fetch (private repo)"
echo "  4. Clone ${GIT_URL} into ${WWW_PROD} and ${WWW_STAGING}"
echo "  5. Copy .env.example → .env, set APP_URL, DB_*, FORCE_HTTPS, SUPERADMIN_PASSWORD"
echo "  6. certbot --nginx -d ${DOMAIN} -d www.${DOMAIN} -d ${STAGING_HOST}"
echo "  7. Add GitHub Actions secrets listed in docs/owner-actions.md"
echo "  8. First release: sudo -u ${DEPLOY_USER} bash -lc 'cd ${WWW_PROD} && GIT_REF=origin/main ./scripts/vps-sync.sh && ./scripts/vps-release.sh'"
