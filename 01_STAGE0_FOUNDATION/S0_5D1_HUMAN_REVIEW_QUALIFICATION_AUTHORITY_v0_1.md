# SIFI S0.5D1 — Human Review & Qualification Authority v0.1

Status: WORKING CONTRACT — NOT FROZEN

Canonical predecessors:

S0.5A — Canonical Evidence Model
S0.5B — Issue Ontology v0
S0.5C — Issue Object Schema v0

Latest canonical predecessor HEAD:

09f0aa3f217edd7010d9c74dd46bda6f0e7492d2

## 1. Purpose

This contract defines human authority over material epistemic and analytical
state transitions in SIFI.

It establishes who may inspect, propose, qualify, reject, correct, merge,
split, suspend, invalidate, reopen, or approve analytical escalation
transitions.

It does not implement identity management, enterprise RBAC, workflow
software, or institutional decision authority.

## 2. VPC-DVA Maturity Boundary

If committed and verified, Human Review Authority may be classified at most
as:

L1 — CONTRACTED.

This contract does not prove:

L2 — IMPLEMENTED;
L3 — FUNCTIONALLY VERIFIED;
L4 — END-TO-END VERIFIED;
L5 — USER-VALIDATED;
L6 — DECISION-VALUE VALIDATED.

Human-review documentation is not proof that representative users can
operate the workflow.

## 3. Permanent Authority Principles

### P1 — AI MAY PROPOSE, HUMAN AUTHORITY DISPOSES

AI-generated classification, qualification, merge/split, trajectory,
authority, escalation, or foresight proposals are not authoritative until
the required human review is completed.

### P2 — REVIEW AUTHORITY ≠ SOURCE RIGHTS AUTHORITY

An Issue reviewer cannot create source rights or change Z0/Z1/Z2/Z3 status.

### P3 — REVIEW AUTHORITY ≠ INSTITUTIONAL POLICY AUTHORITY

SIFI review approval does not authorize enforcement, procurement, budget,
legal, administrative, or binding policy decisions.

### P4 — HUMAN REVIEW ≠ EMPIRICAL VALIDATION

A reviewer can qualify whether available evidence supports a SIFI analytical
state.

Reviewer approval does not itself create empirical truth.

### P5 — NO SILENT OVERRIDE

Every material override requires explicit rationale, authority basis,
timestamp, evidence references, and preserved predecessor state.

### P6 — HISTORY IS NON-DESTRUCTIVE

Corrections, reversals, merge/split actions, invalidation, and reopening must
preserve historical states.

### P7 — RIGHTS / PRIVACY HARD BLOCKS PREVAIL

Human review cannot override:

- prohibited acquisition;
- Z0 prohibition;
- unresolved material rights restriction where production use is prohibited;
- privacy prohibition;
- profiling prohibition;
- unlawful institutional-access dependency;
- prohibited retention or redistribution.

### P8 — ABSTENTION IS VALID

A reviewer may legitimately refuse qualification where evidence,
jurisdiction, coverage, authority, or uncertainty remains insufficient.

## 4. Abstract Reviewer Roles

Stage0 defines abstract authority roles, not named personnel.

### RR-01 — ANALYST_REVIEWER

May:

- inspect Source / Publication / Claim / Evidence / Issue objects;
- annotate evidence;
- flag contradiction;
- propose claim qualification;
- propose Issue qualification;
- propose lifecycle change;
- propose analytical response;
- request additional evidence;
- request higher review.

May not independently execute protected material transitions unless separately
assigned the required authority.

### RR-02 — QUALIFYING_REVIEWER

May, within assigned scope:

- qualify or reject an Issue candidate;
- qualify material claim/evidence interpretation;
- approve lifecycle-state transitions;
- approve persistence-class changes;
- approve material trajectory interpretation;
- approve correction or reopening;
- defer or abstain.

Must preserve evidence basis and limitations.

### RR-03 — IDENTITY_ADJUDICATOR

May:

- approve Issue merge;
- approve Issue split;
- adjudicate canonical Issue identity disputes;
- resolve identity-lineage conflicts.

Must preserve predecessor/successor lineage.

### RR-04 — GOVERNANCE_REVIEWER

References existing source-rights/privacy governance authority.

May review matters governed by S0.3 controls such as:

- source admission;
- rights state;
- retention;
- privacy;
- profiling;
- institutional-data independence.

S0.5D does not redefine or weaken existing S0.3 governance.

### RR-05 — ANALYTICAL_ESCALATION_REVIEWER

May approve a SIFI analytical state such as:

