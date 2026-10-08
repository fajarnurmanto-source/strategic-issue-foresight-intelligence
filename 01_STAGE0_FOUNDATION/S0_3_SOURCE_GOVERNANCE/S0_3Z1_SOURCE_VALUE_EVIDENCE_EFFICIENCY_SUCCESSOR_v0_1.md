# SIFI S0.3Z1 — Source Value & Evidence Efficiency Successor v0.1

Status: WORKING SUCCESSOR — NOT FROZEN

## 1. Purpose

This successor defines how SIFI evaluates the operational and decision value
of lawful candidate sources.

The objective is not maximum data volume.

The objective is:

MAXIMUM DEFENSIBLE ISSUE / FORESIGHT DECISION VALUE

subject to:

- lawful access;
- evidence admissibility;
- privacy;
- reproducibility;
- cost;
- portability;
- dependency risk.

## 2. No Universal Opaque Source Score

SIFI must not prematurely collapse source quality into one universal score.

Source evaluation shall preserve separate dimensions.

A composite score may only be introduced later if:

- dimensions are validated;
- weighting is defensible;
- calibration exists;
- failure semantics are explicit;
- users can inspect the components.

## 3. Source Value Dimensions

Minimum dimensions include:

### Decision Relevance

Does the source materially improve a decision-relevant Issue Object,
trajectory, risk/opportunity assessment, watch condition, or foresight
hypothesis?

### Unique Information Contribution

Does the source add information not already available from other sources?

Repeated or syndicated material has low incremental value.

### Corroboration Contribution

Does the source independently confirm, contradict, or qualify material
claims?

### Lead-Time Contribution

Does the source reveal an issue earlier than the baseline source universe?

### Geographic Contribution

Does the source improve coverage of relevant geographies, cross-local
relationships, or jurisdictional effects?

### Trajectory Contribution

Does the source materially improve detection of:

- emergence;
- acceleration;
- persistence;
- diffusion;
- decline;
- recurrence;
- structural transition?

### Watch-Condition Contribution

Does the source improve the ability to define or evaluate a watch,
escalation, or de-escalation condition?

### Evidence Quality Contribution

Does the source improve:

- authority;
- independence;
- traceability;
- reproducibility;
- contradiction resolution;
- empirical grounding?

### Rights Stability

How stable and explicit are:

- access rights;
- commercial rights;
- retention rights;
- API/platform conditions;
- licence duration;
- revocation risk?

### Operational Burden

What burden is imposed by:

- acquisition;
- parsing;
- maintenance;
- source instability;
- analyst review;
- storage;
- compliance;
- permission renewal?

## 4. Source Cost Model

Source cost should support separate fields for:

- subscription_cost
- api_cost
- acquisition_compute_cost
- storage_cost
- parser_maintenance_cost
- analyst_review_cost
- compliance_cost
- legal_review_cost
- integration_cost
- reliability_maintenance_cost
- permission_renewal_cost

Unknown costs remain UNKNOWN.

Do not silently treat unknown as zero.

## 5. Incremental Source Value

A source should be evaluated against a baseline universe.

Question:

WHAT CHANGES IF THIS SOURCE IS ADDED?

Candidate changes include:

- additional qualified issues;
- earlier detection;
- better corroboration;
- fewer false positives;
- stronger geography coverage;
- better trajectory classification;
- better watch conditions;
- stronger contradiction handling;
- improved actionability.

Raw article count or document count is not sufficient evidence of value.

## 6. Removal Value Test

The inverse test should also be supported:

WHAT CHANGES IF THIS SOURCE IS REMOVED?

If removal causes no material degradation, the source may be redundant.

If removal causes major degradation, the source may be critical or create
dependency risk.

## 7. Evidence Efficiency

Conceptually:

EVIDENCE EFFICIENCY =
INCREMENTAL DECISION IMPROVEMENT
/
TOTAL SOURCE COST

This relationship is initially a decision framework, not a mandatory numeric
ratio.

Do not calculate a pseudo-precise ratio unless both numerator and denominator
are sufficiently defined and measured.

Before numerical validation, allowed forms include:

- qualitative;
- ordinal;
- bounded comparative ranking;
- benchmark-relative comparison.

## 8. Decision Improvement Dimensions

Decision improvement may include:

- issue detection recall improvement;
- issue qualification precision improvement;
- lead-time gain;
- corroboration gain;
- trajectory-quality gain;
- watch-condition gain;
- reduction in false escalation;
- reduction in missed strategic issues;
- decision delta;
- analyst actionability;
- uncertainty reduction.

No single dimension is automatically dominant.

## 9. Source Dependency Risk

A source may be valuable but fragile.

Dependency dimensions include:

- sole-source dependence;
- platform dependence;
- API dependence;
- licence dependence;
- permission dependence;
- geography-specific dependence;
- vendor dependence;
- parser fragility;
- source continuity risk.

High value does not eliminate dependency risk.

## 10. Redundancy vs Resilience

Duplicate information can be wasteful.

Independent alternative sources can be valuable resilience.

SIFI must distinguish:

REDUNDANT DUPLICATION

from:

INDEPENDENT SUBSTITUTION CAPACITY.

## 11. Open / Low-Cost Baseline

SIFI should establish an open or low-cost baseline source universe before
evaluating premium sources.

The baseline should be capable of generating defensible minimum Issue Objects
within the selected coverage profile.

## 12. Premium Source Uplift

Premium or licensed sources should be evaluated by incremental decision
value.

A premium source is not justified merely because it provides:

- more articles;
- more mentions;
- more records;
- a larger historical archive.

Premium uplift must improve meaningful intelligence outcomes.

## 13. No Forced Monetisation Metric

Commercial value is related to but separate from source value.

A source may:

- have high evidence value but poor economics;
- have low acquisition cost but low decision value;
- be commercially attractive but legally unsuitable;
- be legally usable but operationally fragile.

These dimensions must remain visible.

## 14. Point-in-Time Value

Source value may change over time.

SIFI should preserve:

- evaluation time;
- source version/state;
- rights state;
- coverage profile;
- benchmark corpus;
- metric definition;
- comparator universe.

A later source improvement must not be retroactively assumed.

## 15. AI Authority

AI may:

- propose source-value dimensions;
- identify possible redundancy;
- identify potential substitution;
- summarise benchmark differences;
- flag unusual cost/value patterns.

AI may not:

- invent missing costs;
- invent source independence;
- fabricate decision improvement;
- create unsupported source rankings;
- hide uncertainty behind a composite score.

## 16. Implementation Guard

This contract does not authorise:

- source procurement;
- paid API subscription;
- production crawling;
- commercial deployment;
- customer billing.

Implementation Authority remains:

NOT YET AUTHORIZED.

