# SIFI S0.3Y2 — Social Platform Admission & Data Isolation Contract v0.1

Status: WORKING SUCCESSOR — NOT FROZEN

## 1. Purpose

Define the minimum governance requirements before social-platform signals,
customer-authorised private data, or institution-authorised private data may
enter SIFI.

## 2. Social Default

SOCIAL COLLECTION IS OFF BY DEFAULT.

Social content may be useful for:

- weak-signal discovery;
- issue discovery;
- verification routing;
- public-attention analysis;
- public-position analysis.

It is not automatically production evidence.

## 3. Social Admission States

### SOCIAL-0 — DISABLED

No collection or processing.

Default Stage0 state.

### SOCIAL-1 — DISCOVERY_ONLY

Permitted public signal may be used to trigger verification.

It may not independently support a production conclusion.

### SOCIAL-2 — CONTROLLED_PUBLIC_SIGNAL

A permitted platform route and defined retention/use scope exist.

The signal may enter controlled analysis subject to privacy and evidence
admission.

### SOCIAL-3 — PRODUCTION_ELIGIBLE_ROUTE

Platform/API rights, commercial-use requirements, retention,
redistribution, privacy and provenance controls are all sufficiently
resolved for the intended production use.

Evidence-specific admission under S0.3W is still required.

### SOCIAL-4 — BLOCKED

Use is prohibited because acquisition, platform rules, privacy, credentials,
or other governing controls do not permit the intended processing.

## 4. Required Social Platform Review

Before SOCIAL-2 or SOCIAL-3, record where applicable:

- platform name;
- official API availability;
- authorised acquisition route;
- account requirement;
- automation permission;
- commercial-use state;
- retention state;
- deletion obligations;
- redistribution state;
- display requirements;
- attribution requirements;
- developer-policy requirements;
- rate limits;
- personal-data risk;
- profiling risk;
- location-data risk;
- account/credential scope;
- permission expiry;
- revocation triggers.

## 5. Public Post Rule

A public post is not equivalent to unrestricted personal data.

Public visibility does not automatically authorise:

- permanent retention;
- profiling;
- bulk replication;
- commercial redistribution;
- social-graph construction;
- identity enrichment.

## 6. Platform Route Rule

Prefer official, documented, authorised platform routes.

Do not use:

- stolen sessions;
- scraped private interfaces;
- login bypass;
- CAPTCHA bypass;
- anti-bot defeat;
- unauthorised private endpoints;
- credential sharing outside authorised scope.

## 7. Social Evidence Rule

A SOCIAL-1 signal may trigger:

- official-source verification;
- independent-source verification;
- geographical verification;
- event confirmation;
- analyst review.

The original signal does not silently become Z2 production evidence.

## 8. Identity Minimisation

Default social representation should prefer:

- event;
- topic;
- geography;
- organisation;
- actor class;
- aggregate attention pattern.

Retain account-level identity only where necessary and governed.

## 9. Sentiment Boundary

Sentiment is a contextual analytical feature.

Sentiment must not become a dominant issue-priority score.

Negative attention does not itself establish:

- harm;
- truth;
- prevalence;
- severity;
- causality;
- policy failure.

## 10. Social Independence

Cross-posted, syndicated, copied, coordinated, automated, or mirrored social
content must not be treated as independent corroboration merely because
multiple accounts reproduce it.

## 11. Customer Private Workspace

Customer-authorised data must enter a bounded workspace.

Minimum workspace controls:

- customer_id;
- workspace_id;
- authorisation_reference;
- permitted datasets;
- permitted users;
- processing scope;
- retention;
- deletion trigger;
- output scope;
- export scope;
- expiry;
- revocation state.

## 12. No Global Leakage

Customer-private data must not:

- enter global source registry payloads;
- become global evidence;
- train unrelated customer models;
- affect unrelated customer scoring;
- become global benchmarks;
- be exposed through cross-tenant retrieval.

## 13. Institution-Specific Workspace

Institution-authorised private data follows the same isolation principles.

Institutional authorisation does not make that data part of SIFI's public
or global corpus.

## 14. Rights Revocation

When social-platform, customer, or institutional authority expires or is
withdrawn:

- stop new affected ingestion;
- update admission state;
- enforce deletion/retention obligations;
- preserve governance history where lawful;
- recalculate affected coverage;
- identify affected outputs.

## 15. Production Gate

No social source becomes production eligible merely because:

- it is popular;
- it is public;
- it is searchable;
- a browser can access it;
- an employee can log in;
- an API exists.

SOCIAL-3 requires explicit governance evidence.

## 16. Implementation Guard

This contract does not activate any social connector.

It does not authorise:

- social scraping;
- social account login;
- private-message access;
- individual profiling;
- customer-private ingestion.

Implementation Authority remains:

NOT YET AUTHORIZED.

