from __future__ import annotations

from datetime import datetime
from contextlib import contextmanager
from pathlib import Path

from sifi.persistence import SQLiteVersionStore
from sifi.evidence.repository import EvidenceItemRepository
from sifi.local import LocalSIFIPaths

from .repository import WorkflowSessionRepository
from .brief import EvidenceLinkedBrief
from .brief_repository import EvidenceBriefRepository
from .brief_readiness import BriefReadinessAssessment
from .repository_preflight import assess_brief_from_repository
from .service import BoundedAnalystWorkflow, WorkflowSession, WorkflowStepSubmission


class LocalWorkflowRuntime:
    """Local-only durable application boundary for bounded workflow sessions."""

    def __init__(
        self,
        database_path: str | Path,
        *,
        workspace_root: str | Path | None = None,
    ) -> None:
        path = Path(database_path).resolve()
        if path.name == "":
            raise ValueError("database_path must identify a file")
        if workspace_root is not None:
            LocalSIFIPaths.from_root(workspace_root).require_inside(path)
        path.parent.mkdir(parents=True, exist_ok=True)
        self.database_path = path
        self._store = SQLiteVersionStore(path)
        self._repository = WorkflowSessionRepository(self._store)
        self._brief_repository = EvidenceBriefRepository(self._store)
        self._evidence_repository = EvidenceItemRepository(self._store)
        self._workflow = BoundedAnalystWorkflow()

    def close(self) -> None:
        self._store.close()

    def __enter__(self) -> "LocalWorkflowRuntime":
        return self

    def __exit__(self, *_args) -> None:
        self.close()

    @contextmanager
    def transaction(self):
        with self._store.transaction():
            yield self

    @property
    def version_store(self) -> SQLiteVersionStore:
        """Shared append-only store for atomic local bounded-domain extensions."""
        return self._store

    def start_session(
        self, *, session_id: str, task_id: str, analyst_id: str,
        issue_id: str, analytical_cutoff: datetime, started_at: datetime,
    ) -> WorkflowSession:
        session = self._workflow.start(
            session_id=session_id, task_id=task_id, analyst_id=analyst_id,
            issue_id=issue_id, analytical_cutoff=analytical_cutoff,
            started_at=started_at,
        )
        self._repository.append(
            session, version=1, effective_at=started_at, recorded_at=started_at
        )
        return session

    def submit_step(
        self, session_id: str, submission: WorkflowStepSubmission,
        *, analyst_id: str, recorded_at: datetime,
    ) -> WorkflowSession:
        current = self._repository.latest(session_id)
        if current is None:
            raise ValueError("workflow session not found")
        updated = self._workflow.submit(
            current.session, submission, analyst_id=analyst_id,
            recorded_at=recorded_at,
        )
        self._repository.append(
            updated,
            version=current.version + 1,
            effective_at=recorded_at,
            recorded_at=recorded_at,
        )
        return updated

    def latest(self, session_id: str) -> WorkflowSession | None:
        item = self._repository.latest(session_id)
        return item.session if item else None

    def as_of(self, session_id: str, cutoff: datetime) -> WorkflowSession | None:
        item = self._repository.as_of(session_id, cutoff)
        return item.session if item else None

    def record_brief(
        self, brief: EvidenceLinkedBrief, *, recorded_at: datetime
    ) -> EvidenceLinkedBrief:
        latest = self._brief_repository.latest(brief.brief_id)
        version = 1 if latest is None else latest.version + 1
        self._brief_repository.append(
            brief, version=version, effective_at=brief.created_at,
            recorded_at=recorded_at,
        )
        return brief

    def latest_brief(self, brief_id: str) -> EvidenceLinkedBrief | None:
        item = self._brief_repository.latest(brief_id)
        return item.brief if item else None

    def assess_brief_readiness(
        self, brief_id: str
    ) -> BriefReadinessAssessment:
        """Read-only historical evidence-reference preflight.

        This does not grant review approval, rights admission,
        publication permission, or production authorization.
        """
        brief = self.latest_brief(brief_id)
        if brief is None:
            raise ValueError("brief not found")
        return assess_brief_from_repository(
            brief, repository=self._evidence_repository
        )

    def catalog(self, *, limit: int = 100) -> dict[str, tuple]:
        return {
            "sessions": tuple(item.session for item in self._repository.list_latest(limit=limit)),
            "briefs": tuple(item.brief for item in self._brief_repository.list_latest(limit=limit)),
        }