- CONSIDER_ESCALATION;
- CONSIDER_COORDINATION_OR_REFERRAL;
- WATCH_MATERIAL_CHANGE.

This is approval of an analytical recommendation for human consideration.

It is not approval of a binding institutional action.

### RR-06 — REVIEW_ADJUDICATOR

May resolve material reviewer disagreement when ordinary review cannot reach
a defensible disposition.

Must preserve:

- competing interpretations;
- reviewer identities/roles;
- supporting evidence;
- rationale;
- minority/dissenting view where material;
- final adjudication.

## 5. Authority Assignment Rule

A person may hold more than one abstract reviewer role only when explicitly
authorized by the eventual implementation governance.

Stage0 does not require separate individuals for every role.

However, authority must be explicit and auditable.

No authority may be inferred merely because a user can access the system.

## 6. Review Record Contract

Every material review action should preserve at minimum:

- review_id
- reviewed_object_type
- reviewed_object_id
- reviewed_object_version
- reviewer_id_or_role_reference
- reviewer_authority_role
- proposed_action
- final_disposition
- authority_basis
- evidence_refs
- contradictory_evidence_refs
- coverage_limitations
- rights_or_privacy_constraints
- rationale
- uncertainty_state
- disagreement_state
- review_started_at
- decided_at
- analytical_cutoff
- supersedes_review_id
- correction_reference
- escalation_reference
- review_notes

## 7. Review Disposition States

Minimum conceptual dispositions:

### RD-01 — APPROVED

The proposed analytical transition is accepted within declared scope.

### RD-02 — REJECTED

The proposed transition is not supported.

### RD-03 — DEFERRED

Decision is postponed pending additional information or authority.

### RD-04 — ADDITIONAL_EVIDENCE_REQUIRED

Available evidence is insufficient for qualification.

### RD-05 — GOVERNANCE_REVIEW_REQUIRED

Rights, privacy, profiling, or admission review is required.

### RD-06 — AUTHORITY_REVIEW_REQUIRED

Institutional/legal authority remains unresolved.

### RD-07 — DISAGREEMENT_OPEN

Material reviewer disagreement remains unresolved.

### RD-08 — ADJUDICATED

A designated adjudicator has resolved the review conflict with preserved
rationale and dissent history.

### RD-09 — ABSTAINED

Reviewer intentionally declines qualification because the evidence or
authority basis is insufficient.

These are analytical review states, not organizational HR or legal states.

## 8. Protected Material Transitions

The following transitions require authorized human disposition.

### T-01 — CLAIM_QUALIFICATION

Examples:

- unsupported → supported_with_limitations;
- disputed → corroborated;
- supported → contradicted.

### T-02 — ISSUE_QUALIFICATION

Examples:

- ISSUE_CANDIDATE → QUALIFIED_ISSUE;
- candidate rejection;
- reopening a rejected candidate.

### T-03 — STRATEGIC_ISSUE_QUALIFICATION

Qualification that an Issue merits strategic attention within a defined
user/context.

Does not authorize institutional action.

### T-04 — PERSISTENCE_CLASS_CHANGE

Examples:

- INCIDENT → RECURRING;
- RECURRING → STRUCTURAL.

Publication repetition alone is insufficient.

### T-05 — LIFECYCLE_CHANGE

Examples:

- ACTIVE → MONITORING;
- MONITORING → INACTIVE;
- INACTIVE → REEMERGED;
- ACTIVE → RESOLVED_OR_MITIGATED.

### T-06 — ISSUE_MERGE

Requires identity evidence and lineage preservation.

### T-07 — ISSUE_SPLIT

Requires evidence that materially distinct problems or mechanisms were
conflated.

### T-08 — MATERIAL_CORRECTION

Revises a material analytical conclusion while preserving history.

### T-09 — INVALIDATION

Marks an earlier analytical state invalid without deleting its existence.

### T-10 — REOPEN

Reactivates a previously rejected, inactive, resolved, or invalidated
analytical object when justified.

### T-11 — ANALYTICAL_ESCALATION_APPROVAL

Approves a non-binding analytical recommendation for escalation
consideration.

### T-12 — COORDINATION_OR_REFERRAL_APPROVAL

Approves a recommendation that authorized humans consider coordination or
referral.

## 9. Rights and Privacy Boundary

No S0.5D reviewer may:

- convert Z1 into Z2 by analytical preference;
- approve Z0 use;
- authorize prohibited collection;
- bypass security/access controls;
- waive privacy restrictions without applicable governance authority;
- enable prohibited individual profiling;
- use privileged institutional access merely because the reviewer possesses
  such access.

Where these issues arise:

