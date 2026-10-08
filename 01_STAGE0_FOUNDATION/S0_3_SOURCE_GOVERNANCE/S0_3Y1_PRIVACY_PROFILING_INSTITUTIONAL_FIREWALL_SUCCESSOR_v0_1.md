# SIFI S0.3Y1 — Privacy, Profiling & Institutional Firewall Successor v0.1

Status: WORKING SUCCESSOR — NOT FROZEN

## 1. Purpose

This successor introduces explicit controls for:

- personal-data minimisation;
- privacy eligibility;
- actor and individual profiling;
- institutional / employment privilege;
- customer-authorised private data;
- tenant and workspace isolation.

It is additive and non-destructive.

It does not replace:

- S0.3U Source Registry and Rights Governance;
- S0.3W Data Rights & Evidence Admission;
- S0.3X Source Rights / Acquisition / Retention;
- Geography / CoverageProfile controls.

## 2. Core Privacy Principles

SIFI shall default to:

AGGREGATE FIRST
→ ORGANISATION / SECTOR / POLICY / GEOGRAPHY
→ ACTOR CLASS WHERE NECESSARY
→ NAMED INDIVIDUAL ONLY WHEN NECESSARY AND GOVERNED.

Public availability does not remove privacy obligations.

A public post or profile must not be treated as unrestricted personal data.

## 3. Purpose Limitation

Personal data may be processed only when a specific SIFI purpose is defined.

The purpose must be:

- explicit;
- relevant to issue or foresight intelligence;
- proportionate;
- reviewable;
- no broader than necessary.

Convenience, curiosity, or future unspecified usefulness is not a sufficient
purpose.

## 4. Data Minimisation

SIFI must not retain personal identifiers merely because they are available.

Preferred representation is:

issue
→ event
→ organisation
→ sector
→ geography
→ policy
→ actor class

before:

named individual.

Where an identifier is unnecessary for the intended analytical outcome,
it should not enter the canonical evidence representation.

## 5. Operational Personal-Data Risk Classes

These are internal SIFI governance classes, not statutory legal categories.

### PRIV-0 — NON_PERSONAL_OR_AGGREGATE

Examples:

- aggregate counts;
- geographic indicators;
- organisation-level events;
- sector-level patterns;
- policy actions.

Default preferred class.

### PRIV-1 — PUBLIC_ROLE_MINIMAL

Limited information directly necessary to represent a person's public or
organisational role.

Examples:

- office holder;
- official spokesperson;
- named organisational representative;
- author or signatory where materially relevant.

Private-life inference is not implied.

### PRIV-2 — LIMITED_PERSONAL_CONTEXT

Personal information necessary for a defined and documented analytical
purpose.

Requires:

- explicit purpose;
- minimum fields;
- lawful / authorised basis review where applicable;
- retention limit;
- access control;
- reviewability.

### PRIV-3 — HIGH_RISK_PERSONAL_DATA

Material whose processing creates elevated privacy, safety,
discrimination, reputational, or legal risk.

Requires dedicated review before any use.

S0.3Y does not itself authorise production processing of PRIV-3.

### PRIV-4 — PROHIBITED_OR_UNNECESSARY

Material SIFI must not process for ordinary issue/foresight intelligence
because it is prohibited, unlawfully obtained, irrelevant, excessive, or
unnecessary.

## 6. Profiling Levels

Profiling is OFF by default.

### P0 — OFF

Default state.

No individual profile is constructed.

### P1 — PUBLIC FIGURE / ORGANISATIONAL ROLE ONLY

Limited analysis of behaviour or statements strictly within a public,
official, professional, or organisational role.

Must not infer unrelated private traits.

### P2 — LIMITED ACTOR ANALYSIS

Structured analysis of a named actor may occur only when:

- necessary to a defined issue;
- proportionate;
- supported by lawful / authorised data;
- limited to relevant dimensions;
- reviewed;
- retention-controlled.

P2 is not activated merely because data is public.

### P3 — HIGH-RISK PROFILE

Profiling that could materially affect an individual's rights, reputation,
safety, access, treatment, or consequential decision context.

Requires dedicated privacy/legal/governance authorisation.

S0.3Y does not grant such authorisation.

### P4 — PROHIBITED

Profiling must not be performed where it depends on prohibited acquisition,
unlawful personal data, unnecessary sensitive inference, discriminatory
targeting, covert surveillance, or other prohibited processing.

## 7. Sensitive Inference Guard

