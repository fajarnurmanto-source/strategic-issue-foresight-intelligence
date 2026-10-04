# SIFI S0.3T3 — Profile Invariance & Portability Test Spec v0.1

Status: WORKING TEST CONTRACT

## 1. Goal

Prove that SIFI is geography-configurable rather than Yogyakarta-specific.

## 2. Test Corpus Principle

One immutable evidence corpus is used across all profile evaluations.

The corpus must not be:
- rewritten;
- duplicated;
- re-crawled solely because profile changes;
- manually altered to fit expected results.

## 3. Profiles

Required:

- PROFILE-YK
- PROFILE-DIY
- PROFILE-IDN
- PROFILE-SBY

## 4. Invariants

Across profile switching the following must remain identical:

- evidence_id
- source_id
- source URL / canonical reference
- captured_at
- content fingerprint
- provenance
- event extraction evidence
- retained source facts

## 5. Profile-Dependent Outputs

The following MAY differ:

- relevance_class
- relevance_geography
- geographic_relation
- inclusion/exclusion from operational view
- relevance_reason
- profile-specific Issue membership
- profile-specific watch priority

## 6. Prohibited Behavior

Profile switch must not:
- mutate Evidence Item source truth;
- overwrite another profile's relevance assessment;
- duplicate the source object;
- create geography-specific core code branches;
- treat parent containment as automatic relevance;
- convert R4 into R0-R3 without new evidence.

## 7. Portability Test

Create PROFILE-SBY using configuration only.

PASS requires:

1. GeographyNode for Surabaya exists.
2. Parent relation to Jawa Timur and Indonesia resolves.
3. Profile config loads without core-code modification.
4. Yogyakarta-specific source packs are not automatically inherited.
5. national/shared sources may be reused where rights permit.
6. Surabaya-local source pack can be introduced through configuration.
7. benchmark corpus can be assessed for PROFILE-SBY.
8. Yogyakarta logic does not appear in core relevance rules.

## 8. Same-Corpus Test

Given CASE-01 through CASE-15:

run logically against all four profiles.

PASS requires:
- expected class differences are reproducible;
- every admission has an explanation;
- unresolved cases remain unresolved;
- RX decisions are distinguishable from unknown states.

## 9. Stage-0 Portability Gate

SIFI cannot pass Stage-0 freeze if:

- a second city requires a code fork;
- profile switching changes evidence truth;
- national/local distinction depends on publisher identity;
- geography relevance cannot be reproduced;
- profile-specific decisions overwrite one another.

