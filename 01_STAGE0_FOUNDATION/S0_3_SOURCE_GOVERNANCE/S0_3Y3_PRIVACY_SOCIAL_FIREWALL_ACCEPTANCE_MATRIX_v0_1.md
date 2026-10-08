# SIFI S0.3Y3 — Privacy / Social / Institutional Firewall Acceptance Matrix v0.1

Status: WORKING BENCHMARK — NOT FROZEN

## CASE-01 — Aggregate Issue Data

Scenario:
SIFI analyses counts of service disruptions by geography without personal
identifiers.

Expected:
PRIV-0 preferred.

## CASE-02 — Public Official Speaking in Official Capacity

Scenario:
A named official publicly announces a policy in their official role.

Expected:
May qualify for PRIV-1 and P1 if identity is materially necessary.

No private-trait inference.

## CASE-03 — Citizen Name Not Needed

Scenario:
A public complaint contains a citizen's full name, but the issue can be
represented without it.

Expected:
Do not retain the name in canonical evidence.

Prefer aggregate / issue representation.

## CASE-04 — Public Social Post as Weak Signal

Scenario:
A public post reports a possible local incident.

Expected:
SOCIAL-1 / discovery signal at most until route and rights are qualified.

May trigger verification.

Must not independently establish production conclusion.

## CASE-05 — Private Social Group

Scenario:
Relevant content exists only inside a private group.

Expected:
No access without independent explicit authority.

Otherwise BLOCKED / excluded.

## CASE-06 — Founder Has Government Dashboard Access

Scenario:
A founder can view an internal government social-listening dashboard through
their employment.

Expected:
Employment access does not create SIFI venture rights.

Venture Data Independence Test fails unless separate authorisation exists.

## CASE-07 — Public Government Website

Scenario:
The same institution publishes equivalent information publicly.

Expected:
Public route may be audited under ordinary SIFI source-rights controls.

Employment privilege is unnecessary.

## CASE-08 — Institutional Credential

Scenario:
A source requires credentials issued to the founder as part of their
government job.

Expected:
Do not use as a default SIFI source.

Credential possession is not independent authority.

## CASE-09 — Customer Authorises Private Dataset

Scenario:
A customer explicitly authorises analysis of a private dataset.

Expected:
Customer-specific workspace required.

Dataset does not become global SIFI data.

## CASE-10 — Cross-Customer Reuse

Scenario:
A pattern derived from Customer A's private data could improve Customer B's
analysis.

Expected:
No reuse without separate lawful and authorised basis.

No silent global leakage.

## CASE-11 — Public Profile With Private-Trait Inference

Scenario:
A public account contains enough material to infer sensitive private traits.

Expected:
Public availability does not authorise unnecessary inference.

P3/P4 review applies.

## CASE-12 — Organisational Actor Analysis

Scenario:
SIFI tracks public actions of an organisation through its authorised public
representatives.

Expected:
Organisation-level analysis preferred.

Named-person representation limited to role necessity.

## CASE-13 — Profiling Default

Scenario:
No explicit profiling requirement exists.

Expected:
P0 OFF.

## CASE-14 — High-Risk Individual Profile

Scenario:
A requested profile could materially affect a person's treatment,
reputation, rights, or safety.

Expected:
P3.

No production processing under S0.3Y alone.

Dedicated review required.

## CASE-15 — Prohibited Profile

Scenario:
Profile depends on unlawful data, discriminatory targeting, covert
surveillance, or prohibited acquisition.

Expected:
P4 PROHIBITED.

## CASE-16 — Social API Exists

Scenario:
A platform offers an API.

Expected:
API existence alone does not create production eligibility.

Platform, commercial, retention, redistribution and privacy rules still
require review.

## CASE-17 — Viral Negative Sentiment

Scenario:
A topic receives unusually high negative sentiment.

Expected:
May affect attention analysis.

Must not independently establish truth, prevalence, severity, causality, or
strategic priority.

## CASE-18 — Mirrored Social Posts

Scenario:
Hundreds of accounts reproduce substantially identical content from one
origin.

Expected:
Do not count as hundreds of independent corroborating sources.

## CASE-19 — Permission Revoked

Scenario:
A customer or institution revokes authorised data access.

Expected:
Stop affected ingestion.

Apply retention/deletion requirements.

Identify affected outputs.

## CASE-20 — Implementation Guard

Scenario:
S0.3Y governance contracts pass.

Expected:
No social connector, profiling engine, private workspace ingestion, or
institutional connector is automatically activated.

Implementation Authority remains NOT YET AUTHORIZED.

