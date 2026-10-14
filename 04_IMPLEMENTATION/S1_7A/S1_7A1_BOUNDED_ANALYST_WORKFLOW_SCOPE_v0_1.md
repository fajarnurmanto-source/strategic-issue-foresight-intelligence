# SIFI S1.7A — Bounded Analyst Workflow Scope v0.1

Status: CONTROLLED IMPLEMENTATION BASELINE — NOT USER VALIDATED

## Purpose

S1.7 connects already implemented, controlled non-production components into
a bounded analyst workflow. It may establish executable workflow mechanics;
it may not establish representative-user operability, decision value, or
production readiness.

## Authorized workflow

An analyst session receives a defined issue-monitoring task and must be able
to complete these explicit stages:

1. inspect the applicable coverage profile and its limitations;
2. inspect only lawfully admitted evidence;
3. inspect the persistent Issue and its identity state;
4. inspect supporting, contradictory, contextual, and excluded evidence as
   separate categories;
5. inspect geographic relevance dimensions without collapsing them;
6. inspect uncertainty and evidence gaps;
7. inspect each trajectory dimension separately;
8. propose or perform a correction only through explicit human authority;
9. record bounded candidate prioritization without treating attention as
   materiality;
10. produce an evidence-linked analytical brief;
11. retrieve the same workflow state at an explicit historical cutoff.

## Initial implementation boundary

The first implementation slice is a deterministic application service over
synthetic or otherwise rights-safe fixtures. It must:

- accept explicit task, analyst, issue, and cutoff identities;
- fail closed when evidence admission is not production-eligible;
- preserve evidence categories, geography dimensions, uncertainty, and
  trajectories rather than flattening them;
- expose correction as a protected transition rather than a direct mutation;
- create an evidence-linked brief as analytical output only;
- preserve point-in-time retrieval and append-only audit events;
- require every workflow stage to be acknowledged before completion.

## Explicit exclusions

S1.7A does not authorize or claim:

- source acquisition or recurring ingestion;
- use of Z1 material in production output;
- automatic Issue qualification or correction;
- automatic institutional action;
- production deployment;
- CB22 PASS;
- representative-user validation;
- comparative decision-value validation.

CB22 remains `PROTOCOL_ONLY / NOT_EXECUTED / NO_RESULT` until an operational
V1 is used by representative intended analysts under a separately approved
study protocol.

## Exit gate for implementation start

Implementation may proceed only if every workflow step has an explicit input,
output, failure condition, audit event, and governing boundary in the
S1.7A2 matrix, and automated checks confirm the production and maturity holds.
