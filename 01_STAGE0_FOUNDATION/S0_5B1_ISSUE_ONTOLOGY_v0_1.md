# SIFI S0.5B1 — Issue Ontology v0.1

Status: WORKING CONTRACT — NOT FROZEN

Canonical predecessor:

S0.5A — Canonical Evidence Model
HEAD at predecessor completion:
7552b18b7348b866a7880824c9c6f76116e6b77d

## 1. Purpose

This contract defines the minimum ontology required to distinguish real-world
events, persistent issues, narratives, empirically supported conditions,
strategic hypotheses, and institutional implications.

It consumes S0.5A Evidence semantics.

It does not implement clustering, classification, scoring, database schemas,
or automated issue promotion.

## 2. Predecessor Reuse

S0.5B reuses without duplication:

- Source Object;
- Publication / Content Item;
- Claim;
- Evidence Item;
- claim-type-appropriate evidence;
- evidence independence;
- corroboration;
- contradiction;
- observability states;
- Z0/Z1/Z2/Z3;
- CoverageProfile;
- geographic relevance;
- privacy / profiling controls;
- point-in-time evidence eligibility.

## 3. Core Ontology Types

### OT-01 — EVENT

A bounded occurrence or materially identifiable change associated with
time, place, actors, systems, or circumstances.

An Event may be supported, disputed, or incompletely evidenced.

A publication is not automatically an Event.

### OT-02 — ISSUE

A persistent or evolving problem, concern, condition, controversy, or
strategically relevant phenomenon associated with one or more Events,
Claims, Evidence Items, or Empirical Conditions.

An Issue is not synonymous with a topic label or article cluster.

### OT-03 — NARRATIVE_OR_PERCEPTION

An expressed interpretation, framing, perception, discourse, or sentiment
about an Event, Issue, institution, or condition.

Narrative change may occur without change in the underlying empirical
condition.

### OT-04 — EMPIRICAL_CONDITION

A real-world state supported by appropriately qualified observations,
measurements, administrative records, or other suitable evidence.

Its scope must preserve methodological limitations.

### OT-05 — STRATEGIC_HYPOTHESIS

An explicitly unconfirmed proposition about:

- drivers;
- mechanisms;
- structural causes;
- future development;
- risks;
- opportunities;
- scenarios.

A Strategic Hypothesis remains distinct from established fact.

### OT-06 — POLICY_OR_INSTITUTIONAL_IMPLICATION

A potential consequence, relevance, verification need, coordination need,
or decision consideration for an institution or user.

An implication does not establish:

- legal authority;
- operational capability;
- obligation to act;
- policy correctness.

## 4. Fundamental Ontology Guards

### G1

PUBLICATION COUNT ≠ EVENT COUNT.

### G2

EVENT COUNT ≠ REAL-WORLD PREVALENCE.

### G3

MEDIA ATTENTION ≠ IMPACT SEVERITY.

### G4

NARRATIVE CHANGE ≠ EMPIRICAL CONDITION CHANGE.

### G5

ALLEGATION ≠ EVENT ESTABLISHMENT.

### G6

ISSUE RELEVANCE ≠ LEGAL AUTHORITY.

### G7

LEGAL AUTHORITY ≠ OPERATIONAL CAPABILITY.

### G8

ANALYTICAL URGENCY ≠ AUTOMATIC POLICY ACTION.

### G9

CORRELATION ≠ CAUSATION.

### G10

ISSUE CLUSTER SIMILARITY ≠ CANONICAL ISSUE IDENTITY.

## 5. Event Identity

Conceptual Event identity may depend on:

- event_id;
- event type;
- event time or interval;
- event geography;
- involved actor classes;
- affected systems;
- material circumstances;
- supporting Claims;
- supporting Evidence Items;
- contradiction state;
- review state.

Different publications describing the same occurrence may reference one
Event.

One publication may refer to multiple Events.

Event identity must not be determined by headline similarity alone.

## 6. Issue Identity

Conceptual Issue identity may depend on:

- issue_id;
- issue domain;
- persistent concern or condition;
- related Event lineage;
- temporal continuity;
- geographic continuity or functional relationship;
- affected systems;
- empirical-condition evidence;
- narrative context;
- recurrence;
- structural continuity;
- reviewer judgment.

Topic similarity alone must not force Issue identity.

An Issue may exist with few publications.

A heavily reported subject may still fail to qualify as a strategically
meaningful Issue.

## 7. Event–Issue Relationship

Event and Issue are many-to-many in principle.

One Issue may contain:

- one material Event;
- multiple recurring Events;
- multiple geographically related Events;
- Events separated by periods of inactivity.

