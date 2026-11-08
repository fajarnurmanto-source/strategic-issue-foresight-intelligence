# SIFI Master Product Roadmap v1.0 — Laravel-First Execution Addendum
**Date:** 20 October 2026  
**Basis:** `SIFI MASTER PRODUCT ROADMAP v1.0` (17 October 2026); user-confirmed AQ01 Increment 1–7 local prototype.  
**Classification:** Noncanonical engineering proposal / additive execution overlay. **Does not supersede or amend the frozen canonical roadmap or any CB22 artefact.**

## Executive decision
**Use Laravel for routine product capabilities; preserve Python for Strategic Issue & Foresight Intelligence computation and the existing protected evidence/Issue contracts.** React/Inertia remains the primary analyst experience. Use additional third-party packages only with demonstrable benefit, compatible version/license, security review, and regression tests. Do not install packages just to check a box.

The user asked for a single-click offline launcher and a roadmap that avoids rebuilding framework features. This addendum documents how those requests fit the canonical product scope without advancing any governance gate.

### Provenance and nonpromotion
- Canonical baseline in the roadmap: `D:\Research\SIFI_GITHUB_CANDIDATE`, `main`, frozen recorded HEAD `6ce5dd982833e01d405ce806df9949cfb941c0cc`. **This is a dated document claim, not a freshly reverified current HEAD**.
- SIFI AQ01 Increment 1–7: isolated WSL local prototype with Laravel 13 + React/Inertia and Python AQ01 fixture advisory, user-reported UI acceptance for Increment 6 and shown Increment 7 snapshot. This development lane **must not** rewrite canonical `main`, frozen source-rights contract, or counted CB22 protocol.
- Master roadmap next formal gate remains **R1 CB22 Real-Governance Readiness**, followed by R2 counted user validation; R3–R11 remain future/blocked as documented, regardless of local launcher or framework reuse.
- `OFFLINE_FIXTURE_ONLY`, `NOT_EVALUATED`, `NOT_VERIFIED`, `AI MAY PROPOSE, HUMAN AUTHORITY DISPOSES`, and evidence/rights fail-closed remain mandatory.

## Architecture ownership
| Capability type | System of record / executor | Framework reuse | Important boundary |
|---|---|---|---|
| User, login, personal preferences, team/tenant | Laravel | Fortify/starter, Eloquent, Policies | Authenticated ≠ review/rights/legal authority. |
| Case workflow, analyst notes, candidate drafts, UI audit | Laravel | Eloquent, Form Requests, migrations, transactions, events, tests | Draft/review request ≠ qualified evidence or production Issue Object. |
| Local runtime start/stop and desktop shortcut | Windows PowerShell + WSL Bash | Native process control, not a Laravel plugin | Laravel cannot start itself before the server exists. Only loopback ports 8000/8766; no admin/autostart. |
| Source configuration, catalog metadata, cadence, health UI | Laravel | Eloquent Resource Controllers, Policies, Notifications | Candidate source ≠ admitted source; runtime rights matrix controls eligibility. |
| Lawful acquisition/parser, checksumming and deduplication | Python | Existing SIFI adapter and secure URL checks | Runtime collector OFF until lawful source pack and authorized R3. |
| Rights/evidence admission, point-in-time rules | Existing Python SIFI evidence kernel; human authority | Laravel can present decisions and track signoffs | No duplicate business-truth engine in PHP; Z1 must not auto-promote to Z2. |
| Claim/event/issue identity, origin independence, evidence linkage | Python SIFI domain contracts | Laravel can store read-only projections/external identifiers | Publication count ≠ independent origin count; no new ad-hoc Issue Object. |
| NLP, entity/claim extraction, trajectory, risk/foresight hypotheses | Python | Existing model/analysis packages when justified | Every inference explicitly uncertain and reviewable. |
| Analyst UI, issue and foresight workspace | React/Inertia | Current Laravel integration, Tailwind/component reuse | No forked UI framework needed. |
| Integration with PI/II and external clients | Versioned SIFI APIs | Laravel Routes, Resources, HTTP Client, Sanctum if justified | One authoritative model per object; no silent cross-system rights leak. |

