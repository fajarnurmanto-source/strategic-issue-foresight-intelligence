# SIFI S0.3U3 — Rights & Access Audit Contract

Status: WORKING CONTROL CONTRACT

## 1. Purpose

Define the minimum audit required before a Source may move from candidate
status to PILOT_APPROVED.

## 2. Audit Dimensions

Each source must be evaluated for:

### Identity
- canonical owner/publisher
- canonical domain
- source family
- source type

### Discovery
- public index
- sitemap
- RSS/Atom
- API
- search endpoint
- other structured discovery

### Technical Access
- endpoint reachable
- authentication
- API key
- rate limit
- pagination
- robots behavior
- anti-bot restrictions
- browser requirement

### Rights / Terms
- terms located
- terms reviewed
- license located
- automated access position
- metadata use position
- full-text use position
- retention position
- redistribution position
- commercial-use position
- attribution requirements

### Operational Suitability
- update cadence
- stability
- identifier quality
- canonical URL quality
- historical availability
- duplicate/syndication risk
- geography utility
- expected evidence role

## 3. Rights States

For each action use:

- ALLOWED
- ALLOWED_WITH_CONDITIONS
- PROHIBITED
- UNKNOWN
- NOT_APPLICABLE

No UNKNOWN may be interpreted as ALLOWED.

## 4. Storage Layers

Audit separately:

A. raw fetched representation
B. normalized metadata
C. extracted facts/entities
D. embeddings/features
E. Evidence Item
F. derived Issue Object
G. user-visible quotation/snippet

Permission at one layer does not automatically authorize another.

## 5. Minimum Pilot Approval

A source may become PILOT_APPROVED only when:

- identity is verified;
- permitted acquisition path is known;
- terms/rights state is recorded;
- retention behavior is known sufficiently for the chosen pipeline;
- attribution requirement is recorded;
- technical failure behavior is defined;
- source-specific restrictions are represented in Registry;
- commercial-use uncertainty is not hidden.

## 6. Restricted Source Strategy

If full-text retention is prohibited or uncertain, SIFI may consider a
rights-compatible architecture using only permitted combinations such as:

- URL
- title
- timestamp
- publisher metadata
- short permitted snippet
- derived features
- extracted claims/facts where lawful
- content fingerprint where lawful

This is source-specific and may not be assumed globally.

## 7. Commercial Guard

Stage-0 research use and commercial product use are distinct questions.

A source acceptable for internal benchmark research must not automatically
be used in a paid deployment.

## 8. Reverification

Rights/access audit must retain:

- verified_at
- verifier
- evidence locator
- terms version/date where available
- next_review_due
- change notes

## 9. Failure Semantics

If rights remain uncertain:
COMMERCIAL_USE_UNKNOWN

If automated access is uncertain:
AUTOMATED_ACCESS_UNKNOWN

If raw retention is uncertain:
RAW_RETENTION_UNKNOWN

The system must degrade functionality explicitly rather than silently
violate the source contract.

