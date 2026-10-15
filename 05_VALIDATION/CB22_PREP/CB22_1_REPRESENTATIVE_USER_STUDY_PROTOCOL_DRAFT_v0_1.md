# CB22 Representative User Workflow Study Protocol — Draft v0.1

Status: PREPARED — NOT APPROVED, NOT EXECUTED, NO RESULT

## Research question

Can representative intended analysts complete the bounded SIFI workflow from
coverage inspection through historical retrieval without routine developer
intervention, while preserving all S0.3–S0.5 controls?

## Scope

The study uses the Windows-local SIFI package and a rights-safe study case.
It evaluates user operability only. It does not evaluate live ingestion,
production deployment, comparative decision value, or institutional impact.

## Participants

Participants must satisfy the separately approved representative-user profile.
Developers or authors of the workflow may participate only in pilot rehearsals;
their results cannot count toward CB22.

No participant may begin until screening, informed consent, and the study
governance authorization are recorded. Use pseudonymous participant codes in
all observation records. Do not record direct identifiers in SIFI.

## Task

Each participant receives the same defined issue-monitoring task, information
cutoff, rights-safe evidence package, and operator guide. The participant must
complete WF-01 through WF-11 and produce an evidence-linked analytical brief.

The facilitator may explain the study task and safety boundaries before timing
begins. During the task, every intervention must be logged with its reason,
duration, and whether it constitutes routine developer help.

## Required measurements

- task completion and reason for non-completion;
- start and completion time;
- completed workflow stages;
- count, duration, and category of facilitator/developer interventions;
- prohibited outputs or boundary violations;
- evidence-link completeness;
- participant-reported blockers collected after the task;
- application failures and recovery behavior.

## Pre-registration requirement

Before the first counted participant begins, the study authority must freeze:

- representative-user inclusion and exclusion criteria;
- minimum analyzable participant count;
- definition of routine developer intervention;
- completion and evidence-link acceptance rules;
- permitted missing-data handling;
- aggregate PASS, FAIL, and INCONCLUSIVE thresholds;
- retention period and deletion authority;
- adjudicator identities and conflict handling.

Thresholds cannot be selected or changed after outcomes are inspected. Pilot
rehearsals must be labeled and excluded from the confirmatory result.

## Result states

- `PASS`: only if every pre-registered acceptance rule is met;
- `FAIL`: if a pre-registered failure rule is met;
- `INCONCLUSIVE`: if the approved minimum evidence is not obtained or protocol
  integrity is compromised;
- `NO_RESULT`: before an authorized study is executed.

Architecture, automated tests, developer operation, or dashboard existence
cannot produce a CB22 PASS.
