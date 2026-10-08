# SIFI S0.5D2 — Review Authority & Transition Matrix v0.1

Status: WORKING CONTROL — NOT FROZEN

| ID | Material action | Analyst Reviewer | Qualifying Reviewer | Identity Adjudicator | Governance Reviewer | Analytical Escalation Reviewer | Review Adjudicator |
|---|---|---|---|---|---|---|---|
| A01 | Inspect / annotate | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW |
| A02 | Propose claim qualification | PROPOSE | PROPOSE | PROPOSE | PROPOSE | PROPOSE | PROPOSE |
| A03 | Final claim qualification | NO | ALLOW | NO | NO | NO | ADJUDICATE |
| A04 | Propose Issue qualification | PROPOSE | PROPOSE | PROPOSE | PROPOSE | PROPOSE | PROPOSE |
| A05 | Final Issue qualification | NO | ALLOW | NO | NO | NO | ADJUDICATE |
| A06 | Change persistence class | PROPOSE | ALLOW | NO | NO | NO | ADJUDICATE |
| A07 | Change lifecycle state | PROPOSE | ALLOW | NO | NO | NO | ADJUDICATE |
| A08 | Merge Issues | PROPOSE | PROPOSE | ALLOW | NO | NO | ADJUDICATE |
| A09 | Split Issue | PROPOSE | PROPOSE | ALLOW | NO | NO | ADJUDICATE |
| A10 | Correct material conclusion | PROPOSE | ALLOW | ALLOW_IF_IDENTITY | NO | NO | ADJUDICATE |
| A11 | Invalidate analytical state | PROPOSE | ALLOW | ALLOW_IF_IDENTITY | NO | NO | ADJUDICATE |
| A12 | Reopen Issue | PROPOSE | ALLOW | ALLOW_IF_IDENTITY | NO | NO | ADJUDICATE |
| A13 | Change source rights zone | NO | NO | NO | EXISTING_S0_3_AUTHORITY | NO | NO |
| A14 | Override Z0 prohibition | NO | NO | NO | NO | NO | NO |
| A15 | Approve prohibited profiling | NO | NO | NO | NO | NO | NO |
| A16 | Approve analytical escalation | PROPOSE | PROPOSE | NO | NO | ALLOW | ADJUDICATE |
| A17 | Approve coordination/referral consideration | PROPOSE | PROPOSE | NO | NO | ALLOW | ADJUDICATE |
| A18 | Resolve reviewer disagreement | NO | NO | NO | DOMAIN_SPECIFIC_ONLY | NO | ALLOW |
| A19 | Abstain / defer | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW | ALLOW |
| A20 | Make binding institutional action | NO | NO | NO | NO | NO | NO |

## Interpretation

PROPOSE means the role may create a review proposal but not execute the
protected final transition.

ALLOW means the role may execute that analytical transition when assigned
that authority and operating within scope.

ADJUDICATE means authority exists only for resolving an applicable review
dispute.

EXISTING_S0_3_AUTHORITY means S0.5D does not create new source-governance
authority.

NO means this contract does not authorize the action.

This matrix is an abstract Stage0 authority model.

It is not an implemented RBAC table.

