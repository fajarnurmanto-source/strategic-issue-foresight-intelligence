# SIFI S1.4B — Issue Identity + Persistent Issue Object v0.1

Status: CANONICAL — S1.4B VERIFIED

Predecessor HEAD:

97159ae41c14da057aed7bfc9ce09d63f3e0e722

## Implemented

- stable explicit Issue identity;
- persistent Issue Object representation;
- explicit Event / Claim / Evidence references;
- distinct supporting, contradictory, context-only, and
  production-excluded Evidence Item references;
- distinct geography groups;
- five separate canonical trajectory dimensions;
- observability-state preservation;
- authority candidates separated from established authority;
- Issue lineage representation;
- explicit identity proposal verification;
- append-only Issue version persistence;
- sequential Issue version enforcement;
- latest / history / point-in-time reconstruction;
- non-destructive later correction behaviour.

## Explicitly not implemented

- automatic Issue discovery;
- automatic Event-to-Issue clustering;
- automatic Issue identity inference;
- automatic Issue qualification;
- automatic lifecycle transition;
- automatic persistence-class transition;
- automatic merge execution;
- automatic split execution;
- merge/split human authority;
- reviewer authority enforcement;
- trajectory inference;
- empirical-condition qualification;
- risk/opportunity engine;
- strategic-hypothesis engine;
- binding institutional action;
- production ingestion;
- production deployment.

## Identity boundary

Persistent representation != automatic Issue discovery.

issue_id is explicit.

Title, lifecycle state, and reviewer interpretation may change without
silently changing issue_id.

## Review boundary

Review state may be represented.

S1.4B does not grant reviewer authority.

Protected transitions remain owned by S0.5D and future S1.5 runtime work.

## Persistence boundary

S1.4B reuses the S1.1 append-only SQLite VersionStore.

The S1.4 repository adds Issue-specific sequential-version enforcement
without modifying S1.1 predecessor code.

## Capability effect

CAP-05 gains substantial executable implementation evidence.

CAP-04 gains additional executable Issue-representation evidence.

CAP-09 gains persistent Issue-object precursor capability but must not be
promoted automatically.

Capability maturity remains pending post-execution adjudication.

## Production boundary

Production Source Ingestion Authority remains HOLD.

Production Deployment Authority remains HOLD.

MVU_PRODUCTION_STATUS = NOT_YET_ESTABLISHED.
