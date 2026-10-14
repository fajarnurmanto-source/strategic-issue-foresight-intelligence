from __future__ import annotations

from dataclasses import dataclass
from datetime import datetime

from sifi.domain.versioning import VersionedSnapshot, require_aware_datetime
from sifi.persistence import SQLiteVersionStore

from .service import WorkflowAuditEvent, WorkflowSession, WorkflowStep


WORKFLOW_SESSION_OBJECT_TYPE = "BOUNDED_ANALYST_WORKFLOW_SESSION"


class WorkflowSessionRepositoryError(RuntimeError):
    pass


class WorkflowSessionVersionError(WorkflowSessionRepositoryError):
    pass


@dataclass(frozen=True, slots=True)
class PersistedWorkflowSession:
    version: int
    effective_at: datetime
    recorded_at: datetime
    session: WorkflowSession


def _payload(session: WorkflowSession) -> dict:
    return {
        "session_id": session.session_id,
        "task_id": session.task_id,
        "analyst_id": session.analyst_id,
        "issue_id": session.issue_id,
        "analytical_cutoff": session.analytical_cutoff.isoformat(),
        "started_at": session.started_at.isoformat(),
        "completed_steps": [item.value for item in session.completed_steps],
        "audit_events": [
            {
                "sequence": event.sequence,
                "step": event.step.value,
                "analyst_id": event.analyst_id,
                "recorded_at": event.recorded_at.isoformat(),
                "findings": list(event.findings),
                "evidence_refs": list(event.evidence_refs),
            }
            for event in session.audit_events
        ],
        "completed_at": (
            session.completed_at.isoformat() if session.completed_at else None
        ),
        "analytical_only": session.analytical_only,
        "representative_user_validation_performed": (
            session.representative_user_validation_performed
        ),
        "production_action_authorized": session.production_action_authorized,
    }


def _session(payload: dict) -> WorkflowSession:
    return WorkflowSession(
        session_id=payload["session_id"],
        task_id=payload["task_id"],
        analyst_id=payload["analyst_id"],
        issue_id=payload["issue_id"],
        analytical_cutoff=datetime.fromisoformat(payload["analytical_cutoff"]),
        started_at=datetime.fromisoformat(payload["started_at"]),
        completed_steps=tuple(WorkflowStep(item) for item in payload["completed_steps"]),
        audit_events=tuple(
            WorkflowAuditEvent(
                sequence=item["sequence"],
                step=WorkflowStep(item["step"]),
                analyst_id=item["analyst_id"],
                recorded_at=datetime.fromisoformat(item["recorded_at"]),
                findings=tuple(item["findings"]),
                evidence_refs=tuple(item["evidence_refs"]),
            )
            for item in payload["audit_events"]
        ),
        completed_at=(
            datetime.fromisoformat(payload["completed_at"])
            if payload["completed_at"] else None
        ),
        analytical_only=payload["analytical_only"],
        representative_user_validation_performed=(
            payload["representative_user_validation_performed"]
        ),
        production_action_authorized=payload["production_action_authorized"],
    )


class WorkflowSessionRepository:
    """Append-only durable workflow session states."""

    def __init__(self, store: SQLiteVersionStore) -> None:
        self._store = store

    def append(
        self, session: WorkflowSession, *, version: int,
        effective_at: datetime, recorded_at: datetime,
    ) -> PersistedWorkflowSession:
        require_aware_datetime(effective_at, "effective_at")
        require_aware_datetime(recorded_at, "recorded_at")
        expected_from_progress = len(session.completed_steps) + 1
        if version != expected_from_progress:
            raise WorkflowSessionVersionError(
                f"workflow version must equal progress + 1 ({expected_from_progress})"
            )
        latest = self._store.latest(WORKFLOW_SESSION_OBJECT_TYPE, session.session_id)
        expected_from_history = 1 if latest is None else latest.version + 1
        if version != expected_from_history:
            raise WorkflowSessionVersionError(
                f"expected workflow version {expected_from_history}, received {version}"
            )
        self._store.append(
            VersionedSnapshot(
                object_type=WORKFLOW_SESSION_OBJECT_TYPE,
                object_id=session.session_id,
                version=version,
                effective_at=effective_at,
                recorded_at=recorded_at,
                payload=_payload(session),
            )
        )
        return PersistedWorkflowSession(version, effective_at, recorded_at, session)

    def latest(self, session_id: str) -> PersistedWorkflowSession | None:
        return self._decode(self._store.latest(WORKFLOW_SESSION_OBJECT_TYPE, session_id))

    def get(self, session_id: str, version: int) -> PersistedWorkflowSession | None:
        return self._decode(self._store.get(WORKFLOW_SESSION_OBJECT_TYPE, session_id, version))

    def as_of(self, session_id: str, cutoff: datetime) -> PersistedWorkflowSession | None:
        require_aware_datetime(cutoff, "cutoff")
        return self._decode(self._store.as_of(WORKFLOW_SESSION_OBJECT_TYPE, session_id, cutoff))

    def history(self, session_id: str) -> tuple[PersistedWorkflowSession, ...]:
        return tuple(
            self._decode(item)
            for item in self._store.history(WORKFLOW_SESSION_OBJECT_TYPE, session_id)
        )

    @staticmethod
    def _decode(snapshot) -> PersistedWorkflowSession | None:
        if snapshot is None:
            return None
        return PersistedWorkflowSession(
            version=snapshot.version,
            effective_at=snapshot.effective_at,
            recorded_at=snapshot.recorded_at,
            session=_session(dict(snapshot.payload)),
        )
