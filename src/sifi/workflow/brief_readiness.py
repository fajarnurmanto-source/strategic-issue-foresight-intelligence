"""Read-only evidence-reference preflight for analytical briefs.

This component does not qualify evidence, approve publication,
authorize production action, or change human-review authority.
"""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from enum import StrEnum
from typing import Mapping

from sifi.domain.versioning import require_aware_datetime
from .brief import EvidenceLinkedBrief


class BriefReadinessStatus(StrEnum):
    READY_FOR_HUMAN_REVIEW = "READY_FOR_HUMAN_REVIEW"
    NEEDS_REMEDIATION = "NEEDS_REMEDIATION"


@dataclass(frozen=True, slots=True)
class BriefReadinessAssessment:
    brief_id: str
    status: BriefReadinessStatus
    missing_refs: tuple[str, ...]
    later_than_cutoff_refs: tuple[str, ...]
    checked_refs: tuple[str, ...]
    human_approval_granted: bool = False
    production_action_authorized: bool = False
    restricted_refs: tuple[str, ...] = ()


def assess_brief_readiness(
    brief: EvidenceLinkedBrief,
    *,
    available_evidence: Mapping[str, datetime],
) -> BriefReadinessAssessment:
    """Check supplied evidence availability at the analytical cutoff.

    The caller supplies evidence identifiers and their availability
    timestamps. This does not independently verify source truth,
    rights, provenance, or evidence qualification.
    """
    if not isinstance(brief, EvidenceLinkedBrief):
        raise TypeError("brief must be EvidenceLinkedBrief")

    for reference, available_at in available_evidence.items():
        if not isinstance(reference, str) or not reference.strip():
            raise ValueError("evidence reference must be nonblank")
        require_aware_datetime(available_at, "available_at")

    missing = tuple(
        ref for ref in brief.evidence_refs
        if ref not in available_evidence
    )
    later = tuple(
        ref for ref in brief.evidence_refs
        if ref in available_evidence
        and available_evidence[ref] > brief.analytical_cutoff
    )
    checked = tuple(
        ref for ref in brief.evidence_refs
        if ref in available_evidence
        and available_evidence[ref] <= brief.analytical_cutoff
    )

    status = (
        BriefReadinessStatus.NEEDS_REMEDIATION
        if missing or later
        else BriefReadinessStatus.READY_FOR_HUMAN_REVIEW
    )

    return BriefReadinessAssessment(
        brief_id=brief.brief_id,
        status=status,
        missing_refs=missing,
        later_than_cutoff_refs=later,
        checked_refs=checked,
    )
