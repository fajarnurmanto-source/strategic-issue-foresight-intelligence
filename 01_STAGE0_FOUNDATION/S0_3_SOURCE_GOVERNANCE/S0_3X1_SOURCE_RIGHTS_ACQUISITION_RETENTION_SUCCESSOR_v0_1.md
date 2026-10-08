# SIFI S0.3X1 — Source Rights, Acquisition & Retention Successor v0.1

Status: WORKING SUCCESSOR — NOT FROZEN

## 1. Purpose

This successor extends S0.3U and S0.3W with operationally explicit controls
for lawful source acquisition, content retention, copyright/database-right
risk, point-in-time rights evidence, and source revocation.

It is additive.

It does not replace:

- S0.3A-D Source Governance Core;
- S0.3U Source Registry Contract;
- S0.3U Rights & Access Audit Contract;
- S0.3W Data Rights & Evidence Admission Successor.

## 2. Governing Principle

A source may be:

- technically reachable;
- publicly viewable;
- geographically relevant;
- operationally useful;

and still be unsuitable for recurring production ingestion.

Technical capability does not create authorization.

Public access does not establish:

- automated-access permission;
- retention permission;
- commercial-use permission;
- redistribution permission;
- derived-use permission.

## 3. Acquisition Preference Order

Default acquisition preference:

1. OFFICIAL_API
2. RSS_ATOM
3. PUBLIC_EXPORT_OR_DOWNLOAD
4. SITEMAP_OR_STRUCTURED_FEED
5. PERMITTED_STATIC_HTML
6. BROWSER_ASSISTED_RETRIEVAL

The order is a default governance preference.

Source-specific documented terms, licences, contractual rights, or technical
requirements may alter the usable path.

Browser automation is never evidence of permission.

## 4. Prohibited Acquisition Behaviour

SIFI must not use:

- stolen credentials;
- credentials outside their authorised scope;
- CAPTCHA bypass;
- paywall bypass;
- login bypass;
- anti-bot defeat;
- hidden/private endpoint exploitation;
- circumvention of security controls;
- acquisition from private messages/groups without authority.

If acquisition requires prohibited circumvention:

source route state = BLOCKED
admission zone = Z0

## 5. Source Rights Dimensions

Source rights must remain decomposed.

Minimum dimensions include:

- public_access_state
- authentication_requirement
- automated_access_state
- terms_state
- licence_state
- metadata_fetch_state
- metadata_retention_state
- fulltext_fetch_state
- fulltext_retention_state
- excerpt_state
- derived_use_state
- redistribution_state
- commercial_use_state
- attribution_requirement
- rate_limit_state
- privacy_risk_state
- copyright_risk_state
- database_right_risk_state
- confidentiality_risk_state
- rights_jurisdiction
- privacy_jurisdiction
- source_platform_jurisdiction

UNKNOWN must remain explicit.

Unknown material production rights are non-permissive.

## 6. Registry Extension Fields

S0.3U should be extended additively where necessary to support:

- access_url
- public_access_state
- authentication_requirement
- automated_access_state
- licence_identifier
- licence_url_or_reference
- commercial_use_state
- redistribution_state
- derived_use_state
- fulltext_fetch_state
- fulltext_retention_state
- excerpt_state
- excerpt_limit
- metadata_use_state
- metadata_retention_state
- privacy_risk_state
- copyright_risk_state
- database_right_risk_state
- confidentiality_risk_state
- rights_jurisdiction
- privacy_jurisdiction
- source_platform_jurisdiction
- point_in_time_availability_state
- reproducibility_state
- acquisition_cost_class
- compliance_cost_class
- rights_review_state
- admission_zone
- evidence_of_permission_locator
- permission_effective_from
- permission_effective_to
- last_rights_review_at
- next_rights_review_due_at

This is a successor extension of S0.3U, not a replacement registry.

## 7. HTML / News Content Policy

For ordinary web/news sources, permanent storage of an entire page or article
must not be the default.

Preferred canonical representation should use permitted combinations of:

- canonical URL;
- source identity;
- acquisition timestamp;
- publication timestamp;
- title;
- author/byline where relevant and permitted;
- geographic extraction;
- factual-claim extraction;
- bounded excerpt where permitted;
- fingerprint/hash;
- provenance;
- source-rights state;
- lawful archival/reference locator where available.

Full text may be retained only when:

- the intended retention is permitted;
- relevant terms/licence allow it;
- the retention duration is compatible;
- privacy controls are satisfied;
- downstream use remains within permitted scope.

## 8. Copyright Separation

SIFI must distinguish:

### FACTUAL INFORMATION

Facts, events, measurements, dates, organisations and other factual claims.

