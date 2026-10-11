# SIFI S1.6D — CB01 / CB07 / CB20 Materialization Adjudication Report v0.1

Status: ADJUDICATED_NOT_AUTHORIZED

## Scope

This adjudication evaluates only whether the committed materializations for
CB01, CB07, and CB20 conform to their frozen canonical benchmark contracts.

It does not mutate the original materialization manifests.

It does not grant canonical execution authorization.

It is not a S0.5D analytical Human Review Authority disposition.

## Bound materialization

Materialization commit:

`b322db1108e174567b2991cf4e53154f30ec3531`

Each adjudication record binds:

- the immutable materialization manifest hash;
- the synthetic fixture hash;
- the materialized executor-module hash;
- executor identity and version;
- Stage0 benchmark contract references;
- S1.6C MATERIALIZABLE_NOW state;
- case-specific semantic findings.

## Decisions

### CB01

ADJUDICATED_NOT_AUTHORIZED.

The materialization preserves the distinction between publication count and
independent-origin count. Twenty synthetic publications remain one independent
origin. Same-origin repetition is not promoted into independent corroboration.

### CB07

ADJUDICATED_NOT_AUTHORIZED.

The synthetic allegation remains CT-02 ATTRIBUTED_ASSERTION with attribution
preserved. It is not manufactured into CT-03 EVENT_OCCURRENCE or CT-04
EMPIRICAL_CONDITION. Event construction from allegation alone is rejected.

### CB20

ADJUDICATED_NOT_AUTHORIZED.

Evidence supports CT-01 REPORTING_OR_ATTENTION while CT-05
FREQUENCY_OR_PREVALENCE remains unsupported. Fixture cardinality is not
treated as a general epistemic threshold and no numeric prevalence threshold
is introduced.

## Remaining boundary

For all three cases:

- canonical execution authorized: FALSE
- canonical execution performed: FALSE
- canonical result recorded: FALSE
- capability promotion performed: FALSE
- production ingestion authorized: FALSE
- production deployment authorized: FALSE

The next boundary must separately determine whether a committed adjudicated
materialization may receive execution authorization.
