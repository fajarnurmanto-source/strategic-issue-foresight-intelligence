# SIFI — Mandatory Alignment Before Integration

**Date:** 7 November 2026 (Asia/Jakarta)  
**Decision:** `INTEGRATION_HOLD / REBASE_REQUIRED`  
**Applies to:** SIFI Laravel vNext, Core V2, Superadmin Integration Hub, provider connectors and external-source intelligence pipeline.

## 1. Authority and evidence

### Canonical local sources to verify before writing

1. `D:\Research\SIFI\AGENTS.md` — standing owner authorization, staging-before-install, reproducible QA, release and recovery gate.
2. `D:\Research\SIFI\00_CONTROL\SIFI_CBM01_EBPD01_MANDATE_20261106.md` — mandatory CBM-01 and EBPD-01 requirements, effective 6 November 2026.
3. `/home/fajar/projects/sifi-laravel-vnext` — canonical *current* Laravel checkout; inspect its actual `AGENTS.md` and local implementation contracts before editing.
4. `D:\Research\SIFI\05_VALIDATION\SIFI_CORE_V2_AUTOMATED_ANALYST_QA\Run-Gate.ps1` — reviewed Core V2 gate; bind successor candidate without silently resetting the reviewed baseline.

The document contents in items 1–2 were recovered from the owner's uploaded PowerShell logs, not read from the live Windows disk. The exact current bytes and hashes therefore remain **UNVERIFIED** until a tool acting on the owner machine confirms them. Historical GitHub is not an adequate substitute: the connected `fajarnurmanto-source/strategic-issue-foresight-intelligence` repository was last pushed in October 2026, while Laravel vNext has changes dated November 2026.

### Standing owner authorization

| Boundary | Status |
|---|---|
| Local development, safe reversible installation, automated testing | PREAUTHORIZED, within reviewed scope |
| SIFI Core V2 | `SHADOW_READ_ONLY` |
| Real evidence admission | `DENIED` |
| Production admission, public deployment, paid services | `DENIED` without separate authorization |
| Destructive operations or risky remote changes | Separate approval required |
| Owner-driven manual testing | Only at `READY_FOR_PRODUCT_ACCEPTANCE_TESTING`, not after each micro-step |

## 2. Mandatory product outcome

SIFI is **an autonomous, lawful, multi-source issue and media intelligence platform**, not an AI-credential settings page.

Its core journey must autonomously discover, acquire where permitted, normalize, deduplicate, cluster, score and analyze publications/posts, retaining source and rights traceability. Human work focuses on exceptions and material protected decisions, rather than checking every publication. AI can suggest extraction, classification, clustering, summaries, explanatory hypotheses and probabilistic watch conditions, but **cannot establish empirical truth, admit real evidence, or take protected institutional decisions**.

Required source universe, subject to each source's rights and availability: news; official/government; regulations; statistics/open data; research; searchable public documents/datasets; lawful social platforms; and approved search providers.

Required intelligence outputs:

- Comparative media/image or public-perception analysis for people and organizations, with sample/frame caveats.
- Source-backed comparison of *reported or declared* assets/net worth, never inferred private holdings.
- Issue origin and propagation mapping across platforms; independent informational origins separated from reposts and amplifiers.
- Engagement-normalized volume, velocity and acceleration, with coverage and sampling caveats.
- Amplifier, elevator, bridge, trigger and dampener **hypotheses** and counterevidence.
- Credibility and claim-confidence decomposed into source reliability, corroboration independence, primary evidence, contradiction, observability and manipulation risk.
- Probabilistic trajectories, impact scenarios, watch conditions and snapshot executive reporting; never false-certainty forecasts.

**Non-negotiable distinctions:** publication volume ≠ independent origins; social attention ≠ real-world event frequency ≠ prevalence ≠ impact severity; human review ≠ empirical validation; source rights authority ≠ reviewer authority ≠ institutional decision authority.

## 3. CBM-01 / EBPD-01 compliance matrix

