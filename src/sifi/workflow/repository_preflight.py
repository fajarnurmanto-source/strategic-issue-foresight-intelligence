"""Historical, repository-backed analytical brief preflight.

Read-only evaluation of persisted EvidenceItem versions.
No human approval, empirical validation, publication permission,
or production authorization is conferred.
"""

from __future__ import annotations

from dataclasses import replace
from sifi.domain import AdmissionZone
from sifi.evidence.repository import EvidenceItemRepository

from .brief import EvidenceLinkedBrief
from .brief_readiness import (
    BriefReadinessAssessment,
    BriefReadinessStatus,
)
from .evidence_preflight import assess_brief_from_evidence_items


def assess_brief_from_repository(
    brief: EvidenceLinkedBrief,
    *,
    repository: EvidenceItemRepository,
) -> BriefReadinessAssessment:
    """Evaluate references using evidence versions visible at cutoff.

    Missing refs mean unavailable at the analytical cutoff, not
    necessarily nonexistent in the present repository.
    """
    if not isinstance(brief, EvidenceLinkedBrief):
        raise TypeError("brief must be EvidenceLinkedBrief")

    if not isinstance(repository, EvidenceItemRepository):
        raise TypeError("repository must be EvidenceItemRepository")

    visible_items = []
    restricted_refs = []

    for evidence_id in brief.evidence_refs:
        persisted = repository.as_of(
            evidence_id,
            brief.analytical_cutoff,
        )

        if persisted is None:
            continue

        if persisted.evidence.evidence_id != evidence_id:
            raise ValueError("persisted evidence identity mismatch")

        if persisted.evidence.recorded_at > brief.analytical_cutoff:
            raise ValueError("future evidence visible at historical cutoff")

        if persisted.evidence.admission_zone != AdmissionZone.Z2_PRODUCTION_ADMITTED:
            restricted_refs.append(evidence_id)

        visible_items.append(persisted.evidence)

    result = assess_brief_from_evidence_items(
        brief,
        evidence_items=tuple(visible_items),
    )
    if restricted_refs:
        return replace(
            result,
            status=BriefReadinessStatus.NEEDS_REMEDIATION,
            restricted_refs=tuple(restricted_refs),
        )
    return result
