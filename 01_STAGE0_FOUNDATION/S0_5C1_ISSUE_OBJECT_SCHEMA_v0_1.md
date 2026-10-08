# SIFI S0.5C1 — Issue Object Schema v0.1

Status: WORKING CONTRACT — NOT FROZEN

Canonical predecessors:

S0.5A — Canonical Evidence Model
S0.5B — Issue Ontology v0

Latest canonical predecessor HEAD:

9c9fdeeeb2fbe85823983df5656bb3973b061ca7

## 1. Purpose

This contract defines the persistent Issue Object representation used by
SIFI to preserve issue identity, evidence, trajectory, uncertainty,
coverage, geography, institutional relevance, watch conditions, human
review context, and decision-use metadata across time.

It consumes:

S0.5A Evidence semantics
+
S0.5B Issue Ontology semantics.

It does not implement a database, clustering engine, prioritization engine,
dashboard, alerting system, or autonomous decision system.

## 2. VPC-DVA Maturity Boundary

This contract may establish architecture maturity only.

If committed and verified as a contract, the Issue Object capability may be
classified at most as:

L1 — CONTRACTED.

This contract does not establish:

L2 — IMPLEMENTED;
L3 — FUNCTIONALLY VERIFIED;
L4 — END-TO-END VERIFIED;
L5 — USER-VALIDATED;
L6 — DECISION-VALUE VALIDATED.

No readiness claim may be increased because this document exists.

## 3. Predecessor Reuse

S0.5C reuses without duplication:

- Source Object and Source Registry;
- Publication / Content Item;
- Claim;
- Evidence Item;
- claim-type-appropriate evidence;
- evidence independence;
- corroboration;
- contradiction;
- observability states;
- Event semantics;
- Issue identity semantics;
- Narrative / Perception;
- Empirical Condition;
- Strategic Hypothesis;
- persistence classes;
- lifecycle semantics;
- geographic relevance;
- institutional-role semantics;
- Z0 / Z1 / Z2 / Z3;
- privacy and profiling controls;
- CoverageProfile;
- Information Coverage vs Rights Coverage;
- point-in-time evidence eligibility.

## 4. Core Issue Object Identity

Every persistent Issue Object must have stable identity independent from:

- article count;
- headline wording;
- current cluster membership;
- current lifecycle state;
- current reviewer interpretation.

Minimum identity fields:

- issue_id
- issue_version_id
- canonical_issue_label
- issue_domain
- issue_summary
- persistence_class
- lifecycle_state
- created_at
- version_created_at
- predecessor_issue_version_id
- canonical_identity_state
- identity_review_state

An Issue Object may evolve while retaining issue_id.

Material interpretation changes create a new issue_version_id.

## 5. Versioning Rule

Issue Objects are versioned, not silently overwritten.

A new version is required when a material change occurs in:

- canonical interpretation;
- evidence qualification;
- lifecycle state;
- persistence class;
- merge/split lineage;
- material trajectory interpretation;
- authority interpretation;
- material uncertainty;
- strategic hypothesis;
- watch conditions;
- correction or invalidation.

Historical versions remain reconstructable.

## 6. Event / Claim / Evidence Relationships

Minimum reference groups:

- related_event_ids
- supporting_claim_ids
- disputed_claim_ids
- supporting_evidence_ids
- contradictory_evidence_ids
- context_only_evidence_ids
- excluded_production_evidence_ids
- research_signal_references

The Issue Object must not treat all referenced material as equally evidentiary.

Research-only signals and production-admitted evidence must remain distinct.

## 7. Evidence Qualification Summary

An Issue Object must expose evidence status without collapsing evidence into
one opaque confidence score.

Minimum dimensions:

- evidence_support_state
- corroboration_state
- contradiction_state
- source_independence_state
- evidence_completeness_state
- claim_support_limitations
- material_evidence_gaps
- human_evidence_review_state

Evidence status is claim-specific.

An Issue may contain simultaneously:

- supported claims;
- disputed claims;
- unsupported claims;
- unresolved claims.

## 8. Narrative and Empirical Condition Separation

Minimum independent references:

- narrative_state
- narrative_evidence_refs
- empirical_condition_state
- empirical_condition_evidence_refs

Narrative change must not silently modify Empirical Condition.

Empirical Condition change must not require narrative change.

## 9. Multi-Trajectory Representation

Issue Object must preserve at least five separate trajectory dimensions.

### TR-01 — ATTENTION_TRAJECTORY

Observed change in monitored reporting or discourse attention.

### TR-02 — EVIDENCE_TRAJECTORY

Change in evidence quantity, quality, corroboration, contradiction, or
qualification.

### TR-03 — CONDITION_TRAJECTORY

Change in the underlying empirical condition where defensibly evidenced.

### TR-04 — NARRATIVE_TRAJECTORY

Change in perception, framing, discourse, or sentiment.

