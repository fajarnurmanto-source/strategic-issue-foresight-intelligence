# SIFI — Canonical Modular Laravel Architecture

**Decision date:** 2026-11-09 (Asia/Jakarta)  
**Scope:** local development and review; **production admission denied**.  
**Source Center:** explicitly deferred to a later, independently reviewed integration.

## One application, two clearly separated roles

| Role | Location | Policy |
|---|---|---|
| Project root, governance and single reviewed source | `D:\Research\SIFI` | This repository is the canonical project root. |
| Canonical Laravel source tree | `D:\Research\SIFI\laravel` | One app, one Composer project, one routes tree, one migrations tree, one frontend. |
| Active local Laravel runtime | `/home/fajar/projects/sifi-laravel-vnext` (Ubuntu-24.04 WSL) | A deployed working copy of the canonical source, not a separate independent product. |
| Historical validation, snapshots, rollback material | `D:\Research\SIFI\05_VALIDATION` and existing historical folders | Preserve as evidence/archives; never launch or merge them as another production. |
| Old Python/local-v0 runtime and guides | `src`, historical `scripts`, `LOCAL_OPERATOR_GUIDE.md` | Historical baseline only. Do not use the legacy port 8765 launcher to assess Laravel on 8821. |

The `laravel/` tree was introduced by linking the already-reviewed Git tree
`f8b0bf682246d941cdb9837dd72ead360bbb20ec`, originally from commit
`beafab132cdadafb3a8e77e3034fb9b8d82bc947` on branch
`sync/sifi-laravel-vnext-ih03j-20261108_134500`. No Laravel sources were
retyped or rewritten during this structural integration. The historical `main`
and this WSL source snapshot have unrelated Git histories; the subtree
approach preserves both without a destructive or forced merge.

## Laravel modularity contract

The following are *modules within one Laravel application*, not separately
launched backends:

1. **Object and monitoring scope:** owned monitoring objects, keywords, revisions, period windows.
2. **Source and discovery:** registry, provider capabilities, collection permissions, source provenance and research bridges.
3. **Signal intelligence:** normalization, deduplication, relations, credibility and issue candidates.
4. **Investigation and evidence:** investigations, issue cases, claim/event links, evidence intake and review.
5. **Forecast and reporting:** trajectory, foresight, executive brief and revision trail.
6. **Common control plane:** Laravel authentication, permissions, configuration, queue, database migrations, audit and frontend shell.

Existing providers, controllers, services, routes and Blade/Inertia assets remain
reusable; no second login/database/object registry is authorized. The internal
`packages/decision-intelligence` Composer path package remains a module inside
the Laravel project, not a separate server. Future changes must introduce
capabilities within these contracts rather than create parallel standalone apps.

## Promotion rules

1. **Source integrity first.** Review the GitHub integration branch, target
   repository commit and source snapshot SHA. Do not force-merge the orphan
   source branch into historical `main`.
2. **Inspect live WSL before copying.** Confirm `php8.3` serves
   `127.0.0.1:8821` from the expected `public` folder, inventory the live Git
   state and compare its tracked application files with the new canonical tree.
3. **No silent overwrite.** Differences in live application code require a
   three-way review or a reviewed transfer plan. Never overwrite `.env`,
   credentials, user uploads, SQLite/WAL, sessions, logs, caches or backups.
4. **Reversible local promotion only.** Save reviewed file-level backups,
   apply a bounded source delta, perform focused Laravel and frontend checks,
   verify modules and existing routes, and retain rollback receipts. A GitHub
   PR or successful tree import does not prove the runtime is installed.
5. **No destructive cleanup by implication.** Historical folders may be
   reclassified as archives; deletion requires an exact-file census and a
   separate approval.
6. **Only then integrate Source Center.** It is currently a separate draft PR
   whose source paths will need to be rebased under `laravel/` and reconciled
   against the verified canonical runtime. Social-account OAuth, paid-provider
   credentials and production ingestion remain separate approval surfaces.

## Read-only workstation verification

From PowerShell on the owner's laptop, after the canonical branch/source is
available at the project root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File D:\Research\SIFI\scripts\Verify-SifiCanonical.ps1
```

This command checks the Windows source topology, the WSL server's bound
working directory, PHP runtime, Laravel route boot and core SIFI named routes.
It does not modify data or perform network-provider requests. Its success
marker is **`SIFI_CANONICAL_PREFLIGHT=PASS_TOPOLOGY_ONLY`**; it is **not**
the integration acceptance result.

## Conditions for completed local integration

- [ ] The repository's canonical `laravel/` source is committed and reviewed.
- [ ] Live WSL file-level correspondence or reviewed delta and backup is recorded.
- [ ] Authentication, monitoring objects, sources, investigation, issue cases,
      evidence reviews and brief routes pass local feature/smoke checks.
- [ ] Laravel tests and relevant TypeScript/build checks pass on the actual WSL runtime.
- [ ] Local desktop/mobile application journey is accepted; no critical regression.
- [ ] Runtime release receipt identifies source SHA, runtime path, backup and
      test results. No claim of live success without this receipt.

Until every item passes, the truthful status is
**`CANONICAL_SOURCE_INTEGRATED; LOCAL_RUNTIME_ACCEPTANCE_PENDING`**.