| Mandatory obligation | Prior direct AI bridge | Required correction |
|---|---|---|
| Preserve Core V2 investigation, provenance, evidence workflows | Did not integrate with these consumers | Bind adapters only through documented existing Core V2 contracts; additive shadow outputs |
| Competitive benchmarking and selective integration | No measured feature benchmark | Compare user journeys and outcomes against mature tools; adopt only documented high-value features |
| Laravel/OSS reuse; no duplicate runtime | Reused `UserAiSettings`, but duplicated credentials into a second owner-scoped store | One canonical connector registry and policy; reuse AI transport, discovery, retrieval, normalization and audit implementations |
| Modular/removable connectors | No disable/unregister/revoke lifecycle in bridge | Provider adapter contracts; enable, suspend, revoke, retire, remove without orphaned secrets |
| Separate Superadmin Integration Hub | UI present | Keep an isolated **panel/role**, not a permanently separate application or database; global configuration distinct from per-user preferences |
| Configurable AI and lawful data providers | Only Gemini/Groq/Ollama draft import | Unify AI, search, RSS/news, official, regulatory, statistical, research and lawful social source descriptors |
| Quota, cost, licensing, security, audit | Import ignored configured rate limit and rights | Fail-closed rights, rate/token/budget ledger and explicit zero-spend policy before any dispatch |
| Real-data-first | No acquisition consumer | Use lawful public real streams in disposable/shadow mode early; preserve provenance and rights gates |
| Automated acceptance and visual baseline | Tests ran after active source edit | Validate in disposable stage **before** canonical install; reuse Core V2 release gate and reviewed visual baselines |
| Source/database integrity and rollback | File backup, but not staged reversible promotion | Immutable before/after SHA, diff scope, rollback drill, operational DB/WAL untouched |
| Capability/usability/reliability/commercial viability | Single AI settings linkage | Measure end-to-end operator value and cost, errors, coverage, recovery and decision usefulness |

**Decision:** `SIFI_CBM01_DIRECT_AI_BRIDGE_Install.py` is withdrawn. It must not run. It was not installed by this ChatGPT session; the owner machine has not been altered by this alignment work.

## 4. Target integration architecture

1. **Superadmin Control Plane** (existing Filament panel): source/provider registry, ownership and geography/coverage profiles, rights/license review, encrypted secret references, quota/rate/cost policy, health, audit and circuit breakers. A connector is disabled until exact rights, scope and capability admission pass.
2. **Acquisition and Discovery Plane** (reuse existing Laravel SIFI services): scheduled or event-triggered discovery; lawful adapter retrieval, HTTP response constraints, immutable source snapshots, metadata/document/media acquisition without unauthorized bypass or scraping. Do not infer rights from an endpoint merely being reachable.
3. **Canonical Observation Plane** (Core V2): normalize with timestamp/source/content hash and revision; distinguish duplicate publication from separate independent origin; preserve contradictory observations without forced winners.
4. **Intelligence Plane**: cluster, credibility/claim confidence, spread dynamics, role hypotheses, comparative imaging, foresight; AI assists but does not write protected factual conclusions.
5. **Operator Plane** (existing SIFI UX): monitoring objects, source exploration, investigations, issue candidate, trajectory, foresight and Executive Brief. Dashboard indicates confidence, observability, coverage gaps, source rights and actionable watch conditions.

The Superadmin panel is a **control plane**, not a substitute for SIFI's existing analyst workflow. `UserAiSettings` remains a per-user preference mechanism where appropriate; it must not become the de facto global provider registry or a source of unaccounted credential duplication.

### Minimal provider interface (design contract, not an implemented API)

- `ConnectorDescriptor`: stable key/version, kind/capabilities, source domain/jurisdiction, supported geography/language, lawful scope/license/review expiry, authentication reference, rate/volume/budget, retention rules, owner and status.
- `DispatchContext`: actor/tenant, coverage profile, purpose, scope, privacy class, applicable rights, budget reservation, correlation ID and `SHADOW_READ_ONLY` admission state.
- `ConnectorObservationEnvelope`: canonical source and original URL, retrieval and publication timestamps separately, content checksum/revision, observed metrics with sampling method, origin clues, rights classification, provenance links, conflict flags and explicit `UNVERIFIED` evidence state.
- `ConnectorOutcome`: success/blocked/partial/unavailable codes, cost/quota accounting, audit ID, no automatic retry if permission was consumed, no paid fallback.

Adapters must never directly mutate final claim truth, real evidence admission, institutional action or unreviewed production state.

## 5. Selective commercial feature benchmark (official vendor descriptions)

| Benchmark | Observable commercial capability | SIFI selective adaptation |
|---|---|---|
| Brand24 | Cross-channel mentions, sentiment, influencer/reach metrics, trend alerts, share of voice and AI summaries | Source coverage monitor, mention spikes, source-aware sentiment and contributor hypotheses; keep independent-origin counts separate |
| Brandwatch Consumer Research | Topic/segment analysis, configurable alerts and visual/image analysis | Configurable topic/actor/campaign comparison and provenance-aware emerging issue alerts |
| Talkwalker | Text, image/video/speech listening and virality propagation visualization | Media format expansion and cross-platform propagation/bridge hypotheses where lawful data access exists |

These are **benchmarks for user value**, not authorization to copy proprietary code, collect unauthorized platform data, or assert that SIFI already matches commercial source coverage. Validate each chosen feature against measurable accuracy, latency, resource cost and operator usefulness before adoption.

