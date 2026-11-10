#!/usr/bin/env bash
# Reconcile active WSL Laravel source into a separate, clean clone of the
# reviewed canonical integration branch. Never edit the live runtime or
# D:\Research\SIFI main working tree, and never publish or migrate data.
set -euo pipefail

STAGE_INPUT="${1:-}"
LIVE_INPUT="${2:-/home/fajar/projects/sifi-laravel-vnext}"

die() { printf 'SIFI_CANONICAL_STAGE=BLOCKED:%s\n' "$1" >&2; exit 2; }
is_historical_copy() {
  case "$1" in
    .*-backup-*|.*-backup-*/*|*.pre-security-fix|*:Zone.Identifier) return 0 ;;
    *) return 1 ;;
  esac
}
[[ -n "$STAGE_INPUT" ]] || die STAGE_PATH_REQUIRED
command -v git >/dev/null || die GIT_MISSING
command -v realpath >/dev/null || die REALPATH_MISSING

stage="$(realpath -e -- "$STAGE_INPUT" 2>/dev/null)" || die STAGE_MISSING
live="$(realpath -e -- "$LIVE_INPUT" 2>/dev/null)" || die LIVE_MISSING
case "$stage" in
  /mnt/d/Research/SIFI_CANONICAL_STAGE_*) ;;
  *) die STAGE_MUST_BE_SEPARATE_WINDOWS_CLONE ;;
esac
[[ "$stage" != "$live" ]] || die STAGE_EQUALS_LIVE
[[ -d "$stage/.git" && -f "$stage/laravel/artisan" ]] || die STAGE_NOT_CANONICAL_CLONE
[[ -d "$live/.git" && -f "$live/artisan" ]] || die LIVE_NOT_LARAVEL_REPO

branch="$(git -C "$stage" branch --show-current)" || die STAGE_BRANCH_UNKNOWN
[[ "$branch" == integration/sifi-canonical-laravel-20261109 ]] || die WRONG_STAGE_BRANCH
[[ -z "$(git -C "$stage" status --porcelain --untracked-files=all)" ]] || die STAGE_MUST_BE_CLEAN
[[ -z "$(git -C "$stage" ls-files --deleted)" ]] || die STAGE_HAS_MISSING_TRACKED_FILES

origin="$(git -C "$stage" remote get-url origin 2>/dev/null)" || die STAGE_REMOTE_UNKNOWN
case "$origin" in
  *fajarnurmanto-source/strategic-issue-foresight-intelligence*) ;;
  *) die UNEXPECTED_STAGE_REMOTE ;;
esac

# Source selection comes only from Git's tracked and nonignored untracked paths.
# Local ignored .env, SQLite, storage, vendor, and node_modules stay in WSL.
filelist="$(mktemp -t sifi-canonical-list.XXXXXX)" || die TEMP_FILE_FAILED
trap 'rm -f -- "$filelist"' EXIT
git -C "$live" ls-files --cached --others --exclude-standard -z > "$filelist" || die LIVE_INVENTORY_FAILED

protected=0
unsafe=0
missing=0
total=0
historical=0
while IFS= read -r -d '' rel; do
  ((total+=1))
  [[ -n "$rel" && "$rel" != /* && "$rel" != ../* && "$rel" != *'/../'* ]] || die BAD_LIVE_PATH
  if is_historical_copy "$rel"; then
    ((historical+=1))
    continue
  fi
  case "$rel" in
    .env.example|*/.env.example) ;;
    .git|.git/*|.env|.env.*|*/.env|*/.env.*|*.pem|*.key|*.p12|*.pfx|\
    vendor|vendor/*|node_modules|node_modules/*|storage|storage/*|\
    bootstrap/cache|bootstrap/cache/*|database/*.sqlite|database/*.db|\
    *.sqlite|*.sqlite-*|*.db|*.db-*|*.log|.cache/*)
      ((protected+=1))
      if ((protected <= 12)); then printf 'PROTECTED_NOT_COPIED=%s\n' "$rel"; fi
      continue
      ;;
  esac
  src="$live/$rel"
  if [[ ! -e "$src" ]]; then
    ((missing+=1))
    if ((missing <= 20)); then printf 'LIVE_TRACKED_MISSING=%s\n' "$rel"; fi
    continue
  fi
  if [[ ! -f "$src" || -L "$src" ]]; then
    ((unsafe+=1))
    if ((unsafe <= 20)); then printf 'UNSAFE_SOURCE_TYPE=%s\n' "$rel"; fi
    continue
  fi
  source_real="$(realpath -e -- "$src")" || die SOURCE_REALPATH_FAILED
  [[ "$source_real" == "$live/"* ]] || die SOURCE_SYMLINK_ESCAPE
  dst="$stage/laravel/$rel"
  dest_parent_real="$(realpath -m -- "$(dirname -- "$dst")")" || die DESTINATION_REALPATH_FAILED
  [[ "$dest_parent_real" == "$stage/laravel" || "$dest_parent_real" == "$stage/laravel/"* ]] || die DESTINATION_SYMLINK_ESCAPE
  [[ ! -L "$dst" ]] || die DESTINATION_SYMLINK
done < "$filelist"

# Fail closed before writing anything if the inventory is unexpected.
((unsafe == 0)) || die UNSAFE_SOURCE_ENTRIES
((protected == 0)) || die PROTECTED_SOURCE_IN_GIT_INVENTORY
((total > 0)) || die EMPTY_SOURCE_INVENTORY

matched=0
updated=0
added=0
while IFS= read -r -d '' rel; do
  if is_historical_copy "$rel"; then continue; fi
  src="$live/$rel"
  [[ -f "$src" && ! -L "$src" ]] || continue
  dst="$stage/laravel/$rel"
  if [[ -f "$dst" ]] && cmp -s -- "$src" "$dst"; then
    ((matched+=1))
    continue
  fi
  if [[ -e "$dst" ]]; then
    ((updated+=1))
  else
    ((added+=1))
  fi
  mkdir -p -- "$(dirname -- "$dst")"
  # DrvFs/NTFS may reject POSIX ownership or mode preservation; Git tracks
  # source bytes, and core.filemode is disabled only in this staging clone.
  cp -- "$src" "$dst"
done < "$filelist"

printf 'WSL_FILES_IN_GIT_INVENTORY=%s\n' "$total"
printf 'HISTORICAL_COPIES_EXCLUDED=%s\n' "$historical"
printf 'WSL_FILES_IDENTICAL=%s\n' "$matched"
printf 'STAGED_UPDATED_FILES=%s\n' "$updated"
printf 'STAGED_ADDED_FILES=%s\n' "$added"
printf 'LIVE_TRACKED_MISSING_NOT_REMOVED=%s\n' "$missing"
printf 'STAGE_REPO=%s\n' "$stage"
printf 'STAGE_BRANCH=%s\n' "$branch"
printf 'LIVE_SOURCE_COMMIT=%s\n' "$(git -C "$live" rev-parse HEAD)"
printf 'CANONICAL_BASE_COMMIT=%s\n' "$(git -C "$stage" rev-parse HEAD)"
printf 'SOURCE_CENTER=DEFERRED\nPRODUCTION_ADMISSION=DENIED\n'
git -C "$stage" status --short -- laravel | head -n 35 || true
git -C "$stage" diff --stat -- laravel | tail -n 12 || true
if ((missing > 0)); then
  printf 'SIFI_CANONICAL_STAGE=REVIEW_REQUIRED:MISSING_LIVE_FILES\n'
else
  printf 'SIFI_CANONICAL_STAGE=READY_FOR_DIFF_REVIEW\n'
fi
