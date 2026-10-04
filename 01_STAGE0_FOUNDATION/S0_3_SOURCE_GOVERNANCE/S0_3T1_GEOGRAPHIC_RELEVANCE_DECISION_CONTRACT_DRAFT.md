# SIFI S0.3T1 — Geographic Relevance Decision Contract

Status: WORKING DRAFT — NOT FROZEN

## 1. Purpose

This contract defines how SIFI decides whether an Evidence Item or Issue
is relevant to an active Coverage Profile.

Administrative containment alone is insufficient.

Example:
An event in Kota Yogyakarta is geographically inside Indonesia,
but is not automatically strategically relevant to PROFILE-IDN.

## 2. Separate Questions

SIFI must answer independently:

1. Where is the source based or editorially focused?
2. Where did the event occur?
3. Which geography is explicitly affected?
4. Which jurisdiction legally or institutionally applies?
5. Which Coverage Profile is being evaluated?
6. What defensible relation connects the evidence/issue to that profile?
7. Is that relation strong enough for admission?

## 3. Geographic Relation Types

Minimum relation states:

- SAME_TARGET
- CHILD_OF_TARGET
- PARENT_OF_TARGET
- PEER_LOCAL
- CROSS_LOCAL_NETWORK
- MULTI_JURISDICTION
- NATIONAL_TO_SUBNATIONAL
- OUTSIDE_HIERARCHY
- CONTEXT_COMPARATOR
- RELATION_UNKNOWN

Geographic relation and relevance class are separate fields.

## 4. Relevance Classes

### R0 — DIRECT_TARGET
The Evidence Item or Issue directly affects the active target geography
or a system for which the target profile has direct responsibility/exposure.

R0 is not granted merely because an event occurs somewhere inside a broad
parent geography.

### R1 — CROSS_LOCAL
The event occurs in another kabupaten/kota or local jurisdiction but has
a defensible cross-local mechanism affecting the active local profile.

Examples:
- shared waste facility;
- water system;
- commuter corridor;
- transport network;
- regional supply chain;
- shared environmental exposure.

### R2 — PROVINCIAL_TO_LOCAL
A province-level policy, condition, service, or structural issue applies to
or materially affects an active kabupaten/kota profile.

R2 is primarily meaningful when the active profile is below province level.

For an active province profile, the same province-level issue may instead
be R0.

### R3 — NATIONAL_TO_SUBNATIONAL
A national policy, condition, regulation, fiscal mechanism, macroeconomic
change, or system has a defensible effect on a subnational active profile.

For PROFILE-IDN, a national-level issue may instead be R0.

### R4 — CONTEXTUAL
The item is useful as:
- comparator;
- analogue;
- weak signal;
- reference-class candidate;
- external strategic context;

but no sufficient direct effect has been established.

### RX — IRRELEVANT
No defensible relation to the active profile is present.

## 5. Non-Relevance States

The following are NOT equivalent to RX:

- GEOGRAPHY_UNKNOWN
- GEOGRAPHY_AMBIGUOUS
- INSUFFICIENT_RELEVANCE_EVIDENCE
- CONFLICTING_GEOGRAPHIC_EVIDENCE
- PROFILE_RULE_UNAVAILABLE
- CROSS_LEVEL_RELATION_UNRESOLVED

Unresolved evidence must not be silently discarded as irrelevant.

## 6. Decision Order

For each Evidence Item / Issue and active Coverage Profile:

### Step 1 — Resolve Target
Load CoverageProfile and target GeographyNode.

### Step 2 — Resolve Evidence Geography
Attempt to identify:
- event_geography
- affected_geography
- policy_jurisdiction
- mentioned_geographies

Unknown remains explicit.

### Step 3 — Test Direct Target Effect
Ask whether evidence supports direct exposure/effect on the target.

If yes:
candidate R0.

### Step 4 — Test Cross-Local Mechanism
If event occurs in a peer/local external geography, test whether an explicit
functional mechanism connects it to the target.

If yes:
candidate R1.

### Step 5 — Test Provincial Applicability
If active target is kabupaten/kota, test whether province-level policy,
service, condition, or system applies materially.

If yes:
candidate R2.

### Step 6 — Test National Applicability
If active target is subnational, test whether national policy/condition has
a defensible local mechanism.

If yes:
candidate R3.

### Step 7 — Test Contextual Value
If no direct relevance exists but an approved comparator, analogue, weak
signal, or reference-class relationship exists:

candidate R4.

### Step 8 — Irrelevance
If sufficient evidence establishes no defensible relation:

RX.

### Step 9 — Unresolved
If evidence is missing, ambiguous, or contradictory:
use the appropriate unresolved state, not RX.

## 7. Admission Evidence

R0-R3 require at least one defensible admission basis such as:

- explicit affected geography;
- legal/jurisdictional applicability;
- fiscal transfer mechanism;
- infrastructure/network connection;
- service dependency;
- environmental pathway;
- mobility pathway;
- supply-chain/economic linkage;
- institutional applicability;
- explicit government/authority response defining local exposure.

Mention-only linkage is insufficient.

## 8. Mention Guard

The following does NOT establish relevance:

"Yogyakarta was mentioned in the article."

A geographic mention may become:
mentioned_geography

but cannot automatically become:
affected_geography
or
relevance_geography.

## 9. Source Guard

Publisher identity does not determine relevance.

Examples:

National publisher + Yogyakarta event
may produce R0 for PROFILE-YK.

Local Yogyakarta publisher + national policy
may produce R3 for PROFILE-YK.

## 10. Parent Containment Guard

Event geography being a descendant of a broad active profile does not
automatically create R0.

Example:
A neighborhood parking dispute in one Indonesian city is technically
inside Indonesia but may be RX or R4 for PROFILE-IDN.

National relevance requires a national-scale implication, mechanism,
policy concern, systemic pattern, or explicitly configured analytical purpose.

## 11. Profile-Specific Classification

The same Issue may have different relevance classes under different profiles.

Example:

National fiscal-transfer reform:

PROFILE-IDN:
R0

PROFILE-DIY:
R3

PROFILE-YK:
R3

PROFILE-SBY:
R3

provided the mechanism applies to each subnational profile.

## 12. Explainability Requirement

Every R0-R4 decision must preserve:

- coverage_profile_id
- relevance_class
- geographic_relation
- relevance_reason
- evidence_basis
- rule_version
- model_version if used
- human_review_state
- assessed_at

## 13. Deterministic Core

Where administrative hierarchy and jurisdiction rules are known,
the deterministic rule layer takes precedence over generative interpretation.

AI may propose:
- entities;
- geography;
- candidate linkage;
- relevance explanation.

AI may not create binding relevance without evidence and rule validation.

