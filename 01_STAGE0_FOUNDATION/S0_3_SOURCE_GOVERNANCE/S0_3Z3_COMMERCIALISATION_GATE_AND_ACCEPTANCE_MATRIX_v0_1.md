# SIFI S0.3Z3 — Commercialisation Gate & Acceptance Matrix v0.1

Status: WORKING CONTROL — NOT FROZEN

## 1. Purpose

Define minimum governance conditions before SIFI may be considered eligible
for commercial production implementation.

Passing this contract does not itself activate production.

## 2. Commercialisation Gate

Commercial production must remain blocked until the following control
families are sufficiently satisfied:

### G1 — Source Registry

Canonical source identity and source-rights state exist.

### G2 — Rights Admission

Z0 / Z1 / Z2 / Z3 controls exist and UNKNOWN is non-permissive.

### G3 — Acquisition Policy

Permitted acquisition hierarchy and prohibited bypass routes are explicit.

### G4 — Retention / Copyright / Database Rights

Fulltext, excerpt, metadata, derived use and database reuse are separately
governed.

### G5 — Privacy

Personal-data purpose, minimisation, retention and risk controls exist.

### G6 — Profiling

Profiling is P0 by default and higher profiling levels are governed.

### G7 — Social Platforms

Social collection is OFF by default and requires platform-specific
qualification.

### G8 — Institutional Firewall

Employment or institutional access does not become venture access without
independent authorisation.

### G9 — Customer Isolation

Private customer data cannot silently enter global SIFI data or other
customer contexts.

### G10 — Geography Portability

Rights and relevance remain separate and CoverageProfile logic remains
geography configurable.

### G11 — Information vs Rights Coverage

Rights-constrained coverage loss is measurable or explicitly classifiable.

### G12 — Source Dependency / Substitution

Material single-source dependencies and candidate substitutes are known.

### G13 — Evidence Efficiency

Source value can be compared against cost without forced pseudo-precision.

### G14 — Research vs Production Regression

Production-admitted universe can be evaluated against the research universe.

### G15 — Revocation

Rights loss, permission expiry and source shutdown have explicit operational
consequences.

### G16 — Point-in-Time Reconstruction

SIFI can preserve what rights/evidence state applied at time T.

### G17 — Regulatory / Registration Review

Applicable legal, regulatory, registration, contractual, tax, security,
privacy, platform, and electronic-system obligations must be reviewed for
the intended deployment model and jurisdiction.

No universal assumption is made at Stage0 about which registrations or
obligations apply.

### G18 — Commercial Economics

Expected revenue model, source costs, infrastructure costs, compliance
costs, analyst costs and customer-support burden must be economically
reviewable.

## 3. Hard Commercial Blocks

Commercial production remains blocked if any applicable condition exists:

- prohibited acquisition path;
- material commercial rights UNKNOWN;
- required permission missing;
- prohibited fulltext retention;
- unresolved material privacy risk;
- unapproved profiling;
- founder/employment-privileged access without independent authorisation;
- customer-data isolation absent;
- evidence materially untraceable;
- rights revocation ignored;
- unacceptable rights-constrained coverage gap;
- critical source dependency without accepted treatment;
- applicable deployment obligations not reviewed.

No averaged score may override a hard block.

## 4. Commercial Gate Outcomes

Minimum outcomes:

- NOT_READY
- READY_FOR_CONTROLLED_PILOT_WITH_REVIEW
- READY_FOR_LIMITED_PRODUCTION
- BLOCKED_RIGHTS
- BLOCKED_PRIVACY
- BLOCKED_COVERAGE
- BLOCKED_DEPENDENCY
- BLOCKED_ECONOMICS
- BLOCKED_REGULATORY_REVIEW
- DEFERRED

Stage0 does not grant READY_FOR_LIMITED_PRODUCTION.

## 5. Acceptance Matrix

### CASE-01 — High Volume, Low Unique Value

A source adds thousands of records but no material new issues or
corroboration.

Expected:
Low incremental source value.

### CASE-02 — Small Source, High Lead-Time Value

A small source consistently identifies material issues earlier.

Expected:
Potentially high incremental decision value despite low volume.

### CASE-03 — Rights-Constrained Research Source

A research source reveals useful signals but cannot be used in production.

Expected:
Record RIGHTS_CONSTRAINED_GAP.
Do not weaken rights controls.

### CASE-04 — Premium Source Adds Volume Only

Expected:
Premium uplift NOT established.

### CASE-05 — Premium Source Improves Lead Time and Corroboration

Expected:
Premium uplift candidate established, subject to economics.

### CASE-06 — Source Removed With No Effect

Expected:
Potential redundancy.

### CASE-07 — Source Removed and Critical Issues Disappear

Expected:
High dependency value and dependency risk.

### CASE-08 — Substitute Exists With Small Lead-Time Penalty

Expected:
Substitution capacity recorded.

### CASE-09 — No Lawful Substitute Exists

Expected:
RIGHTS_CONSTRAINED_GAP or SOURCE_DEPENDENCY_BLOCK as applicable.

### CASE-10 — Open-Core Universe Meets Minimum Capability

Expected:
Candidate Minimal Viable Source Universe.

### CASE-11 — Open-Core Fails Geography Coverage

Expected:
MVU not qualified.

### CASE-12 — Research Universe Outperforms Production Universe

Expected:
Quantify or classify degradation.
Identify lawful substitution need.

### CASE-13 — Metric Lacks Benchmark Labels

Expected:
NOT_YET_MEASURABLE.
Do not invent percentage performance.

### CASE-14 — Unknown Cost

Expected:
UNKNOWN.
Do not treat as zero.

### CASE-15 — High Evidence Value but Unstable Licence

Expected:
Value and rights stability remain separate.
Dependency/revocation risk elevated.

### CASE-16 — Source Rights Lost

Expected:
Stop affected new ingestion.
Re-run coverage/dependency analysis.

### CASE-17 — Same Source Under YK and SBY Profiles

Expected:
Source rights may remain the same.
Information value and geographic coverage may differ.

### CASE-18 — Customer Wants Prohibited Fulltext Mirror

Expected:
Commercial gate blocked for that requested use.

### CASE-19 — Founder Can Supply Internal Government Data

Expected:
Not a commercial production source absent independent authorisation.

### CASE-20 — Customer Private Data Available

Expected:
Customer workspace only.
No global leakage.

### CASE-21 — Social API Exists but Commercial Rights Unresolved

Expected:
No production admission.

### CASE-22 — Composite Score Hides Hard Rights Failure

Expected:
Invalid decision model.
Hard block governs.

### CASE-23 — Applicable Regulatory Obligation Not Reviewed

Expected:
BLOCKED_REGULATORY_REVIEW or DEFERRED.

### CASE-24 — High Revenue but Unsustainable Compliance Cost

Expected:
Commercial economics not established.

### CASE-25 — All Governance Contracts Pass but No Benchmark Regression Exists

Expected:
Commercialisation remains NOT_READY.

### CASE-26 — Governance and Regression Pass

Expected:
May become candidate for controlled pilot review.

Does not automatically authorise production.

## 6. Stage0 Authority Boundary

Completion of S0.3Z means:

the governance architecture required by the Data Rights / Evidence Admission
mandate has been contractually integrated.

It does not mean:

- crawler activated;
- product commercially deployed;
- customer data accepted;
- paid sources purchased;
- social connectors enabled;
- profiling enabled.

A separate Stage0 reconciliation and freeze-readiness gate is required.

Implementation Authority remains:

NOT YET AUTHORIZED.

