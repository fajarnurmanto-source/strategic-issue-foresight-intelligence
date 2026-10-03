# SIFI S0.3A–D — Source Governance Core Draft

Status: WORKING DRAFT — NOT FROZEN
Stage: 0 — Foundation
Scope: Source Taxonomy, Source Authority/Role, Access Method Taxonomy, Rights & Retention Model

## A. Source Taxonomy

### A1. Design Rule
Source taxonomy describes what a source is and what role it can play in SIFI. It does not by itself establish truth, authority, independence, or evidence strength.

A source class must never be converted directly into issue severity, source credibility, or corroboration.

### A2. Source Classes
- NEWS_PUBLISHER — online news publishers, publisher RSS/news sitemaps; event/issue discovery, chronology, reported statements, attention.
- OFFICIAL_RELEASE — government/institutional releases; official position, administrative action, program/event confirmation.
- REGULATION_LEGAL — JDIH and authentic legal/policy instruments; legal norm, formal policy change, effective date and scope.
- OFFICIAL_STATISTICS_OPEN_DATA — BPS, Satu Data, local open-data portals, documented statistical APIs; measured conditions, trends, denominators and context.
- HAZARD_ENVIRONMENT_OPERATIONAL — official weather, hazard, environmental, transport or operational feeds; operational condition and hazard/environmental corroboration.
- RESEARCH_KNOWLEDGE — peer-reviewed research, institutional reports, working papers and reputable research organizations; structural explanation, mechanisms, competing hypotheses and reference classes.
- AGGREGATOR_DISCOVERY_CORPUS — lawful news aggregators, research corpora and search services; discovery, historical comparison and routing to original sources.
- SOCIAL_PUBLIC_SIGNAL — public social-platform material accessed through permitted routes; attention, perception, narrative and first-person signal. Optional sensor only.
- PUBLIC_CIVIC_INPUT — publicly accessible complaints, aspirations, consultation or participation data; expressed demand and recurring citizen-reported friction.

### A3. Source Roles
Each source may have one or more roles recorded separately from source_class:
DISCOVERY; PRIMARY_EVENT_SENSOR; OFFICIAL_POSITION; LEGAL_NORM; EMPIRICAL_CORROBORATION; OPERATIONAL_CONDITION; STRUCTURAL_EXPLANATION; ATTENTION_PERCEPTION; HISTORICAL_CONTEXT; CONTRADICTION_SOURCE.

### A4. Source Object vs Evidence Item
A Source Object describes a publisher, institution, feed, dataset, platform endpoint or corpus.
An Evidence Item describes one retrieved article, release, document, dataset observation, post or other traceable unit.

Authority and evidentiary use are adjudicated at Evidence Item / claim level, not only at Source Object level.

### A5. Lineage Rule
Syndicated, mirrored, copied, republished or aggregator-indexed items must retain origin/lineage fields where detectable.
Multiple items with one informational origin must not count as multiple independent corroborations.

## B. Source Authority and Role

### B1. Core Rule
Source authority is claim-dependent, not universal.
SIFI must not maintain a single opaque global source-credibility score in Stage 0.

### B2. Claim-Dependent Authority Contexts
- SELF_ACTION_OR_STATEMENT — first-party evidence that the source made a statement, issued a decision or performed a recorded action.
- LEGAL_NORM — authentic legal instrument/repository for rule text, status, date and formal scope, subject to version/revocation checks.
- OFFICIAL_MEASUREMENT — responsible statistical/operational authority for its published measurement, subject to methodology, geography, lag, revision and variable-definition limits.
- SCIENTIFIC_OR_STRUCTURAL_CLAIM — authority depends on method, evidence quality, scope, review status where relevant, and applicability to the target issue.
- JOURNALISTIC_EVENT_REPORT — may provide event evidence, chronology, quotations and investigation; original reporting must be distinguished from syndication.
- PUBLIC_PERCEPTION_OR_FIRST_PERSON_REPORT — may evidence expressed perception, experience or attention; does not automatically establish population prevalence.