## Function-by-function reuse matrix (proposed implementation, not claimed installed)
| Product objective from master roadmap | Reuse native Laravel now / at gate | Optional mature package or infrastructure | Keep custom / Python | Timing/gate |
|---|---|---|---|---|
| S0 product contracts and canonical vocabulary | Config, PHP enums, validation, route model binding, migrations, typed DTO boundaries | None | Preserve frozen contracts and Python schema | Now for future additions; don't rewrite predecessor |
| S0.3 Source Universe, source rights, coverage and access register | Eloquent source/source-pack tables, Form Requests, Policies; change history | **Filament** only for back-office CRUD after version compatibility & UX pilot | Source-rights qualifiers and lawful-access semantics stay in Python/contracts | Prototype config now; acquisition authorization R3 |
| S0.3R/S geography/Coverage Profile | Eloquent relations/scopes, validations, DB indexes | Spatial DB/GIS library only at proven scale | Separate source/event/affected/policy/relevance geographies; explainable R0–R4/RX relevance engine | Schema prototype now; prove multi-geography later |
| S0.5 canonical Evidence Model and Claim/Event/Issue | Laravel views, resources, migrations for **projection only** | None | Python maintains qualification, independence, PIT and protected issue identities | Never bypass qualification |
| S0.5 Human Review Authority and S1.5 | Gates/Policies, Eloquent revision ledger, database transactions, notification events | Spatie Permission **only** if multi-role complexity merits it | Python gate/adjudication and authority semantics; no reviewer shortcut | R1/R2 governance, reviewed release |
| S0.6 benchmarks and regression | Laravel/Pest/PHPUnit feature testing, HTTP::fake, event/queue fakes, parallel tests | Playwright E2E only if selected | Python deterministic benchmarks/frozen fixtures; CB22 counted study separate | Continuous nonproduction CI |
| S1.1 domain persistence / snapshots | Eloquent, migrations, transactions, DB-backed job tracking | None | Existing canonical Python snapshots and append-only contracts | Use projections; avoid database ownership collision |
| S1.2 Rights & Evidence Kernel | Laravel back-office display and approval forms | None | Python rules for Z0–Z3, PIT, retention, derived-output constraints | Do not migrate logic wholesale into PHP |
| S1.3 Claim/Evidence/Event; S1.4 issue identity | Laravel IssueCandidate and investigation projection / Form Requests | Scout only after corpus/query benchmark | Python extraction, event identity, dedupe, contradiction, lifecycle | Post-R3 meaningful evidence; metadata fixture before |
| S1.7 orchestration, resumability, recovery | **Queues, Jobs, Batches**, Scheduler, Events; Laravel storage/logging/notifications | Horizon only if Redis/queue scale justifies; Pulse/Telescope for dev visibility | Python workers for compute, securely contracted over loopback/API | Queue/schedule activation constrained by R3; local manual now |
| S1.7 operator web/app UX | React/Inertia + Laravel Policies + reusable UI components | Filament for admin-only (not analyst intelligence canvas) | Domain-specific interactive issue/foresight visualizations | Now, targeted usability fixes |
| S1.8 CB22 rehearsal/validation | Authentication, immutable audit UI, secure review routes | None | Keep frozen protocol, role separation, consent handling, exact bundle two-role votes in existing validated subsystem | **R1 first**, R2 counted only afterward |
| Stage 1 periodic research/collector health | Laravel Scheduler, Queues, Notifications, cache, HTTP Client | Horizon/Pulse optional post-operational load | Python adapters, source rights checks, rate limits, retries, metadata retention | R3, **not enabled today** |
| Stage 2 Issue Intelligence | Laravel-owned caseboard, APIs, visualization, access | Scout optional on measured query volume | Python clustering, explainable relevance ladder, issue trajectory | Build controlled fixture UX, real-data claims R5 |
| Stage 3 Evidence Intelligence | Laravel review queue, reviewer assignment, record audit, corrections | None | Python evidentiary thresholds, counterevidence, PIT replay | R5 and protected review |
| Stage 4 Foresight | Laravel editable hypothesis/watch-condition records and notifications | None | Python scenarios, horizons, falsification, uncertainty, validation | No free-form prediction presented as certainty |
| Stage 5/6 buyer/pilot/productization | Notifications, API Resources, rate limiting, logging, maintenance CLI, policies | **Sanctum** for token clients, **Cashier** only if billing business case exists | SIFI-specific pricing/buyer evidence, product governance | R8–R10, not now |
| R4/R7 operations & security | Logging, health endpoint, migration, backup jobs, request throttling, auth policies | Pulse/Horizon/Telescope under measured need | Restore rehearsals, incident/change controls, explicit production approval | Technical prerequisite ≠ authority |
| R6 L6 evaluation | Form Requests, review workflow, immutable report/export API | None | Preregistered baseline comparison, missing-issue false escalation analysis, falsification; independent review | Do not equate UI acceptance with L6 |
| R11 commercialization readiness | Traceability, package/version metadata, controlled release workflow | None | Legal/institutional/commercial evidence bundle + formal human disposition | Future gate only |

