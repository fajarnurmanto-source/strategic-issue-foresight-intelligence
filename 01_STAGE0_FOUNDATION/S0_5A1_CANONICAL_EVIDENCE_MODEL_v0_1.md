# SIFI S0.5A1 — Canonical Evidence Model v0.1

Status: WORKING CONTRACT — NOT FROZEN

Canonical predecessor anchor:

f6b198abb935a057c4320ed827a9aab622b4b6be

## 1. Purpose

This contract defines the canonical epistemic layer between admitted source
material and future Event / Issue qualification.

It formalises:

SOURCE
→ PUBLICATION / CONTENT ITEM
→ CLAIM
→ EVIDENCE ITEM
→ downstream EVENT / ISSUE reference.

It does not implement ingestion, databases, clustering, scoring, or AI
pipelines.

## 2. Predecessor Reuse

The following controls are reused and must not be duplicated:

### REUSE

- Source Object / Source Registry: S0.3U.
- source rights and acquisition controls: S0.3U / S0.3X.
- Z0 / Z1 / Z2 / Z3 admission: S0.3W.
- privacy / profiling controls: S0.3Y.
- CoverageProfile and geographic relevance: S0.3R–T.
- Information Coverage vs Rights Coverage: S0.3Z.
- source revocation controls: S0.3W / X / Z.

### HARDEN

- source provenance;
- source independence;
- syndication lineage;
- contradiction;
- point-in-time evidence reconstruction;
- coverage / observability disclosure.

### EXTEND

- Publication / Content Item;
- Claim;
- canonical Evidence Item;
- Claim–Evidence relation;
- claim-type-appropriate evidence;
- explicit evidence insufficiency semantics.

## 3. Permanent Epistemic Invariants

### I1

SOURCE AUTHORITY ≠ EVIDENCE STRENGTH.

An authoritative source may support some claim types strongly while remaining
insufficient for other claim types.

### I2

SOURCE COUNT ≠ INDEPENDENT CORROBORATION.

Copied, syndicated, mirrored, or common-origin publications do not become
independent evidence merely because the publication count is high.

### I3

RIGHTS ADMISSION ≠ FACTUAL TRUTH.

Z2 means the material may be used for the intended production purpose.

Z2 does not mean the content is true.

### I4

MEDIA ATTENTION ≠ REAL-WORLD PREVALENCE.

Publication volume may support a claim about detected attention within the
monitored source universe.

It does not by itself establish population incidence or prevalence.

### I5

ALLEGATION ≠ ESTABLISHED CONDITION.

A sourced allegation may establish that an allegation exists.

It does not automatically establish the alleged condition.

### I6

OFFICIAL STATEMENT ≠ INDEPENDENT EMPIRICAL VERIFICATION.

An official statement may be authoritative evidence of an institution's
position or assertion.

Its underlying empirical content may still require independent evidence.

### I7

NO OBSERVED SIGNAL ≠ EVIDENCE OF ABSENCE.

Absence of detected material is not evidence that the underlying event,
condition, or issue is absent unless a defensible observation model permits
that conclusion.

### I8

GEOGRAPHIC RELEVANCE ≠ INSTITUTIONAL AUTHORITY.

Evidence that an event affects a geography does not establish which
institution has legal or operational authority.

## 4. Canonical Object Boundary

### 4.1 Source Object

Source Object is already governed by S0.3U.

S0.5A does not recreate it.

Canonical reference:

source_id

The Source Object identifies the publisher, institution, platform, dataset
provider, or other admitted information origin.

### 4.2 Publication / Content Item

A Publication / Content Item is a bounded source representation such as:

- article;
- bulletin;
- report;
- legal document;
- API record;
- dataset record;
- public notice;
- transcript;
- structured observation record.

A content item is not itself synonymous with a factual finding.

### 4.3 Claim

A Claim is an attributable proposition or assertion whose support,
contradiction, interpretation, and evidence status may be evaluated.

A single content item may contain multiple claims.

The same claim may appear in multiple content items.

### 4.4 Evidence Item

An Evidence Item is a traceable observation or information unit admitted for
a defined analytical use.

An Evidence Item must state what claim type it can support and under what
limitations.

### 4.5 Event / Issue Boundary

S0.5A may reference candidate events or issues.