### B3. Evidence-Origin Relationship
Evidence Items should support:
origin_type = PRIMARY_ORIGINAL / SECONDARY_ORIGINAL / SYNDICATED / AGGREGATED / UNKNOWN;
origin_source_id;
parent_evidence_id or canonical_origin_reference;
independence_state = INDEPENDENT / SHARED_ORIGIN / UNKNOWN;
quoted_or_reported_source where extractable.

### B4. Corroboration States
UNCORROBORATED;
CORROBORATED_SAME_ORIGIN;
CORROBORATED_NONINDEPENDENT;
CORROBORATED_INDEPENDENT;
OFFICIAL_CONFIRMATION;
EMPIRICALLY_CORROBORATED;
CONTRADICTED;
MIXED;
NOT_APPLICABLE.

Independent corroboration requires materially distinct informational origins.

### B5. Non-Equivalence Guard
These dimensions must remain separate:
publication count;
detected attention;
source reach/popularity;
source authority for a claim;
independence of evidence;
empirical corroboration;
causal support.

No transformation from publication count or popularity to authority is permitted.

### B6. Compatibility Examples
- An official release may establish that an agency announced a program, but not that the program caused an outcome.
- A statistical table may establish a measured change, but not its cause.
- A viral post may establish attention or a reported experience, but not citywide prevalence.
- Many syndicated stories may establish broad publication, but not many independent confirmations.
- Research may support a mechanism or reference class, but not automatically prove the same mechanism operates in the pilot geography.

### B7. Contradiction
Contradictory evidence is first-class evidence.
Material contradiction must preserve the disputed claim, supporting evidence, contradictory evidence, authority context, unresolved assumptions and human qualification state.

## C. Access Method Taxonomy

### C1. Core Rule
Public visibility is not an access license.
Discovery, automated retrieval, storage, retention, transformation, display, redistribution, attribution and commercial reuse are separate constraints.

### C2. Access Methods
- API_OFFICIAL — documented first-party API or machine-to-machine endpoint.
- FEED_RSS_ATOM — publisher/institution RSS or Atom feed.
- SITEMAP — standard/news sitemap for discovery; content rights remain separate.
- OPEN_DATA_DOWNLOAD — official CSV/JSON/XLSX/ZIP/document or open-data endpoint.
- DIRECT_HTML — direct retrieval of public web pages only after terms/robots/rate/retention review.
- AGGREGATOR_API_OR_SEARCH — lawful aggregator/corpus/search service; primarily discovery/history/routing.
- SEARCH_FALLBACK — public search for discovery when direct registry methods are insufficient.
- BROWSER_AUTOMATION — rendered-browser retrieval only when technically necessary and permitted; not default V1.
- PLATFORM_OFFICIAL_API — official social/platform API where available and permitted; optional sensor.
- MANUAL_REFERENCE_ONLY — analyst reference with no automated collection.

### C3. Default Preference Order
1. API_OFFICIAL
2. FEED_RSS_ATOM / SITEMAP
3. OPEN_DATA_DOWNLOAD
4. DIRECT_HTML
5. AGGREGATOR_API_OR_SEARCH / SEARCH_FALLBACK
6. BROWSER_AUTOMATION
7. MANUAL_REFERENCE_ONLY

Source-specific documented terms override this default order.

### C4. Access State
APPROVED_AUTOMATED;
APPROVED_CONDITIONAL;
DISCOVERY_ONLY;
MANUAL_REFERENCE_ONLY;
BLOCKED;
UNKNOWN_REVIEW_REQUIRED;
TEMPORARILY_DISABLED.

UNKNOWN_REVIEW_REQUIRED is non-permissive for recurring production ingestion.

### C5. Collection Contract Fields
access_method;
endpoint_or_discovery_route;
authentication_required;
documented_api_or_feed;
robots_status where applicable;
terms_status;
rate_limit;
recommended_cadence;
incremental_or_delta_supported;
last_successful_fetch;
last_error;
parser_version;
active_or_inactive.

