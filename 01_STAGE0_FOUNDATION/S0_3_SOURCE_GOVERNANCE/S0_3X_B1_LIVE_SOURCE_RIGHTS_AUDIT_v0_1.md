# SIFI S0.3X-B1 — Live Source Rights / Access Audit v0.1

Status: LIVE AUDIT RESULT — NOT FROZEN
Reviewed at: 2026-10-08
Audit protocol:
S0_3X2_LIVE_SOURCE_RIGHTS_AUDIT_PROTOCOL_v0_1.md

## 1. Scope

Initial controlled cohort:

- SRC-BPS
- SRC-DATAGOID
- SRC-BMKG
- SRC-JDIH-YK
- SRC-WARTA-YK
- SRC-OPENDATA-YK

This audit establishes current rights/access evidence only.

It does not activate collection.

## 2. SRC-BPS

Source:
Badan Pusat Statistik

Verification state:
RIGHTS_REVIEWED

Preferred acquisition:
OFFICIAL_API

Automated access:
ALLOWED_WITH_CONDITIONS

Authentication:
REGISTRATION / API AUTHORIZATION REQUIRED

Commercial use:
ALLOWED_WITH_CONDITIONS

Attribution:
REQUIRED

Raw/fulltext posture:
NOT REQUIRED FOR SIFI DEFAULT PIPELINE

Preferred retention:
R1_METADATA_DERIVED

Candidate admission zone:
Z2

Production eligibility:
CONDITIONAL_PRODUCTION_ELIGIBLE

Conditions:
- use official WebAPI for recurring programmatic acquisition;
- comply with API authentication / authorization controls;
- retain BPS citation/credit;
- do not treat separately restricted or paid products as covered by the
  general route.

Evidence locators reviewed:
- BPS — Syarat dan Ketentuan
- WebAPI BPS — Developer Portal
- Portal PPID BPS — Layanan Interoperabilitas Web API

Finding:
BPS is the strongest initial candidate for controlled Z2 production
admission.

Final Z2 admission remains evidence-item specific under S0.3W.

## 3. SRC-DATAGOID

Source:
Portal Satu Data Indonesia

Verification state:
ACCESS_VERIFIED

Portal role:
AGGREGATOR / DISCOVERY / DATA CATALOG

Public access:
YES

Dataset classifications:
May include TERBUKA and restricted states.

Portal-wide commercial rights:
UNKNOWN

Portal-wide retention rights:
UNKNOWN

Dataset reuse licence:
SOURCE / DATASET SPECIFIC

Preferred acquisition:
STRUCTURED OR DOWNLOAD ROUTE WHERE AVAILABLE

Candidate admission zone:
Z1

Production eligibility:
RESEARCH_ONLY_AT_PORTAL_LEVEL

Evidence locators reviewed:
- Portal Satu Data Indonesia — Homepage
- Portal Satu Data Indonesia — Tentang
- representative Portal SDI dataset pages

Finding:
"Terbuka" must not be treated as a universal commercial-use licence.

Portal metadata may support discovery and routing.

A dataset may be considered for Z2 only after producer/source-specific rights
and licence are resolved.

## 4. SRC-BMKG

Source:
Badan Meteorologi, Klimatologi, dan Geofisika

Verification state:
RIGHTS_REVIEWED

Public manual web access:
ALLOWED_WITH_CONDITIONS

Automated website scraping:
PROHIBITED

Machine-to-machine acquisition:
OFFICIAL_API REQUIRED

Official structured APIs:
AVAILABLE FOR SELECT DATA PRODUCTS

Rate limit observed for open weather API:
60 REQUESTS / MINUTE / IP

Attribution:
REQUIRED

Commercial use / third-party application integration:
WRITTEN BMKG PERMISSION REQUIRED

Candidate admission zone for commercial SIFI:
Z1

Production eligibility:
RESEARCH_ONLY_PENDING_WRITTEN_PERMISSION

Evidence locators reviewed:
- BMKG — Ketentuan Penggunaan
- BMKG — Data Prakiraan Cuaca Terbuka
- BMKG — Data Peringatan Dini Cuaca Terbuka
- BMKG robots policy

Finding:
Do not crawl the BMKG website interface.

For SIFI production, use only official API routes and obtain written
permission where commercial/integration use requires it.

A future written permission may support promotion toward Z2.

## 5. SRC-JDIH-YK

Source:
JDIH Pemerintah Kota Yogyakarta

Verification state:
ACCESS_VERIFIED

Public access:
YES

Official legal-document downloads:
AVAILABLE

Automated acquisition permission:
UNKNOWN

Commercial-use terms:
UNKNOWN

Platform retention terms:
UNKNOWN

Copyright state for statutory/regulatory text:
NOT_APPLICABLE TO THE REGULATION TEXT ITSELF under applicable Indonesian
copyright law.

Website/platform expression and technical access rights remain separate.

Candidate admission zone:
Z1