### TR-05 — COVERAGE_CONTEXT

Change in source universe, collection health, observation conditions, or
comparability.

No single trajectory may silently substitute for another.

## 10. Trajectory State Metadata

Each trajectory dimension should preserve where relevant:

- trajectory_state
- observation_window
- baseline_window
- supporting_evidence_refs
- coverage_context
- uncertainty
- reviewer_state
- last_updated_at

Unsupported precision is prohibited.

Trajectory may use qualitative or ordinal semantics until calibrated
benchmark methods exist.

## 11. Geography Group

Issue Object must preserve distinct:

- source_geographies
- event_geographies
- mentioned_geographies
- affected_geographies
- policy_jurisdictions
- relevance_geographies
- coverage_profile_id
- geographic_relation
- relevance_state
- relevance_reason

Rights/privacy jurisdiction remains separate.

No single generic `location` field may replace these distinctions.

## 12. Coverage & Observability Disclosure

Minimum fields:

- monitored_source_universe_version
- information_coverage_state
- rights_coverage_state
- observability_state
- collection_health_state
- known_source_gaps
- geographic_coverage_gaps
- topic_coverage_gaps
- temporal_coverage_gaps
- source_diversity_limitations
- source_universe_change_state
- comparability_state

No Issue Object may imply complete observation when coverage is materially
limited.

NO_OBSERVED_SIGNAL is not equivalent to absence.

## 13. Institutional Relevance Group

Minimum conceptual relationships:

- affected_area_refs
- competent_authority_candidates
- operational_responder_candidates
- coordinating_or_referral_candidates
- interested_stakeholders
- intended_intelligence_users
- authority_review_state
- operational_capability_review_state

Candidate authority is not established authority.

Candidate operational responder is not established capability.

## 14. Institutional Guard

Issue Object must not infer:

LEGAL AUTHORITY
from geographic relevance alone.

Issue Object must not infer:

OPERATIONAL CAPABILITY
from legal mandate alone.

Where authority is unresolved:

AUTHORITY_REVIEW_REQUIRED

or an equivalent explicit unresolved state must be preserved.

## 15. Risk / Opportunity Representation

Issue Object may record candidate:

- risk statements;
- opportunity statements;
- exposure;
- affected systems;
- potential impact;
- reversibility;
- time sensitivity.

Risk or opportunity statements must retain:

- supporting evidence;
- assumptions;
- uncertainty;
- affected scope;
- review state.

A risk statement is not automatically a forecast.

## 16. Strategic Hypothesis Representation

Issue Object may reference one or more Strategic Hypotheses.

Minimum hypothesis fields:

- hypothesis_id
- hypothesis_statement
- driver
- mechanism
- horizon
- geographic_scope
- affected_systems
- supporting_evidence_refs
- contradictory_evidence_refs
- competing_hypothesis_refs
- confounders
- assumptions
- leading_indicators
- watch_conditions
- invalidation_conditions
- uncertainty
- human_review_state
- hypothesis_version
- known_at_cutoff

Hypothesis must remain distinguishable from established fact.

## 17. Watch Conditions

Watch Conditions are explicit observations or developments that would
materially affect interpretation.

Minimum fields:

- watch_condition_id
- condition_description
- related_claim_or_hypothesis
- indicator
- expected_direction_or_state
- observation_horizon
- evidence_requirement
- escalation_relevance
- invalidation_relevance
- review_state

Numerical thresholds are not required unless calibrated and approved.

## 18. Independent Analytical Significance Dimensions

Issue Object may preserve independent analytical dimensions such as:

- attention;
- velocity;
- persistence;
- geographic spread;
- evidence strength;
- uncertainty;
- exposure;
- potential severity;
- time sensitivity;
- reversibility;
- institutional relevance;
- coverage reliability;
- false-negative consequence;
- false-positive consequence;
- analyst effort.

These dimensions must remain separately inspectable.

## 19. No Universal Opaque Issue Score

S0.5C does not authorize one universal issue score.

If a later summary ranking is used, it must preserve:

- component dimensions;
- methodology;
- weighting rationale;
- limitations;
- version;
- benchmark evidence.

A summary rank may not override:

- rights hard blocks;
- insufficient evidence;
- unresolved contradiction;
- authority uncertainty;
- coverage limitations.

## 20. Analytical Response Candidates

Issue Object may expose non-binding analytical next-step candidates.

### AR-01 — CONTINUE_MONITORING

### AR-02 — VERIFY_CLAIM

### AR-03 — REQUEST_ADDITIONAL_EVIDENCE

### AR-04 — HUMAN_REVIEW_REQUIRED

### AR-05 — WATCH_MATERIAL_CHANGE

### AR-06 — CONSIDER_ESCALATION

### AR-07 — CONSIDER_COORDINATION_OR_REFERRAL

### AR-08 — DEFER_CONCLUSION

