# SIFI S0.3X2 — Live Source Rights Audit Protocol v0.1

Status: WORKING AUDIT PROTOCOL — NOT FROZEN

## 1. Purpose

Define a reproducible procedure for live source-rights verification before
any candidate source is promoted toward production admission.

## 2. Initial Audit Cohort

The first controlled audit cohort is:

- SRC-BPS
- SRC-DATAGOID
- SRC-BMKG
- SRC-JDIH-YK
- SRC-WARTA-YK
- SRC-OPENDATA-YK

Presence in this cohort does not imply approval.

## 3. Audit Questions

For each source determine, where evidence is available:

### Identity
- canonical source identity
- operator/publisher
- canonical domain
- source family
- source type

### Discovery
- canonical URL
- RSS/Atom
- API
- public export/download
- sitemap
- structured feed/index

### Technical Access
- public access
- authentication requirement
- documented machine access
- rate limits
- robots information where relevant
- anti-bot/browser dependency

### Rights
- terms located
- licence located
- automated-access position
- metadata-use position
- fulltext-fetch position
- fulltext-retention position
- excerpt position
- derived-use position
- redistribution position
- commercial-use position
- attribution requirements

### Risk
- privacy risk
- copyright risk
- database-right risk
- confidentiality risk

### Temporal / Reproducibility
- reviewed_at
- terms/licence version/date where identifiable
- evidence locator
- reproducibility state
- next review trigger/date

### Admission
- candidate admission zone
- blocking condition
- permitted retention class
- production eligibility
- unresolved questions

## 4. Evidence Standard

Every material rights conclusion must have a traceable evidence locator.

Prefer evidence in this order:

1. official licence/terms;
2. official API/data documentation;
3. official usage policy;
4. official robots/technical documentation where relevant;
5. explicit written permission;
6. other authoritative source-specific evidence.

Search-engine snippets alone are insufficient for a final rights conclusion.

## 5. Unknown Rule

If evidence cannot resolve a material production right:

state = UNKNOWN
production admission = DENIED or Z1

Do not infer permission from absence of prohibition.

## 6. Robots Rule

robots.txt is a technical crawling signal, not a universal licence or
commercial-use grant.

A permissive robots rule does not by itself authorize:

- retention;
- redistribution;
- commercial reuse.

A restrictive robots/technical policy must be respected where applicable.

## 7. Browser Rule

If ordinary permitted structured/static access is unavailable and browser
automation appears necessary:

do not execute browser automation during this audit.

Record:

BROWSER_REQUIRED_PENDING_RIGHTS_REVIEW

unless an explicit permitted route is established.

## 8. Audit Output States

Minimum source audit states:

- DISCOVERED
- ACCESS_VERIFIED
- RIGHTS_REVIEWED
- RESEARCH_ONLY
- PRODUCTION_ELIGIBLE
- RESTRICTED
- BLOCKED
- SUSPENDED
- RETIRED

`ACCESS_VERIFIED` does not imply `PRODUCTION_ELIGIBLE`.

## 9. Zone Mapping

Typical mapping:

BLOCKED / prohibited route
→ Z0

research/discovery useful but production rights unresolved
→ Z1

rights sufficient for intended production use
→ candidate Z2 eligibility

Z2 evidence still requires evidence-specific admission under S0.3W.

## 10. Audit Discipline

The audit must not:

- create credentials solely to bypass ordinary access restrictions;
- bypass CAPTCHA/paywall/login controls;
- scrape private areas;
- reinterpret public access as commercial permission;
- silently promote UNKNOWN to ALLOWED.

## 11. No Production Effect

Completion of the live audit does not activate collection.

It only determines what acquisition paths may later be eligible for
controlled implementation.