SIFI must not infer or construct unnecessary private traits about people.

High-risk inferred attributes must not be generated merely from:

- language;
- associations;
- location;
- following patterns;
- likes;
- social graphs;
- photographs;
- behavioural traces.

Where such analysis would be material, a dedicated governance review is
required before processing.

## 8. Institutional / Employment Firewall

SIFI must distinguish:

PUBLICLY AVAILABLE SOURCE RIGHTS

from:

ACCESS OBTAINED THROUGH EMPLOYMENT OR INSTITUTIONAL PRIVILEGE.

The following must not become default SIFI venture data merely because a
founder, employee, adviser, customer, or collaborator can access them:

- non-public government complaints;
- internal dashboards;
- internal social-listening systems;
- institutional credentials;
- unpublished reports;
- internal analytics;
- internal correspondence;
- private citizen submissions;
- non-public administrative records;
- internal meeting records;
- non-public decision records;
- confidential operational information.

## 9. Venture Data Independence Test

For any institutionally obtained source, ask:

WOULD SIFI STILL HAVE LAWFUL AND AUTHORISED ACCESS TO THIS DATA
IF THE PERSON PROVIDING ACCESS LEFT THEIR INSTITUTIONAL ROLE TOMORROW?

If NO:

the access is not an independent SIFI data right.

The material must not become part of the default global SIFI corpus without
separate, explicit, independently valid authorisation.

## 10. Independent Authorisation

Institutional or customer data may be processed only where separate
authorisation clearly defines, where applicable:

- authorised provider;
- authorised recipient;
- purpose;
- dataset;
- fields;
- permitted processing;
- permitted users;
- retention;
- deletion;
- onward disclosure;
- derived outputs;
- customer visibility;
- expiry;
- revocation.

Employment relationship alone is not sufficient authorisation.

## 11. Public Government Information

Government-origin information that is genuinely available to the public
through a lawful public route may be evaluated like other public sources.

Its public-government origin does not remove:

- rights review;
- privacy review;
- retention limits;
- evidence admission;
- attribution requirements.

## 12. Customer Data Isolation

Customer-authorised private data must remain logically isolated from:

- the global source corpus;
- other customers;
- unrelated projects;
- global model-improvement datasets;
- unrelated benchmark corpora.

Private customer data must not silently improve or alter global SIFI outputs.

## 13. Customer-Derived Intelligence

Derived customer output must preserve lineage to customer-specific evidence
without exposing the customer's private data to other tenants.

A derived pattern may not be promoted into global SIFI knowledge unless a
separate lawful and authorised basis permits that promotion.

## 14. Credential Rule

SIFI must not request, reuse, share, store, or operate institutional
credentials outside explicit authorised scope.

No production design may depend on a founder's personal access to an
institutional account.

Credential possession is not equivalent to data rights.

## 15. Private Communication Rule

Private messages, closed groups, direct messages, internal chats, private
mailboxes, or restricted workspaces are not public-source intelligence.

They require independent explicit authority.

Absent such authority:

admission_zone = Z0 or excluded.

## 16. Retention and Deletion

Personal-data handling must support:

- minimum necessary retention;
- expiry;
- revocation;
- deletion where required;
- suppression where required;
- access review;
- audit history.

Retention must not default to indefinite.

## 17. Privacy Jurisdiction

Privacy eligibility must preserve:

- data-subject context where relevant;
- source jurisdiction;
- privacy jurisdiction;
- customer jurisdiction where relevant;
- processing location where material.

Geographic issue relevance must not be used as a privacy permission shortcut.

## 18. Evidence Relationship

Privacy eligibility does not establish:

- source reliability;
- factual truth;
- geographic relevance;
- evidence strength;
- strategic significance.

Likewise, high evidence strength does not override privacy restrictions.

## 19. AI Authority

AI may:

- propose de-identification;
- classify privacy risk candidates;
- identify unnecessary identifiers;
- propose actor classes;
- flag potential high-risk profiling;
- recommend review.

AI may not:

- create a lawful basis;
- create consent;
- expand an authorisation;
- infer permission from public availability;
- silently activate profiling;
- bypass institutional restrictions;
- convert customer-private data into global data.

## 20. Implementation Guard

During Stage0:

- profiling remains P0 by default;
- no new personal-data collection is authorised;
- no private institutional connector is authorised;
- no customer-private corpus is activated;
- no cross-customer learning is authorised.

Implementation Authority remains:

NOT YET AUTHORIZED.

