# SIFI S0.3Z2 — Coverage, Rights Regression & Substitution Contract v0.1

Status: WORKING SUCCESSOR — NOT FROZEN

## 1. Purpose

This contract separates information availability from lawful production
availability and defines regression tests for rights-constrained SIFI.

## 2. Information Coverage

INFORMATION COVERAGE describes what the research/discovery universe could
potentially observe.

It may include material that is:

- production admitted;
- research only;
- rights unresolved;
- otherwise unsuitable for production use.

Information Coverage is not Production Coverage.

## 3. Rights Coverage

RIGHTS COVERAGE describes the portion of relevant information space that SIFI
can lawfully and operationally use for the intended production purpose.

Rights Coverage is constrained by:

- acquisition rights;
- commercial-use rights;
- retention rights;
- privacy;
- profiling;
- platform rules;
- customer/institution authority;
- reproducibility requirements.

## 4. Rights-Constrained Coverage Gap

When valuable information exists but cannot enter production because of
rights/privacy/admission controls, record:

RIGHTS-CONSTRAINED COVERAGE GAP

Do not weaken the applicable rights control merely to improve coverage.

## 5. Other Coverage Gap Classes

Minimum gap classes:

- SOURCE_ABSENCE_GAP
- RIGHTS_CONSTRAINED_GAP
- GEOGRAPHIC_GAP
- TEMPORAL_GAP
- DOMAIN_GAP
- SOURCE_DIVERSITY_GAP
- INDEPENDENCE_GAP
- LANGUAGE_GAP
- TECHNICAL_ACCESS_GAP
- COST_CONSTRAINED_GAP
- PRIVACY_CONSTRAINED_GAP
- REPRODUCIBILITY_GAP

A single gap may have multiple causes.

## 6. Research vs Production Universe

SIFI must be able to compare:

RESEARCH UNIVERSE

against:

PRODUCTION-ADMITTED UNIVERSE.

The comparison must not assume that identical performance is always
possible.

The purpose is to expose degradation caused by lawful constraints.

## 7. Regression Dimensions

Minimum regression dimensions include:

- qualified issue recall;
- qualification precision;
- detection lead time;
- false-positive rate;
- missed strategic issue rate;
- source diversity;
- independent corroboration;
- geographic coverage;
- cross-local detection;
- trajectory classification;
- contradiction detection;
- watch-condition quality;
- escalation/de-escalation quality;
- decision delta;
- analyst actionability.

Where benchmark labels are insufficient, mark the metric:

NOT_YET_MEASURABLE

rather than inventing precision.

## 8. Rights Regression Test

For a benchmark corpus:

1. run or evaluate using the broader lawful research universe;
2. run or evaluate using only production-admitted evidence;
3. compare decision-relevant outputs;
4. classify material degradation;
5. identify rights-constrained gaps;
6. seek lawful substitutes;
7. repeat the comparison.

The test is not permission to ingest prohibited material.

## 9. Source Loss Simulation

SIFI should support hypothetical removal of a production source.

Examples:

- API shutdown;
- licence expiry;
- source closure;
- commercial restriction;
- privacy restriction;
- unaffordable price increase.

Evaluate:

- issues lost;
- lead-time loss;
- geography loss;
- corroboration loss;
- watch-condition degradation;
- resulting dependency risk.

## 10. Source Substitution Map

For each materially important source, record candidate substitutes where
known.

Minimum concepts:

- primary_source_id
- substitute_source_id
- substitution_scope
- substitution_quality
- lead_time_difference
- geography_difference
- rights_difference
- cost_difference
- operational_difference
- unresolved_gap

A substitute need not be equivalent.

## 11. Minimal Viable Source Universe

The Minimal Viable Source Universe is:

the smallest lawful and operationally supportable source set that preserves
the minimum acceptable issue-intelligence capability for a defined
CoverageProfile and domain.

It is not:

the smallest number of sources regardless of performance.

## 12. MVU Qualification

A candidate MVU must be tested for:

- minimum issue recall;
- acceptable false-positive behaviour;
- basic corroboration;
- geographic sufficiency;
- source diversity;
- source independence;
- trajectory sufficiency;
- watch-condition sufficiency;
- rights stability;
- substitution capacity.

Thresholds may remain benchmark-derived and not yet numerically frozen.

## 13. Open-Core Baseline

The first MVU candidate should prefer:

- lawful open sources;
- official structured sources;
- stable low-cost sources;

before premium-source dependency.

This creates a defensible open/low-cost core.

## 14. Premium Uplift Regression

Evaluate premium sources relative to the open-core baseline.

Measure whether premium access improves:

- issue recall;
- lead time;
- geographic detail;
- source diversity;
- corroboration;
- trajectory;
- watch conditions;
- analyst actionability.

Premium access that only increases raw volume without material decision
improvement does not establish value.

## 15. CoverageProfile Portability

Coverage evaluation must remain profile-configurable.

The same source/evidence universe may produce different coverage results for:

- PROFILE-YK;
- PROFILE-SBY;
- other future profiles.

No Yogyakarta-specific core logic is permitted.

## 16. Rights Loss and Portability

A source may be critical in one CoverageProfile but replaceable in another.

Dependency analysis must therefore preserve:

- CoverageProfile;
- geography;
- domain;
- time;
- source-rights state.

## 17. Failure Semantics

Minimum regression outcomes:

- PASS
- PASS_WITH_REVIEW
- DEGRADED_ACCEPTABLE
- RIGHTS_CONSTRAINED_GAP
- SOURCE_DEPENDENCY_BLOCK
- COVERAGE_INSUFFICIENT
- NOT_YET_MEASURABLE

Do not convert a material failure into a passing score through averaging.

## 18. Evidence Preservation

Regression results must identify:

- benchmark corpus version;
- source universe version;
- production universe version;
- rights state;
- metric definitions;
- execution/review time;
- analyst/reviewer;
- unresolved gaps.

## 19. Implementation Guard

This contract defines future validation behaviour.

It does not authorise:

- production ingestion;
- crawler activation;
- paid-source acquisition;
- customer deployment.

Implementation Authority remains:

NOT YET AUTHORIZED.

