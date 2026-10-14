from __future__ import annotations

import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path

from sifi.workflow import (
    LocalWorkflowRuntime, WorkflowBoundaryError, WorkflowStep,
    WorkflowStepSubmission,
)


BASE = datetime(2026, 10, 15, 8, 0, tzinfo=timezone.utc)


class LocalRuntimeTests(unittest.TestCase):
    def test_001_runtime_creates_database_inside_selected_local_folder(self):
        with tempfile.TemporaryDirectory() as directory:
            database = Path(directory) / "data" / "sifi.sqlite"
            with LocalWorkflowRuntime(database) as runtime:
                session = runtime.start_session(
                    session_id="LOCAL-1", task_id="TASK-1", analyst_id="ANALYST-1",
                    issue_id="ISSUE-1", analytical_cutoff=BASE,
                    started_at=BASE + timedelta(hours=1),
                )
                self.assertEqual(runtime.latest(session.session_id), session)
            self.assertTrue(database.is_file())

    def test_002_valid_step_is_durably_persisted_and_recovered(self):
        with tempfile.TemporaryDirectory() as directory:
            database = Path(directory) / "sifi.sqlite"
            with LocalWorkflowRuntime(database) as runtime:
                runtime.start_session(
                    session_id="LOCAL-2", task_id="TASK-1", analyst_id="ANALYST-1",
                    issue_id="ISSUE-1", analytical_cutoff=BASE,
                    started_at=BASE + timedelta(hours=1),
                )
                runtime.submit_step(
                    "LOCAL-2",
                    WorkflowStepSubmission(
                        step=WorkflowStep.COVERAGE_INSPECTION,
                        findings=("coverage inspected",),
                    ),
                    analyst_id="ANALYST-1", recorded_at=BASE + timedelta(hours=2),
                )
            with LocalWorkflowRuntime(database) as reopened:
                self.assertEqual(
                    reopened.latest("LOCAL-2").completed_steps,
                    (WorkflowStep.COVERAGE_INSPECTION,),
                )

    def test_003_failed_boundary_does_not_persist_partial_progress(self):
        with tempfile.TemporaryDirectory() as directory:
            database = Path(directory) / "sifi.sqlite"
            with LocalWorkflowRuntime(database) as runtime:
                runtime.start_session(
                    session_id="LOCAL-3", task_id="TASK-1", analyst_id="ANALYST-1",
                    issue_id="ISSUE-1", analytical_cutoff=BASE,
                    started_at=BASE + timedelta(hours=1),
                )
                with self.assertRaises(WorkflowBoundaryError):
                    runtime.submit_step(
                        "LOCAL-3",
                        WorkflowStepSubmission(
                            step=WorkflowStep.ADMITTED_SIGNAL_REVIEW,
                            findings=("invalid out of order",),
                            production_evidence_eligible=False,
                        ),
                        analyst_id="ANALYST-1", recorded_at=BASE + timedelta(hours=2),
                    )
                self.assertEqual(runtime.latest("LOCAL-3").completed_steps, ())

    def test_004_historical_state_excludes_later_step(self):
        with tempfile.TemporaryDirectory() as directory:
            database = Path(directory) / "sifi.sqlite"
            with LocalWorkflowRuntime(database) as runtime:
                runtime.start_session(
                    session_id="LOCAL-4", task_id="TASK-1", analyst_id="ANALYST-1",
                    issue_id="ISSUE-1", analytical_cutoff=BASE,
                    started_at=BASE + timedelta(hours=1),
                )
                runtime.submit_step(
                    "LOCAL-4",
                    WorkflowStepSubmission(
                        step=WorkflowStep.COVERAGE_INSPECTION,
                        findings=("coverage inspected",),
                    ),
                    analyst_id="ANALYST-1", recorded_at=BASE + timedelta(hours=3),
                )
                historical = runtime.as_of("LOCAL-4", BASE + timedelta(hours=2))
                self.assertEqual(historical.completed_steps, ())


if __name__ == "__main__":
    unittest.main()
