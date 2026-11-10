# SIFI — Strategic Issue & Foresight Intelligence

SIFI is a modular Laravel intelligence application for lawful multi-source sensing, issue discovery, investigation, evidence review and executive briefing.

## Canonical product layout (integration candidate, 9 November 2026)

- **Project root:** `D:\Research\SIFI` on Windows.
- **One Laravel source:** `D:\Research\SIFI\laravel` (the `laravel/` subtree in this repository).
- **Local execution:** Ubuntu 24.04 WSL, currently `/home/fajar/projects/sifi-laravel-vnext`, serving `http://127.0.0.1:8821`. This WSL folder is a working runtime copy, **not** a second authoritative application.
- **Historical research and validation:** `00_CONTROL`, `01_STAGE0_FOUNDATION`, `02_BENCHMARKS`, `03_STAGE0_FREEZE`, `04_IMPLEMENTATION`, `05_VALIDATION`; these remain preserved.
- **Legacy Python prototype:** the historical `src/` and associated launcher documentation are **not** the current Laravel product. Do not use the old `8765` server to validate the Laravel runtime.

The canonical Laravel tree is imported without rewriting or forcing an unrelated Git history. Read [canonical architecture and controlled-promotion policy](docs/SIFI_CANONICAL_MODULAR_LARAVEL_20261109.md).

### Nonproduction acceptance

On the owner's Windows/WSL workstation, after this integration branch has been reviewed and checked out at the project root:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File D:\Research\SIFI\scripts\Verify-SifiCanonical.ps1
wsl.exe -d Ubuntu-24.04 -e bash /mnt/d/Research/SIFI/scripts/Compare-SifiCanonicalRuntime.sh
```

The first command checks topology/routes; the second compares all tracked canonical Laravel source blobs against the running WSL checkout, read-only. Local Laravel feature tests, frontend checks and actual UI acceptance are additionally required.

**Current status:** canonical source integration prepared on a review branch; local runtime installation and product acceptance **not yet proven**. Production admission remains denied. Source Center, provider API keys, paid services and social OAuth are deferred until the canonical Laravel runtime is reconciled.

### Non-negotiable interpretation

Social attention and publication volume are not empirical prevalence or confirmed impact. SIFI retains provenance, rights, coverage limitations, reviewable uncertainty, and approval gates for material decisions.

The original v0/Stage 0 baseline and operator guide remain in Git history for auditability; they do not define the current Laravel application.
