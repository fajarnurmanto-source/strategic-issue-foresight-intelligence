# SIFI S1.6D — CB01 / CB07 / CB20 Materialization Adjudication Final Audit v0.1

Status: PASS — ADJUDICATED_NOT_AUTHORIZED

## Boundary

The committed materializations for CB01, CB07, and CB20 have been separately
adjudicated for conformance with their canonical benchmark contracts.

The original materialization manifests remain unchanged and continue to record
their historical state as MATERIALIZED_NOT_ADJUDICATED.

Separate additive adjudication records establish the successor state:

ADJUDICATED_NOT_AUTHORIZED

## Verified bindings

Each adjudication record binds:

- materialization commit b322db1108e174567b2991cf4e53154f30ec3531;
- immutable materialization manifest;
- immutable fixture;
- materialized executor module;
- executor identity/version;
- canonical Stage0 case references;
- S1.6C executability state;
- case-specific semantic findings.

## Semantic decisions

CB01:
publication count and independent-origin count remain distinct; common-origin
repetition is not independent corroboration.

CB07:
the allegation remains CT-02 with attribution preserved; CT-03 and CT-04 are
not manufactured; allegation-only Event construction remains rejected.

CB20:
CT-01 is supported while CT-05 remains unsupported; fixture cardinality is
not treated as a general epistemic threshold.

## Authority boundary

Materialization adjudication is not execution authorization.

For all three cases:

- canonical execution authorized: FALSE
- canonical execution performed: FALSE
- canonical result recorded: FALSE

No capability promotion is performed.
Production ingestion remains unauthorized.
Production deployment remains unauthorized.

The next boundary is a separate semantic execution-authorization issuance
mechanism for committed, adjudicated, hash-consistent materializations.
