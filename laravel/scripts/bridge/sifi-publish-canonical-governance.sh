#!/usr/bin/env bash
# Publish exactly two SIFI governance documents from the owner's D: drive to
# a NEW private GitHub candidate branch and draft PR. No live Laravel writes.
set -euo pipefail

ROOT='/mnt/d/Research/SIFI'
AGENTS="$ROOT/AGENTS.md"
ADDENDUM="$ROOT/SIFI_Local_First_Launcher_Roadmap_LaravelFirst_20261020/SIFI_Local_First_Launcher_and_Roadmap/docs/SIFI_ROADMAP_LARAVEL_FIRST_ADDENDUM_2026-10-20.md"
VALIDATION="$ROOT/05_VALIDATION"
REPO='fajarnurmanto-source/strategic-issue-foresight-intelligence'
SOURCE_BRANCH='sync/sifi-laravel-vnext-ih03j-20261108_134500'
REMOTE="https://github.com/$REPO.git"

fail() { printf 'SIFI_CANONICAL_GOVERNANCE_SYNC=HELD:%s\n' "$1" >&2; exit 1; }
[[ "${1:-}" == '--publish' ]] || fail 'RUN_WITH_--publish'
command -v git >/dev/null || fail 'GIT_MISSING'
command -v gh >/dev/null || fail 'GH_MISSING'
command -v sha256sum >/dev/null || fail 'SHA256SUM_MISSING'
[[ -d "$VALIDATION" ]] || fail 'VALIDATION_ROOT_MISSING'
[[ -f "$AGENTS" && ! -L "$AGENTS" ]] || fail 'ROOT_AGENTS_NOT_FOUND'
[[ -f "$ADDENDUM" && ! -L "$ADDENDUM" ]] || fail 'LARAVEL_ADDENDUM_NOT_FOUND'

# Fail closed if the connected repository is not private, or GitHub login has
# expired. No file contents are printed when these checks fail.
private="$(gh repo view "$REPO" --json isPrivate --jq '.isPrivate')" || fail 'GITHUB_REPO_CHECK_FAILED'
[[ "$private" == true ]] || fail 'REPOSITORY_NOT_PRIVATE'

for source in "$AGENTS" "$ADDENDUM"; do
  resolved="$(realpath -- "$source")" || fail 'SOURCE_REALPATH_FAILED'
  [[ "$resolved" == "$ROOT/"* ]] || fail 'SOURCE_OUTSIDE_PROJECT_ROOT'
  [[ "$(stat -c '%s' -- "$source")" -le 1048576 ]] || fail 'GOVERNANCE_DOCUMENT_OVER_1MB'
  if command -v file >/dev/null && [[ "$(file -b --mime-encoding -- "$source")" == binary ]]; then
    fail 'BINARY_GOVERNANCE_DOCUMENT'
  fi
  # Reject high-confidence secret/token patterns; never echo matched contents.
  if LC_ALL=C grep -Eiq \
    -e 'gh[pousr]_[A-Za-z0-9]{25,}' \
    -e 'github_pat_[A-Za-z0-9_]{30,}' \
    -e 'sk-[A-Za-z0-9_-]{32,}' \
    -e 'AIza[0-9A-Za-z_-]{30,}' \
    -e '-----BEGIN (OPENSSH|RSA|EC|DSA|PRIVATE) KEY-----' \
    -- "$source"; then
    fail 'POSSIBLE_SECRET_IN_GOVERNANCE_DOCUMENT'
  fi
done

STAMP="$(date +%Y%m%d_%H%M%S)"
BRANCH="context/sifi-canonical-governance-$STAMP"
STAGE="$(mktemp -d "$VALIDATION/SIFI_CANONICAL_GOVERNANCE_XXXXXXXX")"
git clone --quiet --single-branch --branch "$SOURCE_BRANCH" "$REMOTE" "$STAGE/repo" || fail 'PRIVATE_SOURCE_CLONE_FAILED'
git -C "$STAGE/repo" switch --quiet -c "$BRANCH" || fail 'CONTEXT_BRANCH_CREATE_FAILED'
mkdir -p -- "$STAGE/repo/docs/canonical"
cp -- "$AGENTS" "$STAGE/repo/docs/canonical/SIFI_AGENTS_LOCAL.md"
cp -- "$ADDENDUM" "$STAGE/repo/docs/canonical/SIFI_ROADMAP_LARAVEL_FIRST_ADDENDUM_2026-10-20.md"

git -C "$STAGE/repo" config user.name 'fajarnurmanto-source'
git -C "$STAGE/repo" config user.email '334013547+fajarnurmanto-source@users.noreply.github.com'
git -C "$STAGE/repo" add -- docs/canonical/SIFI_AGENTS_LOCAL.md docs/canonical/SIFI_ROADMAP_LARAVEL_FIRST_ADDENDUM_2026-10-20.md
git -C "$STAGE/repo" commit --quiet -m 'docs: propose canonical SIFI governance and Laravel-first roadmap addendum' || fail 'CONTEXT_COMMIT_FAILED'
git -C "$STAGE/repo" push --quiet --set-upstream origin "HEAD:refs/heads/$BRANCH" || fail 'CONTEXT_PUSH_FAILED'

if ! pr_url="$(gh pr create --repo "$REPO" --base "$SOURCE_BRANCH" --head "$BRANCH" \
  --draft \
  --title 'SIFI: reconcile canonical local governance and Laravel-first roadmap' \
  --body 'Owner-provided D:\Research\SIFI\AGENTS.md and Laravel-first roadmap addendum, staged without modifying live WSL Laravel. Inspect documents before merging. This addendum alone may not contain the complete R0–R11 / Stage 0–6 master roadmap. Production and real-evidence admission remain DENIED.')"; then
  printf 'SIFI_CANONICAL_GOVERNANCE_SYNC=BRANCH_PUSHED_PR_CREATION_HELD\n' >&2
  printf 'BRANCH=%s\n' "$BRANCH"
  exit 1
fi

printf 'SIFI_CANONICAL_GOVERNANCE_SYNC=PASS\n'
printf 'AGENTS_SHA256=%s\n' "$(sha256sum "$AGENTS" | awk '{print $1}')"
printf 'ADDENDUM_SHA256=%s\n' "$(sha256sum "$ADDENDUM" | awk '{print $1}')"
printf 'BRANCH=%s\n' "$BRANCH"
printf 'PULL_REQUEST=%s\n' "$pr_url"
printf 'STAGING=%s\n' "$STAGE"
printf 'LIVE_APPLICATION_WRITES=0\n'
printf 'PRODUCTION_ADMISSION=DENIED\n'
