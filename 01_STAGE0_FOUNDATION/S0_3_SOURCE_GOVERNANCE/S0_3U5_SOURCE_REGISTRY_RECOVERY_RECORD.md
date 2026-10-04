# SIFI S0.3U5 — Source Registry Recovery Record

Status: ACTIVE CONTROL RECORD

## Predecessor Gap

A predecessor artifact was expected at:

S0_3G_SOURCE_REGISTRY_CONTRACT_DRAFT.md

but verification showed that the file did not exist at the expected path.

## Decision

Do not reconstruct the obsolete predecessor.

Its intended function is superseded by:

- S0_3U1_SOURCE_REGISTRY_CONTRACT_v0_2.md
- S0_3U2_NAMED_SOURCE_UNIVERSE_v0_2.md
- S0_3U3_RIGHTS_ACCESS_AUDIT_CONTRACT.md
- S0_3U4_SOURCE_AUDIT_MATRIX_TEMPLATE.csv

## Reason

The original expected S0.3G design predated the finalized
geography-configurable architecture.

The v0.2 successor must support:
- shared sources;
- multi-profile bindings;
- rights precedence;
- geography-independent source identity;
- explicit candidate vs approved states.

