# SIFI Mandatory Development Mandates

Effective date: 2026-11-06

## SIFI-CBM-01
Competitive Benchmarking and Selective Integration

Status: MANDATORY

Requirements:

1. Preserve the existing SIFI Core V2 architecture.
2. Benchmark mature commercial intelligence products.
3. Adapt only features that improve measurable user value.
4. Reuse mature Laravel and open-source components.
5. Avoid duplicate implementations and unnecessary rewrites.
6. Keep external integrations modular and removable.
7. Build a separate Superadmin Integration Hub.
8. Support configurable AI and lawful data providers.
9. Enforce API quota, cost, licensing, security and audit controls.
10. Preserve existing investigation, provenance and evidence workflows.

## SIFI-EBPD-01
Evidence-Based Product Development

Status: MANDATORY

Acceptance dimensions:

1. Capability
2. Usability
3. Reliability
4. Commercial Viability

Requirements:

- Use automated acceptance testing.
- Preserve reviewed visual baselines.
- Verify source and database integrity.
- Measure operational cost and performance.
- Prefer real lawful data when access is available.
- Protect existing production and real evidence boundaries.
- Maintain rollback capability.

## Permanent Development Principles

Results-first.
Reuse-first.
Real-data-first.
Laravel-first.
Selective integration.
No unnecessary architecture rewrite.

## Authorization Boundaries

LOCAL_DEVELOPMENT_AUTHORIZATION=GRANTED
SAFE_LOCAL_INSTALLATION=PREAUTHORIZED
AUTOMATED_TESTING=PREAUTHORIZED
PRODUCTION_ADMISSION=DENIED
REAL_EVIDENCE_ADMISSION=DENIED
DESTRUCTIVE_OPERATIONS=SEPARATE_APPROVAL_REQUIRED

Paid services and public deployment require separate authorization.
