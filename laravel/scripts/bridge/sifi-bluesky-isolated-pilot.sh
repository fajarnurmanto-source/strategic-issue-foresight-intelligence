#!/usr/bin/env bash
# One explicit, locally isolated public Bluesky search. Never edits the live .env.
set -euo pipefail

APP_ROOT='/home/fajar/projects/sifi-laravel-vnext'
VALIDATION_ROOT='/mnt/d/Research/SIFI/05_VALIDATION'
SCRIPT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"

if [[ ! -f "$APP_ROOT/artisan" || ! -f "$APP_ROOT/vendor/autoload.php" ]]; then
  echo 'SIFI_PILOT=HELD: Laravel application or vendor/autoload.php is unavailable.' >&2
  exit 1
fi
if [[ ! -d "$VALIDATION_ROOT" || ! -f "$SCRIPT_ROOT/sifi-bluesky-isolated-pilot.php" ]]; then
  echo 'SIFI_PILOT=HELD: Validation directory or pilot helper is unavailable.' >&2
  exit 1
fi
if [[ -e "$APP_ROOT/bootstrap/cache/config.php" ]]; then
  echo 'SIFI_PILOT=HELD: Laravel config is cached; cannot prove per-process DB isolation.' >&2
  echo 'No database operation was attempted. Do not clear the app cache automatically.' >&2
  exit 1
fi
if ! command -v php >/dev/null 2>&1; then
  echo 'SIFI_PILOT=HELD: PHP CLI unavailable.' >&2
  exit 1
fi

if [[ -z "${SIFI_SOCIAL_CONTACT_EMAIL:-}" ]]; then
  if [[ ! -t 0 ]]; then
    echo 'SIFI_PILOT=HELD: Set SIFI_SOCIAL_CONTACT_EMAIL to a real operator contact before running non-interactively.' >&2
    exit 1
  fi
  read -r -p 'Email kontak pengelola untuk User-Agent Bluesky (bukan sandi/token): ' SIFI_SOCIAL_CONTACT_EMAIL
fi
if [[ -z "$SIFI_SOCIAL_CONTACT_EMAIL" ]]; then
  echo 'SIFI_PILOT=HELD: A real operator contact email is required.' >&2
  exit 1
fi

umask 077
PILOT_DB="$(mktemp "$VALIDATION_ROOT/SIFI_BLUESKY_LIVE_$(date +%Y%m%d_%H%M%S)_XXXXXXXX.sqlite")"
PILOT_STORAGE="${PILOT_DB%.sqlite}_storage"
mkdir -m 700 "$PILOT_STORAGE"
mkdir -p "$PILOT_STORAGE/app/private" "$PILOT_STORAGE/framework/cache/data" \
  "$PILOT_STORAGE/framework/views" "$PILOT_STORAGE/framework/sessions" "$PILOT_STORAGE/logs"

echo 'SIFI_PILOT=STARTED'
echo "ISOLATED_DB=$PILOT_DB"
echo "ISOLATED_STORAGE=$PILOT_STORAGE"
echo 'MAIN_DATABASE=UNTOUCHED_BY_PILOT'
echo 'AUTOWATCH=OFF'

cd "$APP_ROOT"
DB_CONNECTION=sqlite \
DB_DATABASE="$PILOT_DB" \
DB_URL='' \
APP_ENV=local \
APP_DEBUG=false \
LOG_CHANNEL=stderr \
QUEUE_CONNECTION=sync \
CACHE_STORE=array \
SESSION_DRIVER=array \
SIFI_SOCIAL_BLUESKY_PUBLIC_SEARCH=true \
SIFI_IH03_SHADOW_INGEST=true \
SIFI_SOCIAL_AUTOWATCH_ENABLED=false \
SIFI_SOCIAL_PILOT_STORAGE="$PILOT_STORAGE" \
SIFI_SOCIAL_CONTACT_EMAIL="$SIFI_SOCIAL_CONTACT_EMAIL" \
php "$SCRIPT_ROOT/sifi-bluesky-isolated-pilot.php"
