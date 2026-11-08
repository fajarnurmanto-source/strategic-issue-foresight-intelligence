#!/usr/bin/env bash
# Read-only discovery of the owner's canonical SIFI development instructions.
# Does not upload files or read/print their contents. Safe to run in WSL.
set -euo pipefail

ROOT="${SIFI_CANONICAL_ROOT:-/mnt/d/Research/SIFI}"
[[ "${1:---scan}" == '--scan' ]] || { printf 'USAGE: bash %s --scan\n' "$0" >&2; exit 2; }
[[ -d "$ROOT" ]] || { printf 'SIFI_ROADMAP_DISCOVERY=HELD:ROOT_MISSING\n' >&2; exit 1; }

printf 'SIFI_ROADMAP_DISCOVERY_ROOT=%s\n' "$ROOT"
if [[ -f "$ROOT/AGENTS.md" ]]; then
  printf 'CANONICAL_AGENT_INSTRUCTIONS=%s\n' "$ROOT/AGENTS.md"
  printf 'CANONICAL_AGENT_INSTRUCTIONS_SHA256=%s\n' "$(sha256sum "$ROOT/AGENTS.md" | awk '{print $1}')"
else
  printf 'CANONICAL_AGENT_INSTRUCTIONS=MISSING\n'
fi

# Prune heavyweight runtime, validation and dependency folders. The canonical
# roadmap should be a maintained authoring document, not a runtime artifact.
declare -a files=()
while IFS= read -r -d '' path; do
  [[ -f "$path" && ! -L "$path" ]] || continue
  [[ "$path" != "$ROOT/AGENTS.md" ]] || continue
  bytes="$(stat -c '%s' -- "$path")"
  (( bytes <= 2097152 )) || continue
  files+=("$path")
done < <(find "$ROOT" \
  \( -type d \( -iname .git -o -iname node_modules -o -iname vendor \
     -o -iname '02_RUNTIME' -o -iname 'runtime' \
     -o -iname '05_VALIDATION' -o -iname '*backup*' \
     -o -iname '__pycache__' -o -iname '.venv' \) -prune \) -o \
  \( -type f \( -iname '*roadmap*' -o -iname '*milestone*' \
     -iname '*master*plan*' -o -iname '*canonical*' \) -print0 \))

printf 'SIFI_ROADMAP_CANDIDATES=%s\n' "${#files[@]}"
count=0
for path in "${files[@]}"; do
  ((count+=1))
  printf 'CANDIDATE_%02d_PATH=%q\n' "$count" "$path"
  printf 'CANDIDATE_%02d_BYTES=%s\n' "$count" "$(stat -c '%s' -- "$path")"
  printf 'CANDIDATE_%02d_MODIFIED=%s\n' "$count" "$(date -r "$path" '+%Y-%m-%d %H:%M:%S %z')"
  printf 'CANDIDATE_%02d_SHA256=%s\n' "$count" "$(sha256sum "$path" | awk '{print $1}')"
  if (( count == 60 )); then
    printf 'SIFI_ROADMAP_DISCOVERY=TRUNCATED_AT_60_CANDIDATES\n'
    exit 0
  fi
done

if (( count == 0 )); then
  printf 'SIFI_ROADMAP_DISCOVERY=HELD:NO_NAMED_CANDIDATES\n'
else
  printf 'SIFI_ROADMAP_DISCOVERY=PASS:PATHS_AND_HASHES_ONLY\n'
fi
printf 'NOTE=No file contents, API tokens, databases, or runtime state were accessed or uploaded.\n'
