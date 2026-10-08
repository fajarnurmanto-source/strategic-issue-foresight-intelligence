# SIFI S0.6A3 — Canonical Benchmark Case Specifications v0.1

Status: SPECIFIED — NOT EXECUTED

## CB01 — Syndication / Source-Origin Inflation

Legacy refs: S01, SIFI-V01, SIFI-V17

Scenario:
Multiple publications or domains reproduce one originating disclosure.

Reference:
Deterministic source-lineage annotation.

Rights:
Synthetic test material or independently admitted source material.

Expected:
Publication count and independent-origin count remain distinct.

Forbidden:
Treating republished items as independent corroboration.

Acceptance:
Same-origin lineage is preserved and evidence independence is not inflated.

Regression:
S0.3 source governance; S0.5A evidence independence.

## CB02 — High Attention, Low Impact

Legacy refs: SIFI-V02

Scenario:
Large publication volume exists without qualified evidence of material impact.

Expected:
High attention may be represented.

Forbidden:
Automatic high severity or strategic importance.

Acceptance:
Attention and impact remain separate.

Regression:
S0.5B/C.

## CB03 — Low Attention, High Materiality

Legacy refs: S02, SIFI-V03

Scenario:
Few publications accompany strong qualified evidence of a material condition.

Expected:
Issue remains eligible for strategic review.

Forbidden:
Dismissal solely because mention volume is low.

Regression:
S0.5A/C.

## CB04 — Coverage Gap / Weak Observability

Legacy refs: S03, SIFI-V04

Scenario:
A relevant geography has materially weak source coverage.

Expected:
INSUFFICIENT_COVERAGE and/or explicit coverage limitation.

Forbidden:
Inferring issue absence.

Regression:
S0.3 CoverageProfile; S0.5A observability; S0.5C coverage disclosure.

## CB05 — Collection Failure

Legacy refs: S04, SIFI-V05

Scenario:
A major expected source or collector fails during an observation window.

Expected:
COLLECTION_FAILED or equivalent coverage-health state.

Forbidden:
Interpreting lower observed volume as issue resolution.

Regression:
S0.5A/C.

## CB06 — Source Universe / Coverage Profile Change

Legacy refs: S05, SIFI-V06

Scenario:
Sources are added, removed, or materially changed between comparison periods.

Expected:
Comparability limitation is disclosed.

Forbidden:
Automatic trajectory acceleration/decline conclusion.

Regression:
S0.3S/T/Z; S0.5A/C.

## CB07 — Allegation vs Verified Fact

Legacy refs: S06, SIFI-V07

Scenario:
A publication contains an unverified accusation.

Expected:
Attribution remains visible.

Forbidden:
Promotion into established event/condition solely from allegation.

Regression:
S0.5A/B.

## CB08 — Independent Corroboration

Legacy refs: S07

Scenario:
Multiple independently originated Evidence Items support the same bounded Claim.

Expected:
Corroboration may strengthen the supported portion of the Claim.

Forbidden:
Corroborating unsupported portions or treating common-origin repetition as
independent evidence.

Regression:
S0.5A.

## CB09 — Contradictory Evidence

Legacy refs: S08, SIFI-V08

Scenario:
Independent Evidence Items materially disagree.

Expected:
Contradiction, uncertainty, and human-review need remain visible.

Forbidden:
Silent source selection or averaging away conflict.

Regression:
S0.5A/C/D.

## CB10 — Multi-Jurisdiction Relevance

Legacy refs: S09, SIFI-V09

Scenario:
An event occurs outside the active profile but materially affects it.

Expected:
event_geography, affected_geography, relevance_geography and jurisdiction
remain distinct.

Forbidden:
Discarding relevance solely because the event lies outside the target boundary.

Regression:
S0.3R–T; S0.5B/C.

## CB11 — Institutional Authority Mismatch

Legacy refs: S10, SIFI-V10

Scenario:
An Issue is strategically relevant to a city but legal authority lies elsewhere.

Expected:
Relevance remains visible without inventing municipal authority.

Forbidden:
relevance_geography → competent authority shortcut.

Regression:
S0.5B/C/D.

## CB12 — Z1 Research-Only Signal

Legacy refs: S11, SIFI-V11

Scenario:
A material early signal is lawfully available only under research-only admission.

Expected:
May trigger research or verification.

Forbidden:
Production conclusion supported solely by Z1 evidence.

Regression:
S0.3W/X/Y/Z; S0.5A/C/D.

## CB13 — Issue Re-emergence

Legacy refs: S12, SIFI-V12

Scenario:
A previously inactive Issue produces materially related new Events.

Expected:
Prior identity and lifecycle history remain available.

Forbidden:
Automatic unrelated new Issue or destructive historical rewrite.

Regression:
S0.5B/C/D.

## CB14 — Narrative Spike Without Condition Change

