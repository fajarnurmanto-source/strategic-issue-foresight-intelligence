# SIFI S0.6A1 — Benchmark Corpus Contract v0.1

Status: WORKING CONTRACT — NOT FROZEN

Canonical predecessor HEAD:

e7353e74030af3e249c578aeaba2c1054dbb6ca0

## 1. Purpose

This contract defines the canonical Stage0 benchmark corpus used to test
whether SIFI's Source Governance, Evidence Model, Issue Ontology,
Issue Object, Geography/Relevance, and Human Review contracts behave
correctly under adversarial and realistic conditions.

The benchmark is designed to falsify unsafe or unsupported product claims.

It does not constitute implementation or test execution.

## 2. Benchmark Reconciliation

Two prior benchmark requirement sets exist:

- legacy S01–S20 domain-integrity cases;
- VPC-DVA SIFI-V01–V22 product-capability/value cases.

They must not be implemented as two duplicate suites.

Canonical S0.6A reconciles them into 24 non-duplicative benchmark cases:

CB01–CB24.

Legacy identifiers remain traceability references only.

## 3. Benchmark State Model

Each benchmark case must distinguish:

SPECIFIED
→ MATERIALIZED
→ ADJUDICATED
→ EXECUTED
→ RESULT_RECORDED.

A case must not be labelled PASS or FAIL before EXECUTED.

Current Stage0 benchmark cases may therefore remain:

SPECIFIED_NOT_EXECUTED

or:

PROTOCOL_ONLY

where real users, real data, or later implementation are required.

## 4. Evidence / Rights Rule

Every case must declare its evidence-rights basis.

Permitted categories include:

- SYNTHETIC_NO_EXTERNAL_RIGHTS;
- PUBLIC_REFERENCE_REQUIRES_RIGHTS_REVIEW;
- Z2_REQUIRED_FOR_PRODUCTION_TEST;
- USER_STUDY_DATA_REQUIRES_CONSENT_AND_GOVERNANCE;
- CONTRACT_ONLY_REGRESSION.

Synthetic evidence must not be misrepresented as proof of real-world
production performance.

Z1 evidence may support permitted research/verification tests but cannot
independently establish production conclusions.

## 5. Point-in-Time Rule

Cases involving historical analysis must define:

- analytical cutoff;
- evidence available by cutoff;
- publication/source-available time;
- later-known evidence;
- expected historical output.

Future information must never leak backward.

## 6. Reference / Ground-Truth Rule

Each case must declare its reference methodology.

Allowed reference types include:

- deterministic synthetic reference;
- adjudicated documentary reference;
- qualified empirical reference;
- expert adjudication;
- paired human comparison;
- contract regression reference.

No ground truth may be assumed merely because one source is official or
popular.

## 7. Required Case Fields

Every canonical case must define:

- case_id;
- title;
- legacy requirement references;
- domain;
- scenario;
- benchmark input;
- rights state;
- analytical cutoff;
- reference methodology;
- expected output;
- forbidden output;
- acceptance condition;
- failure condition;
- existing contract coverage;
- regression dependency;
- materialization state;
- execution state.

## 8. No Invented Thresholds

S0.6A does not invent:

- minimum precision;
- minimum recall;
- acceptable alert burden;
- time-saving targets;
- issue-detection advantage;
- user-value threshold;
- decision-value threshold.

Thresholds require measured baseline evidence and explicit approval.

## 9. Evaluation Partitions

Where later empirical cases are materialized, the corpus should distinguish:

- DEVELOPMENT / CALIBRATION;
- HOLDOUT / REGRESSION;
- USER-WORKFLOW;
- COMPARATIVE DECISION-VALUE.

Cases used to tune behaviour must not silently become independent proof of
performance.

## 10. Product Workability vs Decision Value

Benchmark evidence must keep separate:

TRACK A — PRODUCT WORKABILITY

Can SIFI correctly execute its bounded workflow?

TRACK B — INCREMENTAL DECISION VALUE

Does SIFI materially improve a real analytical decision compared with a
credible baseline?

Track A success does not establish Track B success.

## 11. Comparator Rule

Comparative decision-value testing must use an appropriate baseline such as:

- competent human issue/media analyst;
- existing professional monitoring workflow;
- current manual triage workflow;
- appropriate keyword-monitoring workflow;
- appropriate general-purpose AI-assisted workflow.

Comparator access conditions, information cutoff, rights, expertise, and
time must be disclosed.

## 12. Failed Cases

Failed and ambiguous cases must remain visible.

They must not be removed from reporting simply because they lower apparent
performance.

## 13. Regression Requirement

Canonical benchmark execution must protect at minimum:

- Source Governance;
- Geography Relevance;
- CoverageProfile portability;
- Rights Admission Z0–Z3;
- privacy/profiling controls;
- Evidence Model;
- Issue Ontology;
- Issue Object;
- Human Review Authority;
- point-in-time correctness.

## 14. Maturity Boundary

Creation of this corpus supports only:

"SIFI has a contracted canonical benchmark design."

It does not prove:

- benchmark execution;
- functional correctness;
- end-to-end capability;
- V1 user operability;
- decision superiority.

## 15. Implementation Guard

S0.6A does not authorize:

- crawler implementation;
- database implementation;
- issue engine implementation;
- UI implementation;
- automated benchmark execution;
- production deployment.

Implementation Authority remains:

NOT YET AUTHORIZED.


## 16. Canonical Acceptance and Failure Semantics

Where a canonical case specification does not contain a separately written
`Acceptance:` paragraph, its acceptance condition is:

- every stated Expected condition is satisfied;
- no stated Forbidden condition is observed;
- all stated regression dependencies remain satisfied;
- applicable rights, privacy, geography, evidence, review, and point-in-time
  constraints remain intact.

The canonical failure condition is:

EXPECTED_NOT_MET_OR_FORBIDDEN_OUTPUT_OBSERVED

and additionally includes any material predecessor regression.

This default does not create a benchmark result.

A case remains NOT_EXECUTED until actual execution evidence is recorded.
