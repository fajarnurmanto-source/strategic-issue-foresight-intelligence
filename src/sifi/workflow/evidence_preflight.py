"""Read-only bridge from EvidenceItem objects to brief preflight.

Evidence membership and recorded time are checked here.
This adapter does not verify truth, rights, provenance,
human approval, or production authorization.
"""

from __future__ import annotations

from collections.abc import Iterable

from sifi.evidence.model import EvidenceItem

from .brief import EvidenceLinkedBrief
from .brief_readiness import (
    BriefReadinessAssessment,
    assess_brief_readiness,
)


class EvidencePreflightError(ValueError):
    pass


def assess_brief_from_evidence_items(
    brief: EvidenceLinkedBrief,
    *,
    evidence_items: Iterable[EvidenceItem],
) -> BriefReadinessAssessment:
    """Run preflight against explicitly supplied evidence records.

    The caller remains responsible for selecting a trusted,
    appropriately scoped source of EvidenceItem records.
    """
    if not isinstance(brief, EvidenceLinkedBrief):
        raise TypeError("brief must be EvidenceLinkedBrief")

    available = {}

    for item in evidence_items:
        if not isinstance(item, EvidenceItem):
            raise TypeError("all records must be EvidenceItem")

        if item.evidence_id in available:
            raise EvidencePreflightError(
                "duplicate evidence_id: " + item.evidence_id
            )

        available[item.evidence_id] = item.recorded_at

    return assess_brief_readiness(
        brief,
        available_evidence=available,
    )
