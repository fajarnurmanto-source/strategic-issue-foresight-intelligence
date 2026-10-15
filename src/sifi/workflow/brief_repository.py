from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

from sifi.domain.versioning import VersionedSnapshot, require_aware_datetime
from sifi.persistence import SQLiteVersionStore

from .brief import EvidenceLinkedBrief


BRIEF_OBJECT_TYPE = "EVIDENCE_LINKED_ANALYTICAL_BRIEF"


class BriefRepositoryError(RuntimeError):
    pass


@dataclass(frozen=True, slots=True)
class PersistedBrief:
    version: int
    effective_at: datetime
    recorded_at: datetime
    brief: EvidenceLinkedBrief


def _payload(brief: EvidenceLinkedBrief) -> dict:
    return {
        "brief_id": brief.brief_id,
        "issue_id": brief.issue_id,
        "summary": brief.summary,
        "evidence_refs": list(brief.evidence_refs),
        "created_at": brief.created_at.isoformat(),
        "analytical_cutoff": brief.analytical_cutoff.isoformat(),
        "analytical_only": brief.analytical_only,
        "production_action_authorized": brief.production_action_authorized,
    }


def _brief(payload: dict) -> EvidenceLinkedBrief:
    return EvidenceLinkedBrief(
        brief_id=payload["brief_id"],
        issue_id=payload["issue_id"],
        summary=payload["summary"],
        evidence_refs=tuple(payload["evidence_refs"]),
        created_at=datetime.fromisoformat(payload["created_at"]),
        analytical_cutoff=datetime.fromisoformat(payload["analytical_cutoff"]),
        analytical_only=payload["analytical_only"],
        production_action_authorized=payload["production_action_authorized"],
    )


class EvidenceBriefRepository:
    def __init__(self, store: SQLiteVersionStore) -> None:
        self._store = store

    def append(
        self, brief: EvidenceLinkedBrief, *, version: int,
        effective_at: datetime, recorded_at: datetime,
    ) -> PersistedBrief:
        require_aware_datetime(effective_at, "effective_at")
        require_aware_datetime(recorded_at, "recorded_at")
        latest = self._store.latest(BRIEF_OBJECT_TYPE, brief.brief_id)
        expected = 1 if latest is None else latest.version + 1
        if version != expected:
            raise BriefRepositoryError(
                f"expected brief version {expected}, received {version}"
            )
        self._store.append(
            VersionedSnapshot(
                object_type=BRIEF_OBJECT_TYPE,
                object_id=brief.brief_id,
                version=version,
                effective_at=effective_at,
                recorded_at=recorded_at,
                payload=_payload(brief),
            )
        )
        return PersistedBrief(version, effective_at, recorded_at, brief)

    def latest(self, brief_id: str) -> PersistedBrief | None:
        return self._decode(self._store.latest(BRIEF_OBJECT_TYPE, brief_id))

    def history(self, brief_id: str) -> tuple[PersistedBrief, ...]:
        return tuple(
            self._decode(item)
            for item in self._store.history(BRIEF_OBJECT_TYPE, brief_id)
        )

    @staticmethod
    def _decode(snapshot) -> PersistedBrief | None:
        if snapshot is None:
            return None
        return PersistedBrief(
            version=snapshot.version,
            effective_at=snapshot.effective_at,
            recorded_at=snapshot.recorded_at,
            brief=_brief(dict(snapshot.payload)),
        )
