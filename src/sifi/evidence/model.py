"""Canonical evidence-layer executable primitives.

These classes implement a bounded executable subset of S0.5A.
They preserve Publication, Claim, and Evidence as separate objects.
"""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime
from enum import StrEnum

from sifi.domain import (
    AdmissionZone,
    ClaimType,
    ObservabilityState,
)
from sifi.domain.versioning import require_aware_datetime


class EvidenceRelationType(StrEnum):
    SUPPORTS = "SUPPORTS"
    CONTRADICTS = "CONTRADICTS"


@dataclass(frozen=True, slots=True)
class PublicationContentItem:
    publication_id: str
    source_id: str
    collected_at: datetime
    content_fingerprint: str
    admission_zone: AdmissionZone
    origin_key: str

    title: str | None = None
    canonical_url: str | None = None
    published_at: datetime | None = None
    source_available_at: datetime | None = None
    content_type: str | None = None
    supersedes_publication_id: str | None = None

    def __post_init__(self) -> None:
        for name, value in [
            ("publication_id", self.publication_id),
            ("source_id", self.source_id),
            ("content_fingerprint", self.content_fingerprint),
            ("origin_key", self.origin_key),
        ]:
            if not value.strip():
                raise ValueError(
                    f"{name} must not be blank"
                )

        require_aware_datetime(
            self.collected_at,
            "collected_at",
        )

        for name, value in [
            ("published_at", self.published_at),
            (
                "source_available_at",
                self.source_available_at,
            ),
        ]:
            if value is not None:
                require_aware_datetime(
                    value,
                    name,
                )


@dataclass(frozen=True, slots=True)
class Claim:
    claim_id: str
    source_id: str
    claim_type: ClaimType
    claim_text: str
    recorded_at: datetime

    publication_id: str | None = None
    attribution: str | None = None
    observed_at: datetime | None = None
    review_state: str | None = None
    last_reviewed_at: datetime | None = None
    supersedes_claim_id: str | None = None

    def __post_init__(self) -> None:
        if not self.claim_id.strip():
            raise ValueError(
                "claim_id must not be blank"
            )

        if not self.source_id.strip():
            raise ValueError(
                "source_id must not be blank"
            )

        if not self.claim_text.strip():
            raise ValueError(
                "claim_text must not be blank"
            )

        require_aware_datetime(
            self.recorded_at,
            "recorded_at",
        )

        for name, value in [
            ("observed_at", self.observed_at),
            (
                "last_reviewed_at",
                self.last_reviewed_at,
            ),
        ]:
            if value is not None:
                require_aware_datetime(
                    value,
                    name,
                )


@dataclass(frozen=True, slots=True)
class EvidenceItem:
    evidence_id: str
    source_id: str
    evidence_kind: str
    recorded_at: datetime
    admission_zone: AdmissionZone
    origin_key: str

    supported_claim_ids: tuple[str, ...] = ()
    contradicted_claim_ids: tuple[str, ...] = ()

    publication_id: str | None = None
    observed_at: datetime | None = None
    source_authority_context: str | None = None
    observability_state: ObservabilityState | None = None
    human_review_state: str | None = None
    supersedes_evidence_id: str | None = None

    def __post_init__(self) -> None:
        for name, value in [
            ("evidence_id", self.evidence_id),
            ("source_id", self.source_id),
            ("evidence_kind", self.evidence_kind),
            ("origin_key", self.origin_key),
        ]:
            if not value.strip():
                raise ValueError(
                    f"{name} must not be blank"
                )

        require_aware_datetime(
            self.recorded_at,
            "recorded_at",
        )

        if self.observed_at is not None:
            require_aware_datetime(
                self.observed_at,
                "observed_at",
            )

        overlap = (
            set(self.supported_claim_ids)
            & set(self.contradicted_claim_ids)
        )

        if overlap:
            raise ValueError(
                "one Evidence Item cannot both support "
                "and contradict the same Claim: "
                + ",".join(sorted(overlap))
            )


@dataclass(frozen=True, slots=True)
class ClaimEvidenceAssessment:
    claim_id: str
    as_of: datetime

    publication_count: int
    independent_origin_count: int

    supporting_evidence_ids: tuple[str, ...]
    contradicting_evidence_ids: tuple[str, ...]
    production_support_evidence_ids: tuple[str, ...]

    observability_states: tuple[
        ObservabilityState, ...
    ]

    contradiction_present: bool
    production_support_present: bool

    def __post_init__(self) -> None:
        require_aware_datetime(
            self.as_of,
            "as_of",
        )
