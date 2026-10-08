# SIFI S0.5C3 — Issue Object Acceptance Matrix v0.1

Status: WORKING BENCHMARK CONTRACT — NOT FROZEN

These IO01–IO20 cases test the S0.5C contract.

They are not claims of executed product capability.

## IO01 — Persistent Identity

Input:
One Issue persists across several acquisition cycles.

Expected:
issue_id remains stable while issue_version_id may change.

Forbidden:
Creating a new Issue solely because new publications arrive.

## IO02 — High Attention, Weak Evidence

Input:
Large publication volume exists but empirical support remains weak.

Expected:
Attention state may be high while evidence state remains limited.

Forbidden:
Automatic severity or empirical-condition promotion.

## IO03 — Low Attention, Material Evidence

Input:
Few publications accompany strong qualified evidence of a material condition.

Expected:
Issue Object can preserve high material significance despite low attention.

Forbidden:
Dismissal based solely on low mention volume.

## IO04 — Contradictory Evidence

Input:
Independent qualified evidence disagrees materially.

Expected:
Contradiction and uncertainty remain visible.

Forbidden:
Averaging contradiction into a single confidence number.

## IO05 — Coverage Gap

Input:
Relevant geography has weak monitored-source coverage.

Expected:
Coverage limitation is disclosed.

Forbidden:
Issue absence inference.

## IO06 — Collector Failure

Input:
A major source fails during the observation period.

Expected:
Coverage context records collection failure.

Forbidden:
Automatic interpretation of volume decline as resolution.

## IO07 — Source-Universe Expansion

Input:
New sources are added mid-period.

Expected:
Comparability limitation is recorded.

Forbidden:
Automatic issue acceleration conclusion.

## IO08 — Cross-Jurisdiction Issue

Input:
Event occurs outside the profile but affects the profile.

Expected:
event_geography, affected_geography, relevance_geography and
policy_jurisdiction remain distinct.

## IO09 — Authority Mismatch

Input:
Issue is locally relevant but legal competence is external.

Expected:
Issue remains relevant.
Authority state remains correctly external or unresolved.

Forbidden:
Invented local authority.

## IO10 — Authority Without Demonstrated Capability

Input:
Legal mandate exists but practical operational capacity is unverified.

Expected:
Authority and operational capability remain distinct.

## IO11 — Z1 Early Signal

Input:
Research-only material identifies a potentially important development.

Expected:
May appear as research signal / verification trigger.

Forbidden:
Production conclusion supported solely by Z1 material.

## IO12 — Re-emergence

Input:
An inactive Issue later produces related Events.

Expected:
Persistent identity and lifecycle history allow REEMERGED interpretation.

## IO13 — Merge

Input:
Two Issue candidates are qualified as the same persistent Issue.

Expected:
Merge lineage is preserved.

Forbidden:
Deleting predecessor identities.

## IO14 — Split

Input:
One Issue is later shown to contain two materially distinct problems.

Expected:
Split lineage preserves predecessor and successor relationships.

## IO15 — Narrative / Condition Divergence

Input:
Public sentiment improves while qualified condition evidence deteriorates.

Expected:
Narrative and Condition trajectories diverge explicitly.

Forbidden:
Narrative improvement = condition improvement.

## IO16 — Historical Cutoff

Input:
Material corrective evidence becomes available after analytical cutoff T.

Expected:
Historical Issue version at T excludes later-known evidence.

A later version may incorporate it.

## IO17 — Watch Condition

Input:
A Strategic Hypothesis has a specific invalidation signal.

Expected:
Watch and invalidation condition remain linked to the hypothesis.

Forbidden:
Hypothesis treated as established forecast.

## IO18 — Analytical Response Candidate

Input:
Evidence is insufficient for conclusion but potentially material.

Expected:
VERIFY_CLAIM, REQUEST_ADDITIONAL_EVIDENCE, HUMAN_REVIEW_REQUIRED,
DEFER_CONCLUSION or ABSTAIN may be represented as appropriate.

Forbidden:
Automatic institutional action.

## IO19 — Rights-Safe Derived Output

Input:
Issue contains both Z1 research signals and Z2 production evidence.

Expected:
Production findings use only admissible supporting evidence.
Research signal lineage remains distinguishable.

Forbidden:
Z1 laundering through derived Issue output.

## IO20 — Reproducibility Boundary

Input:
Reviewer requests Issue state as known at a prior cutoff.

Expected:
Schema contains sufficient conceptual fields for eventual reconstruction.

Forbidden:
Claiming reconstruction is already functionally verified merely because
fields exist.

