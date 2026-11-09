# SIFI — Source Modules and Activation Contract
Date: 2026-11-09 · Scope: Laravel source inventory + E4 UI · Admission: production DENIED

## Decision

Provider **catalogue**, **implemented connector**, **authorized request**, **historical coverage**, and **evidence admission** are five distinct states. The source inventory must never claim that a listed platform is being collected.

Module IDs (8):

| Module | Providers / roles | Safe current delivery |
| --- | --- | --- |
| MEDIA_ONLINE | ANTARA RSS, generic RSS/Atom, Google News RSS | Existing ANTARA latest-only one-shot E4 with local permission; other feeds planned or rights review |
| GDELT | GDELT DOC API | Existing bounded GDELT pilot outside E4; historical period adapter not admitted |
| WEB_SEARCH | Google Search, Bing, Brave, Tavily candidate | Listed, not activated; official API credentials, terms and quotas must be assessed |
| SOCIAL_MEDIA | Bluesky, Mastodon, Instagram, Facebook, TikTok, X, YouTube, Threads, LinkedIn | Existing Bluesky public metadata pilot outside E4; remaining APIs require adapters and, for many, app permissions |
| RESEARCH | Crossref, OpenAlex, arXiv, Wikipedia | Existing Crossref historical publication-date filter E4 with local permission; rest not E4 |
| SEARCH_TRENDS | Google Trends RSS | Existing one-shot Indonesian trending terms pilot outside E4; NOT Google Search results or post archive |
| OFFICIAL_OPEN_DATA | Government/regulation, BPS, World Bank, legal index | Existing World Bank workflow outside E4; no blanket historical news adapter |
| DOCUMENTS_DATASETS | Owned documents, owned datasets | Internal controlled workflow only; rights must be verified |

Total registered in source taxonomy: 28 providers. This is **not** a connected-provider count.

## Actual data flow

1. User registers monitoring object + aliases/keywords/exclusions and selects publication-date range.
2. E4 calls `SourceModuleCatalog::forE4(endDate)` once; it produces no network traffic.
3. Each provider has `module_id`, `state`, `connection`, `workflow`, `coverage`, `dispatchable` and explanation. The same catalog appears in E4 and Source & Coverage.
4. E4 is allowed to **dispatch** only existing explicit Crossref and latest-feed ANTARA operations when the connector review, opt-in, isolated SQLite location, time window, local environment, and per-provider request budgets all pass.
5. All other providers remain visible as unavailable/conditional, not silently omitted; adding rows never grants credentials or collection rights.
6. Metadata/links obtained in authorized pilots remain non-admitted research drafts; no unverified claim or source count becomes a fact, event frequency, prevalence, or impact severity.

## Access-by-module activation backlog (priority)

- **P0 (ready for bounded isolated pilots):** Crossref, ANTARA, reviewed GDELT one-shot, reviewed Bluesky public search, reviewed Google Trends RSS. Keep each in its existing reviewed command/workflow. Crossref and ANTARA only can be called by E4, subject to guards. Provider HTTP calls are not made by inventory views.
- **P1 (public feed capability):** configurable publisher RSS/Atom allowlist, update frequency, canonical URL normalization, attribution, dedupe/origin grouping. Verify each publisher's terms; do not silently download paywalled full text.
- **P1 (social):** Mastodon instance-scoped public API, YouTube official API for scoped video metadata, Meta/TikTok/X only after owner provides approved app access. Preserve per-platform query limits, deletion/retention rules, source lineage, and privacy safeguards.
- **P1 (search):** assess currently offered official search API rather than scraping Google result pages; Google Custom Search JSON legacy API is not a dependable new-customer path. Search results are discovery pointers, not permission to extract pages or evidence.
- **P2 (official & research):** OpenAlex/arXiv, BPS/JDIH, government RSS/API, World Bank indicators. Keep year- or regulation-effective-date separate from publication date and event time.
- **P2 (historical):** provider-specific archives and explicit date filters; never label latest-only feed as 366-day coverage. Implement E4 bounded adapter with isolated integration tests before enabling.
- **P3:** automatic clustering, origin independence, velocity, credibility signals, watch conditions and probabilistic foresight, with review at exceptions and material decisions.

## Operator requirements

- Access to public endpoints does not override any provider ToS, API scope, quota, access restrictions or paywall.
- Configure secrets only in local environment/secret store; never commit to GitHub or expose to UI.
- Use `/mnt/d/Research/SIFI/05_VALIDATION/<isolated-name>.sqlite` for existing opt-in validation workflows. Do not switch the active production database in place.
- Existing provider review expiry dates must be checked afresh before every run. Existing crossref permission was reviewed until 2026-11-14 and ANTARA until 2026-11-15, subject to all other gates.
- Do not enable `SIFI_E4_LIVE_ENABLED` on the main local app database; E4 intentionally denies execution outside isolated SQLite.
- No changes to real evidence admission, production admission, or scheduler; no bulk download, paid API, scraping behind login, or unattended requests.

## Verification

Suggested focused checks on a disposable Laravel checkout:
`php artisan test --filter='SourceModuleCatalogTest|E4PeriodDiscoveryTest|SifiSourceCatalogWorkflowTest'`
`npm run types:check` and `npm run build`.
After the UI release gate and human UX acceptance, merge the feature into the authorized bridge branch; the bridge applies source files only with backups and hash checks.

## Limitations as of this change

The feature is a concrete **source classification and UI inventory**, not a declaration of provider activation. Provider credentials, Google Search APIs, social-platform approval, live source ingestion, broad historical backfill, and production activation are out of scope pending their own verified legal and technical requirements.
