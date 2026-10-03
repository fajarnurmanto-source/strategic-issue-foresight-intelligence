# SIFI S0.2R — Product & Pilot Boundary Successor v0.2

Status: WORKING SUCCESSOR — NOT FROZEN

## Product Boundary
SIFI is a standalone geography-configurable intelligence platform.

## Pilot Boundary
Kota Yogyakarta remains the first operational pilot Coverage Profile.

## Product vs Pilot
Product geography: configurable.
Pilot geography: Kota Yogyakarta.
Shadow portability profile: Kota Surabaya.

Yogyakarta is used for benchmark construction, source validation,
corpus quality testing, and acceptance testing.

## Minimum Coverage Hierarchy
- Kabupaten/Kota
- Province
- National

Changing the active geography must not require rebuilding the corpus
or modifying core application code.

## Operational User vs Economic Buyer
The first operational workflow remains optimized for analysts performing
recurring issue monitoring and briefing.

The economic buyer must be validated separately and must not be assumed
to be identical to the operational user.

## Product Success
Success includes the ability to transform one evidence universe into
different relevant-issue views for different geographic profiles with
explicit reasons, provenance, and human review.

## Failure Conditions
This architecture fails if:
- Yogyakarta becomes a permanent special case in core code;
- source geography is treated as issue geography;
- every national issue automatically becomes locally relevant;
- cross-local impact is ignored because an event occurs outside the profile;
- changing geography requires a code fork;
- relevance cannot be explained or audited.

