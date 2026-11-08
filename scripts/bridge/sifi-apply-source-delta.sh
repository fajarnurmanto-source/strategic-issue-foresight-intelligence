#!/usr/bin/env bash
# Apply NEW commits from the isolated GitHub source snapshot to the live SIFI
# Laravel worktree. Never pulls unrelated Git histories or resets live changes.
# First-use acceptance: --docs-only (documentation, no application code).
set -euo pipefail

INITIAL_SHA='9cc232acbc3da4c412bd523180d9eeea315df765'
EXPECTED_BRANCH='sync/sifi-laravel-vnext-ih03j-20261108_134500'
LIVE="${SIFI_LIVE_ROOT:-/home/fajar/projects/sifi-laravel-vnext}"
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
SNAPSHOT="$(cd -- "$SCRIPT_DIR/../.." && pwd -P)"
STATE="$SNAPSHOT/.sifi_github_bridge_applied_sha"
MODE="${1:---plan}"

fail() { printf 'SIFI_GITHUB_TO_LIVE=HELD:%s\n' "$1" >&2; exit 1; }

case "$MODE" in --plan|--docs-only|--apply) ;; *) fail 'USE_--plan_--docs-only_OR_--apply' ;; esac
command -v git >/dev/null || fail 'GIT_MISSING'
command -v cmp >/dev/null || fail 'CMP_MISSING'
command -v sed >/dev/null || fail 'SED_MISSING'
command -v sha256sum >/dev/null || fail 'SHA256SUM_MISSING'
[[ -d "$LIVE/.git" && -f "$LIVE/artisan" && -f "$LIVE/composer.json" ]] || fail 'LIVE_LARAVEL_NOT_FOUND'
[[ "$(git -C "$SNAPSHOT" rev-parse --show-toplevel)" == "$SNAPSHOT" ]] || fail 'SNAPSHOT_NOT_GIT_ROOT'
[[ "$(git -C "$SNAPSHOT" branch --show-current)" == "$EXPECTED_BRANCH" ]] || fail 'UNEXPECTED_SOURCE_BRANCH'
[[ "$(git -C "$SNAPSHOT" remote get-url origin)" == *'fajarnurmanto-source/strategic-issue-foresight-intelligence'* ]] || fail 'UNEXPECTED_REMOTE'

HEAD_SHA="$(git -C "$SNAPSHOT" rev-parse HEAD)"
BASE_SHA="$INITIAL_SHA"
if [[ -f "$STATE" ]]; then
  read -r BASE_SHA < "$STATE"
  [[ "$BASE_SHA" =~ ^[0-9a-f]{40}$ ]] || fail 'INVALID_STATE_SHA'
fi
git -C "$SNAPSHOT" cat-file -e "$BASE_SHA^{commit}" 2>/dev/null || fail 'BASE_COMMIT_MISSING'
git -C "$SNAPSHOT" merge-base --is-ancestor "$BASE_SHA" "$HEAD_SHA" || fail 'HISTORY_DIVERGED'

# Don't act upon a dirty snapshot: changes must have arrived as GitHub commits.
[[ -z "$(git -C "$SNAPSHOT" diff --name-only HEAD)" ]] || fail 'SNAPSHOT_HAS_LOCAL_EDITS'
[[ -z "$(git -C "$SNAPSHOT" diff --cached --name-only)" ]] || fail 'SNAPSHOT_HAS_STAGED_EDITS'

TMP_STAGE="$(mktemp -d)"
trap 'if [[ -n "${TMP_STAGE:-}" && -d "$TMP_STAGE" ]]; then rm -r -- "$TMP_STAGE"; fi' EXIT

same_text() {
  cmp -s -- "$1" "$2" || cmp -s <(sed 's/\r$//' -- "$1") <(sed 's/\r$//' -- "$2")
}

