# SIFI S0.3E — Geographic Relevance Admission Draft

Status: WORKING DRAFT — NOT FROZEN
Stage: 0 — Foundation

## 1. Purpose
Define when an Evidence Item or candidate issue outside Kota Yogyakarta may enter the pilot corpus.

## 2. Separate Geographic Concepts
SIFI must not collapse geography into one field. At minimum distinguish:
- source_geography: where the publisher/institution is based or scoped;
- event_geography: where the reported event occurred;
- affected_geography: where consequences are observed or expected;
- policy_jurisdiction: where a rule or official decision applies;
- relevance_geography: geography for which the item is analytically relevant;
- evidence_geography: geography actually supported by the evidence.

## 3. Admission States
Each externally located item receives one of:
- LOCAL_DIRECT: event/effect is within Kota Yogyakarta.
- JURISDICTION_APPLIES: external/national rule explicitly applies to Kota Yogyakarta.
- EXTERNAL_DIRECT_EFFECT: event occurs elsewhere but has a direct and reviewable effect on Kota Yogyakarta.
- CONTEXT_ONLY: useful context/reference class but not direct evidence of a Kota Yogyakarta condition.
- WATCH_ONLY: plausible future relevance; retained only as a watch candidate.
- OUT_OF_SCOPE: no defensible pilot relevance.

## 4. Admission Rule
An item may contribute to a Kota Yogyakarta Issue Object only when its admission state is LOCAL_DIRECT, JURISDICTION_APPLIES, or EXTERNAL_DIRECT_EFFECT.

CONTEXT_ONLY and WATCH_ONLY items may support interpretation or foresight, but must not silently count as direct local evidence.

OUT_OF_SCOPE items must not enter the active pilot issue corpus.

## 5. Required Admission Basis
Every admitted external item must record at least one reviewable basis:
- explicit jurisdiction;
- explicit named location/affected population;
- documented service/supply/mobility linkage;
- documented economic/fiscal/market exposure;
- documented environmental/hazard pathway;
- documented institutional dependency;
- analyst-qualified relevance with cited evidence.

## 6. Guard Against Geographic Leakage
Keyword co-occurrence with "Yogyakarta" is not sufficient.
Publisher location is not sufficient.
National popularity is not sufficient.
Similarity to a local issue is not sufficient.

## 7. Review
Automated extraction may propose geographic fields and admission state.
Human review is required for ambiguous EXTERNAL_DIRECT_EFFECT and WATCH_ONLY classifications until benchmark evidence supports a narrower automated rule.

## 8. Failure Condition
This contract fails if national or regional information is routinely promoted into local Issue Objects without an explicit relevance basis and audit trail.
