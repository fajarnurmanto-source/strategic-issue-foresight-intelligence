# SIFI S1.1A — Core Domain & Persistence Scope v0.1

Status: WORKING — NOT YET CANONICAL

Canonical predecessor HEAD:

e725aa6efd6b2b5ef3cf6ff05650e8ace34d5164

## Purpose

Establish the first executable SIFI product-code foundation using
Python standard library only.

## Implemented in this gate

- canonical executable enum vocabulary for selected frozen Stage0 states;
- explicit orthogonal geography representation;
- timezone-aware versioned snapshot primitive;
- append-only SQLite local version store;
- latest/history retrieval;
- point-in-time retrieval constrained by both effective time and
  recorded/known time;
- stdlib unittest verification.

## Explicitly not implemented

- live source acquisition;
- crawler;
- production ingestion;
- production database;
- rights/admission decision engine;
- Claim/Evidence qualification engine;
- Event extraction;
- Issue construction;
- persistent canonical Issue Object;
- human review state machine;
- benchmark execution harness;
- API/UI;
- foresight runtime;
- AI/LLM integration.

## Capability relationship

This gate creates shared executable foundations relevant to CAP-02,
CAP-03 and CAP-05.

It does not by itself satisfy any full capability contract.

Therefore:

S1_1_MATURITY_EFFECT = NONE_NO_CAPABILITY_PROMOTION

Capability-specific L2 adjudication is deferred until a successor gate
implements the complete applicable capability behaviour.

## Production boundary

Production source ingestion remains HOLD.

Production deployment remains HOLD.

MVU_PRODUCTION_STATUS = NOT_YET_ESTABLISHED.
