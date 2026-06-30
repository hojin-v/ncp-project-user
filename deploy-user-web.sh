#!/usr/bin/env bash
set -euo pipefail

# web-user-01에서 실행하는 user-web 배포 스크립트.
# 실행 위치: repo root (./public, ./src 가 있는 곳)
# 예: sudo bash deploy-user-web.sh

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ ! -d "$ROOT_DIR/public" || ! -d "$ROOT_DIR/src" ]]; then
  echo "ERROR: public/ and src/ must exist next to this script." >&2
  exit 1
fi

if [[ ! -r /var/www/weather-app-config/config.php ]]; then
  echo "ERROR: /var/www/weather-app-config/config.php is missing or unreadable." >&2
  echo "Create the webroot-outside config first, then rerun." >&2
  echo "Template is included as weather-app-config.template.php." >&2
  exit 2
fi

if [[ "${EUID}" -ne 0 ]]; then
  echo "ERROR: run with sudo/root. Example: sudo bash deploy-user-web.sh" >&2
  exit 3
fi

echo "[1/5] PHP syntax check"
CHECK_DIRS=("$ROOT_DIR/public" "$ROOT_DIR/src")
if [[ -d "$ROOT_DIR/bin" ]]; then
  CHECK_DIRS+=("$ROOT_DIR/bin")
fi
find "${CHECK_DIRS[@]}" -type f -name '*.php' -print0 \
  | xargs -0 -n1 php -l >/dev/null

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="/var/www/backups/user-web-${STAMP}"

echo "[2/5] Backup existing webroot -> ${BACKUP}"
mkdir -p "$BACKUP"
if [[ -d /var/www/html ]]; then
  rsync -a /var/www/html/ "$BACKUP/html/"
fi
if [[ -d /var/www/src ]]; then
  rsync -a /var/www/src/ "$BACKUP/src/"
fi
if [[ -d /var/www/bin ]]; then
  rsync -a /var/www/bin/ "$BACKUP/bin/"
fi

echo "[3/5] Deploy public/, src/ and optional bin/"
mkdir -p /var/www/html /var/www/src
rsync -a --delete "$ROOT_DIR/public/" /var/www/html/
rsync -a --delete "$ROOT_DIR/src/" /var/www/src/
if [[ -d "$ROOT_DIR/bin" ]]; then
  mkdir -p /var/www/bin
  rsync -a --delete "$ROOT_DIR/bin/" /var/www/bin/
fi
find /var/www/html /var/www/src /var/www/bin -type d -exec chmod 755 {} + 2>/dev/null || true
find /var/www/html /var/www/src /var/www/bin -type f -exec chmod 644 {} + 2>/dev/null || true
echo "$BACKUP" > /var/www/.last-user-backup

echo "[4/5] Local HTTP checks"
curl -fsS http://127.0.0.1/health.php >/dev/null
curl -fsS http://127.0.0.1/api/weather.php?city=seoul >/dev/null
curl -fsS http://127.0.0.1/api/comments.php >/dev/null
if [[ -f /var/www/bin/healthcheck.php ]]; then
  php /var/www/bin/healthcheck.php >/dev/null
fi

echo "[5/5] Done"
echo "backup=${BACKUP}"
echo "verify=http://127.0.0.1/"

