from __future__ import annotations

import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from sifi.demo_workflow import LocalDemoWorkflow
from sifi.workflow import LocalDomainOrchestrator, LocalWorkflowRuntime, WorkflowStep
from tests.test_s1_7e_domain_orchestration import BASE, context


class AtomicOrchestrationTests(unittest.TestCase):
    def test_001_start_inspection_rolls_back_every_version_on_internal_failure(self):
        with tempfile.TemporaryDirectory() as directory:
            with LocalWorkflowRuntime(Path(directory) / "sifi.sqlite") as runtime:
                service = LocalDomainOrchestrator(runtime)
                original = runtime.submit_step
                calls = 0

                def fail_on_third(*args, **kwargs):
                    nonlocal calls
                    calls += 1
                    if calls == 3:
                        raise RuntimeError("simulated persistence boundary failure")
                    return original(*args, **kwargs)

                with patch.object(runtime, "submit_step", side_effect=fail_on_third):
                    with self.assertRaises(RuntimeError):
                        service.start_inspection(
                            session_id="ATOMIC-START", context=context(),
                            started_at=BASE, recorded_at=BASE,
                        )
                self.assertIsNone(runtime.latest("ATOMIC-START"))

    def test_002_completion_and_brief_roll_back_together(self):
        with tempfile.TemporaryDirectory() as directory:
            with LocalWorkflowRuntime(Path(directory) / "sifi.sqlite") as runtime:
                service = LocalDomainOrchestrator(runtime)
                before = service.start_inspection(
                    session_id="ATOMIC-COMPLETE", context=context(),
                    started_at=BASE, recorded_at=BASE,
                )
                self.assertEqual(before.next_step, WorkflowStep.AUTHORIZED_CORRECTION)
                with patch.object(
                    runtime, "record_brief",
                    side_effect=RuntimeError("simulated brief failure"),
                ):
                    with self.assertRaises(RuntimeError):
                        service.complete_case(
                            session_id="ATOMIC-COMPLETE", context=context(),
                            recorded_at=BASE, correction_review=None,
                            priority_rationale="bounded materiality",
                            attention_only_basis=False,
                            brief_id="ATOMIC-BRIEF",
                            brief_summary="Synthetic atomic brief.",
                            brief_evidence_refs=("EV-1", "EV-2"),
                            historical_cutoff=BASE,
                        )
                after = runtime.latest("ATOMIC-COMPLETE")
                self.assertEqual(after, before)
                self.assertFalse(after.is_complete)
                self.assertIsNone(runtime.latest_brief("ATOMIC-BRIEF"))

    def test_003_nested_transaction_commits_once_at_outer_boundary(self):
        with tempfile.TemporaryDirectory() as directory:
            with LocalWorkflowRuntime(Path(directory) / "sifi.sqlite") as runtime:
                with runtime.transaction():
                    with runtime.transaction():
                        runtime.start_session(
                            session_id="NESTED", task_id="TASK", analyst_id="A",
                            issue_id="ISSUE", analytical_cutoff=BASE,
                            started_at=BASE,
                        )
                self.assertIsNotNone(runtime.latest("NESTED"))

    def test_004_interactive_final_step_rolls_back_when_brief_fails(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / "SIFI"
            (root / "src" / "sifi").mkdir(parents=True)
            workflow = LocalDemoWorkflow(root)
            state = workflow.start(analyst_id="ATOMIC-OPERATOR", now=BASE)
            for _ in range(10):
                state = workflow.advance(
                    session_id=state.session.session_id,
                    analyst_id="ATOMIC-OPERATOR",
                    now=BASE,
                )
            self.assertEqual(state.session.next_step, WorkflowStep.HISTORICAL_RETRIEVAL)
            with patch.object(
                LocalWorkflowRuntime, "record_brief",
                side_effect=RuntimeError("simulated interactive brief failure"),
            ):
                with self.assertRaises(RuntimeError):
                    workflow.advance(
                        session_id=state.session.session_id,
                        analyst_id="ATOMIC-OPERATOR",
                        now=BASE,
                    )
            with LocalWorkflowRuntime(
                root / "runtime/data/sifi.sqlite", workspace_root=root
            ) as runtime:
                recovered = runtime.latest(state.session.session_id)
                self.assertFalse(recovered.is_complete)
                self.assertEqual(recovered.next_step, WorkflowStep.HISTORICAL_RETRIEVAL)
                self.assertIsNone(runtime.latest_brief("BRIEF-" + recovered.session_id))


if __name__ == "__main__":
    unittest.main()
