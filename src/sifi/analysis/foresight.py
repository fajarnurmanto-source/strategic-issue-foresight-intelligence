"""Derive foresight proposals and risk profiles from an IssueObject.

This module produces *proposals*, never decisions. Per SIFI constitution,
AI may propose; human authority disposes. Additive: it does not modify
IssueObject, does not touch canonical runtime, and does not authorize action.
"""

from __future__ import annotations

from dataclasses import dataclass

from sifi.issues.model import IssueObject


RISK_LOW = "LOW"
RISK_ELEVATED = "ELEVATED"
RISK_HIGH = "HIGH"
RISK_INSUFFICIENT = "INSUFFICIENT"

DIRECTION_STABLE = "STABLE"
DIRECTION_ESCALATING = "ESCALATING"
DIRECTION_DECLINING = "DECLINING"
DIRECTION_UNKNOWN = "UNKNOWN"

_ESCALATION_MARKERS = ("escalat", "worsen", "accelerat", "spread", "intensif")
_DECLINE_MARKERS = ("declin", "improv", "resolv", "deescalat", "stabiliz")


@dataclass(frozen=True, slots=True)
class WatchCondition:
    condition: str
    trigger: str
    observation_window: str
    falsification_condition: str
    basis_evidence_ids: tuple[str, ...]


@dataclass(frozen=True, slots=True)
class DimensionRisk:
    dimension: str
    level: str
    direction: str
    rationale: str
    basis_evidence_ids: tuple[str, ...]


@dataclass(frozen=True, slots=True)
class RiskProfile:
    level: str
    direction: str
    rationale: str
    basis_evidence_ids: tuple[str, ...]
    abstention_reasons: tuple[str, ...] = ()


@dataclass(frozen=True, slots=True)
class ForesightProposal:
    issue_id: str
    risk: RiskProfile
    dimension_risks: tuple[DimensionRisk, ...]
    watch_conditions: tuple[WatchCondition, ...]
    recommendation_proposals: tuple[str, ...]
    abstained: bool
    abstention_reasons: tuple[str, ...]


