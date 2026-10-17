"""Versioned EvidenceItem persistence for controlled development.

No evidence admission, truth finding, human approval, or production
authorization is granted by this repository.
"""

from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

from sifi.domain import AdmissionZone
from sifi.domain.versioning import (
    VersionedSnapshot,
    require_aware_datetime,
)
from sifi.persistence import SQLiteVersionStore

from .model import EvidenceItem, ObservabilityState


EVIDENCE_OBJECT_TYPE = "EVIDENCE_ITEM"


class EvidenceRepositoryError(ValueError):
    pass


@dataclass(frozen=True, slots=True)
class PersistedEvidence:
    version: int
    effective_at: datetime
    recorded_at: datetime
    evidence: EvidenceItem


def _payload(evidence: EvidenceItem) -> dict:
    return {
        "evidence_id": evidence.evidence_id,
        "source_id": evidence.source_id,
        "evidence_kind": evidence.evidence_kind,
        "recorded_at": evidence.recorded_at.isoformat(),
        "admission_zone": evidence.admission_zone.value,
        "origin_key": evidence.origin_key,
        "supported_claim_ids": list(evidence.supported_claim_ids),
        "contradicted_claim_ids": list(evidence.contradicted_claim_ids),
        "publication_id": evidence.publication_id,
        "observed_at": (
            evidence.observed_at.isoformat()
            if evidence.observed_at is not None else None
        ),
        "source_authority_context": evidence.source_authority_context,
        "observability_state": (
            evidence.observability_state.value
            if evidence.observability_state is not None else None
        ),
        "human_review_state": evidence.human_review_state,
        "supersedes_evidence_id": evidence.supersedes_evidence_id,
    }


def _evidence(payload: dict) -> EvidenceItem:
    observed = payload["observed_at"]
    observability = payload["observability_state"]

    return EvidenceItem(
        evidence_id=payload["evidence_id"],
        source_id=payload["source_id"],
        evidence_kind=payload["evidence_kind"],
        recorded_at=datetime.fromisoformat(payload["recorded_at"]),
        admission_zone=AdmissionZone(payload["admission_zone"]),
        origin_key=payload["origin_key"],
        supported_claim_ids=tuple(payload["supported_claim_ids"]),
        contradicted_claim_ids=tuple(payload["contradicted_claim_ids"]),
        publication_id=payload["publication_id"],
        observed_at=(
            datetime.fromisoformat(observed)
            if observed is not None else None
        ),
        source_authority_context=payload["source_authority_context"],
        observability_state=(
            ObservabilityState(observability)
            if observability is not None else None
        ),
        human_review_state=payload["human_review_state"],
        supersedes_evidence_id=payload["supersedes_evidence_id"],
    )


class EvidenceItemRepository:
    def __init__(self, store: SQLiteVersionStore) -> None:
        self._store = store

    def append(
        self,
        evidence: EvidenceItem,
        *,
        version: int,
        effective_at: datetime,
        recorded_at: datetime,
    ) -> PersistedEvidence:
        if not isinstance(evidence, EvidenceItem):
            raise TypeError("evidence must be EvidenceItem")

        require_aware_datetime(effective_at, "effective_at")
        require_aware_datetime(recorded_at, "recorded_at")

        if evidence.recorded_at > recorded_at:
            raise EvidenceRepositoryError(
                "evidence recorded_at cannot follow persistence recorded_at"
            )

        latest = self._store.latest(
            EVIDENCE_OBJECT_TYPE,
            evidence.evidence_id,
        )
        expected = 1 if latest is None else latest.version + 1

        if version != expected:
            raise EvidenceRepositoryError(
                f"expected evidence version {expected}, received {version}"
            )

        self._store.append(
            VersionedSnapshot(
                object_type=EVIDENCE_OBJECT_TYPE,
                object_id=evidence.evidence_id,
                version=version,
                effective_at=effective_at,
                recorded_at=recorded_at,
                payload=_payload(evidence),
            )
        )

        return PersistedEvidence(
            version, effective_at, recorded_at, evidence
        )

    def get(
        self, evidence_id: str, version: int
    ) -> PersistedEvidence | None:
        return self._decode(
            self._store.get(EVIDENCE_OBJECT_TYPE, evidence_id, version)
        )

    def latest(
        self, evidence_id: str
    ) -> PersistedEvidence | None:
        return self._decode(
            self._store.latest(EVIDENCE_OBJECT_TYPE, evidence_id)
        )

    def as_of(
        self, evidence_id: str, cutoff: datetime
    ) -> PersistedEvidence | None:
        return self._decode(
            self._store.as_of(
                EVIDENCE_OBJECT_TYPE, evidence_id, cutoff
            )
        )

    def history(
        self, evidence_id: str
    ) -> tuple[PersistedEvidence, ...]:
        return tuple(
            self._decode(snapshot)
            for snapshot in self._store.history(
                EVIDENCE_OBJECT_TYPE, evidence_id
            )
        )

    @staticmethod
    def _decode(
        snapshot: VersionedSnapshot | None,
    ) -> PersistedEvidence | None:
        if snapshot is None:
            return None

        evidence = _evidence(dict(snapshot.payload))

        if evidence.evidence_id != snapshot.object_id:
            raise EvidenceRepositoryError(
                "stored evidence identity mismatch"
            )

        if evidence.recorded_at > snapshot.recorded_at:
            raise EvidenceRepositoryError(
                "stored evidence chronology mismatch"
            )

        return PersistedEvidence(
            version=snapshot.version,
            effective_at=snapshot.effective_at,
            recorded_at=snapshot.recorded_at,
            evidence=evidence,
        )
