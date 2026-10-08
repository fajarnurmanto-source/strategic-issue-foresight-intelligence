# SIFI S0.3W2 — Data Rights & Evidence Admission Acceptance Matrix v0.1

Status: WORKING BENCHMARK — NOT FROZEN

## CASE-01 — Public Page, Commercial Rights Unknown

Scenario:
A publicly accessible article is technically retrievable.
Commercial production-use rights are unresolved.

Expected:

admission_zone = Z1
admission_state = VERIFICATION_REQUIRED

Must not become canonical production evidence.

## CASE-02 — Official Open Dataset With Compatible Rights

Scenario:
An official dataset is lawfully accessible, relevant rights are documented,
retention is permitted for the intended use, and provenance is reproducible.

Expected:

candidate admission_zone = Z2

subject to evidence-specific and privacy checks where applicable.

## CASE-03 — Highly Relevant but Rights-Unclear

Scenario:
A source reports an event directly affecting Kota Yogyakarta.
Geographic relevance is R0.
Production rights are unresolved.

Expected:

relevance_class = R0
admission_zone = Z1

Purpose:
Prove geographic relevance cannot override rights admission.

## CASE-04 — Lawfully Usable but Geographically Irrelevant

Scenario:
Evidence is production-admitted but unrelated to the active CoverageProfile.

Expected:

admission_zone = Z2
relevance_class = RX

Purpose:
Prove rights admission cannot create relevance.

## CASE-05 — Viral Social Post

Scenario:
A viral public social post contains a potentially important weak signal.
Production reuse/retention status is unresolved.

Expected:

admission_zone = Z1
admission_state = RESEARCH_SIGNAL

Allowed:
trigger verification.

Not allowed:
independent production conclusion or escalation.

## CASE-06 — Social Signal Independently Confirmed

Scenario:
A Z1 social signal triggers verification.
The event is independently confirmed by admissible Z2 sources.

Expected:

The original Z1 material remains Z1.

The newly admitted corroborating evidence becomes Z2.

The production issue must cite/support itself from Z2 evidence.

## CASE-07 — CAPTCHA / Paywall Bypass Required

Scenario:
Acquisition would require CAPTCHA bypass, paywall bypass, login bypass,
or defeating access controls.

Expected:

admission_zone = Z0
admission_state = BLOCKED

No acquisition.

## CASE-08 — Stolen or Leaked Private Dataset

Scenario:
A dataset was obtained through unauthorised disclosure or breach.

Expected:

Z0
BLOCKED

No payload retention or processing.

A governance-level block record may exist without retaining the prohibited
payload.

## CASE-09 — Source Approved, Particular Item Restricted

Scenario:
A source is generally approved, but a particular dataset/item has a
different licence or use restriction.

Expected:

source approval must not auto-admit the item.

Evidence-specific admission required.

## CASE-10 — Rights Revocation

Scenario:
Previously production-admitted source rights are revoked.

Expected:

new ingestion stops;
admission state becomes SUSPENDED or REVOKED;
effective timestamp is preserved;
historical material follows applicable retention/deletion requirements;
coverage loss is disclosed.

## CASE-11 — Z1 Cannot Mutate Silently Into Z2

Scenario:
An analyst determines that a Z1 source now appears useful for production.

Expected:

new explicit admission decision required.

Forbidden:
silent state mutation.

## CASE-12 — Z3 Output With Restricted Supporting Evidence

Scenario:
A derived Issue Object depends materially on evidence that later becomes
restricted or revoked.

Expected:

Issue lineage exposes affected evidence;
output status is re-evaluated;
restriction is not hidden.

## CASE-13 — Unknown Rights

Scenario:
No reliable determination is available regarding a material production-use
right.

Expected:

UNKNOWN != ALLOWED

Production admission denied or retained in Z1.

## CASE-14 — Weak Signal Novel but Low Strategic Value

Scenario:
A source detects a novel anomaly with no corroborated impact, persistence,
or strategic relevance.

Expected:

may remain research/watch signal.

Must not become strategic issue solely due to novelty.

## CASE-15 — Same Evidence, Different Coverage Profiles

Scenario:
A Z2 Evidence Item is evaluated under PROFILE-YK and PROFILE-SBY.

Expected:

admission_zone remains Z2.

Geographic relevance may differ.

Rights state does not mutate when CoverageProfile changes.

## CASE-16 — Profile Cannot Override Rights

Scenario:
A CoverageProfile strongly prioritises a source whose retention or production
rights prohibit the intended operation.

Expected:

profile configuration cannot override the rights restriction.

## CASE-17 — Untraceable Material Evidence

Scenario:
A material claim has no reproducible source/provenance chain.

Expected:

PRODUCTION_REJECTED or VERIFICATION_REQUIRED.

It must not silently support a production conclusion.

## CASE-18 — Implementation Guard

Scenario:
S0.3W is complete but downstream privacy/crawling/commercialisation controls
are not yet complete.

Expected:

Implementation Authority remains NOT YET AUTHORIZED.

No crawler activation is implied by S0.3W PASS.

