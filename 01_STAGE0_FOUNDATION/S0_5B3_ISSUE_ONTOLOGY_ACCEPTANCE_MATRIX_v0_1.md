# SIFI S0.5B3 — Issue Ontology Acceptance Matrix v0.1

Status: WORKING BENCHMARK CONTRACT — NOT FROZEN

These B01–B20 cases test S0.5B ontology semantics.

They do not replace S01–S20 adversarial benchmarks required in S0.6A.

## B01 — Many Publications, One Event

Input:
Fifty publications describe the same verified occurrence.

Expected:
Publication volume remains 50.
Canonical Event count may remain 1.

Forbidden:
Automatically creating 50 Events.

## B02 — Multiple Events, One Recurring Issue

Input:
Related service disruptions recur over several months.

Expected:
Distinct Events may be linked to one recurring Issue where continuity is
supported.

Forbidden:
Treating every Event as an unrelated Issue by default.

## B03 — Similar Topic, Different Issues

Input:
Two Events share keywords but concern different mechanisms and affected
systems.

Expected:
Separate Issue identities may be retained.

Forbidden:
Merge solely from semantic similarity.

## B04 — Narrative Spike

Input:
Media attention sharply increases after a press conference without new
empirical-condition evidence.

Expected:
ATTENTION_TRAJECTORY and/or NARRATIVE_TRAJECTORY may change.

Forbidden:
Automatic worsening CONDITION_TRAJECTORY.

## B05 — Empirical Worsening Without Media Spike

Input:
Qualified measurements worsen while publication volume remains flat.

Expected:
CONDITION_TRAJECTORY may worsen independently of attention.

Forbidden:
Concluding no change because media attention is unchanged.

## B06 — Allegation Without Established Event

Input:
Several publications repeat one unverified allegation.

Expected:
Narrative/reporting may be represented.

Forbidden:
Automatic establishment of Event or Empirical Condition.

## B07 — Cross-Jurisdiction Event

Input:
An Event physically occurs outside Kota Yogyakarta but materially affects
services or residents inside the profile.

Expected:
event_geography and affected/relevance geography remain distinct.

Forbidden:
Discarding the Event solely because physical occurrence is outside target.

## B08 — Authority Mismatch

Input:
An Issue materially affects a city but primary legal competence is provincial
or national.

Expected:
Issue relevance remains visible.

Direct municipal authority is not invented.

## B09 — Authority vs Capability

Input:
An institution has legal mandate but lacks evidence of direct operational
response capacity.

Expected:
COMPETENT_AUTHORITY and OPERATIONAL_RESPONDER remain distinct.

## B10 — Relevant but Not Actionable

Input:
A strategically important Issue affects the user but no legitimate immediate
operational action is established.

Expected:
Issue may remain relevant/watch-worthy.

Forbidden:
Automatic escalation into policy action.

## B11 — Issue Merge

Input:
Two Issue candidates are later shown to represent the same persistent
underlying problem.

Expected:
Merge preserves predecessor lineage and rationale.

Forbidden:
Destructive deletion of historical identities.

## B12 — Issue Split

Input:
One Issue cluster is shown to contain two materially distinct mechanisms.

Expected:
Split preserves predecessor/successor lineage.

Forbidden:
Silent historical rewrite.

## B13 — Correction

Input:
Material evidence supporting an Issue interpretation is corrected.

Expected:
Issue history and prior interpretation remain reconstructable.

Forbidden:
Retroactively rewriting the original known-at-T state.

## B14 — Re-emergence

Input:
An inactive Issue later produces related but non-identical Events.

Expected:
REEMERGED may be considered when continuity is supported.

Forbidden:
Requiring identical Events for recurrence.

## B15 — Structural Classification

Input:
Repeated publications occur without evidence of persistent underlying
condition.

Expected:
STRUCTURAL is not established from publication persistence alone.

## B16 — Correlation

Input:
Two trends move together.

Expected:
May form a hypothesis requiring further evidence.

Forbidden:
Automatic causal conclusion.

## B17 — Competing Hypotheses

Input:
Two plausible mechanisms explain the same Issue pattern.

Expected:
Both may remain visible with evidence, contradictions, and uncertainty.

Forbidden:
Silent selection without qualification.

## B18 — Weak Signal

Input:
One unusual but potentially important observation appears.

Expected:
May trigger monitoring or verification.

Forbidden:
Automatic promotion into qualified Strategic Issue.

## B19 — Narrative / Empirical Divergence

Input:
Public sentiment improves while qualified condition indicators deteriorate.

Expected:
Narrative and Empirical Condition remain separately represented.

Forbidden:
Using sentiment improvement as proof of condition improvement.

## B20 — S0.5B Boundary

Input:
Ontology semantics are complete enough to describe Event and Issue
relationships.

Expected:
S0.5C remains responsible for persistent Issue Object schema.
S0.5D remains responsible for transition authority.

Forbidden:
Treating S0.5B as implementation authorization.