def derive_foresight(issue: IssueObject) -> ForesightProposal:
    if not isinstance(issue, IssueObject):
        raise TypeError("derive_foresight requires an IssueObject")

    supporting = tuple(issue.evidence.supporting_evidence_ids)
    contradictory = tuple(issue.evidence.contradictory_evidence_ids)
    context_only = tuple(issue.evidence.context_only_evidence_ids)
    trajectories = tuple(issue.trajectories)

    abstention_reasons: list[str] = []
    if not supporting:
        abstention_reasons.append("no supporting evidence at analytical cutoff")
    if not trajectories:
        abstention_reasons.append("no trajectory state available")
    if len(issue.evidence_gaps) > len(supporting):
        abstention_reasons.append("evidence gaps exceed supporting evidence")

    basis_evidence = supporting + contradictory

    if abstention_reasons:
        return ForesightProposal(
            issue_id=issue.issue_id,
            risk=RiskProfile(
                level=RISK_INSUFFICIENT,
                direction=DIRECTION_UNKNOWN,
                rationale="Abstained: derivation preconditions not met.",
                basis_evidence_ids=basis_evidence,
                abstention_reasons=tuple(abstention_reasons),
            ),
            dimension_risks=(),
            watch_conditions=(),
            recommendation_proposals=(),
            abstained=True,
            abstention_reasons=tuple(abstention_reasons),
        )

    # --- 1. PER-DIMENSION RISK (Evidence Chain Depth) ---
    dim_risks: list[DimensionRisk] = []
    highest_dim_risk = RISK_LOW
    
    for t in trajectories:
        # Cari bukti mana yang benar-benar menempel di dimensi ini
        t_supporting = tuple(e for e in t.evidence_ids if e in supporting)
        t_contradictory = tuple(e for e in t.evidence_ids if e in contradictory)
        
        t_ratio = len(t_contradictory) / max(1, len(t_supporting))
        
        if t_ratio >= 0.5:
            t_level = RISK_HIGH
        elif t_ratio > 0:
            t_level = RISK_ELEVATED
        else:
            t_level = RISK_LOW
            
        if t_level == RISK_HIGH:
            highest_dim_risk = RISK_HIGH
        elif t_level == RISK_ELEVATED and highest_dim_risk != RISK_HIGH:
            highest_dim_risk = RISK_ELEVATED

        t_esc = any(m in t.trajectory_state.lower() for m in _ESCALATION_MARKERS)
        t_dec = any(m in t.trajectory_state.lower() for m in _DECLINE_MARKERS)
        
        if t_esc:
            t_dir = DIRECTION_ESCALATING
        elif t_dec:
            t_dir = DIRECTION_DECLINING
        else:
            t_dir = DIRECTION_STABLE

        dim_risks.append(DimensionRisk(
            dimension=t.dimension.value,
            level=t_level,
            direction=t_dir,
            rationale=f"Derived from {len(t_supporting)} supporting and {len(t_contradictory)} contradictory items attached to this dimension.",
            basis_evidence_ids=tuple(t.evidence_ids)
        ))

    # --- 2. GLOBAL RISK AGGREGATION ---
    contradiction_ratio = len(contradictory) / max(1, len(supporting))
    gap_count = len(issue.evidence_gaps)
    uncertainty_count = len(issue.uncertainty_notes)

    if contradiction_ratio >= 0.5 or gap_count >= 3 or highest_dim_risk == RISK_HIGH:
        level = RISK_HIGH
    elif contradiction_ratio > 0 or uncertainty_count >= 2 or gap_count >= 1 or highest_dim_risk == RISK_ELEVATED:
        level = RISK_ELEVATED
    else:
        level = RISK_LOW

    escalating = sum(1 for d in dim_risks if d.direction == DIRECTION_ESCALATING)
    declining = sum(1 for d in dim_risks if d.direction == DIRECTION_DECLINING)
    
    if escalating > declining:
        direction = DIRECTION_ESCALATING
    elif declining > escalating:
        direction = DIRECTION_DECLINING
    else:
        direction = DIRECTION_STABLE

    # --- 3. WATCH CONDITIONS & RECOMMENDATIONS ---
    watch_conditions = tuple(
        WatchCondition(
            condition=f"{t.dimension.value} trajectory: current state '{t.trajectory_state}'",
            trigger=f"any transition away from '{t.trajectory_state}' for dimension {t.dimension.value}",
            observation_window="next scheduled review cycle",
            falsification_condition=(t.limitations[0] if t.limitations else "no falsification condition declared"),
            basis_evidence_ids=tuple(t.evidence_ids),
        )
        for t in trajectories
    )

    proposals: list[str] = []
    if level == RISK_HIGH:
        proposals.append("ESCALATE_FOR_HUMAN_REVIEW: contradiction or gap threshold crossed")
    if direction == DIRECTION_ESCALATING:
        proposals.append("INCREASE_MONITORING_FREQUENCY: escalating trajectory observed")
    
    high_dimensions = [d.dimension for d in dim_risks if d.level == RISK_HIGH]
    if high_dimensions:
        proposals.append(f"FOCUS_EVIDENCE_GATHERING: high risk detected in dimensions {', '.join(high_dimensions)}")
        
    if any(t.limitations for t in trajectories):
        proposals.append("CLOSE_LIMITATION_GAP: at least one trajectory carries explicit limitations")
    if context_only:
        proposals.append(f"TREAT_CONTEXT_ONLY_EVIDENCE_AS_NON_SUPPORTING: {len(context_only)} context-only items")
    
    if not proposals:
        proposals.append("MAINTAIN_CURRENT_WATCH: no escalation or gap indicator above threshold")

    rationale = (
        f"Derived from {len(supporting)} supporting, {len(contradictory)} contradictory, "
        f"{len(context_only)} context-only items; {gap_count} evidence gaps; "
        f"Highest dimension risk: {highest_dim_risk}."
    )

    return ForesightProposal(
        issue_id=issue.issue_id,
        risk=RiskProfile(
            level=level,
            direction=direction,
            rationale=rationale,
            basis_evidence_ids=basis_evidence,
        ),
        dimension_risks=tuple(dim_risks),
        watch_conditions=watch_conditions,
        recommendation_proposals=tuple(proposals),
        abstained=False,
        abstention_reasons=(),
    )
