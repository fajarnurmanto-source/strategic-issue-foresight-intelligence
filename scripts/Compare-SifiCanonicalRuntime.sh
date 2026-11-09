#!/usr/bin/env bash
# Compare the reviewed single-repository Laravel source to the current WSL
# runtime without changing either tree. Do not copy, delete, reset or migrate.
set -euo pipefail

WORKSPACE="${SIFI_WORKSPACE_ROOT:-/mnt/d/Research/SIFI}"
LIVE="${SIFI_LIVE_ROOT:-/home/fajar/projects/sifi-laravel-vnext}"
expected_tree='f8b0bf682246d941cdb9837dd72ead360bbb20ec'

die() { printf 'SIFI_CANONICAL_COMPARE=BLOCKED:%s\n' "$1" >&2; exit 2; }
command -v git >/dev/null || die GIT_MISSING
[[ -d "$WORKSPACE/.git" && -d "$WORKSPACE/laravel" ]] || die PROJECT_ROOT_OR_LARAVEL_MISSING
[[ -f "$LIVE/artisan" && -f "$LIVE/composer.json" ]] || die WSL_LIVE_LARAVEL_MISSING
[[ "$(git -C "$WORKSPACE" rev-parse --show-toplevel)" == "$(cd "$WORKSPACE" && pwd -P)" ]] || die NOT_PROJECT_ROOT

source_tree="$(git -C "$WORKSPACE" rev-parse HEAD:laravel 2>/dev/null)" || die CANONICAL_SOURCE_TREE_MISSING
source_commit="$(git -C "$WORKSPACE" rev-parse HEAD)"
live_commit="$(git -C "$LIVE" rev-parse HEAD 2>/dev/null || true)"
printf 'PROJECT_ROOT=%s\nCANONICAL_SOURCE=%s\nWSL_RUNTIME=%s\n' "$WORKSPACE" "$WORKSPACE/laravel" "$LIVE"
printf 'SOURCE_COMMIT=%s\nSOURCE_LARAVEL_TREE=%s\nLIVE_COMMIT=%s\n' "$source_commit" "$source_tree" "${live_commit:-UNAVAILABLE}"
if [[ "$source_tree" == "$expected_tree" ]]; then
  printf 'SOURCE_RELEASE_FINGERPRINT=ORIGINAL_IH03J_SNAPSHOT\n'
else
  printf 'SOURCE_RELEASE_FINGERPRINT=CHANGED_AFTER_INITIAL_IMPORT\n'
fi

matched=0
drifted=0
missing=0
unsafe=0
displayed=0
while IFS= read -r -d '' entry; do
  rel="${entry#*$'\t'}"
  metadata="${entry%%$'\t'*}"
  read -r mode kind blob <<< "$metadata"
  [[ "$rel" == laravel/* ]] || die INVALID_TREE_PATH
  sub="${rel#laravel/}"
  case "$sub" in
    *'../'*|*'/..'*|/*) die PATH_ESCAPE ;;
  esac
  if [[ "$kind" != blob || ( "$mode" != 100644 && "$mode" != 100755 ) ]]; then
    ((unsafe+=1))
    if ((displayed < 20)); then printf 'UNSAFE_ENTRY=%s\n' "$sub"; ((displayed+=1)); fi
    continue
  fi
  target="$LIVE/$sub"
  if [[ -L "$target" ]]; then
    ((unsafe+=1))
    if ((displayed < 20)); then printf 'LIVE_SYMLINK=%s\n' "$sub"; ((displayed+=1)); fi
  elif [[ ! -f "$target" ]]; then
    ((missing+=1))
    if ((displayed < 20)); then printf 'MISSING_IN_RUNTIME=%s\n' "$sub"; ((displayed+=1)); fi
  else
    actual="$(git -C "$WORKSPACE" hash-object -- "$target")" || die HASH_FAILURE
    if [[ "$actual" == "$blob" ]]; then
      ((matched+=1))
    else
      ((drifted+=1))
      if ((displayed < 20)); then printf 'DIFFERENT_IN_RUNTIME=%s\n' "$sub"; ((displayed+=1)); fi
    fi
  fi
done < <(git -C "$WORKSPACE" ls-tree -rz HEAD -- laravel)

# Files present only in the live Git index may be newer custom modules and
# must be reviewed; never remove or implicitly exclude them.
extra=0
if [[ -d "$LIVE/.git" ]]; then
  while IFS= read -r -d '' rel; do
    if [[ ! -f "$WORKSPACE/laravel/$rel" && ! -L "$WORKSPACE/laravel/$rel" ]]; then
      ((extra+=1))
      if ((displayed < 20)); then printf 'LIVE_ONLY_TRACKED=%s\n' "$rel"; ((displayed+=1)); fi
    fi
  done < <(git -C "$LIVE" ls-files -z)
fi

printf 'SOURCE_FILES_MATCHED=%s\nSOURCE_FILES_DIFFERENT=%s\nSOURCE_FILES_MISSING=%s\nSOURCE_FILES_UNSAFE=%s\nLIVE_ONLY_TRACKED=%s\n' \
  "$matched" "$drifted" "$missing" "$unsafe" "$extra"
printf 'SOURCE_CENTER=DEFERRED\nPRODUCTION_ADMISSION=DENIED\n'
if ((matched == 0 || drifted || missing || unsafe || extra)); then
  printf 'SIFI_CANONICAL_COMPARE=RECONCILIATION_REQUIRED\n'
  exit 1
fi
printf 'SIFI_CANONICAL_COMPARE=MATCHED_TRACKED_SOURCE\n'