Legacy refs: S13, SIFI-V13

Scenario:
Media attention rises sharply without new qualified evidence of empirical deterioration.

Expected:
Attention/Narrative trajectory may change.

Forbidden:
Condition deterioration inferred from attention alone.

Regression:
S0.5B/C.

## CB15 — Foresight Falsification

Legacy refs: S14, SIFI-V14

Scenario:
A Strategic Hypothesis receives material disconfirming evidence.

Expected:
Hypothesis revision/invalidation is recorded with historical version preserved.

Forbidden:
Rewriting earlier hypothesis history or ignoring falsification evidence.

Regression:
S0.5B/C/D.

## CB16 — Reviewer Correction

Legacy refs: SIFI-V15

Scenario:
Authorized reviewer rejects or corrects a generated analytical classification.

Expected:
Correction lineage, reviewer, rationale and prior state remain preserved.

Forbidden:
Silent overwrite.

Regression:
S0.5D.

## CB17 — Reviewer Disagreement

Legacy refs: S15

Scenario:
Two authorized reviewers materially disagree.

Expected:
Disagreement remains explicit and can enter adjudication.

Forbidden:
Selecting first reviewer or averaging judgments.

Regression:
S0.5D.

## CB18 — Alert Fatigue / Review Burden

Legacy refs: S16, SIFI-V16

Scenario:
Many low-value candidates compete with a small number of material developments.

Expected:
Evaluation preserves both missed-material-issue risk and unnecessary-review burden.

Forbidden:
Treating alert volume as product value.

Regression:
S0.5C/D.

Execution note:
Performance thresholds are NOT YET APPROVED.

## CB19 — Personal-Data Boundary

Legacy refs: S17

Scenario:
Publicly accessible material contains unnecessary personal identifiers.

Expected:
Data minimization and profiling restrictions remain active.

Forbidden:
Retaining or profiling individuals merely because data are public.

Regression:
S0.3Y; S0.5A/C/D.

## CB20 — Claim-Type Leakage

Legacy refs: S19, SIFI-V18

Scenario:
Evidence strongly establishes media attention but not underlying prevalence.

Expected:
REPORTING_OR_ATTENTION may be supported.

Forbidden:
Unsupported FREQUENCY_OR_PREVALENCE conclusion.

Regression:
S0.5A.

## CB21 — Historical Point-in-Time Leakage

Legacy refs: S18, SIFI-V19

Scenario:
Material outcome or correction becomes known only after cutoff T.

Expected:
Historical assessment at T excludes later-known information.

Forbidden:
Future-information leakage.

Regression:
S0.3 rights PIT controls; S0.5A/C/D.

## CB22 — Bounded V1 User Workflow

Legacy refs: SIFI-V20

Scenario:
A representative intended analyst receives a defined issue-monitoring task.

Required workflow eventually includes, within approved V1 scope:

coverage inspection;
lawfully admitted signal review;
persistent Issue inspection;
evidence/contradiction inspection;
geographic relevance;
uncertainty;
trajectory;
authorized correction;
candidate prioritization;
evidence-linked brief;
historical retrieval.

Reference:
Representative user task protocol.

Rights:
Only appropriately admitted production evidence.

Expected:
User completes the bounded task without routine developer intervention.

Forbidden:
Declaring user readiness from schema or dashboard existence.

Materialization:
PROTOCOL_ONLY pending implementation and representative-user study.

Regression:
All S0.3–S0.5 controls.

## CB23 — Human Baseline Comparison

Legacy refs: SIFI-V21

Scenario:
SIFI-assisted and credible baseline workflows evaluate comparable issue cases.

Reference design must preserve:

same or disclosed information cutoff;
source-access differences;
analyst expertise;
rights conditions;
adjudicated reference;
missed issues;
false escalations;
time/review burden;
uncertainty;
failed cases.

Expected:
Comparative result is reported only from actually measured evidence.

Forbidden:
Claiming superiority from architecture, timestamp alone, or anecdotal preference.

Materialization:
PROTOCOL_ONLY pending operational V1 and approved comparative design.

Regression:
VPC-DVA Track B.

## CB24 — Predecessor Non-Regression

Legacy refs: S20, SIFI-V22

Scenario:
A successor contract or implementation change is evaluated against valid
predecessor requirements.

Expected:
No semantic collision, predecessor mutation, rights bypass, geography collapse,
or unauthorized production path.

Forbidden:
Passing new capability by weakening earlier controls.

Reference:
Canonical predecessor hashes and contract assertions.

Regression:
All frozen/canonical predecessor controls.

---

# Execution State

CB01–CB21: SPECIFIED_NOT_EXECUTED

CB22–CB23: PROTOCOL_ONLY_NOT_EXECUTED

CB24: SPECIFIED_NOT_EXECUTED

No benchmark result is asserted by this document.

