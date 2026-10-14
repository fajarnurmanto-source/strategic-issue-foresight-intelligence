from __future__ import annotations

import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path

from sifi.persistence import SQLiteVersionStore
from sifi.workflow import (
    BoundedAnalystWorkflow, WorkflowSessionRepository,
    WorkflowSessionVersionError, WorkflowStep, WorkflowStepSubmission,
)


UTC = timezone.utc
BASE = datetime(2026, 10, 15, 8, 0, tzinfo=UTC)


class WorkflowPersistenceTests(unittest.TestCase):
    def session(self):
        return BoundedAnalystWorkflow.start(
            session_id="SESSION-PERSIST", task_id="TASK-1", analyst_id="ANALYST-1",
            issue_id="ISSUE-1", analytical_cutoff=BASE, started_at=BASE + timedelta(hours=1),
        )

    def test_001_initial_and_progress_states_are_append_only(self):
        workflow = BoundedAnalystWorkflow()
        session = self.session()
        with SQLiteVersionStore(":memory:") as store:
            repo = WorkflowSessionRepository(store)
            repo.append(session, version=1, effective_at=BASE, recorded_at=BASE)
            progressed = workflow.submit(
                session,
                WorkflowStepSubmission(
                    step=WorkflowStep.COVERAGE_INSPECTION, findings=("coverage inspected",)
                ),
                analyst_id="ANALYST-1", recorded_at=BASE + timedelta(hours=2),
            )
            repo.append(
                progressed, version=2,
                effective_at=BASE + timedelta(hours=2),
                recorded_at=BASE + timedelta(hours=2),
            )
            self.assertEqual(tuple(item.version for item in repo.history(session.session_id)), (1, 2))
            self.assertEqual(repo.get(session.session_id, 1).session.completed_steps, ())
            self.assertEqual(
                repo.latest(session.session_id).session.completed_steps,
                (WorkflowStep.COVERAGE_INSPECTION,),
            )

    def test_002_progress_and_version_must_match(self):
        with SQLiteVersionStore(":memory:") as store:
            with self.assertRaises(WorkflowSessionVersionError):
                WorkflowSessionRepository(store).append(
                    self.session(), version=2, effective_at=BASE, recorded_at=BASE
                )

    def test_003_file_backed_session_survives_reopen(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "workflow.sqlite"
            with SQLiteVersionStore(path) as store:
                WorkflowSessionRepository(store).append(
                    self.session(), version=1, effective_at=BASE, recorded_at=BASE
                )
            with SQLiteVersionStore(path) as reopened:
                recovered = WorkflowSessionRepository(reopened).latest("SESSION-PERSIST")
                self.assertEqual(recovered.session.issue_id, "ISSUE-1")
                self.assertFalse(recovered.session.is_complete)

    def test_004_point_in_time_excludes_later_progress(self):
        workflow = BoundedAnalystWorkflow()
        session = self.session()
        progressed = workflow.submit(
            session,
            WorkflowStepSubmission(
                step=WorkflowStep.COVERAGE_INSPECTION, findings=("coverage inspected",)
            ),
            analyst_id="ANALYST-1", recorded_at=BASE + timedelta(hours=4),
        )
        with SQLiteVersionStore(":memory:") as store:
            repo = WorkflowSessionRepository(store)
            repo.append(session, version=1, effective_at=BASE, recorded_at=BASE)
            repo.append(
                progressed, version=2,
                effective_at=BASE + timedelta(hours=4),
                recorded_at=BASE + timedelta(hours=4),
            )
            historical = repo.as_of("SESSION-PERSIST", BASE + timedelta(hours=3))
            self.assertEqual(historical.version, 1)
            self.assertEqual(historical.session.completed_steps, ())


if __name__ == "__main__":
    unittest.main()
