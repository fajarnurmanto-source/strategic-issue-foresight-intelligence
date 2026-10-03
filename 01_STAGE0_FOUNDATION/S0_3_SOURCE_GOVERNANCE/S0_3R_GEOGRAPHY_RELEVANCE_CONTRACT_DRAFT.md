# SIFI S0.3R — Geography & Relevance Contract Draft

Status: WORKING DRAFT — NOT FROZEN

## 1. Core Objects
Stage 0 introduces:
1. GeographyNode
2. CoverageProfile
3. SourceCoverageBinding
4. RelevanceAssessment
5. CrossLevelIssueLink

## 2. GeographyNode
Minimum fields:
- geography_id
- canonical_name
- aliases
- geography_level
- parent_geography_id
- country_code
- administrative_code where available
- version
- active_state

## 3. CoverageProfile
CoverageProfile selects the active target geography and its relevance policy.

It must be configuration/data driven and versioned.

## 4. SourceCoverageBinding
A source may bind to zero, one, or many Coverage Profiles.

The binding may define:
- discovery relevance
- editorial/geographic scope
- evidence role
- collection priority
- cadence
- source-pack membership
- active/inactive state

Source binding does NOT mean every item from that source is relevant.

## 5. Evidence Geography
Every Evidence Item should preserve when derivable:
- source_editorial_scope
- source_geography
- event_geography
- mentioned_geographies
- affected_geography
- policy_jurisdiction
- relevance_geography

Unknown geography remains UNKNOWN.

It must not be inferred merely from publisher identity.

## 6. RelevanceAssessment
Minimum fields:
- assessment_id
- evidence_id and/or issue_id
- coverage_profile_id
- relevance_class
- geographic_relation
- relevance_reasons
- supporting evidence
- uncertainty
- contradictory relevance evidence
- rule/model version
- human_review_state
- timestamp

## 7. Relevance Classes
R0 DIRECT
R1 CROSS_LOCAL
R2 PROVINCIAL
R3 NATIONAL
R4 CONTEXTUAL
RX IRRELEVANT

These are categorical and explainable states.

## 8. Admission Logic
An item may become relevant when evidence supports at least one relation:

- event occurs in the active geography;
- affected geography contains the active geography;
- policy jurisdiction applies to the active geography;
- infrastructure/service networks cross into the active geography;
- economic/fiscal mechanisms materially affect the active geography;
- environmental/hazard mechanisms affect the active geography;
- mobility/supply-chain linkage affects the active geography;
- institutional rules apply to the active organization/government;
- approved comparator/context relationship exists.

Mention-only linkage is insufficient for R0-R3.

## 9. Cross-Level Issue Link
Issue Objects may have related parent/child objects across geographic levels.

The system must preserve:
- provenance;
- profile-specific relevance;
- lifecycle differences by geography;
- trajectory differences by geography;
- audit trail.

Similar labels alone must not force a merge.

## 10. Default View Semantics
Operational view:
R0 + R1 + R2 + R3

Exploratory / foresight view:
R0 + R1 + R2 + R3 + R4

RX:
excluded from normal issue views but retained where governance requires it.

## 11. Multi-Profile Invariance Test
Using one unchanged evidence corpus:

Profile A: Kota Yogyakarta
Profile B: DI Yogyakarta
Profile C: Indonesia

must be able to produce different relevant-issue sets without changing
source data or core application code.

## 12. Human Review
Human reviewers must be able to:
- change relevance class;
- correct affected geography;
- correct policy jurisdiction;
- reject false geographic relations;
- explain cross-local relevance;
- preserve pre-change state in audit history.

## 13. Failure Semantics
Explicit states are required for:
- GEOGRAPHY_UNKNOWN
- INSUFFICIENT_RELEVANCE_EVIDENCE
- CONFLICTING_GEOGRAPHIC_EVIDENCE
- PROFILE_RULE_UNAVAILABLE
- CROSS_LEVEL_RELATION_UNRESOLVED

These must not silently collapse into R0 or RX.

## 14. AI Authority
AI may propose:
- geography extraction;
- relevance hypotheses;
- geographic relations.

AI may not silently:
- infer impact from mention alone;
- convert source scope into event scope;
- convert national coverage into local relevance;
- invent causal geographic mechanisms.

