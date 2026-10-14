# SIFI S1.6D — Initial Canonical Execution Final Audit v0.1

Status: PASS — THREE CONTROLLED NON-PRODUCTION RESULTS RECORDED

## Authority

The human project owner explicitly authorized continued execution on
2026-10-14. The authorization scope was limited to one controlled
non-production canonical benchmark execution for each initial case.

Committed authorization artifacts:

- CB01 — `S1.6D-CB01-AUTH-001`;
- CB07 — `S1.6D-CB07-AUTH-001`;
- CB20 — `S1.6D-CB20-AUTH-001`.

Authorization artifact commit:
`065190ba987df328b4615e7c7d9d58301dceff91`.

## Execution results

| Case | Execution | Consumption | Recorded result |
|---|---|---|---|
| CB01 | EXECUTED | CONSUMED | PASS |
| CB07 | EXECUTED | CONSUMED | PASS |
| CB20 | EXECUTED | CONSUMED | PASS |

All executions were bound to canonical contract fingerprint:
`a36f96b363b31235a33bc02104bceb1115713a6e61bdbd608511abf0e6e14a6a`.

The detailed batch record is preserved in
`execution_results/S1_6D_INITIAL_BATCH_EXECUTION_RESULT_v0_1.json`.

## Semantic findings

CB01 preserved the distinction between twenty publications and one
independent origin. Repetition from the same origin was not counted as
independent corroboration.

CB07 preserved attribution and CT-02 allegation status. The executor did not
manufacture CT-03 or CT-04 support and rejected allegation-only Event
construction.

CB20 supported the attention-production claim while preserving zero support
for prevalence. Fixture cardinality was not converted into a prevalence
claim.

## Durable controls

- all three authorization IDs are durably `CONSUMED`;
- all three result records survive store reopen;
- replay of CB01 authorization was rejected before executor invocation;
- execution and recording timestamps are timezone-aware;
- execution IDs and authorization IDs remain separately bound;
- authorization artifacts remain immutable historical issuance records.

## Regression

After execution and durable recovery verification:

- full test suite: 239 PASS;
- regression failures: 0.

## Maturity and production boundary

These PASS results establish functional evidence only for the three bounded
canonical benchmark fixtures and their current executors.

They do not establish:

- end-to-end product verification;
- representative user validation;
- incremental decision value;
- production source-ingestion authority;
- production deployment authority.

Capability promotion is not performed by this audit. Any maturity transition
requires a separate evidence-led adjudication against the applicable
capability contract.
