# SIFI S1.6B — Canonical Benchmark Harness Scope v0.1

Status: CONTROLLED NON-PRODUCTION HARNESS IMPLEMENTATION

Canonical predecessor:

`5d443f179b7533c4d98f66856e0b67e7af8481e2`

## Purpose

S1.6B implements the deterministic harness required to execute canonical
benchmark cases CB01–CB24 without rewriting the frozen Stage0 benchmark
registry.

This stage implements harness semantics only.

It does not yet claim execution of any canonical benchmark case.

## Canonical source of truth

The harness reads:

- S0_6A2_CANONICAL_BENCHMARK_CASE_REGISTRY_v0_1.csv
- S0_6A4_BENCHMARK_CASE_CONTROL_MATRIX_v0_1.csv

It requires exactly CB01–CB24.

The frozen Stage0 execution states must remain NOT_EXECUTED / NO_RESULT.

## Execution semantics

A specified canonical case may have one of four harness execution states:

- EXECUTED
- BLOCKED_BY_MISSING_CAPABILITY
- PROTOCOL_ONLY
- EXECUTION_ERROR

Benchmark result states are:

- PASS
- FAIL
- NO_RESULT

PASS or FAIL is valid only when execution_state = EXECUTED.

A case without a registered executor is not treated as FAIL and cannot be
treated as PASS. It remains BLOCKED_BY_MISSING_CAPABILITY / NO_RESULT.

CB22 and CB23 remain PROTOCOL_ONLY / NO_RESULT and cannot receive synthetic
executors through this harness.

## Acceptance semantics

For an actually executed case, PASS requires all of:

- stated Expected conditions satisfied;
- no Forbidden output observed;
- regression dependencies satisfied;
- applicable rights constraints satisfied;
- execution evidence recorded.

Otherwise an executed case is FAIL.

A runtime exception or invalid execution observation produces
EXECUTION_ERROR / NO_RESULT rather than manufacturing a benchmark result.

## Point-in-time guard

Cases with FROZEN_T_REQUIRED must record an analytical cutoff during actual
execution.

Missing cutoff produces EXECUTION_ERROR / NO_RESULT.

## Frozen Stage0 boundary

S1.6B does not mutate:

- Stage0 benchmark registry;
- Stage0 benchmark control matrix;
- Stage0 capability ledger;
- Stage0 product-claim register.

Successor execution evidence will be recorded additively in later S1.6 work.

## Explicit non-claims

S1.6B does not establish:

- that any CB01–CB24 case has been canonically executed;
- that any canonical case has PASS or FAIL result;
- production correctness;
- end-to-end V1 workability;
- representative-user operability;
- incremental decision value;
- production ingestion authorization;
- production deployment authorization.

Component tests for the harness are not canonical benchmark executions.

## R1 adversarial closure

Pre-canonical adversarial review identified three material harness gaps:

1. executor identity/version/reference was not persisted in execution records;
2. an executor could mutate frozen benchmark artifacts and still return PASS;
3. an arbitrary unresolved evidence-reference string could satisfy the
   execution-evidence presence check.

R1 closes these boundaries.

Actual execution attempts now bind:

- executor ID;
- executor version;
- executor reference.

Execution evidence now requires a structured reference containing:

- evidence ID;
- evidence kind;
- repository-relative artifact path;
- SHA-256 digest;
- producer executor ID;
- producer executor version.

The harness validates that:

- the artifact exists;
- the path does not escape the repository root;
- SHA-256 matches;
- producer identity/version matches the registered executor.

The harness fingerprints protected canonical/frozen inputs when instantiated
and verifies their integrity both before and after each executor attempt.

Protected inputs include, when present:

- PROJECT_STATE;
- Stage0 foundation artifacts;
- S0.6A benchmark artifacts;
- S0.6B regression/capability artifacts.

A protected-artifact mutation or pre-execution integrity mismatch produces
EXECUTION_ERROR / NO_RESULT.

It cannot produce canonical PASS or FAIL.

These controls do not themselves execute any canonical CB case.
