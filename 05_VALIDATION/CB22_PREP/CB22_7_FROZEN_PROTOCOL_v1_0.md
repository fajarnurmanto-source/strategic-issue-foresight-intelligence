# CB22 Representative User Workflow Study — Frozen Protocol v1.0

Status: PROJECT AUTHORIZED — PRE-EXECUTION — NO RESULT

Protocol identifier: `CB22-v1.0`  
Task package identifier: `CB22-TASK-v1.0`  
Freeze date: 2026-10-15 (Asia/Jakarta)

## Claim under test

At least four of five analyzable representative participants can complete the
bounded WF-01–WF-11 SIFI workflow using a rights-safe fixed task package, no
forbidden output is produced, every completed brief passes its evidence-link
check, and no more than one of five participants requires routine developer
help.

This is an initial representative-user operability study. It cannot establish
live-ingestion fitness, production safety, comparative decision value,
institutional impact, or commercial readiness by itself.

## Representative-user profile

Inclusion requires all of the following:

- age and legal capacity sufficient to provide informed consent under the
  applicable local process;
- current or recent work involving issue monitoring, research synthesis,
  policy analysis, risk analysis, intelligence analysis, or an analogous
  evidence-review role;
- routine use of a desktop browser and ability to read the study language;
- no prior use of the SIFI workflow beyond the standardized orientation.

Exclusion applies to SIFI developers, workflow authors, anyone who helped set
the acceptance thresholds, anyone lacking valid consent, and anyone whose
participation would breach an applicable organizational or ethics requirement.
Excluded people may join clearly labeled rehearsals only.

## Sample and fixed thresholds

- Minimum analyzable counted participants: 5.
- Task-completion acceptance: at least 80% (4 of 5; scaled proportionally if
  more than five valid sessions are included).
- Routine-developer-help acceptance: at most 20% of counted sessions.
- Forbidden output acceptance: zero observations.
- Evidence-link acceptance: every completed counted session must pass.
- Counted sessions may not be selectively removed after outcomes are known.

`Routine developer help` means participant-specific navigation, recovery, or
analytical assistance beyond the standardized task explanation and safety
boundary. A safety stop is logged but is not routine help unless it also
supplies workflow or analytical guidance. Any analytical coaching is routine
developer help.

## Result rules

- `NOT_READY`: no valid counted session exists.
- `IN_PROGRESS`: one or more but fewer than five valid counted sessions exist.
- `PASS`: the minimum sample exists and every fixed acceptance rule passes.
- `FAIL`: the minimum sample exists and any fixed acceptance rule fails.
- `INCONCLUSIVE`: reserved for protocol-integrity compromise or approved study
  closure without the minimum analyzable sample; it must be entered by a
  documented adjudication, never inferred as PASS.

## Consent and data minimization

The facilitator records only pseudonymous codes in SIFI. The participant must
receive the study information and confirm consent before timing begins. The
consent record is maintained by the authorized study custodian outside the
SIFI database; SIFI stores only its pseudonymous reference and timestamp.

Direct identifiers, free-text participant statements, contact details, IP
addresses, and signatures must not be entered into SIFI. Structured blocker,
failure, reason, and intervention codes are used instead.

## Task package and rights

Every counted participant receives the same frozen, rights-safe task package.
Its sources must be redistribution-safe or created for the study; its manifest
and checksum must be approved before use. A synthetic rehearsal package cannot
be silently relabeled as counted evidence.

## Retention and deletion

Pseudonymous observation records are retained for 12 months after final
adjudication, unless a shorter applicable requirement controls. The study data
custodian authorizes deletion. Consent/contact records, if any, remain outside
SIFI and follow their separately approved retention rule. Backups containing
study observations are included in the same deletion scope.

## Adjudication

Before the first counted participant, two role assignments are required:
`PRIMARY_ADJUDICATOR` and `INDEPENDENT_CHECKER`. Neither may change thresholds
after seeing outcomes. Conflicts are disclosed; a conflicted adjudicator is
replaced before result release. Real names need not be stored in SIFI: stable
authorized staff codes are sufficient, with the identity mapping held by the
study authority.

## Immutable boundaries

Automated tests, rehearsals, synthetic sessions, developer sessions, missing
consent, protocol mismatch, task-package mismatch, or fewer than five counted
participants cannot produce CB22 PASS.