### C6. Failure Semantics
NETWORK_ERROR;
DNS_ERROR;
TIMEOUT;
HTTP_BLOCKED;
RATE_LIMITED;
AUTH_REQUIRED;
TERMS_OR_RIGHTS_BLOCK;
PARSER_BREAK;
CONTENT_NOT_FOUND;
SOURCE_CHANGED;
UNKNOWN.

A failed source must reduce disclosed coverage; it must not silently disappear from the monitored universe.

## D. Rights and Retention Model

### D1. Core Rule
The right or permission to discover/access material is separate from the right or permission to store, retain, transform, display, redistribute or commercially reuse it.

"Publicly accessible" is not equivalent to "unrestricted commercial reuse."

### D2. Rights Decision States
ALLOW;
ALLOW_WITH_CONDITIONS;
METADATA_DERIVED_ONLY;
TRANSIENT_PROCESSING_ONLY;
DISCOVERY_ONLY;
MANUAL_REFERENCE_ONLY;
BLOCKED;
UNKNOWN_REVIEW_REQUIRED.

UNKNOWN_REVIEW_REQUIRED is non-permissive for recurring production ingestion.

### D3. Retention Classes
- R0_REFERENCE_ONLY — source identity and reference/URL only; no fetched content retained.
- R1_METADATA_DERIVED — permitted metadata, timestamps, hashes/fingerprints, extracted entities/features, classifications and provenance; no full text by default.
- R2_LIMITED_EXCERPT — R1 plus permitted short excerpt/snippet under source-specific limits.
- R3_FULL_CONTENT_GOVERNED — full content only where lawful, necessary and explicitly governed.
- R4_TRANSIENT — content may be processed transiently but not persistently stored beyond the permitted processing window; derived outputs must respect source-specific restrictions.

### D4. Required Rights Fields
terms_url_or_reference;
terms_checked_at;
terms_status;
robots_checked_at where applicable;
robots_status;
access_basis;
automated_access_status;
commercial_use_status;
metadata_retention_status;
full_text_retention_status;
excerpt_status_and_limit;
derived_features_status;
redistribution_status;
attribution_requirement;
required_attribution_text_or_pattern;
rate_limit_or_fair_use_constraint;
retention_expiry_or_revalidation_date;
deletion_requirement;
geographic_or_user_restrictions where relevant;
reviewer;
last_rights_review_date;
rights_notes.

### D5. Default Storage Posture
Unless a named-source contract explicitly supports more:
- prefer R1_METADATA_DERIVED;
- preserve canonical reference/URL, source identity, publication/fetch timestamps and content hash where lawful;
- avoid full-text retention;
- retain only the minimum representation required for provenance, reproducibility, deduplication and analyst review.

### D6. Rights Change Rule
The Source Registry must support revalidation date, rights-state versioning, collection suspension, retention/deletion action where required, and an audit history of who changed the decision and why.

A later restriction does not authorize continued collection merely because prior collection was permitted.

### D7. Attribution and Commercialization Guard
Mandatory attribution must survive into downstream brief/API/export representations.
Commercial-use status must be explicit and must not be inferred from public availability or noncommercial access.

## E. Stage-1 Source Gate
Recurring ingestion of a named source is prohibited until:
- access state is not UNKNOWN_REVIEW_REQUIRED;
- rights decision is recorded;
- retention class is assigned;
- attribution and rate-limit obligations are representable;
- commercial-use restrictions are recorded;
- lineage handling is representable;
- reviewer and review date exist.

## F. Open Work after S0.3A–D
S0.3E — Geographic Relevance Admission.
S0.3F — Named Pilot Source Universe.
S0.3G — Source Registry Contract/schema.

The A–D draft must not be frozen before E–G and benchmark evidence expose whether these definitions are sufficient.
