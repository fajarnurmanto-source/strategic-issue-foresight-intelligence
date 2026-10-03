# SIFI S0.2 — Pilot Boundary Draft

Status: WORKING DRAFT — NOT FROZEN
Stage: 0 — Foundation

## 1. Pilot Geography
Primary geography: Kota Yogyakarta.

The pilot is geographically bounded to issues that:
- occur within Kota Yogyakarta; or
- have a direct, explicit, and reviewable relevance to Kota Yogyakarta.

## 2. External Relevance Envelope
Regional or national sources may enter the pilot corpus only when the evidence item has a defensible link to Kota Yogyakarta, for example:
- a regulation that applies to the city;
- a regional hazard/environmental condition with plausible local impact;
- a national economic or sector development with direct local exposure;
- an event outside the city that directly affects mobility, supply, tourism, public services, or institutional operations in the city.

This rule prevents the pilot from silently expanding into a national monitoring product.

## 3. Pilot Domain
Working domain:
Urban governance and public-service strategic issues.

Initial benchmark subdomains:
1. waste / environment;
2. mobility / infrastructure;
3. public service / governance;
4. local economy / tourism.

These subomains are chosen to provide heterogeneous event, recurrence, evidence, and trajectory patterns. They are benchmark scopes, not a permanent commercial taxonomy.

## 4. Exclusions for the Pilot
The pilot does not attempt:
- national full-spectrum issue coverage;
- 100% internet coverage;
- real-time streaming;
- privileged social-platform access as a dependency;
- automated causal claims;
- automated policy decisions;
- full copyrighted-article republication;
- procurement, budgeting, or government workflow automation.

## 5. Collection Cadence Assumption
Working baseline:
- priority local/national news relevant to the pilot: up to 2 cycles/day;
- secondary media: 1 cycle/day;
- official news/regulation: 1 cycle/day;
- open-data sources: daily/weekly according to source update frequency;
- research/reports: weekly;
- foresight indicators: weekly/monthly.

Cadence remains source-specific and rights-aware.

## 6. Initial Buyer/User Boundary
The first workflow optimized in the pilot is:
daily/weekly issue sensing and evidence-linked briefing for a government communications/public-information analyst.

The product must still be architected as a standalone commercial system rather than a bespoke internal government tool.

## 7. Pilot Deployment Assumption
Local-first, single-tenant research/pilot deployment using:
- Ubuntu 24.04 LTS on WSL2;
- Python;
- Docker Desktop integration;
- version-controlled project state.

Cloud scale, multi-tenant production, and external API exposure are post-foundation concerns.

## 8. Open Decisions Deferred to Subsequent Stage-0 Work
The following are explicitly NOT decided by this draft:
- named monitored-source universe;
- per-source access and retention rights;
- source authority classes;
- Issue ontology v0;
- Evidence Item schema finalization;
- Issue Object schema finalization;
- benchmark corpus composition;
- quantitative acceptance thresholds;
- human review authority matrix.

## 9. Boundary Failure Conditions
The pilot boundary fails if:
- national issues are admitted without explicit Yogyakarta relevance;
- social media becomes a required single point of failure;
- source popularity is used as source authority;
- media attention is treated as real-world prevalence;
- the product becomes a bespoke briefing service with no persistent reusable Issue Objects;
- implementation begins before Stage-0 freeze.
