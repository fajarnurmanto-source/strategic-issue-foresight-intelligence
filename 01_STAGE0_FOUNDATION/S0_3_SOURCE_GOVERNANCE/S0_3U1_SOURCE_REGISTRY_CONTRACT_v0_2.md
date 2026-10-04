# SIFI S0.3U1 — Source Registry Contract v0.2

Status: WORKING DRAFT — NOT FROZEN

## 1. Purpose
Source Registry is the canonical control layer for every external source
known to SIFI.

A Source object describes the source itself.

It must remain separate from:
- CoverageProfile
- SourceCoverageBinding
- Evidence Item
- geographic relevance
- factual authority
- issue relevance

## 2. Core Source Object

Minimum fields:

- source_id
- canonical_name
- source_family
- publisher_or_owner
- canonical_domain
- country_code
- declared_editorial_scope
- declared_geographic_scope
- source_type
- access_method
- discovery_method
- machine_readable_endpoint
- terms_url
- robots_url
- license_or_rights_state
- retention_state
- redistribution_state
- commercial_use_state
- attribution_requirement
- rate_limit_policy
- authentication_requirement
- fulltext_collection_state
- metadata_collection_state
- derived_feature_state
- active_state
- first_verified_at
- last_verified_at
- verification_status
- registry_version

## 3. Source Families

Initial source families:

- OFFICIAL_GOVERNMENT
- OFFICIAL_REGULATION
- OFFICIAL_STATISTICS
- OPEN_DATA
- WEATHER_ENVIRONMENT
- NEWS_MEDIA
- RESEARCH_KNOWLEDGE
- PUBLIC_INSTITUTION
- SOCIAL_PLATFORM
- AGGREGATOR_INDEX
- OTHER

Source family must not be treated as proof of factual truth.

## 4. Source Types

Initial source types may include:

- WEBSITE
- RSS_ATOM
- SITEMAP
- API
- DATA_PORTAL
- DOCUMENT_REPOSITORY
- DATABASE
- RESEARCH_REPOSITORY
- SOCIAL_FEED
- INDEX_AGGREGATOR

One source may expose multiple access surfaces.

## 5. Verification States

Required:

- UNVERIFIED
- DISCOVERED
- ACCESS_VERIFIED
- RIGHTS_REVIEWED
- PILOT_APPROVED
- RESTRICTED
- SUSPENDED
- RETIRED

A source must not become PILOT_APPROVED merely because it is technically
accessible.

## 6. Access Method Precedence

Preferred order where available and permitted:

1. official API
2. official structured feed
3. official RSS/Atom
4. official sitemap / index
5. permitted direct web retrieval
6. browser-assisted retrieval only when necessary and permitted

Technical possibility is not access authorization.

## 7. Rights Separation

The following rights questions are separate:

- may discover URL?
- may fetch metadata?
- may fetch full text?
- may retain raw content?
- may retain metadata?
- may retain extracted facts/features?
- may redistribute raw content?
- may redistribute derived output?
- may use commercially?
- is attribution required?

UNKNOWN must remain explicit.

## 8. Registry vs Binding

Source Registry:
"What is this source and what may SIFI legally/operationally do with it?"

SourceCoverageBinding:
"For which Coverage Profiles should SIFI inspect this source and at what
collection priority?"

The binding cannot override Registry restrictions.

## 9. Authority Separation

The Registry may record declared institutional authority or source role.

It must not collapse:

- official status
- popularity
- independence
- corroboration
- empirical strength
- causal support

into one authority score.

## 10. Source Change

Terms, endpoints, robots rules, feed availability, ownership, access patterns,
and licenses may change.

Therefore source verification is time-versioned.

A previously approved source can become:
RESTRICTED
SUSPENDED
or
RETIRED.

## 11. Failure Semantics

Required explicit states include:

- ACCESS_UNKNOWN
- ACCESS_BLOCKED
- ENDPOINT_UNAVAILABLE
- TERMS_UNKNOWN
- RIGHTS_UNKNOWN
- RETENTION_UNKNOWN
- COMMERCIAL_USE_UNKNOWN
- AUTH_REQUIRED
- RATE_LIMIT_UNKNOWN
- TECHNICALLY_ACCESSIBLE_BUT_NOT_APPROVED

These must not silently become approval.

## 12. Evidence Provenance

Every Evidence Item derived from a registered source must retain at minimum:

- source_id
- acquisition timestamp
- canonical locator
- acquisition method
- content/metadata fingerprint where applicable
- parser/extractor version
- rights state effective at acquisition time

