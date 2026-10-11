# SIFI S1.6D — CB01 / CB07 / CB20 Materialization Report v0.1

Status: MATERIALIZED_NOT_ADJUDICATED

## Scope

This work materializes the first three canonical benchmark cases selected by
S1.6C as MATERIALIZABLE_NOW:

- CB01 — Syndication / Source-Origin Inflation
- CB07 — Allegation vs Verified Fact
- CB20 — Claim-Type Leakage

Materialization includes deterministic synthetic fixtures, bounded executors,
fixture/executor hash bindings, and component self-tests.

## Verified component semantics

CB01:
- 20 publications remain distinguishable from 1 independent origin.
- common-origin repetition is not treated as independent corroboration.

CB07:
- the allegation remains CT-02 ATTRIBUTED_ASSERTION.
- attribution remains visible.
- no CT-03 EVENT_OCCURRENCE is manufactured.
- no CT-04 EMPIRICAL_CONDITION is manufactured.
- EventCandidate construction from allegation alone is rejected.

CB20:
- CT-01 REPORTING_OR_ATTENTION receives support.
- CT-05 FREQUENCY_OR_PREVALENCE receives no support.
- fixture cardinality is not an epistemic threshold.
- no general prevalence threshold is introduced.

## Governance boundary

The materializations remain:

MATERIALIZED_NOT_ADJUDICATED

and explicitly remain:

- canonical execution authorized: FALSE
- canonical execution performed: FALSE
- canonical result recorded: FALSE

Registration of the executors without explicit execution authorization remains
NOT_READY_FOR_EXECUTION / NO_RESULT.

Component self-tests do not constitute canonical benchmark execution.

No capability promotion is performed.
Production ingestion remains unauthorized.
Production deployment remains unauthorized.
