# SIFI S0.5A3 — Evidence Model Acceptance Matrix v0.1

Status: WORKING BENCHMARK CONTRACT — NOT FROZEN

These E01–E20 cases test the S0.5A semantic contract.

They do not replace the S01–S20 adversarial benchmark set required in S0.6A.

## E01 — Publication Does Not Equal Truth

Input:
One admitted article asserts an event occurred.

Expected:
Publication/content and Claim are created as distinct conceptual objects.

Forbidden:
Treating the publication itself as proof of the underlying event.

## E02 — Repeated Syndication

Input:
Twenty publications reproduce one original report.

Expected:
Publication count = 20 may be preserved.

Independent-origin count must not become 20.

## E03 — Attention vs Prevalence

Input:
A topic receives a large increase in news coverage.

Expected:
May support REPORTING_OR_ATTENTION.

Forbidden:
Automatic FREQUENCY_OR_PREVALENCE conclusion.

## E04 — Attributed Statement

Input:
An official states that a service problem has been resolved.

Expected:
Evidence may support ATTRIBUTED_ASSERTION.

Forbidden:
Automatically treating the underlying condition as empirically resolved.

## E05 — Allegation

Input:
An article reports an unverified allegation.

Expected:
The allegation and its attribution remain representable.

Forbidden:
Promotion into established EVENT_OCCURRENCE solely from the allegation.

## E06 — Empirical Measurement

Input:
An admitted measurement dataset records a material condition.

Expected:
May support EMPIRICAL_CONDITION within measurement scope.

Forbidden:
Automatic population-wide extrapolation beyond scope.

## E07 — Prevalence Without Denominator

Input:
Thirty admitted reports describe similar incidents.

Expected:
May support detected reports and candidate recurrence investigation.

Forbidden:
Population prevalence estimate without defensible denominator/model.

## E08 — Sentiment vs Severity

Input:
Highly negative public commentary surrounds an issue.

Expected:
May support NARRATIVE_OR_PERCEPTION.

Forbidden:
Automatic IMPACT_OR_SEVERITY conclusion.

## E09 — Independent Contradiction

Input:
Two materially independent Evidence Items disagree.

Expected:
Contradiction remains explicit.
Review/uncertainty may be triggered.

Forbidden:
Silent selection of one source as truth.

## E10 — Research-Only Early Signal

Input:
A Z1 source provides an important early signal.

Expected:
Signal may trigger verification.

Forbidden:
Using Z1 as production evidence.

## E11 — Collection Failure

Input:
A priority source fails during a monitoring cycle.

Expected:
COLLECTION_FAILED.

Forbidden:
Interpreting lower observed volume as real-world decline.

## E12 — Zero Observation Under Weak Coverage

Input:
No relevant signal is observed in a poorly covered geography.

Expected:
INSUFFICIENT_COVERAGE and/or NO_OBSERVED_SIGNAL.

Forbidden:
Claiming issue absence.

## E13 — Source-Universe Change

Input:
Several new sources are added between period A and period B.

Expected:
OBSERVATION_NOT_COMPARABLE may apply unless comparison is adjusted.

Forbidden:
Automatic interpretation of higher volume as acceleration.

## E14 — Historical Time Leakage

Input:
A correction becomes available after the original analytical cutoff.

Expected:
Historical reconstruction excludes the later correction from the original
known-at-T evidence state.

A new version may incorporate it.

## E15 — Correction Lineage

Input:
A publisher retracts or corrects a material claim.

Expected:
Original Claim/Evidence history remains traceable.

Correction creates explicit lineage.

Forbidden:
Destructive silent overwrite.

## E16 — Geography Does Not Establish Authority

Input:
A major event affects Kota Yogyakarta.

Expected:
Geographic relevance may be established independently.

Forbidden:
Automatically assigning municipal legal authority.

## E17 — Authority Does Not Establish Capability

Input:
An institution has formal legal responsibility.

Expected:
INSTITUTIONAL_AUTHORITY may be supported.

Forbidden:
Automatically asserting practical OPERATIONAL_CAPABILITY.

## E18 — Correlation vs Causality

Input:
Two indicators rise during the same period.

Expected:
May support co-occurrence.

Forbidden:
CAUSAL_OR_STRUCTURAL conclusion without additional evidence.

## E19 — Foresight Hypothesis

Input:
Evidence suggests a plausible future risk.

Expected:
Represent as FORESIGHT_HYPOTHESIS with assumptions, uncertainty, alternative
explanation, watch and invalidation conditions.

Forbidden:
Presenting it as established future fact or fabricated probability.

## E20 — Downstream Object Boundary

Input:
Multiple claims and Evidence Items appear related.

Expected:
S0.5A preserves evidence relationships and optional downstream references.

Forbidden:
S0.5A silently creating canonical Event or Issue identity before S0.5B/C.

