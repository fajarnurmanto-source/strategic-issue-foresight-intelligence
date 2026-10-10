"""Controlled Event-candidate construction."""

from __future__ import annotations

from sifi.domain import AdmissionZone, ClaimType
from sifi.evidence import Claim, EvidenceItem

from .model import (
    EventCandidate,
    EventContradictionState,
)


class EventConstructionError(ValueError):
    pass


class EventCandidateBuilder:
    """Construct an explicit Event candidate from supplied objects.

    No headline clustering, semantic similarity, publication count,
    or source popularity can automatically create an Event.
    """

    def build(
        self,
        *,
        event_id: str,
        claims: tuple[Claim, ...],
        evidence_items: tuple[EvidenceItem, ...],
        event_type: str | None = None,
        event_start=None,
        event_end=None,
        event_geography: tuple[str, ...] = (),
        involved_actor_classes: tuple[str, ...] = (),
        affected_systems: tuple[str, ...] = (),
        material_circumstances: tuple[str, ...] = (),
        review_state: str | None = None,
    ) -> EventCandidate:

        occurrence_claims = tuple(
            claim
            for claim in claims
            if claim.claim_type
            == ClaimType("CT-03")
        )

        if not occurrence_claims:
            raise EventConstructionError(
                "explicit EVENT_OCCURRENCE claim "
                "is required; attention, allegation, "
                "narrative, or similarity alone "
                "cannot construct an Event candidate"
            )

        occurrence_ids = {
            claim.claim_id
            for claim in occurrence_claims
        }

        supporting = tuple(
            evidence
            for evidence in evidence_items
            if occurrence_ids.intersection(
                evidence.supported_claim_ids
            )
        )

        contradicting = tuple(
            evidence
            for evidence in evidence_items
            if occurrence_ids.intersection(
                evidence.contradicted_claim_ids
            )
        )

        production_support = tuple(
            evidence
            for evidence in supporting
            if evidence.admission_zone
            == AdmissionZone.Z2_PRODUCTION_ADMITTED
        )

        contradiction_state = (
            EventContradictionState.CONTRADICTION_PRESENT
            if contradicting
            else EventContradictionState.NONE_OBSERVED
        )

        return EventCandidate(
            event_id=event_id,
            event_type=event_type,
            event_start=event_start,
            event_end=event_end,
            event_geography=event_geography,
            involved_actor_classes=(
                involved_actor_classes
            ),
            affected_systems=affected_systems,
            material_circumstances=(
                material_circumstances
            ),
            supporting_claim_ids=tuple(
                claim.claim_id
                for claim in occurrence_claims
            ),
            supporting_evidence_ids=tuple(
                evidence.evidence_id
                for evidence in supporting
            ),
            contradicting_evidence_ids=tuple(
                evidence.evidence_id
                for evidence in contradicting
            ),
            production_support_evidence_ids=tuple(
                evidence.evidence_id
                for evidence in production_support
            ),
            contradiction_state=contradiction_state,
            review_state=review_state,
        )