Production eligibility:
RESEARCH_ONLY_FOR_RECURRING_AUTOMATED_ROUTE

Evidence locators reviewed:
- JDIH Pemerintah Kota Yogyakarta — official portal
- JDIH product-law catalogue
- Indonesian Copyright Law, Article 42

Finding:
Copyright is not the principal blocker for official regulations.

The unresolved issue is recurring automated acquisition and platform-use
terms.

A separately verified lawful acquisition route may later permit regulatory
Evidence Items to enter Z2.

## 6. SRC-WARTA-YK

Source:
Portal Berita Pemerintah Kota Yogyakarta

Verification state:
ACCESS_VERIFIED

Public access:
YES

Source role:
OFFICIAL_RELEASE / JOURNALISTIC_EVENT_REPORT

Explicit commercial reuse licence:
NOT LOCATED

Explicit automated acquisition permission:
NOT LOCATED

Fulltext retention right:
UNKNOWN

Protected expression risk:
PRESENT

Preferred research representation:
- canonical reference
- source
- publication time
- title
- factual claims
- permitted bounded excerpt if later established
- fingerprint / provenance

Default retention:
R1_METADATA_DERIVED

Candidate admission zone:
Z1

Production eligibility:
RESEARCH_ONLY

Evidence locators reviewed:
- Portal Berita Pemerintah Kota Yogyakarta — official portal
- Portal Berita Pemerintah Kota Yogyakarta — directory/footer controls

Finding:
Do not assume official-government authorship creates unrestricted commercial
reuse rights.

Do not default to fulltext storage.

Use as a discovery/official-position signal pending explicit rights evidence.

## 7. SRC-OPENDATA-YK

Source:
Open Data Kota Yogyakarta

Discovery portal:
https://opendata.jogjakota.go.id/

Dataset registry / CKAN surface:
https://dataset.jogjakota.go.id/

Verification state:
ACCESS_VERIFIED

Public access:
YES

Registry/API:
AVAILABLE THROUGH CKAN

Dataset download:
AVAILABLE

Generic portal statement:
data is intended for public access and use.

Dataset-level licence state:
MANY CURRENT DATASETS = LICENSE NOT SPECIFIED / NO LICENSE PROVIDED

Commercial-use state:
DATASET SPECIFIC / UNKNOWN WHERE LICENCE NOT SPECIFIED

Retention state:
DATASET SPECIFIC

Candidate admission zone:
Z1 DEFAULT

Production eligibility:
DATASET_BY_DATASET_REVIEW_REQUIRED

Evidence locators reviewed:
- Jogja Go Open Data — portal description
- Open Data Kota Yogyakarta — CKAN dataset registry
- representative current dataset pages showing License not specified

Finding:
API availability and the generic "open data" description do not override a
missing dataset licence.

The CKAN registry is suitable for discovery.

Each dataset must independently satisfy rights admission before promotion to
Z2.

## 8. Cohort Decision Matrix

| source_id | verification_state | preferred_route | production_eligibility | candidate_zone |
|---|---|---|---|---|
| SRC-BPS | RIGHTS_REVIEWED | OFFICIAL_API | CONDITIONAL_PRODUCTION_ELIGIBLE | Z2 |
| SRC-DATAGOID | ACCESS_VERIFIED | STRUCTURED/DOWNLOAD | RESEARCH_ONLY_AT_PORTAL_LEVEL | Z1 |
| SRC-BMKG | RIGHTS_REVIEWED | OFFICIAL_API | RESEARCH_ONLY_PENDING_WRITTEN_PERMISSION | Z1 |
| SRC-JDIH-YK | ACCESS_VERIFIED | OFFICIAL_DOWNLOAD / PENDING AUTOMATION REVIEW | RESEARCH_ONLY_FOR_RECURRING_AUTOMATED_ROUTE | Z1 |
| SRC-WARTA-YK | ACCESS_VERIFIED | PUBLIC_WEB_DISCOVERY | RESEARCH_ONLY | Z1 |
| SRC-OPENDATA-YK | ACCESS_VERIFIED | CKAN_API / DOWNLOAD | DATASET_BY_DATASET_REVIEW_REQUIRED | Z1 |

## 9. Cross-Source Finding

The live audit demonstrates that:

OFFICIAL SOURCE
does not equal
PRODUCTION ELIGIBLE SOURCE.

Likewise:

PUBLIC ACCESS
does not equal
COMMERCIAL USE RIGHT.

And:

API AVAILABILITY
does not equal
DATASET LICENCE.

## 10. Production Consequence

At this gate:

- no source collection is activated;
- no crawler is authorised;
- no browser automation is authorised;
- no Z1 material may silently enter production evidence;
- BPS is only a candidate for Z2 and remains subject to evidence-specific
  admission;
- all other cohort sources remain Z1 by default under the evidence currently
  available.

Implementation Authority remains:

NOT YET AUTHORIZED.

