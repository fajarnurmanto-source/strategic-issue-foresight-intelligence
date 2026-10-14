# SIFI S1.6D — Semantic Authorization and Execution Boundary Report v0.1

Status: IMPLEMENTED AND VERIFIED — AWAITING HUMAN AUTHORIZATION

## Purpose

Complete the technical controls required before any initial canonical
benchmark execution can be considered.

This report does not grant execution authorization and does not record a
canonical benchmark result.

## Implemented controls

### Semantic authorization issuer

Commit: `18578c539edf738cf811a979cc03e7af2410f8d6`

The issuer verifies committed and hash-consistent materialization,
adjudication, fixture, executor identity, authorization artifact semantics,
single-use declaration, timezone-aware issuance time, and the current
canonical contract fingerprint.

Issuance creates a `CaseExecutionAuthorization` value only. It does not
register authorization with the harness and does not execute a case.

### Durable single-use consumption

Commit: `26c02d9`

Authorization consumption is persisted by authorization ID as an append-only
version-1 record. A second consumption attempt fails closed, including after
the SQLite store is closed and reopened.

### Execution identity and time boundary

Commit: `7881c1d`

The execution coordinator binds one attempt to:

- execution ID;
- authorization ID;
- canonical case ID;
- timezone-aware requested, started, and completed times;
- the resulting execution record.

Durable authorization consumption occurs before executor invocation.

### Non-authoritative authorization proposal

Commit: `1b75d53`

The proposal builder prepares the immutable technical bindings for CB01,
CB07, and CB20 while preserving the authority boundary. Proposal records use:

- `PENDING_HUMAN_AUTHORIZATION`;
- `PROPOSED_AWAITING_HUMAN_AUTHORITY`;
- `AI_PROPOSAL_ONLY_HUMAN_AUTHORITY_REQUIRED`.

Human authority fields remain empty. The semantic issuer rejects a proposal
if it is presented as an execution authorization.

## Verification

- semantic authorization issuer tests: 12 PASS;
- durable consumption tests: 6 PASS;
- execution identity/time tests: 5 PASS;
- authorization proposal tests: 4 PASS;
- full regression suite: 239 PASS;
- canonical authorization artifacts created: 0;
- canonical benchmark executions performed: 0;
- canonical benchmark results recorded: 0.

## Initial batch readiness

CB01, CB07, and CB20 are technically eligible for a human authorization
decision because their materializations and adjudications remain committed,
hash-consistent, and semantically conformant.

Technical eligibility is not authorization.

## Remaining authority gate

Before an authorization artifact may be issued, an authorized human must
explicitly provide and accept responsibility for:

- authorization ID and matching execution intent ID;
- issuing person or role reference;
- authority basis;
- timezone-aware issuance time;
- the decision to authorize exactly one canonical execution;
- the bound case, artifacts, executor, and contract fingerprint.

AI generation, repository access, implementation authority, or executor
availability does not satisfy this gate.

## Standing limitations

- production source ingestion remains unauthorized;
- production deployment remains unauthorized;
- capability maturity is not promoted;
- no PASS or FAIL claim is made for CB01, CB07, or CB20;
- no canonical execution should occur until the human authority gate is
  satisfied and the resulting artifact passes semantic issuance checks.