Official references consulted 7 November 2026:

- https://brand24.com/features/media-monitoring/
- https://brand24.com/features/sentiment-analysis/
- https://www.brandwatch.com/products/consumer-research/features/
- https://www.talkwalker.com/social-intelligence-product
- https://www.talkwalker.com/social-media-listening

## 6. Prioritized product outcomes (one coherent integration batch, not micro-screen checkpoints)

| Priority | Product outcome | Completion evidence |
|---|---|---|
| P0 | Canonical contracts and registry reuse | Source map of actual services; no duplicate credentials/transport; revocable connector states |
| P0 | Lawful autonomous source flow | At least one permitted real-public stream through existing discovery → acquisition → normalized shadow observation, with rights and lineage intact |
| P0 | Identity, independence and credibility | Duplicate/reprint/origin test fixture; explicit coverage and contradiction; no synthetic evidence promoted |
| P0 | Global safety ledger | Quota/cost/license/SSRF/privacy/retry policy enforced at actual dispatch boundary; audit, fail-closed tests |
| P1 | Signal graph and temporal analytics | Spread graph, origin vs amplifier hypotheses, comparable metrics and watch conditions |
| P1 | Analyst experience | Existing SIFI workbench consumes new shadow observations; useful executive snapshot with provenance and unknowns |
| P1 | Superadmin operational UX | Manage/disable/revoke connectors, audit and health; no default Laravel widget or unreadable encoding |
| P2 | Comparative media-image and declared assets | Domain-specific evidence/coverage caveats and source-backed comparisons |
| P2 | Model-assisted foresight | Calibrated scenarios, uncertainty and forecast/watch verification separated from facts |

One batch may contain multiple safely separated commits, but the owner should receive a **coherent integrated capability**, not a request to manually verify each intermediate page.

## 7. Mandatory release sequence

1. Read and hash the live mandatory and `AGENTS.md`, actual Laravel repo HEAD/dirty state, current Core V2 gate, existing provider/ingestion and UX contracts. Stop on mismatch or permission barrier.
2. Establish feature baseline and explicit end-to-end operator journey. Record competitor feature/user-value rationale and capability KPIs (coverage and origin accuracy, latency, false-positive/false-truth safeguards, CPU/RAM, provider cost, accessibility).
3. Build the integrated candidate in a **disposable stage/worktree**, with synthetic fixtures and authorized lawful public shadow sources; never start by patching the active checkout.
4. Run focused unit/feature tests, cross-service regression, security rights/quota/SSRF tests, TypeScript build, Playwright desktop/mobile, axe-core, visual regression and the Core V2 successor release gate as applicable. Reuse fresh evidence only when scoped inputs are unchanged.
5. Investigate any current regressions. An uploaded 7 November full-regression log showed failures including SQLite `There is already an active transaction` in multisource tests; this is **historical evidence, not proof the latest build still fails**, but acceptance cannot presume resolution.
6. Only after successful stage gate, compare source hashes, preserve active database/WAL/authorization, prove recovery, and promote exactly the reviewed code scope. Do not migrate an operational DB or contact external paid services under this standing authorization.
7. Generate one installation/QA receipt with before/after hashes, test counts, cost observations, supported coverage, residual risks and rollback path. Do not claim L4/L5 without genuinely closed journeys and authorized user acceptance.

## 8. Explicitly excluded from this alignment step

- Applying the withdrawn AI bridge or modifying live WSL/GitHub source.
- Operational migrations, production rights, paid API calls, credential distribution or real-evidence admission.
- Claiming external connectors are `CONNECTED`, free-eligible, or lawful without their separate evidence.
- Rebuilding mature existing source/runtime services merely to satisfy a new dashboard.

## 9. Next executable action when a local-code agent is available

Perform a **read-only mandatory/source-contract census in one run**, collecting hashes and necessary file/route/scheme inventory into `05_VALIDATION`, then generate a staged unified implementation candidate from those actual contracts. The owner should not be asked for manual UI screenshots during development. Stop only for a genuine authorization conflict, missing canonical mandatory source, or concrete data-loss risk.

The companion `SIFI_MANDATORY_READONLY_CENSUS_20261107.py` implements this next read-only handoff. It does not install code or contact providers. Review the resulting ZIP for sensitive literals before sharing it with any development agent.

**Final decision:** `MANDATORY_ALIGNMENT_DOCUMENTED`, `DIRECT_AI_BRIDGE_WITHDRAWN`, `INTEGRATION_NOT_YET_EXECUTED`, `PRODUCTION_ADMISSION=DENIED`.