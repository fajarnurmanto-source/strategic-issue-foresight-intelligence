# SIFI — MASTER PRODUCT ROADMAP v1.2
## Mandatory successor: period-first universal acquisition, source classification, issue graph, and longitudinal trends

**Owner instruction:** 9 November 2026 (Asia/Jakarta)  
**Status:** OWNER-MANDATED PRODUCT REQUIREMENT — engineering implementation pending.  
**Repository target:** `fajarnurmanto-source/strategic-issue-foresight-intelligence` (private); branch-level adoption via reviewed GitHub PR.  
**Relationship:** This is an **additive successor** to [SIFI Master Product Roadmap v1.1 (8 Nov 2026)](https://chatgpt.com/library?file_id=libfile_415748d051548191b9e05f207004b849&account_id=personal), AE-12–AE-16 and frozen v1.0 R0–R11 gates. Do not silently rewrite the v1.0 historic baseline or count engineering work as R-gate admission.

## 1. Explicit interpretation of the owner's requirement

SIFI is a **source-neutral intelligence acquisition and extraction system**. It must be able to discover, lawfully retrieve, classify, normalize and extract information from available sources, identify the originating platform/publication, group information into **horizontal** and **vertical** issue relationships, and analyze the development of those issues **over time**.

**The only required input for the initial discovery mode is a user-selected time interval.** Users must be able to select `start_date` and `end_date`, then ask SIFI to scan *all currently eligible and configured source categories* within that interval. A keyword, brand, geographic area, named monitoring object, publisher or platform filter is **optional**, never a hard prerequisite for the first results. Optional filters refine or pivot the acquired data after discovery; they must not be secretly required to start.

This is **different** from a Google Trends connector or single-keyword search. Google is one optional discovery provider/sensor under lawful access; Google Trends RSS measures search attention, not full-text news discovery, and cannot substitute for the universal acquisition pipeline.

### Required operator journey

`SELECT TIME WINDOW → ENUMERATE ELIGIBLE SOURCES → DISCOVER/FETCH → CLASSIFY SOURCE/PLATFORM → EXTRACT INFORMATION → NORMALIZE/DEDUP → HORIZONTAL ISSUE CLUSTERS → VERTICAL ISSUE GRAPH → TIME-SERIES ANALYTICS → REVIEWABLE ISSUE WORKSPACE/BRIEF`

No manual review of every publication is required. Human review remains for exceptions, contested claims, important decisions, evidence admission and protected release gates.

## 2. Mandatory source-universe and acquisition behavior

Source types must be extensible and at least distinguish:

1. **News/media:** editorial web publications, RSS/Atom, licensed news feeds and permitted publisher archives.
2. **Social/platform:** public posts and interactions accessible under each platform's official interface, permissions and quotas.
3. **Government/official:** official releases, websites, public notices, ministry/local government and institutional publications.
4. **Regulation/legal:** regulatory databases, gazettes and official legal documents.
5. **Statistics/open data:** structured datasets, public dashboards, statistics offices and catalog APIs.
6. **Academic/research:** scholarly metadata, publications and properly licensed full texts.
7. **Forums/community:** lawful public discussion endpoints and community publications.
8. **Open web/document:** permitted public pages, sitemaps, document uploads and owner-provided files.
9. **Discovery/search providers:** lawful search indices and APIs, including an approved Google-based source when actually available; discovery hits alone are not underlying articles.
10. **Attention sensors:** Google Trends and analogous trend signals, **never** counted as independent news origins or confirmed real-world events.

Each source **must** have separate `source_class`, `platform_id`, `publisher/original_origin`, `acquisition_provider`, `source_url`, `original_publication_url`, `content_type`, `language`, `region/jurisdiction`, `observed_at`, `published_at`, `rights/license`, `access/retention limits`, `coverage`, `sampling`, `retrieval_status` and `provenance`. In particular, **Google discovery result ≠ news source identity**; reposted/syndicated content ≠ new independent origin.

Acquisition adapters can use official APIs, RSS/Atom, permitted sitemaps, open-data endpoints, authorized web extraction, documented bulk exports and eligible user documents. They must operate under the existing SIFI source registry, owner/tenant context, rights Z0–Z3, host policy, quotas, costs, checkpoints, incremental windows and stop/error states. No CAPTCHA/paywall/login/anti-bot bypass or unapproved full-text storage. Do not assume free Google web-search API access exists.

### Period-first acquisition and historical coverage

- The UI presents the **time interval as the first mandatory choice**; period selection can trigger source discovery directly, without a keyword or monitoring object.
- The source selector enumerates currently authorized source packs for the interval and records eligibility per source. Sources without archival/backfill rights or retention in the requested window yield `HISTORICAL_COVERAGE_UNAVAILABLE`, never a fabricated empty result.
- Date filtering distinguishes `published_at` from `first_observed_at` and `retrieved_at`. Publication dates unknown or outside requested range are explicitly partitioned; no artificial backdating.
- Pagination, historical availability, quotas and source-specific date search constraints must be shown. `ZERO_MATCH` means zero **among observed eligible results**, not no issue existed.
- Automatic source fallback is permitted only among independently approved sources; blocked GDELT/social endpoints cannot justify unauthorized scraping. If no eligible source remains, return `INSUFFICIENT_COVERAGE` with reasons and available alternatives.
- For a large interval, execute bounded asynchronous tasks and partial-results UX with user-visible time, source and cost budgets. Incremental checkpoints prevent re-reading the same source/date pages without reason.

## 3. Extraction contract, separated from platform classification

For each legally usable publication/document/post, perform reproducible extraction of:

- Subject, entities, organizations, public actors, locations and disambiguated names.
- Main propositions/claims, claimed events, reported conditions, policy actions, dates, quantities and units.
- Candidate issue label, context and relevant thematic keywords.
- Source attribution and exact supporting span/offset/page/timecode **when retention and rights permit**.
- Temporal qualifiers, certainty/hedging, negation, contradictions and evidence gaps.
- Publication/source lineage, text hash or derived metadata fingerprint, extraction method/version and confidence.

Where only metadata/snippets may legally be held, extract only from that permitted representation and mark `CONTENT_NOT_AVAILABLE`. No AI fabrication of full text, facts, quotes or event dates. A single article may express multiple claims and support multiple issue candidates, but an article count is not an event count.

**Deterministic extraction, normalized metadata and basic issue matching must work with AI OFF.** Optional free/local AI may propose entity resolution, semantic similarity and relations only through configured permissions; it never grants evidence status or overrides contradictory primary material.

## 4. Two-dimensional issue classification is mandatory

### 4.1 Horizontal — same-issue relationships across platforms and publications

Group observations that discuss the **same underlying issue** even when written with different words, language, headlines or platform conventions. Distinguish:

- `EXACT_DUPLICATE` — same canonical item or hash.
- `SYNDICATED_OR_REPOSTED` — derived from one informational origin.
- `SAME_EVENT_CANDIDATE` — reports plausibly describing the same bounded occurrence; unverified until corroboration.
- `SAME_ISSUE_CLUSTER` — multiple reports/topics linked to a common ongoing issue.
- `RELATED_BUT_DISTINCT` — topical overlap without sufficient basis to merge.

Identity resolution must use content/time/entity/location/provenance jointly, not keyword overlap alone. Each cluster stores original member IDs, observed/published times, platform diversity, independent-origin **estimate or unknown**, duplicate ratio, conflicting claims and traceable merge/split revisions.

### 4.2 Vertical — issue hierarchy and typed relationships

Construct an **Issue Graph**, not only a flat list of hashtags. Support:

- `PARENT_ISSUE → SUBISSUE` (e.g. public infrastructure → drainage → blocked drainage in one area).
- `ISSUE → EVENT/INCIDENT` when the incident belongs to an issue.
- `ISSUE → ACTOR/INSTITUTION/POLICY` through typed involvement or jurisdiction **only with source support**.
- `REPORTED_CAUSE → REPORTED_CONDITION → REPORTED_IMPACT → REPORTED_RESPONSE` as **claim-level relationships**, not verified causal truth.
- `CROSSCUTTING/DEPENDENT_ISSUE` when one observation pertains to several themes or levels.

Every vertical edge must include relation type, direction, evidence/source IDs, date window, confidence/uncertainty, review state and version history. **Hierarchy (`IS_SUBISSUE_OF`) is not causation (`CAUSES`)**; causal claims default to `ALLEGED_OR_HYPOTHESIZED` pending independent support. Conflicts stay visible. The graph allows many-to-many links, not an artificially exclusive tree.

## 5. Longitudinal intelligence — trend analysis over time

SIFI must maintain **period-comparable historical snapshots** and compute trend series for each canonical issue/cluster and each level of the issue graph.

Minimum dimensions and analytics:

| Dimension | Required metric/output | Interpretation guard |
|---|---|---|
| Publication activity | Publications/mentions per day/week/month by issue, platform and source | Counts are observed coverage, not total media universe. |
| Informational independence | Independent-origin candidates, syndicated duplicates and unknown-origin count | Raw publication volume cannot inflate corroboration. |
| Platform distribution | Share/count by source class, platform and publisher across comparable intervals | Different quotas and coverage require explicit comparability. |
| Velocity & acceleration | Period-over-period rate of change, peak, onset, persistence and decline, with denominators | Increases can result from added sources or sampling changes. |
| Social engagement | Platform-specific interaction rate/velocity normalized for time and available denominator | Not population prevalence or verified impact. |
| Search interest | Google Trends or other approved sensor tracked separately from articles and posts | Search spike is attention, not incident frequency. |
| Issue hierarchy | Parent/subissue trend, emerging branches and convergence/divergence | Parent aggregates must not double count one child observation. |
| Narrative & claim evolution | New/repeated/contradicted claims, frame changes, source entry/exit | Unverified claims remain labeled. |
| Foresight/watch | Trend-break signals, scenario conditions, uncertainty and counter-evidence | Forecast is probabilistic, not a fact or authorized decision. |

Baseline comparability requires the same time grain, configured eligible source population where possible, stable identity rules, observation cutoffs and explicit handling of missing history. Maintain `first_seen`, `last_seen`, `peak_window`, `window_count`, `independent_origin_window_count`, `velocity`, `acceleration`, `coverage_change` and `confidence/unknown` for each series. Do **not** infer an origin date from the first time SIFI encountered content.

Issue graph and historical series must be **revision-safe and point-in-time reproducible**: new data can revise future views but must not silently rewrite previously issued snapshots/briefs.

## 6. User-facing output contract

After selecting dates, the operator sees:

1. Eligible/blocked/unavailable source inventory with retrieval progress and actual sampled coverage.
2. Results split by source type and original platform/publisher, with provenance and rights state.
3. Auto-generated issue explorer: horizontal clusters, duplicate/syndication groups and unknowns.
4. Vertical graph: parent/subissue/incident/claimed cause/impact/policy links with evidence and confidence labels.
5. Interactive timeline per issue and parent issue, with selectable daily/weekly/monthly granularity and source-composition changes.
6. Investigation links, exceptions, source health, comparisons, watch conditions and draft executive brief, extending existing Core V2 UI.

Optional keyword, monitoring object, location, publisher, platform and issue filters can be applied **after** the period-first initial discovery, without forcing the operator to start again. Do not require a manually named issue before the system discovers candidate issues.

## 7. Milestone mapping and acceptance, integrated with existing E0–E12

| Milestone | Added mandatory deliverable | Definition of Done |
|---|---|---|
| **E2 / P0** | Source-class/platform/publisher taxonomy, rights/health and per-source coverage state in the existing Superadmin registry | At least 3 source families visible with honest access states; source eligibility does not imply content rights. |
| **E3 / P0** | One lawful real-data period-bound acquisition flowing into existing research/observation consumers | A dated source item has provenance and isolated receipt; no evidence admission. |
| **E4 / P0 (promoted)** | **Period-first multi-source discovery without mandatory keyword/object** | Select a window; source plan spans all eligible families; out-of-window and unsupported-history outcomes explicit. |
| **E5 / P0 (promoted)** | Source-specific information extraction and normalized publication/claim records | Same schema from ≥2 distinct permitted source classes; metadata-only vs fulltext rights correctly distinguished. |
| **E6 / P1** | Incremental date-window scheduler with source health/fallback/quotas and checkpoints | Repeat scan uses idempotence, bounded retries and no unauthorized endpoint; historical limitations visible. |
| **E7 / P0 (promoted)** | Horizontal issue clusters plus vertical typed Issue Graph into Core V2 | Cross-platform mentions form one issue candidate; parent/subissue graph navigable; no unsupported causal edge. |
| **E8 / P0 (promoted)** | Longitudinal trend-series engine and cross-platform analysis, building on credibility and propagation existing code | ≥2 comparable time buckets; per-platform/origin/issue trend, velocity and acceleration; missing data/coverage visible. |
| **E10 / P1** | Trend-based watch/forecast and revision-safe executive snapshots | Foresight cites observed series, assumptions, alternative drivers and uncertainty. |
| **E11 / continuous** | Period picker → source health → issue graph → timeline → investigation/brief UX | One readable end-to-end journey on desktop and mobile using existing screens. |

**Acceptance scenario (real-data-first):** The operator selects only a bounded historic date range (e.g. 1–7 November 2026). SIFI enumerates eligible news, official/open-data, research/social or other permitted sources, retrieves only what source/date rights permit, classifies source/platform, extracts claim/issue candidates, horizontally groups common issues, vertically links supported subissues, and shows daily/weekly changes. At least two truly independent source classes and two comparable time bins must be demonstrated **when legally accessible**. If an eligible data pack cannot provide sufficient coverage, return `PARTIAL / INSUFFICIENT_COVERAGE`, not a synthetic PASS. One integrated acceptance receipt per usable increment; reuse existing tests and engines.

## 8. Work-order and precedence for the next executor

1. **Do not repeat the Google Trends pilot as the main product milestone.** Its 9 November WSL receipt proved one public attention feed (`HTTP 200`, 10 trends, 10 isolated observations, zero keyword matches), not universal discovery or temporal issue intelligence.
2. Inspect and reuse existing Laravel Core V2, research/monitoring, source registry, observation projector, extraction, issue candidate/case and graph/timeline components. Mark `EXISTING`, `PARTIAL`, `MISSING` against this mandate once, not repeated micro-audits.
3. Implement exactly **one high-value, visible end-to-end slice at a time**, starting with date-range-only discovery over eligible sources; source classification and locally computed issue grouping should follow in the same analyst journey.
4. Integrate horizontal identity and vertical relation models into existing issue/evidence contracts; no independent second truth engine, shadow-only until approved.
5. Build period-comparable series and trend UI on top of persistent observations, ensuring no double counting and explicit source coverage.
6. Preserve Laravel-first, reuse-first, real-data-first, zero-spend-first, privacy, immutable provenance, license/rights/quotas and production admission DENIED until a separate authorized gate.

**Owner mandate summary:** **Time period first; broad lawful cross-platform acquisition; classify source/platform; extract actual information; cluster horizontally; relate issues vertically; analyze temporal change and risk.** This chain, not individual API availability, defines SIFI's product finish line.
