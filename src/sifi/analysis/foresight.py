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
                rationale=(
                    "Abstained: derivation preconditions not met. "
                    "SIFI preserves insufficiency rather than fabricating a risk level."
                ),
                basis_evidence_ids=basis_evidence,
                abstention_reasons=tuple(abstention_reasons),
            ),
            watch_conditions=(),
            recommendation_proposals=(),
            abstained=True,
            abstention_reasons=tuple(abstention_reasons),
        )

    contradiction_ratio = len(contradictory) / max(1, len(supporting))
    gap_count = len(issue.evidence_gaps)
    uncertainty_count = len(issue.uncertainty_notes)

    if contradiction_ratio >= 0.5 or gap_count >= 3:
        level = RISK_HIGH
    elif contradiction_ratio > 0 or uncertainty_count >= 2 or gap_count >= 1:
        level = RISK_ELEVATED
    else:
        level = RISK_LOW

    escalating = sum(
        1 for t in trajectories
        if any(m in t.trajectory_state.lower() for m in _ESCALATION_MARKERS)
    )
    declining = sum(
        1 for t in trajectories
        if any(m in t.trajectory_state.lower() for m in _DECLINE_MARKERS)
    )
    if escalating > declining:
        direction = DIRECTION_ESCALATING
    elif declining > escalating:
        direction = DIRECTION_DECLINING
    else:
        direction = DIRECTION_STABLE

    watch_conditions = tuple(
        WatchCondition(
            condition=(
                f"{t.dimension.value} trajectory: current state '{t.trajectory_state}'"
            ),
            trigger=(
                f"any transition away from '{t.trajectory_state}' "
                f"observed for dimension {t.dimension.value}"
            ),
            observation_window="next scheduled review cycle",
            falsification_condition=(
                t.limitations[0]
                if t.limitations
                else "no falsification condition declared by analyst"
            ),
            basis_evidence_ids=tuple(t.evidence_ids),
        )
        for t in trajectories
    )

    proposals: list[str] = []
    if level == RISK_HIGH:
        proposals.append(
            "ESCALATE_FOR_HUMAN_REVIEW: contradiction or gap threshold crossed"
        )
    if direction == DIRECTION_ESCALATING:
        proposals.append(
            "INCREASE_MONITORING_FREQUENCY: escalating trajectory observed"
        )
    if any(t.limitations for t in trajectories):
        proposals.append(
            "CLOSE_LIMITATION_GAP: at least one trajectory carries explicit limitations"
        )
    if context_only:
        proposals.append(
            "TREAT_CONTEXT_ONLY_EVIDENCE_AS_NON_SUPPORTING: "
            f"{len(context_only)} context-only items cannot back conclusions"
        )
    if not proposals:
        proposals.append(
            "MAINTAIN_CURRENT_WATCH: no escalation or gap indicator above threshold"
        )

    rationale = (
        f"Derived from {len(supporting)} supporting, "
        f"{len(contradictory)} contradictory, {len(context_only)} context-only "
        f"evidence items; {uncertainty_count} uncertainty notes; "
        f"{gap_count} evidence gaps; {len(trajectories)} trajectory dimensions."
    )

    return ForesightProposal(
        issue_id=issue.issue_id,
        risk=RiskProfile(
            level=level,
            direction=direction,
            rationale=rationale,
            basis_evidence_ids=basis_evidence,
        ),
        watch_conditions=watch_conditions,
        recommendation_proposals=tuple(proposals),
        abstained=False,
        abstention_reasons=(),
    )
