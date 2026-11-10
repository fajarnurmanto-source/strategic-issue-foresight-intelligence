# SIFI E4 — Object-Based, Period-Bound Multi-Source Discovery

Implemented on 9 November 2026. Laravel-first; no Python dependency or production-admission change.

## Object-first user path

1. Register or revise an owned **SIFI Monitoring Object** at /sifi/monitoring-objects. The object must be CONFIGURED and have at least one registered keyword.
2. Open /sifi/discovery-period (also linked from the object page, Workbench, and sidebar).
3. Choose one of the user's objects, **publication start/end dates inclusive**, up to 366 calendar days, not in the future.
4. GET _Periksa cakupan sumber_ builds an owner-bound, **read-only** source plan with explicit platform classes and reasons for non-coverage. No third-party request is made.
5. With separate, explicit consent and only in a configured **isolated** local SQLite database, POST performs at most one reviewed acquisition attempt per eligible provider. The ordinary development and production databases cannot receive this POST.
6. Show a source/platform-classified, period-checked metadata report with source receipts and coverage omissions. This is not automatic evidence admission and does not promise historical completeness.

## Covered provider classes

| Provider                        | Platform classification   | Period handling                                                                                                      | E4 dispatch                                                                          |
| ------------------------------- | ------------------------- | -------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------ |
| Crossref                        | RESEARCH / Crossref       | Reviewed bibliographic **publication** dates; date-filtered API query, no abstract/full text                         | Only with current IH03B local permission and E4 isolated opt-in                      |
| ANTARA latest RSS               | NEWS / ANTARA             | Only latest 5 links; bounded by registered keywords in the **headline** and publication timestamp in selected window | Only when selected end date is today, reviewed RSS permission and E4 isolated opt-in |
| GDELT DOC                       | NEWS_INDEX / GDELT        | Historical backfill not yet reviewed for E4; known rate/availability restrictions                                    | Not dispatched                                                                       |
| Bluesky public search           | SOCIAL / Bluesky          | Historical archive coverage not established                                                                          | Not dispatched                                                                       |
| Google Trends RSS               | ATTENTION / Google Trends | Trending search terms, not publications or verified events; latest only                                              | Not dispatched                                                                       |
| Official regulation / open data | OFFICIAL_OPEN_DATA        | No universal reviewed period adapter                                                                                 | Not dispatched                                                                       |

Other unconnected social platforms or news sources are **not** silently represented as covered. Source availability is explicitly recorded; E4 is a **partial bounded multi-source discovery**, not a comprehensive cross-platform crawler.

## Canonical constraints

- E4 uses the source registry, permissions, one-shot source adapters, existing sifi_research_sessions receipt history, lineage, and source-observation projection. It creates **no schema migration**.
- E4 does not create Issue Cases, admit evidence, or assume factual verification. Later stages handle platform-type extraction, horizontal issue clustering, vertical issue hierarchy, and time-series trend analysis.
- Google Trends is an attention measurement, **not** a surrogate for all web sources.
- Crossref publication metadata is **not** equivalent to a news article, a social post, or a real-world event.
- Headline count is **not** event incidence, prevalence, impact, or independent source-origin count.
- No scheduled background crawling, retry loop, dynamic URL injection, proxy rotation, captcha circumvention, or unreviewed scraping.

## Activation

Default SIFI_E4_LIVE_ENABLED=false.
Isolated acquisition additionally requires:

- Laravel APP_ENV=local or testing;
- database.default=sqlite and database exactly :memory: or a .sqlite path inside /mnt/d/Research/SIFI/05_VALIDATION/;
- provider-specific reviewed local permissions (IH03B Crossref, IH03E ANTARA), live validity, real contact email, and per-provider budgets;
- authenticated, email-verified object owner, current object revision, UUID submission token, explicit source consent, and POST throttle.

**Do not enable on the main development database**. The Google Trends pilot DB is not intended as an automatically approved E4 database. No production admission has been granted.

## Deployment and narrow validation

Pull the existing SIFI bridge snapshot, then apply the **source-only** bridge. Review its backup path and verify:

    php artisan test --filter='ProductRoadmapPageTest|E4PeriodDiscoveryTest'
    php artisan route:list --path=sifi/discovery-period

A Vite/TypeScript build can be run by the WSL operator under the regular project workflow. No production restart, migration, or live provider test is implicit in the source-only bridge.

### Known incomplete capability (post-E4)

- Generic news and social **historical** crawling remain unavailable without reviewed source-specific providers.
- The E4 source-classification/report layer is not full content extraction, issue clustering (horizontal/vertical), or historical trend scoring.
- A real-data demonstration requires an active, unexpired provider grant in an isolated database and the operator's opt-in.

Related canonical roadmap: docs/canonical/SIFI_MASTER_PRODUCT_ROADMAP_v1_2_20261109.md.