One Event may be relevant to more than one Issue when analytically justified.

No automatic many-to-many assignment is authorised by this contract.

## 8. Issue Persistence Class

Minimum conceptual persistence classes:

### PC-01 — INCIDENT

Predominantly bounded to one occurrence or short event chain.

### PC-02 — RECURRING

Materially similar or connected Events recur across time.

### PC-03 — STRUCTURAL

Evidence supports a persistent underlying condition, mechanism, institutional
pattern, systemic exposure, or other durable phenomenon.

Recurring publication is insufficient to establish STRUCTURAL.

These are ontology semantics, not frozen engineering enums.

## 9. Operational Lifecycle

Minimum conceptual lifecycle states:

- ISSUE_CANDIDATE;
- ACTIVE;
- MONITORING;
- INACTIVE;
- RESOLVED_OR_MITIGATED;
- REEMERGED;
- MERGED;
- SPLIT;
- INVALIDATED.

Lifecycle state and persistence class are separate dimensions.

For example:

a STRUCTURAL issue may temporarily be INACTIVE in observed reporting.

## 10. Multiple Trajectory Dimensions

SIFI must not collapse trajectory into one opaque value.

At minimum distinguish:

### ATTENTION_TRAJECTORY

Change in monitored publication or discourse attention.

### EVIDENCE_TRAJECTORY

Change in quantity, quality, corroboration, contradiction, or certainty of
qualified evidence.

### CONDITION_TRAJECTORY

Change in the underlying real-world condition where sufficiently evidenced.

### NARRATIVE_TRAJECTORY

Change in framing, sentiment, interpretation, or discourse.

### COVERAGE_CONTEXT

Changes in monitored-source coverage that may distort apparent trends.

A rise in ATTENTION_TRAJECTORY does not establish worsening
CONDITION_TRAJECTORY.

## 11. Issue Lineage

Canonical issue history must support:

- continuation;
- recurrence;
- re-emergence;
- merge;
- split;
- correction;
- invalidation;
- successor relationship.

Lineage must be attributable and reversible through authorised review.

Historical identity must not be silently rewritten.

## 12. Merge Rule

Two Issue candidates may be merged only when there is defensible evidence of
shared issue identity or continuity.

Similarity of:

- keywords;
- titles;
- topic labels;
- location names

is insufficient by itself.

Merge must preserve predecessor identities and lineage.

## 13. Split Rule

One Issue may be split when evidence shows that materially distinct problems,
conditions, mechanisms, geographies, or trajectories have been conflated.

Split must preserve the historical relationship to the predecessor Issue.

## 14. Recurrence and Re-emergence

A previously inactive or resolved Issue may re-emerge.

Re-emergence does not require every Event to be identical.

Assessment should consider:

- common underlying condition;
- mechanism;
- affected system;
- geography;
- institutional context;
- temporal relationship.

## 15. Narrative / Reality Separation

SIFI must preserve the possibility that:

- narrative rises while empirical condition is unchanged;
- narrative falls while empirical condition worsens;
- competing narratives exist around the same Event;
- narrative may itself be strategically relevant.

Narrative is therefore neither noise nor automatic empirical truth.

## 16. Empirical Condition Boundary

An Empirical Condition requires claim-type-appropriate Evidence under S0.5A.

Media attention alone cannot establish:

- incidence;
- prevalence;
- severity;
- exposure;
- causal mechanism.

Empirical Condition state must preserve:

- supporting evidence;
- limitations;
- contradiction;
- uncertainty;
- temporal scope;
- geographic scope.

## 17. Weak / Emerging Signal Boundary

A weak or emerging signal may warrant:

- monitoring;
- verification;
- additional evidence collection;
- human review.

A weak signal is not automatically:

- an established Event;
- a qualified Issue;
- a Strategic Issue;
- an empirical trend.

Novelty alone does not establish importance.

## 18. Strategic Hypothesis Boundary

A Strategic Hypothesis should preserve where relevant:

- driver;
- mechanism;
- horizon;
- supporting evidence;
- alternative explanation;
- competing hypothesis;
- confounder;
- lag;
- contradiction;
- assumption;
- uncertainty;
- leading indicator;
- watch condition;
- falsification / invalidation condition;
- point-in-time version.

S0.5B defines the semantic boundary.

The persistent representation will be integrated by S0.5C.

## 19. Causal Discipline

A causal or structural interpretation must not arise solely from:

- temporal sequence;
- correlation;
- repeated reporting;
- shared geography;
- semantic similarity.

Material causal hypotheses should consider:

- candidate mechanism;
- alternative explanations;
- competing hypotheses;
- confounders;
- temporal ordering;
- lag;
- disconfirming evidence;
- falsification conditions.

S0.5B does not create a causal inference engine.

## 20. Geography Semantics

S0.5B preserves existing distinct dimensions:

- source_geography;
- event_geography;
- mentioned_geographies;
- affected_geography;
- policy_jurisdiction;
- relevance_geography;
- coverage_profile_id.

Rights/privacy jurisdiction remains independent.

Administrative containment alone is insufficient for strategic relevance.

## 21. Institutional Roles

Issue relevance and institutional responsibility must remain separate.

Minimum conceptual roles:

### IR-01 — AFFECTED_AREA

A geography, community, system, market, or institution experiencing
consequences.

### IR-02 — COMPETENT_AUTHORITY

An institution for which appropriate evidence supports relevant legal
mandate or jurisdiction.

### IR-03 — OPERATIONAL_RESPONDER

An institution or actor with practical capability or operational role in
response.

### IR-04 — COORDINATING_OR_REFERRAL_INSTITUTION

An institution to which evidence, verification need, coordination need, or
response consideration may appropriately be referred.

### IR-05 — INTERESTED_STAKEHOLDER

A user or institution for which an Issue is strategically relevant despite
not necessarily holding direct legal or operational responsibility.

These roles may overlap but are not interchangeable.

## 22. Authority Evidence Guard

COMPETENT_AUTHORITY requires appropriate authority evidence.

Examples may include:

- legislation;
- regulation;
- formal delegation;
- authoritative institutional mandate.

Geographic proximity alone is insufficient.

Source assertion alone may also be insufficient unless appropriate to the
claim.

Where competence cannot be established, use an unresolved or
AUTHORITY_REVIEW_REQUIRED semantic state rather than inventing authority.

## 23. Operational Capability Guard

Legal authority does not automatically establish operational capacity.

Operational capability may require evidence of:

- assigned function;
- organisational capacity;
- operational arrangements;
- service responsibility;
- resource availability;
- verified response mechanism.

S0.5B does not judge organisational performance merely from mandate text.

## 24. Actionability Distinctions

An Issue may be:

- relevant to a user;
- relevant but not currently actionable;
- requiring verification;
- requiring additional evidence;
- potentially requiring coordination;
- outside direct user authority;
- authority unresolved;
- suitable for monitoring only.

These are conceptual distinctions.

S0.5D will govern human authority over escalation or disposition.

## 25. Promotion Boundary

S0.5B recognises the conceptual distinction among:

SIGNAL
→ ISSUE_CANDIDATE
→ QUALIFIED_ISSUE
→ STRATEGIC_ISSUE
→ WATCH / ESCALATION / DE-ESCALATION CONTEXT.

This contract does not authorise automatic promotion.

Evidence requirements come from S0.5A.

Transition authority is owned by S0.5D.

Persistent representation is owned by S0.5C.

## 26. Temporal Truth

Issue history must preserve where applicable:

- earliest relevant Event time;
- first observed signal time;
- first qualification time;
- lifecycle-transition time;
- evidence-revision time;
- correction time;
- merge/split time;
- resolution/mitigation time;
- re-emergence time;
- decision/brief cutoff.

Later information must not silently alter what was knowable at an earlier
cutoff.

## 27. Relationship to S0.5C

S0.5C must implement the conceptual Issue Object contract using this ontology
and S0.5A evidence semantics.

S0.5C must not collapse:

- Event into Issue;
- Narrative into Empirical Condition;
- Attention into Severity;
- Relevance into Authority;
- Hypothesis into Fact;
- all trajectories into one score.

## 28. Relationship to S0.5D

S0.5D must define authority for:

- qualification;
- rejection;
- merge;
- split;
- correction;
- invalidation;
- reopen;
- escalation proposal;
- de-escalation;
- material conclusion revision.

## 29. AI Authority

AI may propose:

- candidate Event relationships;
- candidate Issue relationships;
- candidate persistence class;
- possible narrative shift;
- possible recurrence;
- possible institutional relevance;
- possible competing hypotheses.

AI may not autonomously:

- establish canonical Issue identity;
- perform irreversible merge/split;
- establish legal authority without evidence;
- convert attention into empirical condition;
- promote hypothesis to fact;
- authorize policy action;
- erase disagreement or historical lineage.

## 30. Implementation Guard

This contract does not authorize:

- issue clustering implementation;
- automatic Event extraction;
- issue-scoring engine;
- causal inference engine;
- institutional-routing automation;
- escalation automation;
- database implementation;
- dashboard development.

Implementation Authority remains:

NOT YET AUTHORIZED.