check_path() {
  local rel="$1" part walk
  case "$rel" in /*|*'\'*|*'//'*) return 1 ;; esac
  [[ "$rel" != '../'* && "$rel" != *'/../'* && "$rel" != *'/..' ]] || return 1
  case "$rel" in
    *'.env'*|*'.sqlite'*|*'.db'|*'.pem'|*'.key'|*'.p12'|*'.pfx'|*'.log'|*'Zone.Identifier'*|*/storage/*|*/vendor/*|*/node_modules/*) return 1 ;;
  esac
  walk="$LIVE"
  IFS='/' read -r -a parts <<< "$rel"
  for part in "${parts[@]}"; do
    [[ -n "$part" && "$part" != '.' && "$part" != '..' ]] || return 1
    walk="$walk/$part"
    [[ ! -L "$walk" ]] || return 1
  done
  [[ ! -L "$SNAPSHOT/$rel" ]] || return 1
  case "$rel" in
    app/*|bootstrap/app.php|bootstrap/providers.php|config/*|database/migrations/*|database/factories/*|database/seeders/*|resources/*|routes/*|tests/*|tools/*|packages/*|lang/*|public/css/*|public/fonts/*|public/js/*|public/images/*|public/img/*|public/icons/*|public/index.php|public/robots.txt|public/favicon.ico|public/.htaccess|docs/*|scripts/bridge/*|artisan|composer.json|composer.lock|package.json|package-lock.json|pnpm-lock.yaml|yarn.lock|vite.config.ts|vite.config.js|tsconfig.json|phpunit.xml|.gitignore|.gitattributes|.editorconfig|README.md) return 0 ;;
    *) return 1 ;;
  esac
}

declare -a rels=() actions=() candidates=() hashes=()
count_changed=0
count_ready=0
count_unchanged=0
mapfile -d '' -t changed < <(git -C "$SNAPSHOT" diff --name-status --no-renames -z "$BASE_SHA" "$HEAD_SHA")
(( ${#changed[@]} % 2 == 0 )) || fail 'UNEXPECTED_DIFF_FORMAT'

for ((j=0; j<${#changed[@]}; j+=2)); do
  status="${changed[j]}"
  rel="${changed[j+1]}"
  ((count_changed+=1))
  check_path "$rel" || fail "UNSAFE_OR_UNKNOWN_PATH:$rel"

  # The bridge script runs from the snapshot; do not install it to live Laravel.
  case "$rel" in scripts/bridge/*) printf 'SKIP_BRIDGE_TOOL=%s\n' "$rel"; continue ;; esac
  if [[ "$MODE" == '--docs-only' && "$rel" != docs/* ]]; then
    fail "DOCS_ONLY_CONTAINS_APPLICATION_CHANGE:$rel"
  fi
  [[ "$status" == 'A' || "$status" == 'M' ]] || fail "DELETION_OR_TYPE_CHANGE_REQUIRES_REVIEW:$rel"
  [[ -f "$SNAPSHOT/$rel" ]] || fail "SNAPSHOT_FILE_MISSING:$rel"

  idx="${#rels[@]}"
  candidate="$TMP_STAGE/remote-$idx"
  git -C "$SNAPSHOT" show "$HEAD_SHA:$rel" > "$candidate"
  # Do not overlay binary content or unexpectedly large authored files.
  [[ "$(stat -c '%s' "$candidate")" -le 5242880 ]] || fail "FILE_TOO_LARGE:$rel"
  if command -v file >/dev/null && [[ "$(file -b --mime-encoding "$candidate")" == 'binary' ]]; then
    fail "BINARY_CHANGE_REQUIRES_REVIEW:$rel"
  fi

  local_file="$LIVE/$rel"
  action='ADD'
  if [[ -e "$local_file" ]]; then
    [[ -f "$local_file" ]] || fail "LIVE_NOT_A_FILE:$rel"
    if same_text "$local_file" "$candidate"; then
      printf 'ALREADY_CURRENT=%s\n' "$rel"
      ((count_unchanged+=1))
      continue
    fi
    if git -C "$SNAPSHOT" cat-file -e "$BASE_SHA:$rel" 2>/dev/null; then
      old="$TMP_STAGE/old-$idx"
      git -C "$SNAPSHOT" show "$BASE_SHA:$rel" > "$old"
      if same_text "$local_file" "$old"; then
        action='UPDATE'
      else
        # Preserve independent local edits when the three-way merge is clean.
        merged="$TMP_STAGE/merged-$idx"
        if git merge-file -p -- "$local_file" "$old" "$candidate" > "$merged"; then
          candidate="$merged"
          action='MERGE'
        else
          fail "LOCAL_SOURCE_CONFLICT:$rel"
        fi
      fi
    else
      fail "NEW_GITHUB_FILE_ALREADY_DIFFERENT_LOCALLY:$rel"
    fi
  elif git -C "$SNAPSHOT" cat-file -e "$BASE_SHA:$rel" 2>/dev/null; then
    fail "EXISTING_SOURCE_MISSING_LOCALLY:$rel"
  fi

  rels+=("$rel")
  actions+=("$action")
  candidates+=("$candidate")
  if [[ -f "$local_file" ]]; then
    hashes+=("$(sha256sum "$local_file" | awk '{print $1}')")
  else
    hashes+=('NOT_PRESENT')
  fi
  ((count_ready+=1))
  printf 'READY_%s=%s\n' "$action" "$rel"
done

printf 'SOURCE_HEAD=%s\n' "$HEAD_SHA"
printf 'LIVE_ROOT=%s\n' "$LIVE"
printf 'CHANGED_PATHS=%s; READY=%s; ALREADY_CURRENT=%s\n' "$count_changed" "$count_ready" "$count_unchanged"
if [[ "$MODE" == '--plan' ]]; then
  printf 'SIFI_GITHUB_TO_LIVE=PLAN_PASS_NO_CHANGES\n'
  exit 0
fi

BACKUP_ROOT="$(cd -- "$SNAPSHOT/.." && pwd -P)/SIFI_GITHUB_BRIDGE_APPLY_BACKUPS"
BACKUP="$BACKUP_ROOT/$(date +%Y%m%d_%H%M%S)_$$"
mkdir -p -- "$BACKUP/original"

# All files are staged and conflict-checked before touching live Laravel.
for ((i=0; i<${#rels[@]}; i++)); do
  rel="${rels[i]}"
  local_file="$LIVE/$rel"
  if [[ "${hashes[i]}" == 'NOT_PRESENT' ]]; then
    [[ ! -e "$local_file" ]] || fail "LIVE_CHANGED_DURING_PREFLIGHT:$rel"
  else
    [[ -f "$local_file" && "$(sha256sum "$local_file" | awk '{print $1}')" == "${hashes[i]}" ]] || fail "LIVE_CHANGED_DURING_PREFLIGHT:$rel"
    mkdir -p -- "$BACKUP/original/$(dirname -- "$rel")"
    cp -p -- "$local_file" "$BACKUP/original/$rel"
  fi
done

applied=0
rollback() {
  local k back target
  for ((k=applied-1; k>=0; k--)); do
    target="$LIVE/${rels[k]}"
    back="$BACKUP/original/${rels[k]}"
    if [[ -f "$back" ]]; then
      cp -p -- "$back" "$target" || true
    else
      rm -f -- "$target" || true
    fi
  done
}

for ((i=0; i<${#rels[@]}; i++)); do
  rel="${rels[i]}"
  local_file="$LIVE/$rel"
  mkdir -p -- "$(dirname -- "$local_file")" || { rollback; fail "MKDIR_FAILED:$rel"; }
  temp_target="$(mktemp "$(dirname -- "$local_file")/.sifi-github-bridge.XXXXXXXX")" || { rollback; fail "TEMP_FAILED:$rel"; }
  if ! cp -- "${candidates[i]}" "$temp_target"; then
    rm -f -- "$temp_target"
    rollback
    fail "STAGING_COPY_FAILED:$rel"
  fi
  if [[ -f "$local_file" ]]; then
    chmod --reference="$local_file" "$temp_target" || { rm -f -- "$temp_target"; rollback; fail "CHMOD_FAILED:$rel"; }
  else
    chmod 0644 -- "$temp_target" || { rm -f -- "$temp_target"; rollback; fail "CHMOD_FAILED:$rel"; }
  fi
  if ! mv -f -- "$temp_target" "$local_file"; then
    rm -f -- "$temp_target"
    rollback
    fail "LIVE_INSTALL_FAILED:$rel"
  fi
  ((applied+=1))
done

printf '%s\n' "$HEAD_SHA" > "$STATE.tmp.$$" || { rollback; fail 'STATE_WRITE_FAILED'; }
if ! mv -- "$STATE.tmp.$$" "$STATE"; then rollback; fail 'STATE_INSTALL_FAILED'; fi
printf 'SIFI_GITHUB_TO_LIVE=PASS\n'
printf 'MODE=%s\n' "$MODE"
printf 'APPLIED_FILES=%s\n' "$applied"
printf 'BACKUP=%s\n' "$BACKUP"
printf 'APP_RUNTIME_RESTART=NOT_PERFORMED\n'
printf 'PRODUCTION_ADMISSION=DENIED\n'
