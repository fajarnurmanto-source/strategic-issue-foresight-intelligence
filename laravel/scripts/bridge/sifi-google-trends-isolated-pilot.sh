#!/usr/bin/env bash
# One-shot official Google Trending Now RSS ingestion on fresh validation-only SQLite.
set -euo pipefail

APP_ROOT='/home/fajar/projects/sifi-laravel-vnext'
VALIDATION_ROOT='/mnt/d/Research/SIFI/05_VALIDATION'
SCRIPT_ROOT="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"

if [[ ! -f "$APP_ROOT/artisan" || ! -f "$APP_ROOT/vendor/autoload.php" ]]; then
  echo 'SIFI_GOOGLE_PILOT=HELD: Laravel application or Composer autoload unavailable.' >&2
  exit 1
fi
if [[ ! -d "$VALIDATION_ROOT" || ! -f "$SCRIPT_ROOT/sifi-google-trends-isolated-pilot.php" ]]; then
  echo 'SIFI_GOOGLE_PILOT=HELD: Validation folder or helper unavailable.' >&2
  exit 1
fi
if [[ -e "$APP_ROOT/bootstrap/cache/config.php" ]]; then
  echo 'SIFI_GOOGLE_PILOT=HELD: Cached Laravel configuration prevents proof of database isolation.' >&2
  exit 1
fi
if ! command -v php >/dev/null 2>&1; then
  echo 'SIFI_GOOGLE_PILOT=HELD: PHP CLI unavailable.' >&2
  exit 1
fi

umask 077
PILOT_DB="$(mktemp "$VALIDATION_ROOT/SIFI_GOOGLE_TRENDS_LIVE_$(date +%Y%m%d_%H%M%S)_XXXXXXXX.sqlite")"
PILOT_STORAGE="${PILOT_DB%.sqlite}_storage"
mkdir -m 700 "$PILOT_STORAGE"
mkdir -p "$PILOT_STORAGE/app/private" "$PILOT_STORAGE/framework/cache/data" \
  "$PILOT_STORAGE/framework/views" "$PILOT_STORAGE/framework/sessions" "$PILOT_STORAGE/logs"

echo 'SIFI_GOOGLE_PILOT=STARTED'
echo "ISOLATED_DB=$PILOT_DB"
echo "ISOLATED_STORAGE=$PILOT_STORAGE"
echo 'MAIN_DATABASE=UNTOUCHED_BY_PILOT'
echo 'NETWORK_BUDGET=ONE_GET'
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
SIFI_GOOGLE_TRENDS_RSS=true \
SIFI_IH03_SHADOW_INGEST=true \
SIFI_SOCIAL_AUTOWATCH_ENABLED=false \
SIFI_GOOGLE_PILOT_STORAGE="$PILOT_STORAGE" \
php "$SCRIPT_ROOT/sifi-google-trends-isolated-pilot.php"
