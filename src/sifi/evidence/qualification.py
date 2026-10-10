"""Deterministic evidence relationship and qualification helpers."""

from __future__ import annotations

from datetime import datetime

from sifi.domain import AdmissionZone
from sifi.domain.versioning import require_aware_datetime

from .model import (
    Claim,
    ClaimEvidenceAssessment,
    EvidenceItem,
    PublicationContentItem,
)


def publications_as_of(
    publications: tuple[
        PublicationContentItem, ...
    ],
    *,
    cutoff: datetime,
) -> tuple[PublicationContentItem, ...]:

    require_aware_datetime(cutoff, "cutoff")

    return tuple(
        p
        for p in publications
        if p.collected_at <= cutoff
    )


def publication_origin_metrics(
    publications: tuple[
        PublicationContentItem, ...
    ],
    *,
    cutoff: datetime,
) -> tuple[int, int]:

    visible = publications_as_of(
        publications,
        cutoff=cutoff,
    )

    publication_count = len(
        {
            p.publication_id
            for p in visible
        }
    )

    independent_origin_count = len(
        {
            p.origin_key
            for p in visible
        }
    )

    return (
        publication_count,
        independent_origin_count,
    )


def evidence_as_of(
    evidence_items: tuple[EvidenceItem, ...],
    *,
    cutoff: datetime,
) -> tuple[EvidenceItem, ...]:

    require_aware_datetime(cutoff, "cutoff")

    return tuple(
        e
        for e in evidence_items
        if e.recorded_at <= cutoff
    )


def assess_claim(
    claim: Claim,
    evidence_items: tuple[EvidenceItem, ...],
    publications: tuple[
        PublicationContentItem, ...
    ],
    *,
    cutoff: datetime,
) -> ClaimEvidenceAssessment:

    require_aware_datetime(cutoff, "cutoff")

    visible_evidence = evidence_as_of(
        evidence_items,
        cutoff=cutoff,
    )

    related = tuple(
        e
        for e in visible_evidence
        if (
            claim.claim_id in e.supported_claim_ids
            or claim.claim_id
            in e.contradicted_claim_ids
        )
    )

    supporting = tuple(
        e
        for e in related
        if claim.claim_id in e.supported_claim_ids
    )

    contradicting = tuple(
        e
        for e in related
        if claim.claim_id
        in e.contradicted_claim_ids
    )

    production_support = tuple(
        e
        for e in supporting
        if e.admission_zone
        == AdmissionZone.Z2_PRODUCTION_ADMITTED
    )

    related_publication_ids = {
        e.publication_id
        for e in related
        if e.publication_id is not None
    }

    visible_publications = tuple(
        p
        for p in publications_as_of(
            publications,
            cutoff=cutoff,
        )
        if p.publication_id
        in related_publication_ids
    )

    publication_count = len(
        {
            p.publication_id
            for p in visible_publications
        }
    )

    # Independent origins are deliberately not publication count.
    origin_keys = {
        e.origin_key
        for e in related
    }

    # Publication lineage may add origin information even where
    # multiple evidence items derive from one publication family.
    origin_keys.update(
        p.origin_key
        for p in visible_publications
    )

    observability_states = tuple(
        dict.fromkeys(
            e.observability_state
            for e in related
            if e.observability_state
            is not None
        )
    )

    return ClaimEvidenceAssessment(
        claim_id=claim.claim_id,
        as_of=cutoff,
        publication_count=publication_count,
        independent_origin_count=len(origin_keys),
        supporting_evidence_ids=tuple(
            e.evidence_id
            for e in supporting
        ),
        contradicting_evidence_ids=tuple(
            e.evidence_id
            for e in contradicting
        ),
        production_support_evidence_ids=tuple(
            e.evidence_id
            for e in production_support
        ),
        observability_states=observability_states,
        contradiction_present=bool(contradicting),
        production_support_present=bool(
            production_support
        ),
    )


def production_evidence_eligible(
    evidence: EvidenceItem,
) -> bool:
    """Rights admission is necessary, not proof of truth."""
    return (
        evidence.admission_zone
        == AdmissionZone.Z2_PRODUCTION_ADMITTED
    )
