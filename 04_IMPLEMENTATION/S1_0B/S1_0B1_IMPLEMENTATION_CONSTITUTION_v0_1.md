# SIFI S1.0B1 — Implementation Constitution v0.1

Status: WORKING — NOT YET CANONICAL

## 1. Authority Basis

Canonical activation HEAD:

4c81db6f41d1da89f13732242fbae55f44ac708b

Stage0 Foundation:

FROZEN — S0.7 VERIFIED

Implementation Authority:

AUTHORIZED — CONTROLLED NON-PRODUCTION IMPLEMENTATION ONLY

Production Source Ingestion Authority:

HOLD — NOT AUTHORIZED

Production Deployment Authority:

HOLD — NOT AUTHORIZED

MVU_PRODUCTION_STATUS = NOT_YET_ESTABLISHED

## 2. Purpose

This constitution governs the transition from frozen architectural intent
to executable SIFI capability without weakening Stage0 epistemic,
rights, privacy, geography, review, point-in-time, or product-claim
boundaries.

S1.0B defines implementation discipline and traceability.

S1.0B does not itself implement product capability.

## 3. Contract-to-Code Rule

Every material implementation component must be traceable through:

Stage0 contract
→ implementation obligation
→ code boundary
→ executable test
→ benchmark or regression evidence where applicable
→ capability evidence
→ maturity state.

Untraceable product logic must not silently become canonical.

## 4. Stage0 Non-Regression

Frozen Stage0 artefacts are predecessors and must not be rewritten to
manufacture implementation consistency.

Implementation may:

- REUSE;
- HARDEN;
- EXTEND through explicit successor control.

Implementation must not silently weaken:

- Z0 / Z1 / Z2 / Z3 semantics;
- no-Z1-laundering;
- rights vs relevance separation;
- information coverage vs rights coverage;
- geography-configurable relevance;
- Source / Publication / Claim / Evidence / Event / Issue separation;
- point-in-time correctness;
- evidence-type discipline;
- Issue identity and lineage;
- Human Review Authority;
- abstention;
- benchmark falsification semantics;
- Track A vs Track B separation.

## 5. Domain First, Adapter Second

Core domain rules must remain separable from:

- network acquisition;
- web frameworks;
- databases;
- external APIs;
- user-interface frameworks;
- deployment infrastructure.

External adapters must not define domain truth.

## 6. Deterministic Core

Where Stage0 rules are deterministic, implementation should preserve
deterministic and reproducible behaviour.

Probabilistic or AI-assisted components may propose classifications or
hypotheses only where permitted by governing contracts.

They must not silently exercise protected human authority.

## 7. Rights Fail-Closed Rule

UNKNOWN rights, unresolved required permissions, prohibited acquisition,
or applicable privacy hard blocks must not be converted into permissive
production behaviour.

Z1 remains research-only.

Source-level Z2 candidacy does not automatically admit an Evidence Item
into Z2.

Z3 cannot launder Z1.

## 8. Evidence Separation Rule

Implementation must preserve distinct canonical objects:

SOURCE
→ PUBLICATION / CONTENT ITEM
→ CLAIM
→ EVIDENCE ITEM
→ EVENT
→ ISSUE.

A Publication must not automatically become an Event.

An Evidence Item must not automatically become an Issue.

Publication count must not silently become independent corroboration.

Attention must not silently become prevalence, severity, causality, or
empirical condition.

## 9. Geography / Authority Rule

Source geography, event geography, affected geography, policy
jurisdiction, relevance geography, rights jurisdiction, and
CoverageProfile must remain distinguishable where applicable.

Geographic relevance does not establish legal authority.

Legal authority does not establish operational capability.

## 10. Issue Object Rule

Issue identity must remain stable across versions where identity persists.

Material revisions must be non-destructive and historically
reconstructable.

Trajectory dimensions must remain distinct.

No universal opaque issue score may replace the contracted dimensions.

## 11. Human Review Rule

AI MAY PROPOSE; HUMAN AUTHORITY DISPOSES where S0.5D requires protected
human disposition.

Review authority does not create:

- source rights;
- legal authority;
- empirical evidence;
- institutional policy authority.

No silent override is permitted.

Reviewer disagreement and corrections must remain auditable.

## 12. Point-in-Time Rule

Historical output must use information, rights states, evidence states,
review states, and other relevant state that was eligible at the
historical cutoff.

Later knowledge must not silently rewrite earlier analytical state.

## 13. Test-Fixture Boundary

Until separately authorized, implementation testing may use only:

- synthetic fixtures;
- benchmark fixtures;
- locally authored fixtures;
- explicitly rights-safe non-production material.

Recurring live production ingestion is outside current authority.

## 14. Dependency Rule

Third-party dependencies must be introduced only when a concrete
implementation requirement justifies them.

Framework selection does not establish capability maturity.

S1.0B introduces no third-party dependency requirement.

## 15. Maturity Rule

L0 = architectural intent only.

L1 = contracted and verified architecture.

L2 requires executable implementation evidence for the specific
capability.

L3 requires executed functional verification for that capability.

L4 requires applicable end-to-end workflow verification.

L5 requires representative-user validation.

L6 requires incremental decision-value validation against a credible
comparator where applicable.

Documentation, file creation, package installation, scaffolding, or test
existence alone must not promote maturity.

## 16. Product-Claim Guard

PCL-08 through PCL-12 remain HOLD.

S1.0B must not claim that SIFI:

- already constructs strategic Issue Objects correctly in operation;
- already avoids runtime false conclusions;
- is already usable by representative analysts without developer help;
- already outperforms a competent analyst;
- already improves strategic decisions.

## 17. Initial Implementation Boundary

The intended future package boundaries are implementation mappings, not
new domain semantics:

- src/sifi/domain/
- src/sifi/rights/
- src/sifi/geography/
- src/sifi/evidence/
- src/sifi/issues/
- src/sifi/review/
- src/sifi/persistence/
- src/sifi/benchmarks/
- tests/

These paths do not yet constitute implemented capability.

They may be created only by successor implementation work.

## 18. Initial Sequencing

S1.1 — Core Domain Model + Persistence Foundation

S1.2 — Rights / Evidence Enforcement Kernel

S1.3 — Claim / Evidence / Event Construction Kernel

S1.4 — Issue Identity + Persistent Issue Object

S1.5 — Human Review State Machine

S1.6 — Benchmark Execution Harness

S1.7 — Bounded Analyst Workflow

Foresight runtime and decision-value validation remain later successors
unless a verified dependency requires earlier work.

## 19. S1.0B Claim Boundary

S1.0B may establish only:

"SIFI has a controlled implementation constitution and contract-to-code
traceability baseline."

It does not establish L2 product capability.