## Avoid unnecessary packages (default policy)
1. Already in Laravel 13: authentication with existing starter/Fortify, authorization Policies/Gates, Eloquent, migrations, DB transactions, Form Requests, HTTP Client, queues, scheduler, notifications, events, testing, route throttling. **Prefer these**.
2. Candidate optional packages: Filament (source administration), Laravel Scout (search), Sanctum (API clients), Horizon (Redis queue ops), Pulse/Telescope (ops/dev monitoring), Spatie Permission (complex RBAC). Install only when requirements and dependencies are checked; avoid overlapping responsibility.
3. Do not add Redis, Elasticsearch/Meilisearch, Filament, Electron/Tauri or another AI inference provider merely to make a prototype appear advanced.
4. No new package should silently overwrite Composer lock, force-update core framework, add external telemetry, or activate internet-dependent behavior without a reviewed request.
5. Outstanding observed npm audit note from initial starter install: **3 critical vulnerabilities** were reported at creation time; current status not reverified. Prioritize dependency assessment before any distribution or production, not `npm audit fix --force`.

## Priority-ordered delivery: fast product value while preserving gate order
| Lane | Next deliverable | What to reuse | Test/exit, no overclaim |
|---|---|---|---|
| A0 - Immediate local usability | SIFI Windows local one-click launcher + status/stop; no background auto-run | Windows shortcuts, WSL services | User starts offline without manual shells; localhost 8000/8766; scoped stop; no network |
| A1 - Protected current work | Stabilize Laravel/Python bridge: executable integration, stale/revision diagnostics, service health panel | HTTP Client, Policies, Form Requests, PHPUnit | Increment 1–7 regressions PASS; no verified-evidence promotion |
| A2 - Parallel **formal** gate, not optional | Complete R1 CB22 **real** role/ethics/consent determinations with appointed humans; only then R2 sessions | Existing frozen CB22 machinery; Laravel UI if strictly needed | No counted trial until governance; no automatic pass |
| A3 - Product efficiency slice | Source registry admin, Coverage Profile editing, configurable UI, reviewer assignment mock (no admission) | Eloquent, Policies, Notifications; Filament evaluated for admin | Test CRUD/permission; all ingestion OFF |
| A4 - Downstream (gated) | Lawful source packs + periodic data acquisition + telemetry | Scheduler, Queues, Events, HTTP Client | R3 source rights/admission/operator controls; narrow live allowlist |
| A5 - Intelligence core | Qualified real evidence → trustworthy Issue/Trajectory/Foresight | Laravel workflows + existing Python domain contracts | R5 and L6 evidence; keep contradictions, provenance, abstention |
| A6 - Commercial/deployment | Productized installation (local/on-prem/cloud), multi-tenant rights, billing only where needed | Laravel auth/policies, Storage, Sanctum optional | R7–R11 explicit authorization and commercial evidence |

## Working rules for future increments
1. `CURRENT STATE -> FRAMEWORK OVERLAP AUDIT -> EXISTING CONTRACT REUSE -> MINIMUM EXTENSION -> TEST -> REGRESSION -> HUMAN GATE WHEN REQUIRED`. This refines, but does not delete, the roadmap's original successor method.
2. Every new ticket must specify: **Capability**, **Laravel native**, **Optional package (why)**, **Python/contracts**, **data owner**, **rights/authority status**, **acceptance test**, **rollback**, and **which R gate it may/may not satisfy**.
3. Laravel and Python may share **immutable IDs and versioned schemas**, not dual-writable authoritative objects. Laravel `IssueCandidate` is **not** a Python `Issue Object`.
4. Laravel host, AQ01 Python API, and SQlite remain `127.0.0.1` / local fixture-only until an explicitly authorized step. **No scheduled live collection**.
5. Future package upgrades must first verify `composer.lock`, `package-lock.json`, license/security, framework version, and PHP runtime before touching app.
6. Every installer needs targeted backups, preflight hash/versions, migration snapshots, immutable safety guards, tests, and an abort-on-failure path; no silent Git commit/push.
7. User confirmation of a working screen is UI acceptance in that scope, not certified L5/L6 or production authority.

## Technology documentation references (verified 20 October 2026)
- Laravel 13.x Authorization Policies: https://laravel.com/framework/docs/13.x/authorization
- Laravel 13.x HTTP Client and test fakes: https://laravel.com/framework/docs/13.x/http-client
- Laravel 13.x Framework overview / queues and scheduled tasks: https://laravel.com/framework/docs
- Laravel 13.x Testing: https://laravel.com/framework/docs/13.x/testing

### Successor roadmap decision
**Preserve Master Product Roadmap v1.0 as canonical**. Adopt this **noncanonical execution addendum** for developer task decomposition and future Laravel-first increments. Promotion to canonical planning requires normal change-control authority and regression evidence, not unilateral modification by the assistant.
