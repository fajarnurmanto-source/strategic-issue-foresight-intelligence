# SIFI S0.3S3 — Source-to-Coverage Binding Contract Draft

Status: WORKING DRAFT — NOT FROZEN

## 1. Purpose
SourceCoverageBinding connects a Source to one or more Coverage Profiles
without making source identity equivalent to issue relevance.

## 2. Core Principle
Source relevance is not Evidence Item relevance.

A national source may contain highly local evidence.
A local source may report a national issue.
Publisher geography must never determine issue geography by itself.

## 3. Minimum Fields
- binding_id
- source_id
- coverage_profile_id
- binding_role
- editorial_scope
- geographic_scope
- collection_priority
- cadence_policy
- discovery_enabled
- evidence_candidate_enabled
- inheritance_allowed
- rights_contract_ref
- valid_from
- valid_to
- active_state
- binding_version

## 4. Binding Roles
Initial roles:
- CORE_LOCAL
- CORE_PROVINCIAL
- CORE_NATIONAL
- SHARED_OFFICIAL
- SHARED_DATA
- CONTEXTUAL
- FALLBACK
- EXPERIMENTAL

Binding role describes collection strategy.
It does not describe factual authority.

## 5. Shared Sources
One Source may bind to many profiles.

Examples:
BPS national services:
- Indonesia
- DI Yogyakarta
- Kota Yogyakarta
- Kota Surabaya

National regulation source:
- may be shared by all Indonesian profiles

This must not require duplicate Source objects.

## 6. Local Source Packs
Coverage Profiles may reference local source packs.

Example:
Yogyakarta pack:
- municipal official sources
- provincial official sources
- local media cohort

Surabaya pack:
- municipal official sources
- provincial official sources
- local media cohort

Source packs are configuration bundles, not separate collector codebases.

## 7. Admission Separation
Collection eligibility:
"Should SIFI inspect this source?"

Issue relevance:
"Should this Evidence Item or Issue appear for this Coverage Profile?"

These are separate decisions.

## 8. Rights Precedence
A SourceCoverageBinding cannot override source rights.

If source access/retention rights prohibit an operation,
profile configuration cannot authorize it.

## 9. Inheritance Guard
A parent profile may expose a source to a child profile,
but individual items still require relevance adjudication.

National source availability does not imply R3 relevance.

## 10. Failure Conditions
Binding architecture fails if:
- the same source must be recreated for every profile;
- source popularity determines authority;
- source geography determines event geography;
- source binding automatically admits every article;
- rights constraints can be bypassed by profile configuration.

