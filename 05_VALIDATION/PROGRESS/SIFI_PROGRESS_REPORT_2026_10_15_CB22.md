# SIFI Progress Report — CB22 Engineering and Rehearsal

Date: 15 October 2026 (Asia/Jakarta)  
Workspace: `D:\Research\SIFI`  
Latest basis commit: `3f3918b`  
Verification: 383 Windows-local tests PASS; release check PASS

## Delivered in this work session

- Project authorization was recorded without treating it as participant
  consent or an institutional/ethics determination.
- CB22 protocol `CB22-v1.0`, representative profile, sample size (minimum 5),
  completion threshold (80%), routine-help ceiling (20%), zero forbidden-output
  rule, evidence-link rule, retention rule, and result states were frozen.
- An original synthetic rights-safe task package was frozen and protected by
  SHA-256 together with the protocol and observation schema.
- A local CB22 dashboard mode was added with pseudonymous screening/consent
  references, counted/rehearsal separation, structured evidence choices for
  WF-01–WF-11, coded interventions/incidents/stops, resume, observation catalog,
  and adjudication export.
- Counted sessions fail closed for missing consent, ineligible profile,
  developer participation, duplicate participants, missing/changed frozen
  assets, unassigned adjudicators, incomplete conflict review, or unresolved
  external-review status.
- The first counted session locks governance assignments and frozen hashes in
  SQLite; later changes are detected and block continuation.
- Observation history is append-only and terminal records cannot be rewritten.
- Aggregate assessment cannot PASS with no/small/active sample, duplicate
  participant, insufficient completion, excess routine help, forbidden output,
  or evidence-link failure.
- Exports cross-check every observation against its workflow audit events and
  brief. Missing/mismatched linkage demotes final assessment to INCONCLUSIVE.
- Automated PASS is explicitly non-claiming. A final claim requires immutable
  matching votes from the assigned primary adjudicator and independent checker
  against the same bundle hash.
- Recruitment runbook, consent/screening form, pseudonymous roster template,
  session-day checklist, configuration/status/export/adjudication scripts, and
  local operator guidance were added.
- A real local automated rehearsal completed all 11 stages in `REHEARSAL` mode,
  was backed up locally, and correctly left CB22 at NOT_READY with zero counted
  participants.

## Current verified state

- Controlled non-production prototype index: **93%**.
- Operational-product index: **65%**.
- Commercial-readiness index: **53%**.
- CB22 engineering: ready for governed human coordination.
- CB22 counted gate: closed.
- Real counted representative participants: 0.
- Automated CB22 result: NOT_READY.
- Final adjudication receipt: none.
- CB22/L5/production/commercial claim: not allowed.

Local runtime evidence retained inside SIFI:

- database: `runtime\data\sifi.sqlite`;
- rehearsal backup: `runtime\backups\sifi-CB22_REHEARSAL_20261015.sqlite`;
- latest adjudication bundle: shown by `scripts\status-cb22.ps1`;
- local execution gate: `runtime\config\cb22-execution-gate.json`.

## Human actions still required before the first counted session

1. Assign a real primary adjudicator and a different real independent checker,
   using pseudonymous staff codes.
2. Resolve and document whether external/institutional review is required; if
   required, obtain it. Do not infer `NOT_REQUIRED` from project-owner approval.
3. Run `scripts\configure-cb22.ps1` with those real determinations.
4. Recruit representative analysts through an authorized human coordinator;
   keep names and contact details outside SIFI.
5. Screen and obtain individual informed consent before timing each session.

## Next roadmap action

Execute at least five finalized, unique counted sessions under the frozen
package, without protocol changes. Then export one adjudication bundle and have
both assigned reviewers independently vote on that exact file. Only a final
two-role PASS receipt permits updating CB22 and the representative-operability
readiness dimension. CB23, live acquisition, production security/deployment,
support operations, and commercial proof remain subsequent independent gates.