S0.5A does not define canonical Event identity, Issue identity, clustering,
merge/split semantics, or lifecycle rules.

Those are owned by S0.5B and S0.5C.

A publication must never silently become an Event.

An Evidence Item must never silently become an Issue.

## 5. Publication / Content Item Contract

Minimum conceptual fields:

- content_id
- source_id
- source_registry_version
- canonical_locator
- content_type
- title_or_label
- author_or_issuer
- publication_time
- source_available_time
- retrieved_at
- content_fingerprint
- parent_content_id
- syndication_origin_id
- acquisition_method
- parser_version
- rights_admission_reference
- admission_zone
- retention_class
- coverage_profile_context
- provenance_state
- correction_state
- supersedes_content_id
- content_notes

Fields may be null only when absence is explicit and semantically valid.

## 6. Claim Contract

Minimum conceptual fields:

- claim_id
- content_id
- source_id
- claimant_identity_or_role
- normalized_claim
- claim_type
- claim_scope
- claim_geography
- claimed_event_time
- attribution_state
- evidence_state
- uncertainty_state
- contradiction_state
- review_state
- first_observed_at
- last_reviewed_at
- supersedes_claim_id
- correction_reference
- claim_notes

The normalized claim must not silently broaden the meaning of the source
assertion.

## 7. Minimum Claim Types

S0.5A defines the following minimum analytical claim families:

### CT-01 — REPORTING_OR_ATTENTION

A claim that an issue, event, narrative, or subject is being published,
reported, discussed, or observed within the monitored source universe.

### CT-02 — ATTRIBUTED_ASSERTION

A claim that a named or identifiable actor, institution, or source asserted
something.

### CT-03 — EVENT_OCCURRENCE

A claim that a real-world occurrence happened.

### CT-04 — EMPIRICAL_CONDITION

A claim that a measurable or observable real-world condition exists.

### CT-05 — FREQUENCY_OR_PREVALENCE

A claim about how frequently something occurs or how prevalent it is in a
defined population or denominator.

### CT-06 — IMPACT_OR_SEVERITY

A claim about consequence magnitude, affected population, damage, exposure,
or material severity.

### CT-07 — NARRATIVE_OR_PERCEPTION

A claim about expressed perception, framing, narrative, or public discourse.

### CT-08 — INSTITUTIONAL_AUTHORITY

A claim that an institution has relevant legal mandate or jurisdiction.

### CT-09 — OPERATIONAL_CAPABILITY

A claim that an institution or actor has practical capability to respond.

### CT-10 — CAUSAL_OR_STRUCTURAL

A claim concerning drivers, mechanisms, causal relationships, or structural
explanation.

### CT-11 — FORESIGHT_HYPOTHESIS

A proposition about potential future development, risk, opportunity,
scenario, or mechanism that remains explicitly hypothetical.

These claim types are Stage0 semantic controls.

They do not require identical engineering enums in later implementation.

## 8. Evidence Item Contract

Minimum conceptual fields:

- evidence_id
- source_id
- content_id
- evidence_kind
- evidence_locator
- observed_feature_or_fact
- supported_claim_ids
- contradicted_claim_ids
- evidence_relation
- claim_support_sufficiency
- source_authority_context
- source_independence_state
- origin_lineage_id
- corroboration_state
- contradiction_state
- coverage_profile_id
- observability_state
- coverage_limitation
- source_geography
- event_geography_reference
- affected_geography_reference
- relevance_geography_reference
- policy_jurisdiction_reference
- observation_time
- publication_time
- source_available_time
- retrieved_at
- qualified_at
- decision_cutoff_eligibility
- point_in_time_state
- source_registry_version
- admission_zone
- evidence_admission_reference
- privacy_clearance_state
- parser_or_extractor_version
- extraction_method
- human_review_state
- uncertainty_note
- correction_state
- supersedes_evidence_id
- provenance_hash_or_fingerprint
- evidence_notes

Event and Issue references remain optional downstream references until S0.5B
and S0.5C establish their canonical contracts.

## 9. Evidence Kinds

Minimum evidence-kind semantics may include:

