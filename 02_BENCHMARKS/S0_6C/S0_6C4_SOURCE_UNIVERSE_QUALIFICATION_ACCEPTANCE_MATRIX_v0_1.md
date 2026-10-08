# SIFI S0.6C4 — Source Universe Qualification Acceptance Matrix v0.1

Status: CONTRACT TESTS — NOT EXECUTED PRODUCT TESTS

## QU01 — Audited Cohort Identity

Input:
Current S0.3 live-rights audit cohort.

Expected:
Exactly the currently audited canonical Source IDs are represented.

Forbidden:
Inventing additional audited sources.

## QU02 — BPS Conditional Candidate

Input:
SRC-BPS source-level candidate zone is Z2 and production eligibility is
conditional.

Expected:
Source remains a conditional production candidate.

Forbidden:
Treating all BPS material as automatically Z2 production evidence.

## QU03 — Data.go.id Portal-Level Z1

Input:
SRC-DATAGOID is Z1 at portal level.

Expected:
Research/discovery use only within applicable controls.

Forbidden:
Portal-wide production admission from public availability.

## QU04 — BMKG Permission Boundary

Input:
SRC-BMKG requires the applicable official API route and unresolved written
permission for the relevant commercial/integration use.

Expected:
Remain research-only until applicable permission is satisfied.

Forbidden:
Web-interface scraping or assumed commercial permission.

## QU05 — JDIH Recurring Automation Boundary

Input:
SRC-JDIH-YK has public official material but recurring automation rights
remain unresolved.

Expected:
Research/manual discovery may remain distinct from recurring production
automation.

Forbidden:
Public official status automatically authorizing recurring ingestion.

## QU06 — Warta Research-Only Boundary

Input:
SRC-WARTA-YK is currently research-only.

Expected:
May support discovery within applicable controls.

Forbidden:
Using it as production evidence solely because it is an official-government
publication.

## QU07 — Open Data Dataset-Specific Review

Input:
SRC-OPENDATA-YK portal is technically accessible.

Expected:
Dataset-level rights/admission remains required where applicable.

Forbidden:
Portal accessibility becoming universal dataset production permission.

## QU08 — Information Coverage vs Rights Coverage

Input:
A highly informative source remains Z1.

Expected:
Information value remains visible while production rights remain restricted.

Forbidden:
High information value causing rights promotion.

## QU09 — Rights vs Geography

Input:
CoverageProfile changes.

Expected:
Source-rights state remains unchanged unless rights themselves change.

Forbidden:
Geographic relevance mutating rights state.

## QU10 — No Source-Count Sufficiency

Input:
Six audited sources exist.

Expected:
Sufficiency depends on defensible information-role, rights, diversity and
coverage evidence.

Forbidden:
Six-source count alone implying sufficient production universe.

## QU11 — Source Diversity

Input:
Multiple sources share official-government or open-data families.

Expected:
Source-family and informational independence remain explicit.

Forbidden:
Counting every source as independent corroboration.

## QU12 — Substitution Unknown

Input:
No source-specific lawful equivalent substitute has been demonstrated.

Expected:
substitution_status = NOT_YET_ESTABLISHED.

Forbidden:
Topic similarity treated as proven substitution.

## QU13 — Source Loss

Input:
A material source becomes unavailable or loses rights.

Expected:
Coverage-gap and substitution review are triggered.

Forbidden:
Interpreting missing observations as issue disappearance.

## QU14 — Rights Regression

Input:
A source moves from permissive to restrictive rights state.

Expected:
Applicable ingestion/admission stops and the new state is versioned.

Forbidden:
Continuing production use based solely on historical permission.

## QU15 — Point-in-Time Rights

Input:
Rights change after analytical cutoff T.

Expected:
Historical state at T remains reconstructable.

Forbidden:
Later rights knowledge silently rewriting historical state.

## QU16 — Z1 Verification Promotion Path

Input:
A Z1 signal identifies a potentially material issue.

Expected:
It may trigger searches for independent admissible evidence.

Forbidden:
The original Z1 material silently becoming Z2.

## QU17 — Production MVU Boundary

Input:
Only one audited source is a conditional source-level Z2 candidate.

Expected:
MVU_PRODUCTION_STATUS = NOT_YET_ESTABLISHED.

Forbidden:
Declaring a sufficient production universe without supporting evidence.

## QU18 — Implementation Boundary

Input:
S0.6C contracts and qualification records are complete.

Expected:
Implementation Authority remains NOT YET AUTHORIZED.

Forbidden:
Treating source-universe documentation as executable product readiness.

---

These cases are Stage0 qualification contracts.

They are not proof of source-acquisition runtime behaviour.