### PROTECTED EXPRESSION

The publisher's wording, narrative structure, creative expression,
photographs, illustrations, graphics, and other protected expression.

### DATABASE / COMPILATION RIGHTS

Rights or restrictions applicable to a structured collection, compilation,
database, API corpus, or substantial extraction/reuse.

A right to analyse factual claims must not be silently treated as a right to:

- republish protected expression;
- mirror articles;
- redistribute database contents;
- reproduce substantial parts of a protected compilation.

## 9. Customer-Facing Content Rule

Default production posture:

CITE
→ SUMMARISE
→ LINK

rather than:

COPY
→ MIRROR
→ REPUBLISH.

Customer-visible excerpts must remain within the source-specific permitted
scope.

## 10. Raw Content Retention Classes

Minimum classes:

R0_REFERENCE_ONLY
- source identity and reference only.

R1_METADATA_DERIVED
- metadata, timestamps, hashes, extracted factual features and provenance.

R2_LIMITED_EXCERPT
- R1 plus permitted bounded excerpt.

R3_FULLTEXT_PERMITTED
- full text may be retained under documented applicable rights.

R4_TRANSIENT_PROCESSING
- content may be processed transiently but persistent raw storage is not
  permitted.

The class must not be assigned merely from source family.

## 11. Evidence of Permission

A production-relevant rights decision should preserve evidence sufficient to
explain why the acquisition/use was allowed.

Examples:

- licence page;
- API documentation;
- publisher terms;
- official policy;
- explicit written permission;
- contract;
- public-domain statement;
- applicable open-data licence.

The evidence locator must include an observation/review timestamp.

## 12. Point-in-Time Rights State

SIFI must preserve:

- terms/licence observed at time T;
- rights interpretation at time T;
- reviewer;
- evidence locator;
- effective period where known;
- source-registry version;
- subsequent rights changes.

Current terms must not be retroactively assumed to have applied to historical
acquisition.

## 13. Reproducibility

A source acquisition path should record enough information to determine:

- which source was accessed;
- which endpoint/path was used;
- when it was accessed;
- by which permitted method;
- which rights state applied;
- which parser/extractor version processed it;
- which fingerprint represents the acquired material.

Failure to reproduce a source later does not erase historical provenance.

## 14. Rights Revocation

Revocation triggers include:

- licence expiry;
- terms change;
- explicit permission withdrawal;
- API shutdown;
- access-policy change;
- source removal;
- platform prohibition;
- legal/privacy deletion requirement;
- rights review downgrade.

When production rights are lost:

1. stop new affected production ingestion;
2. record effective time;
3. set source/evidence state to SUSPENDED or REVOKED as appropriate;
4. determine historical retention/deletion treatment;
5. preserve governance history where lawful;
6. report resulting Rights Coverage loss.

No silent continuation is allowed.

## 15. Source Removal Is Not Evidence Erasure

Where lawful retention remains permitted, historical evidence may preserve
its point-in-time provenance even if the source later disappears.

Where deletion or non-retention is required, SIFI must respect that
requirement.

The treatment must be explicit rather than inferred.

## 16. Rights Jurisdiction vs Relevance Geography

The following must remain distinct:

- source_geography
- event_geography
- affected_geography
- policy_jurisdiction
- relevance_geography
- rights_jurisdiction
- privacy_jurisdiction
- source_platform_jurisdiction

A source may be relevant in one geography but governed by rights or platform
rules associated with another jurisdiction.

## 17. Social-Media Boundary

This contract does not fully qualify social-platform acquisition.

Social sources remain subject to a later dedicated control covering:

- platform/API terms;
- account-level access;
- automation;
- retention;
- redistribution;
- personal data;
- profiling.

Until that control exists, social expansion is not authorised.

## 18. Cost Visibility

Acquisition governance should be able to represent:

- subscription cost;
- API cost;
- crawl/compute cost;
- storage cost;
- parser maintenance cost;
- analyst review cost;
- compliance cost;
- source instability cost.

These fields do not yet form a universal score.

They will support later Source Value and Evidence Efficiency analysis.

## 19. Production Admission Relationship

A source rights review does not itself create production evidence.

The chain remains:

SOURCE RIGHTS REVIEW
→ SOURCE ELIGIBILITY
→ EVIDENCE-SPECIFIC ADMISSION
→ Z2 PRODUCTION EVIDENCE
→ ISSUE QUALIFICATION
→ Z3 DERIVED OUTPUT.

## 20. Implementation Guard

This contract defines governance only.

It does not authorise:

- new crawler implementation;
- browser automation;
- expanded social collection;
- paid production ingestion;
- personal profiling.

Implementation Authority remains:

NOT YET AUTHORIZED.

