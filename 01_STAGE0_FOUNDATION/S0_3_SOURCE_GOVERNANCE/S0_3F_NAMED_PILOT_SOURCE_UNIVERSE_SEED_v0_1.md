# SIFI S0.3F — Named Pilot Source Universe Seed v0.1

Status: VERIFIED SEED + CANDIDATE COHORT — NOT FROZEN
Date basis: 2026-10-04
Pilot geography: Kota Yogyakarta

## 1. Purpose
Seed the monitored-source universe with sources whose role, authority, access path, and rights state can be explicitly reviewed.

This is not yet the final monitored universe.

## 2. Status Vocabulary
- VERIFIED_SEED: identity and current public role verified; suitable for Source Registry onboarding subject to per-source contract.
- CONDITIONAL_SEED: useful and publicly accessible, but automated access/retention/commercial reuse requires additional rights review.
- CANDIDATE: source is relevant but not yet rights/access-qualified.
- EXCLUDED: not admissible for automated pilot acquisition under current evidence.

## 3. Initial Verified/Conditional Sources

### SRC-OFFICIAL-JDIH-YK
Name: JDIH Pemerintah Kota Yogyakarta
Class: regulation / official release
Authority role: primary official source for local legal instruments and status metadata
Evidence role: rule, policy change, formal legal status
Access baseline: public web pages and linked documents
Automation status: CONDITIONAL_SEED pending explicit automated-access/retention review
Retention baseline: metadata + canonical URL; full-text retention only after rights confirmation
Cadence candidate: daily

### SRC-OFFICIAL-WARTA-YK
Name: Portal Berita Pemerintah Kota Yogyakarta
Class: official government news/release
Authority role: primary source for official statements, announced programs, events, and implementation claims
Evidence role: official position / administrative claim; not empirical truth by itself
Access baseline: public web
Automation status: CONDITIONAL_SEED pending terms/robots/access review
Retention baseline: metadata + derived features by default
Cadence candidate: up to 2x daily

### SRC-OPENDATA-YK
Name: Portal Open Data Kota Yogyakarta
Class: official open data
Authority role: primary/official dataset publisher depending on dataset metadata
Evidence role: empirical corroboration and context
Access baseline: public open-data portal; dataset-level resources
Automation status: CONDITIONAL_SEED; verify dataset license, resource endpoint, and update cadence per dataset
Retention baseline: dataset metadata + permitted resource snapshot according to dataset license
Cadence candidate: daily/weekly according to dataset update frequency

### SRC-BPS-WEBAPI
Name: BPS Web API / BPS-Statistics Indonesia
Class: official statistics
Authority role: national statistical authority
Evidence role: empirical corroboration, denominator/base-rate, structural context
Access baseline: official Web API; registration required
Automation status: VERIFIED_SEED via documented API
Commercial-use posture: allowed under current BPS website Terms of Use, subject to citation/credit and lawful use
Retention baseline: derived/returned data with required citation and provenance
Cadence candidate: daily/weekly/monthly according to release frequency

### SRC-SDI-DATAGOID
Name: Portal Satu Data Indonesia (data.go.id)
Class: official open-government data catalog/portal
Authority role: discovery and access layer; dataset authority remains with producer/walidata metadata
Evidence role: empirical corroboration and dataset discovery
Access baseline: public portal/resources
Automation status: CONDITIONAL_SEED; access method and dataset-specific license/rights must be registered
Retention baseline: metadata-first; resource retention per dataset terms
Cadence candidate: weekly or dataset-specific

### SRC-BMKG
Name: Badan Meteorologi, Klimatologi, dan Geofisika
Class: official hazard/weather/environment information
Authority role: primary official meteorological/climatological/geophysical source
Evidence role: hazard/environmental corroboration and leading indicators
Access baseline: official API only for automated/machine-to-machine access
Automation status: CONDITIONAL_SEED
Critical restriction: do not scrape BMKG web interfaces
Commercial posture: third-party app integration/repackaging/commercial use requires official written permission under current Terms of Use
Retention baseline: API-derived metadata/data subject to attribution, integrity, and permission requirements
Cadence candidate: source/API-specific

## 4. Candidate Media Cohort
Named local/regional/national media sources will be added only after a per-domain rights/access audit.

Until then:
- do not assume public HTML is crawlable;
- do not assume article full text may be retained;
- do not assume commercial reuse is permitted;
- prefer RSS, sitemap, documented feed, licensed API, or metadata-first discovery;
- record syndication/origin relationships before using publication counts.

## 5. Source Independence Rule
Multiple articles that reproduce the same wire copy, press release, or source origin are not independent evidence.
SIFI must maintain informational-origin/syndication lineage where defensible.

## 6. Freeze Requirement
This seed cannot be frozen until:
- media cohort rights/access review is completed;
- each retained source has a Source Registry row;
- coverage gaps are documented;
- substitution/fallback paths exist for critical source classes;
- source-role and evidence-role conflicts are reviewed.
