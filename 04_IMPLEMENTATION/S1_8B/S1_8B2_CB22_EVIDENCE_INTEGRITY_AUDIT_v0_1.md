# S1.8B CB22 Evidence Integrity Audit v0.1

Assessment date: 15 October 2026  
Basis: commit `26fe727`, 380 Windows-local tests PASS

## Outcome

CB22 study engineering is ready for governed rehearsal and, after the explicit
gate is completed, real counted execution. Every participant stage now exposes
the fixed five-item synthetic evidence package and requires a structured
boundary-preserving selection. Invalid selections do not advance the workflow
and are retained as coded retry blockers.

Observation versions are append-only: participant/gate identity cannot change,
stage history cannot regress or jump, intervention and incident histories
cannot be rewritten, and an ended record cannot be reopened. The first counted
session atomically records the protocol/task/schema hashes and two adjudicator
assignments as the counted-study gate lock. Later configuration or frozen-file
changes close the workflow.

Adjudication exports contain the observation, linked workflow audit events, and
linked evidence brief. A missing or mismatched session, participant, task,
stage sequence, Issue, brief, or brief evidence reference demotes a final
automated result to `INCONCLUSIVE`.

An automated PASS remains only a candidate. A claim requires immutable votes
from the assigned primary adjudicator and independent checker against the same
bundle hash. One vote, conflicting votes, a conflicted reviewer, an unassigned
staff code, or a changed bundle cannot authorize PASS.

## Current hold

No real counted participant exists. The local gate remains closed for missing
real role assignments, conflict review, and external/institutional review
determination. CB22 is `NOT_READY`; representative-user validation, L5,
production readiness, and commercial readiness remain unclaimed.
