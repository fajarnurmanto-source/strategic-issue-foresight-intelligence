from __future__ import annotations

from datetime import datetime
from pathlib import Path

from sifi.persistence import SQLiteVersionStore

from .repository import WorkflowSessionRepository
from .service import BoundedAnalystWorkflow, WorkflowSession, WorkflowStepSubmission


class LocalWorkflowRuntime:
    """Local-only durable application boundary for bounded workflow sessions."""

    def __init__(self, database_path: str | Path) -> None:
        path = Path(database_path).resolve()
        if path.name == "":
            raise ValueError("database_path must identify a file")
        path.parent.mkdir(parents=True, exist_ok=True)
        self.database_path = path
        self._store = SQLiteVersionStore(path)
        self._repository = WorkflowSessionRepository(self._store)
        self._workflow = BoundedAnalystWorkflow()

    def close(self) -> None:
        self._store.close()

    def __enter__(self) -> "LocalWorkflowRuntime":
        return self

    def __exit__(self, *_args) -> None:
        self.close()

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
