# SIFI S0.3T2 — Geographic Relevance Acceptance Matrix v0.1

Status: BENCHMARK DRAFT — NOT FROZEN

Profiles:

- PROFILE-YK  = Kota Yogyakarta
- PROFILE-DIY = Daerah Istimewa Yogyakarta
- PROFILE-IDN = Indonesia
- PROFILE-SBY = Kota Surabaya

## CASE-01 — Direct Local Event

Scenario:
A disruption occurs in Kota Yogyakarta and evidence explicitly states
that Kota Yogyakarta residents/services are affected.

Expected:

PROFILE-YK  = R0
PROFILE-DIY = R4 or RX unless province-level relevance is established
PROFILE-IDN = R4 or RX unless national relevance is established
PROFILE-SBY = RX

Purpose:
Test that administrative containment does not automatically produce
parent-level strategic relevance.

## CASE-02 — TPA Piyungan Cross-Local Service Dependency

Scenario:
An event occurs at a shared waste facility in Kabupaten Bantul.
Evidence establishes operational impact on waste services in
Kota Yogyakarta and other participating jurisdictions.

Expected:

PROFILE-YK  = R1
PROFILE-DIY = R0 when the issue is established as a province-level /
              multi-jurisdiction service-system issue
PROFILE-IDN = R4 or RX
PROFILE-SBY = RX

Purpose:
Test event_geography != affected_geography.

## CASE-03 — Province-Wide Regulation

Scenario:
A DI Yogyakarta regulation applies to local governments or services
throughout the province, including Kota Yogyakarta.

Expected:

PROFILE-YK  = R2
PROFILE-DIY = R0
PROFILE-IDN = R4 or RX unless national implication exists
PROFILE-SBY = RX

Purpose:
Test province-to-local relevance.

## CASE-04 — National Fiscal Transfer Reform

Scenario:
National government changes a fiscal-transfer formula applicable to
subnational governments.

Expected:

PROFILE-YK  = R3
PROFILE-DIY = R3
PROFILE-IDN = R0
PROFILE-SBY = R3

Purpose:
Test national-to-subnational policy relevance.

## CASE-05 — National Fuel-Price Policy

Scenario:
A national fuel-price policy has documented transport/economic effects
on multiple regions.

Expected:

PROFILE-YK  = R3 if local exposure mechanism exists
PROFILE-DIY = R3 if regional exposure mechanism exists
PROFILE-IDN = R0
PROFILE-SBY = R3 if local exposure mechanism exists

Purpose:
Test that national coverage alone is insufficient; mechanism is required.

## CASE-06 — Unrelated Local Event Elsewhere

Scenario:
A local parking dispute occurs in Medan.
No evidence connects it to Yogyakarta, DIY, Surabaya, or a national
systemic pattern.

Expected:

PROFILE-YK  = RX
PROFILE-DIY = RX
PROFILE-IDN = RX or R4 only if explicit comparator/reference-class mode
              is enabled
PROFILE-SBY = RX

Purpose:
Test parent-containment guard.

## CASE-07 — Surabaya Local Flood as Comparator

Scenario:
A Surabaya flood is used as an explicitly approved urban-risk comparator.

Expected:

PROFILE-SBY = R0
PROFILE-YK  = R4
PROFILE-DIY = R4 where comparator mode is enabled
PROFILE-IDN = R4 where comparative national analysis is enabled

Purpose:
Test contextual relevance without false direct impact.

## CASE-08 — Tangential Geographic Mention

Scenario:
An article mentions "Yogyakarta" in background text, while the actual
event and affected population are elsewhere.

Expected:

PROFILE-YK = RX

unless another defensible linkage exists.

Purpose:
Test mention guard.

## CASE-09 — Unknown Geography

Scenario:
The evidence contains insufficient information to determine event or
affected geography.

Expected:

ALL PROFILES =
GEOGRAPHY_UNKNOWN or INSUFFICIENT_RELEVANCE_EVIDENCE

NOT RX.

Purpose:
Protect unresolved evidence.

## CASE-10 — Conflicting Geographic Evidence

Scenario:
Two credible sources disagree on affected jurisdiction and the conflict
cannot yet be resolved.

Expected:

CONFLICTING_GEOGRAPHIC_EVIDENCE

until adjudicated.

Purpose:
Prevent false deterministic classification.

## CASE-11 — National Publisher, Local Event

Scenario:
A national media publisher reports an event occurring in Kota Yogyakarta
with clear local effects.

Expected:

PROFILE-YK = R0

Publisher national scope must not force R3.

Purpose:
Test source geography separation.

## CASE-12 — Local Publisher, National Policy

Scenario:
A Yogyakarta local publisher reports a national regulation that clearly
applies to all municipal governments.

Expected:

PROFILE-YK = R3

Publisher local scope must not force R0.

Purpose:
Test source geography separation.

## CASE-13 — Province-Wide Tourism Condition

Scenario:
A province-level tourism condition is evidenced across DI Yogyakarta and
materially affects Kota Yogyakarta.

Expected:

PROFILE-YK  = R2
PROFILE-DIY = R0
PROFILE-IDN = R4 or RX
PROFILE-SBY = RX

Purpose:
Test economic linkage across administrative scale.

## CASE-14 — Bound Source, Irrelevant Item

Scenario:
A source belongs to the Yogyakarta source pack, but one article concerns
an unrelated national celebrity story with no relevant local mechanism.

Expected:

PROFILE-YK = RX

Purpose:
Test SourceCoverageBinding != item relevance.

## CASE-15 — Same Corpus / Different Profiles

Scenario:
Run one unchanged benchmark corpus through:

PROFILE-YK
PROFILE-DIY
PROFILE-IDN
PROFILE-SBY

Expected:
Different relevant-issue sets and relevance classes may result.

Required invariant:
- source evidence remains unchanged;
- provenance remains unchanged;
- only profile-specific relevance assessment changes.

Purpose:
Core geography-configurable acceptance test.

