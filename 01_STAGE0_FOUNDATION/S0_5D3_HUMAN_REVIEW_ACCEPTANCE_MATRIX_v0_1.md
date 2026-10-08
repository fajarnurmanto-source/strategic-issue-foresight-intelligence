# SIFI S0.5D3 — Human Review Acceptance Matrix v0.1

Status: WORKING BENCHMARK CONTRACT — NOT FROZEN

These HR01–HR20 cases test the S0.5D contract.

They do not prove implemented human-review capability.

## HR01 — AI Qualification Proposal

Input:
AI proposes qualification of an Issue candidate.

Expected:
Proposal requires authorized human disposition.

Forbidden:
AI final qualification.

## HR02 — Unsupported Promotion

Input:
Analyst proposes promotion with insufficient evidence.

Expected:
Qualifying Reviewer may reject, defer, request evidence, or abstain.

Forbidden:
Promotion merely because analyst requested it.

## HR03 — Z1 Rights Boundary

Input:
Reviewer prefers an important Z1 signal.

Expected:
May request verification.

Forbidden:
Reviewer converting Z1 into production evidence.

## HR04 — Z0 Boundary

Input:
Material information is classified Z0.

Expected:
No analytical reviewer can override prohibition.

## HR05 — Claim Qualification

Input:
Evidence partially supports a Claim.

Expected:
Authorized reviewer may qualify only the supported scope.

Forbidden:
Broadening beyond evidence.

## HR06 — Incident to Structural

Input:
Repeated news publications exist without structural evidence.

Expected:
Reviewer cannot defensibly promote STRUCTURAL solely from repetition.

## HR07 — Lifecycle Transition

Input:
Issue reporting declines after collector failure.

Expected:
Reviewer must consider coverage failure.

Forbidden:
Automatic ACTIVE → RESOLVED transition.

## HR08 — Merge Proposal

Input:
Two semantically similar Issues have materially different mechanisms.

Expected:
Identity Adjudicator may reject merge.

Forbidden:
Keyword similarity forcing merge.

## HR09 — Approved Merge

Input:
Evidence establishes shared canonical Issue identity.

Expected:
Merge may be approved with predecessor lineage retained.

## HR10 — Split

Input:
One Issue is shown to contain two materially distinct problems.

Expected:
Authorized split retains predecessor/successor history.

## HR11 — Reviewer Disagreement

Input:
Two authorized reviewers materially disagree.

Expected:
DISAGREEMENT_OPEN and adjudication path remain explicit.

Forbidden:
Silently selecting first reviewer.

## HR12 — Adjudication with Dissent

Input:
Adjudicator resolves disagreement but one view remains materially defensible.

Expected:
Final disposition and dissent history remain auditable.

## HR13 — Correction

Input:
Later evidence shows earlier qualified conclusion was materially wrong.

Expected:
Correction preserves original conclusion, cutoff, reviewer, and new rationale.

Forbidden:
Silent historical overwrite.

## HR14 — Reopen

Input:
Previously inactive/rejected Issue receives new material evidence.

Expected:
Authorized reviewer may reopen with rationale and new evidence references.

## HR15 — Authority Unresolved

Input:
Issue is relevant but legal competence cannot be established.

Expected:
AUTHORITY_REVIEW_REQUIRED or abstention/defer state.

Forbidden:
Invented legal authority.

## HR16 — Analytical Escalation

Input:
Evidence warrants higher human attention.

Expected:
Authorized analytical reviewer may approve CONSIDER_ESCALATION.

Forbidden:
Treating approval as binding institutional action.

## HR17 — Coordination / Referral

Input:
Issue likely falls within another institution's domain.

Expected:
Reviewer may approve consideration of coordination/referral.

Forbidden:
SIFI issuing an institutional directive.

## HR18 — Foresight Review

Input:
Strategic Hypothesis has supporting and contradictory evidence.

Expected:
Reviewer preserves assumptions, uncertainty, alternatives and falsification
conditions.

Forbidden:
Review approval converting hypothesis into fact.

## HR19 — Historical Reconstruction

Input:
A later correction exists after cutoff T.

Expected:
Review state at T remains reconstructable without later-known correction.

## HR20 — Maturity Boundary

Input:
All S0.5D contracts are complete.

Expected:
Human Review capability remains at most L1 — CONTRACTED.

Forbidden:
Claiming L2–L6 from documentation alone.

