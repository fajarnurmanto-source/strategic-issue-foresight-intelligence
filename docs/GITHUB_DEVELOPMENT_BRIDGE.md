# SIFI Laravel vNext — GitHub Development Bridge

Date: 2026-11-08 (Asia/Jakarta)
Repository: fajarnurmanto-source/strategic-issue-foresight-intelligence
Source branch: sync/sifi-laravel-vnext-ih03j-20261108_134500

## Source snapshot received

- Owner's WSL source: `/home/fajar/projects/sifi-laravel-vnext`.
- Owner's Git baseline: `2335871` (2026-10-21).
- Owner-published source snapshot: `9cc232acbc3da4c412bd523180d9eeea315df765` (519 paths reported).
- ChatGPT verified directly on GitHub: IH03J controller, routes, config, React multi-source UI, feature test, Composer manifest and research service are present.
- Source upload: PASS. Source parity/complete operational reproduction: NOT YET CLAIMED.

## Branch strategy

- The existing GitHub `main` is an older Stage 0 lineage; do not force-push or merge unrelated histories.
- Treat this new branch as a **candidate** Laravel vNext development baseline until the source and local install mapping are agreed.
- Keep the Stage 0 branch available for archival.
- GitHub is the exchange and versioning medium. A GitHub commit does not automatically install changes in the actively running WSL Laravel checkout.

## Next local bridge

1. Pull only this source branch into the isolated source-snapshot checkout under `D:\Research\SIFI\05_VALIDATION\SIFI_GITHUB_SOURCE_SYNC_20261108_134500`.
2. Future ChatGPT changes on the source branch should have explicit changed-file manifests and expected parent hashes.
3. Local delivery must check modified-file drift against active WSL checkout, preserve existing edits, and make recoverable backups before installation.
4. Use fast-forward Git operations; no broad `git add -A` in the live checkout, hard reset, forced merge, or production deployment.
5. Verify a small local build/targeted test after installation; `PRODUCTION_ADMISSION=DENIED`.

## GitHub bridge acceptance

- Synthetic local -> GitHub -> ChatGPT -> GitHub -> local transport: PASS.
- Current Laravel source -> GitHub private branch: PASS.
- GitHub source branch -> isolated local checkout: PASS (`bdc6507` fast-forward observed, 2026-11-08).
- GitHub source branch -> active WSL Laravel: bridge script committed (`scripts/bridge/sifi-apply-source-delta.sh`); **owner's first docs-only run still pending**.
- GitHub code modifications -> active Laravel WSL checkout: pending post-smoke acceptance.

No secrets, operational DBs, real evidence, or credentials belong in the GitHub source branch.

## Apply bridge operating modes

- `--plan` (default) examines only GitHub commits since the last local bridge state; no changes to live code.
- `--docs-only` is the **first-run acceptance**: installs documentation only, refuses pending application-code diffs. Requires no server restart.
- `--apply` installs subsequent allowlisted source changes with exact-content or three-way-merge detection, local-original backups, and no deletion support. A conflict stops before application.
- The bridge reads the independent GitHub snapshot but never runs `git pull` or `git reset` in the active WSL Laravel checkout.
- Use the source snapshot's `scripts/bridge/sifi-apply-source-delta.sh` as the entry point. A successful first docs-only run should report `SIFI_GITHUB_TO_LIVE=PASS`, `MODE=--docs-only`, and `PRODUCTION_ADMISSION=DENIED`.