GOVERNANCE_REVIEW_REQUIRED.

## 10. Evidence Boundary

A reviewer may interpret evidence.

A reviewer may not manufacture evidence.

A reviewer may not convert:

- article count into prevalence;
- attention into severity;
- correlation into causation;
- geographic relevance into legal authority;
- hypothesis into fact.

Reviewer rationale must remain consistent with S0.5A and S0.5B.

## 11. Merge / Split Governance

Merge or split review must record:

- affected issue IDs;
- proposed operation;
- identity rationale;
- supporting Evidence Items;
- contradictory evidence;
- temporal context;
- geographic context;
- reviewer authority;
- final disposition;
- predecessor/successor lineage.

Merge/split must never erase prior canonical identities.

## 12. Reviewer Disagreement

Material disagreement must remain visible.

Minimum disagreement states:

- NO_MATERIAL_DISAGREEMENT;
- DISAGREEMENT_OPEN;
- SECOND_REVIEW_REQUESTED;
- ADJUDICATION_REQUIRED;
- ADJUDICATED_WITH_CONSENSUS;
- ADJUDICATED_WITH_DISSENT_RETAINED.

The system must not hide disagreement by selecting the first reviewer or
averaging judgments.

## 13. Correction & Historical Integrity

A correction must preserve:

- original state;
- original reviewer;
- original evidence cutoff;
- correction rationale;
- corrected state;
- correcting reviewer;
- correction time;
- affected downstream outputs.

Later reviewer knowledge must not silently rewrite historical analytical
states.

## 14. Point-in-Time Review Correctness

For analytical cutoff T, historical reconstruction should eventually recover:

- evidence available at T;
- rights/admission state at T;
- Issue version at T;
- reviewer disposition known at T;
- unresolved disagreement at T;
- analytical response state at T.

Later reviews may produce newer versions.

They must not contaminate the historical state.

## 15. Analytical Escalation Boundary

SIFI may support a reviewer-approved analytical recommendation such as:

- monitor more closely;
- verify a claim;
- seek additional evidence;
- consider escalation;
- consider coordination/referral.

SIFI does not authorize the underlying institutional action.

ANALYTICAL_ESCALATION_APPROVAL means:

"the intelligence warrants human consideration"

not:

"the institution must act."

## 16. Foresight Review

Material Strategic Hypotheses should preserve human review of:

- hypothesis statement;
- driver;
- mechanism;
- horizon;
- evidence;
- competing hypotheses;
- assumptions;
- contradiction;
- watch conditions;
- falsification conditions;
- uncertainty.

Review approval does not convert a hypothesis into a forecasted fact.

## 17. AI Authority Boundary

AI may:

- propose review candidates;
- summarize evidence;
- identify possible contradiction;
- propose qualification;
- propose merge/split;
- propose lifecycle changes;
- propose watch conditions;
- propose analytical response.

AI may not:

- issue final protected dispositions;
- authorize rights admission;
- approve merge/split;
- qualify legal authority without evidence;
- approve binding action;
- suppress reviewer disagreement;
- erase review history.

## 18. Human Error and Reversibility

Human review is a control, not a guarantee of correctness.

Material reviewer error must be correctable through:

- correction;
- supersession;
- invalidation;
- reopening;
- adjudication.

Historical reviewer error remains part of the audit record.

## 19. Conflict of Authority

If reviewers possess conflicting domain, governance, or institutional
authority:

- analytical qualification does not override rights governance;
- rights governance does not establish empirical truth;
- institutional authority does not override evidence insufficiency.

Applicable control domains remain separate.

## 20. Product Claim Boundary

Existence of this contract supports only the claim:

"SIFI has a contracted human review and qualification authority model."

It does not prove that:

- reviewers can already operate the workflow;
- reviewer decisions are reliable;
- disagreements are resolved effectively;
- review reduces analytical error;
- review improves decision quality;
- review workload is acceptable.

Those claims require implementation and empirical evaluation.

## 21. Downstream Verification Requirements

S0.6 must test at minimum:

- correct authority assignment;
- unauthorized transition rejection;
- reviewer disagreement preservation;
- correction lineage;
- merge/split lineage;
- rights hard-block precedence;
- abstention;
- analytical escalation boundary;
- historical review reconstruction.

Representative user validation is required before L5.

Comparative decision-value testing is required before L6.

## 22. Implementation Guard

This contract does not authorize:

- authentication implementation;
- RBAC implementation;
- workflow software;
- database implementation;
- user-management systems;
- production review queues;
- escalation automation;
- production deployment.

Implementation Authority remains:

NOT YET AUTHORIZED.

