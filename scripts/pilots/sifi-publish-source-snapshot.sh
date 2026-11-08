#!/usr/bin/env bash
# One-time SIFI Laravel vNext source snapshot publisher.
# Reads the live source without altering its Git branch, index, or working tree.
# Writes only to a fresh, isolated validation directory and a new GitHub branch.
set -euo pipefail

SOURCE="${SIFI_SOURCE:-/home/fajar/projects/sifi-laravel-vnext}"
VALIDATION="${SIFI_VALIDATION:-/mnt/d/Research/SIFI/05_VALIDATION}"
REMOTE='https://github.com/fajarnurmanto-source/strategic-issue-foresight-intelligence.git'

fail() {
    printf 'SIFI_SOURCE_SYNC_HELD=%s\n' "$1" >&2
    exit 1
}

[[ "${1:-}" == '--publish' ]] || fail 'RUN_WITH_--publish'
[[ -d "$SOURCE/app" && -f "$SOURCE/artisan" && -d "$SOURCE/.git" ]] || fail 'SOURCE_NOT_FOUND'
[[ -d "$VALIDATION" ]] || fail 'VALIDATION_DIRECTORY_NOT_FOUND'
command -v git >/dev/null || fail 'GIT_NOT_FOUND'
command -v tar >/dev/null || fail 'TAR_NOT_FOUND'

STAMP="$(date +%Y%m%d_%H%M%S)"
BRANCH="sync/sifi-laravel-vnext-ih03j-$STAMP"
STAGE="$VALIDATION/SIFI_GITHUB_SOURCE_SYNC_$STAMP"
[[ ! -e "$STAGE" ]] || fail 'STAGE_ALREADY_EXISTS'
mkdir -- "$STAGE"

# Include application source and authored assets, never runtime state or dependencies.
paths=(
  app
  bootstrap/app.php bootstrap/providers.php
  config
  database/migrations database/factories database/seeders
  resources
  routes
  tests
  tools
  packages
  lang
  public/css public/fonts public/js public/images public/img public/icons
  public/index.php public/robots.txt public/favicon.ico public/.htaccess
  artisan composer.json composer.lock
  package.json package-lock.json pnpm-lock.yaml yarn.lock
  vite.config.ts vite.config.js tsconfig.json phpunit.xml
  .gitignore .gitattributes .editorconfig README.md
)
present=()
for path in "${paths[@]}"; do
    if [[ -e "$SOURCE/$path" || -L "$SOURCE/$path" ]]; then
        present+=("$path")
    fi
done
(( ${#present[@]} > 8 )) || fail 'TOO_FEW_SOURCE_PATHS'

# Do not follow symlinks. Exclude backups, credentials, runtime DBs, logs, and archives.
tar -C "$SOURCE" -cf - \
  --exclude='.git' \
  --exclude='vendor' \
  --exclude='node_modules' \
  --exclude='storage' \
  --exclude='cache' \
  --exclude='.env*' \
  --exclude='*.env*' \
  --exclude='*.sqlite*' \
  --exclude='*.db' \
  --exclude='*.db-*' \
  --exclude='*.pem' \
  --exclude='*.key' \
  --exclude='*.p12' \
  --exclude='*.pfx' \
  --exclude='*.log' \
  --exclude='*.zip' \
  --exclude='*.7z' \
  --exclude='*.tar' \
  --exclude='*.csv' \
  --exclude='*.jsonl' \
  --exclude='*.parquet' \
  --exclude='*.pdf' \
  --exclude='*.docx' \
  --exclude='*.xlsx' \
  --exclude='*.mp4' \
  --exclude='*.webm' \
  --exclude='*Zone.Identifier*' \
  --exclude='*backup-*' \
  "${present[@]}" | tar -C "$STAGE" -xf -

large_file="$(find "$STAGE" -type f -size +50M -print -quit)"
[[ -z "$large_file" ]] || fail 'LARGE_FILE_OVER_50MB'
snapshot_kib="$(du -sk "$STAGE" | awk '{print $1}')"
(( snapshot_kib <= 204800 )) || fail 'SOURCE_SNAPSHOT_OVER_200MB'

git -C "$STAGE" init -q -b "$BRANCH"
git -C "$STAGE" config user.name 'fajarnurmanto-source'
git -C "$STAGE" config user.email '334013547+fajarnurmanto-source@users.noreply.github.com'
git -C "$STAGE" add --all

file_count="$(git -C "$STAGE" ls-files | wc -l)"
(( file_count > 100 )) || fail 'UNEXPECTEDLY_SMALL_SOURCE_SNAPSHOT'

# Names only; never display credential contents.
bad_names="$(git -C "$STAGE" ls-files | grep -Ei '(^|/)(\.env($|\.)|[^/]*\.(sqlite|db|pem|key|p12|pfx|log|zip|7z|tar)$|[^/]*Zone\.Identifier$|[^/]*backup-[^/]*)' || true)"
[[ -z "$bad_names" ]] || fail 'SENSITIVE_FILENAME_IN_SNAPSHOT'

# Reject obvious hardcoded credential/token shapes without printing matching contents.
if git -C "$STAGE" grep --cached -q -I -E -i \
    -e 'ghp_[A-Za-z0-9]{25,}' \
    -e 'github_pat_[A-Za-z0-9_]{30,}' \
    -e 'gho_[A-Za-z0-9]{25,}' \
    -e 'sk-[A-Za-z0-9_-]{32,}' \
    -e '-----BEGIN (OPENSSH|RSA|EC|DSA|PRIVATE) KEY-----'; then
    fail 'POSSIBLE_HARDCODED_CREDENTIAL'
fi

git -C "$STAGE" commit -qm "chore: publish SIFI Laravel vNext IH03J source snapshot $STAMP"
git -C "$STAGE" remote add origin "$REMOTE"

# A new remote branch; never modify main, pull unrelated histories, or force-push.
git -C "$STAGE" push --set-upstream origin "HEAD:refs/heads/$BRANCH"

printf 'SIFI_LARAVEL_VNEXT_GITHUB_SOURCE_SYNC=PASS\n'
printf 'SOURCE=%s\n' "$SOURCE"
printf 'SNAPSHOT=%s\n' "$STAGE"
printf 'BRANCH=%s\n' "$BRANCH"
printf 'FILES=%s\n' "$file_count"
printf 'SOURCE_BASE_COMMIT=%s\n' "$(git -C "$SOURCE" rev-parse --short HEAD)"
printf 'PUBLISHED_COMMIT=%s\n' "$(git -C "$STAGE" rev-parse HEAD)"
printf 'PRODUCTION_ADMISSION=DENIED\n'