### AR-09 — ABSTAIN

These are analytical response candidates.

They are not binding institutional actions.

## 21. Actionability Guard

Issue priority does not automatically authorize action.

Issue Object may support:

- monitoring;
- verification;
- review;
- referral consideration;
- coordination consideration;
- escalation consideration.

It must not autonomously issue:

- legal orders;
- enforcement actions;
- budget decisions;
- procurement decisions;
- binding policy decisions;
- formal institutional determinations.

## 22. Uncertainty & Contradiction

Minimum fields:

- uncertainty_state
- uncertainty_reasons
- unresolved_questions
- contradictory_evidence_refs
- contradiction_materiality
- competing_interpretations
- review_required
- abstention_reason

Uncertainty must remain visible.

Contradiction must not be hidden by averaging.

## 23. Temporal / Point-in-Time Group

Minimum fields:

- earliest_event_time
- first_observed_at
- first_issue_candidate_at
- first_qualified_at
- latest_evidence_available_at
- reviewer_decision_at
- analytical_cutoff
- version_created_at
- correction_at
- lifecycle_transition_at
- last_material_update_at

Historical Issue Objects must be reproducible using only information lawfully
available at the relevant analytical cutoff.

## 24. Lineage / Merge / Split / Correction

Minimum lineage relationships:

- predecessor_issue_ids
- successor_issue_ids
- merged_from_issue_ids
- merged_into_issue_id
- split_from_issue_id
- split_into_issue_ids
- supersedes_issue_version_id
- correction_reference
- invalidation_reference
- lineage_rationale
- lineage_review_state

History must never be erased for convenience.

## 25. Rights / Privacy Integration

Issue Object must expose production admissibility limitations.

Minimum controls:

- production_evidence_state
- excluded_rights_restricted_evidence
- privacy_limitation_state
- attribution_requirements
- retention_constraints
- redistribution_constraints

Z1 material may trigger verification or research.

It may not be laundered into production conclusions.

Profiling remains OFF by default.

## 26. Human Review Boundary

S0.5C records review state.

S0.5C does not grant reviewer authority.

S0.5D will define who may:

- qualify;
- reject;
- correct;
- merge;
- split;
- override;
- suspend;
- invalidate;
- reopen;
- approve escalation;
- revise material conclusions.

## 27. Decision-Use Metadata

Minimum fields may include:

- intended_user_role
- decision_question
- analytical_response_candidates
- verification_requirements
- unresolved_questions
- issue_brief_state
- decision_cutoff
- decision_use_limitations
- required_human_authority
- follow_up_due_state

The object must make clear what the evidence warrants and what it does not.

## 28. Briefing Support

A future evidence-linked Issue Brief may be generated from an Issue Object.

The brief must preserve:

- issue identity;
- cutoff time;
- evidence links;
- coverage limitations;
- uncertainty;
- contradiction;
- geography;
- institutional relevance;
- watch conditions;
- analytical next steps.

A brief is a derived view.

It does not replace the canonical Issue Object.

## 29. Reproducibility Requirement

Given an issue_version_id and analytical cutoff, SIFI must eventually be able
to reconstruct:

- evidence available;
- rights state;
- issue interpretation;
- trajectory state;
- uncertainty;
- reviewer state;
- analytical-response candidates

as they existed at that point in time.

This is a future implementation requirement.

This contract alone does not prove that capability.

## 30. AI Authority

AI may propose:

- candidate Issue summaries;
- candidate links;
- trajectory interpretation;
- possible risks/opportunities;
- possible watch conditions;
- possible analytical responses.

AI may not autonomously:

- change canonical Issue identity;
- merge/split Issues;
- qualify unsupported empirical conditions;
- invent legal authority;
- bypass rights controls;
- hide contradiction;
- fabricate probabilities;
- approve escalation;
- make binding institutional decisions.

## 31. Product Claim Boundary

Existence of this Issue Object schema supports only the claim:

"SIFI has a contracted persistent Issue Object architecture."

It does not support claims that SIFI:

- already constructs Issue Objects correctly;
- improves analyst productivity;
- detects issues earlier;
- reduces false escalation;
- produces superior decisions.

Those claims require later implementation and evaluation evidence.

## 32. Downstream Dependencies

S0.5D must define human authority over material state transitions.

S0.6A must provide adjudicated benchmark cases.

S0.6B must test integrated Issue Object behaviour.

S0.6C must determine whether the source universe is sufficient for the
claimed workflow.

S0.7 must reconcile all Stage0 requirements before freeze.

## 33. Implementation Guard

This contract does not authorize:

- database schema implementation;
- issue persistence code;
- issue clustering;
- alert engine;
- prioritization engine;
- dashboard implementation;
- automated briefing;
- foresight engine;
- production deployment.

Implementation Authority remains:

NOT YET AUTHORIZED.