- SOURCE_ASSERTION_RECORD
- DIRECT_OBSERVATION
- ADMINISTRATIVE_RECORD
- MEASUREMENT
- STRUCTURED_DATA_RECORD
- RESEARCH_FINDING
- MEDIA_OBSERVATION
- DOCUMENTARY_RECORD
- DERIVED_FEATURE
- OTHER_REVIEW_REQUIRED

Evidence kind does not determine truth automatically.

## 10. Claim–Evidence Relation

Evidence and claim are many-to-many.

One Evidence Item may bear on multiple claims.

One Claim may require multiple Evidence Items.

Minimum evidence relation states:

- SUPPORTS
- PARTIALLY_SUPPORTS
- CONTRADICTS
- CONTEXT_ONLY
- DOES_NOT_BEAR
- INSUFFICIENT_TO_ASSESS

`SUPPORTS` means supports the stated claim within declared scope.

It does not grant permission to broaden the claim.

## 11. Claim Support Sufficiency

Minimum semantic states:

- UNASSESSED
- UNSUPPORTED
- INSUFFICIENT_EVIDENCE
- PARTIALLY_SUPPORTED
- SUPPORTED_WITH_LIMITATIONS
- CORROBORATED
- DISPUTED
- CONTRADICTED
- NOT_EVALUABLE

CORROBORATED requires materially independent support.

Multiple same-origin publications do not satisfy independent corroboration.

These are claim-specific states, not universal source-quality scores.

## 12. No Universal Evidence Score

SIFI must preserve separate dimensions for:

- source authority;
- source independence;
- claim support;
- empirical corroboration;
- contradictory evidence;
- coverage quality;
- geographic relevance;
- uncertainty;
- human review;
- rights/admission.

Do not collapse these into one opaque Evidence Score.

A future summary metric requires benchmark validation and inspectable
components.

## 13. Source Independence & Syndication

Minimum independence states:

- INDEPENDENT_ORIGIN
- SAME_ORIGIN
- DERIVED_OR_SYNDICATED
- COMMON_UPSTREAM_SOURCE
- INDEPENDENCE_UNKNOWN

Source independence is evaluated at the evidence-origin level.

Different URLs, domains, or accounts do not automatically establish
independence.

Publication volume and independent-origin count must remain separate
measures.

## 14. Corroboration Rule

Corroboration must identify:

- claim being corroborated;
- supporting evidence;
- independent origin;
- supported portion of the claim;
- unsupported portion;
- contradictions;
- limitations.

Partial corroboration must not silently elevate an entire compound claim.

## 15. Contradiction Rule

Material contradictory evidence must remain visible.

SIFI must not silently choose one source and discard the other.

A contradiction record must be able to identify:

- claim_id;
- supporting evidence;
- contradictory evidence;
- independence context;
- temporal context;
- unresolved assumptions;
- review state;
- eventual disposition;
- correction history.

Contradiction may increase uncertainty or trigger human review.

## 16. Observability & Missingness

SIFI must distinguish information absence from real-world absence.

Minimum semantic states:

### OBS-01 — NO_OBSERVED_SIGNAL

No relevant signal was observed in the monitored universe.

This is not proof of real-world absence.

### OBS-02 — INSUFFICIENT_COVERAGE

Available monitoring coverage is insufficient for a reliable negative
inference.

### OBS-03 — COLLECTION_FAILED

Expected collection did not occur or materially failed.

Observed decline must not be interpreted as issue decline.

### OBS-04 — OBSERVATION_NOT_COMPARABLE

The observation universe materially changed, making direct comparison
unsafe.

Examples:

- new sources added;
- sources removed;
- collection method changed;
- historical backfill introduced.

### OBS-05 — EVIDENCE_INCOMPLETE

Some relevant evidence exists, but it is incomplete for the intended claim.

### OBS-06 — QUALIFIED_NEGATIVE_EVIDENCE

A defensible negative observation exists because the observation process,
scope, denominator, and expected detection conditions support such an
inference.

QUALIFIED_NEGATIVE_EVIDENCE requires explicit methodological justification.

These are semantic states.

S0.6 benchmark work will determine their practical acceptance behaviour.

## 17. Claim-Type-Appropriate Evidence

Evidence sufficiency must be evaluated relative to claim type.

Examples:

A Z2 news article can support:

REPORTING_OR_ATTENTION

and may support:

ATTRIBUTED_ASSERTION

while remaining insufficient for:

FREQUENCY_OR_PREVALENCE,
IMPACT_OR_SEVERITY,
or CAUSAL_OR_STRUCTURAL claims.

A measurement dataset may support an EMPIRICAL_CONDITION while remaining
irrelevant to public NARRATIVE_OR_PERCEPTION.

No source family receives universal evidentiary authority.

## 18. Geographic Integration

S0.5A reuses existing geography architecture.

It does not collapse:

- source geography;
- event geography;
- affected geography;
- relevance geography;
- policy jurisdiction;
- rights jurisdiction.

Evidence may carry references to these fields.

Their downstream issue interpretation remains governed by S0.5B / C.

## 19. Institutional Claim Guard

A geographic relationship is insufficient evidence for:

INSTITUTIONAL_AUTHORITY

or:

OPERATIONAL_CAPABILITY.

Authority claims require appropriate mandate or jurisdiction evidence.

Operational-capability claims require evidence about practical response
capacity.

Legal authority and operational capability remain distinct claim types.

## 20. Temporal Model

Where available, preserve separately:

- claimed event time;
- observation time;
- publication time;
- source-available time;
- retrieval time;
- qualification/review time;
- decision/brief cutoff;
- correction/revision time.

Later-known facts must not leak into historical evidence reconstruction.

## 21. Point-in-Time Eligibility

For a decision cutoff T, evidence may support historical reconstruction only
if:

- it was available or lawfully knowable by T;
- its rights/admission state permitted the intended use at T;
- the relevant evidence version existed at T;
- later corrections are not silently back-propagated.

Later information may create a new version.

It must not rewrite the historical analytical context.

## 22. Correction & Revision Lineage

Corrections must preserve history.

Do not destructively overwrite:

- original publication reference;
- original claim;
- original evidence interpretation;
- historical review state.

Use explicit:

- supersedes;
- corrects;
- retracts;
- invalidates;
- review revision

relationships where appropriate.

Authority to execute these transitions is owned by S0.5D.

## 23. Rights & Evidence Admission Integration

S0.5A reuses S0.3W.

### Z0

No evidence use.

### Z1

May support permitted discovery / research / verification triggering.

Must not support production findings.

### Z2

May become production evidence within permitted scope.

Z2 does not establish truth.

### Z3

Derived output must preserve supporting-evidence lineage and restrictions.

Z1 must never be laundered into production through Z3 derivation.

## 24. Privacy Integration

S0.5A reuses S0.3Y.

Canonical evidence should prefer non-personal or aggregate representation
when identity is unnecessary.

Personal identifiers must not be retained solely because the source
contained them.

Profiling remains P0 by default.

## 25. Human Review Boundary

S0.5A stores human review state but does not define review authority.

S0.5D will define:

- reviewer roles;
- qualification authority;
- correction authority;
- override authority;
- merge/split authority;
- suspend/revoke/reopen authority.

No review state may imply authority that S0.5D has not yet granted.

## 26. AI Authority

AI may:

- propose claim extraction;
- propose claim type;
- propose evidence relations;
- detect possible syndication;
- flag contradiction;
- propose observability limitations;
- propose uncertainty notes.

AI may not:

- create source rights;
- convert Z1 to Z2;
- invent independent corroboration;
- convert media attention into prevalence;
- convert allegation into fact;
- establish legal authority from geography;
- fabricate causal evidence;
- rewrite historical evidence;
- autonomously make binding issue decisions.

## 27. Downstream Dependency

S0.5B must consume this evidence model to define:

- Event;
- Issue;
- Narrative / Perception;
- Empirical Condition;
- Strategic Hypothesis;
- Issue identity / recurrence / merge / split.

S0.5C must consume both S0.5A and S0.5B to define the persistent Issue Object.

S0.5D must govern human authority over material epistemic transitions.

S0.6 must execute benchmark tests before these contracts can be treated as
verified implementation behaviour.

## 28. Implementation Guard

This is a semantic and governance contract only.

It does not authorize:

- database schema implementation;
- crawler development;
- extraction pipeline implementation;
- embedding/vector infrastructure;
- issue clustering;
- scoring engines;
- dashboards;
- automated foresight;
- social collection;
- production deployment.

Implementation Authority remains:

NOT YET AUTHORIZED.

